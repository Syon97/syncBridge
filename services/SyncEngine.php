<?php

namespace app\services;

use app\models\DbConnection;
use app\models\SyncJob;
use app\models\SyncLog;
use app\models\SyncPair;

/**
 * SyncEngine
 *
 * Core sync logic. Called by SyncController (API) and SyncCommand (cron).
 * Pulls changed rows from the local DB and pushes them to the cloud DB
 * using the pair's configured conflict strategy.
 */
class SyncEngine
{
    private SyncPair $pair;
    private \PDO     $localPdo;
    private \PDO     $cloudPdo;

    // Tallies for this run
    private int   $pushed    = 0;
    private int   $deleted   = 0;
    private int   $conflicts = 0;
    private array $tablesDone = [];

    public function __construct(SyncPair $pair)
    {
        $this->pair = $pair;
    }

    // ----------------------------------------------------------------
    // Public entry point
    // ----------------------------------------------------------------

    /**
     * Run a full sync for this pair.
     * Returns the completed SyncJob.
     */
    public function run(int $attempt = 1): SyncJob
    {
        $job = SyncJob::createForPair($this->pair->id, $attempt);
        $job->markRunning();

        SyncLog::write($this->pair->id, 'info',
            "Sync started — pair: {$this->pair->localConn->label} → {$this->pair->cloudConn->label} (attempt {$attempt})");

        try {
            $this->localPdo = $this->openConnection($this->pair->localConn);
            $this->cloudPdo = $this->openConnection($this->pair->cloudConn);

            $tables = $this->resolveTables();

            foreach ($tables as $table) {
                $this->syncTable($table);
            }

            // Success — update pair
            $this->pair->status        = 'active';
            $this->pair->last_error    = null;
            $this->pair->last_synced_at = date('Y-m-d H:i:s');
            $this->pair->next_sync_at  = date('Y-m-d H:i:s', strtotime("+{$this->pair->interval_minutes} minutes"));
            $this->pair->retry_count   = 0;
            $this->pair->save(false);

            $job->markCompleted($this->pushed, $this->deleted, $this->conflicts, $this->tablesDone);

            SyncLog::write($this->pair->id, 'success',
                "Sync completed — pushed: {$this->pushed}, deleted: {$this->deleted}, conflicts: {$this->conflicts}",
                $this->pushed + $this->deleted);

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();

            $this->pair->retry_count = ($this->pair->retry_count ?? 0) + 1;
            $this->pair->last_error  = $errorMsg;

            if ($this->pair->retry_count >= SyncJob::MAX_ATTEMPTS) {
                $this->pair->status = 'error';
                SyncLog::write($this->pair->id, 'error',
                    "Sync failed after " . SyncJob::MAX_ATTEMPTS . " attempts: {$errorMsg}");
            } else {
                // Schedule a retry in 2 minutes
                $this->pair->next_sync_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
                SyncLog::write($this->pair->id, 'error',
                    "Sync failed (retry {$this->pair->retry_count}/" . SyncJob::MAX_ATTEMPTS . "): {$errorMsg}");
            }

            $this->pair->save(false);
            $job->markFailed($errorMsg);
        }

        return $job;
    }

    // ----------------------------------------------------------------
    // Per-table sync
    // ----------------------------------------------------------------

    private function syncTable(string $table): void
    {
        $lastSync = $this->pair->last_synced_at
            ? $this->pair->last_synced_at
            : '1970-01-01 00:00:00';

        // Validate table exists in local DB (safety check)
        if (!$this->tableExists($this->localPdo, $table)) {
            SyncLog::write($this->pair->id, 'warning', "Table '{$table}' not found in local DB — skipping.");
            return;
        }
        if (!$this->tableExists($this->cloudPdo, $table)) {
            SyncLog::write($this->pair->id, 'warning', "Table '{$table}' not found in cloud DB — skipping.");
            return;
        }

        $start = microtime(true);

        // ── 1. Push new / updated rows ───────────────────────────
        $pushed = $this->pushUpserted($table, $lastSync);

        // ── 2. Push soft-deleted rows ────────────────────────────
        $deleted = $this->pushDeleted($table, $lastSync);

        $ms = (int) round((microtime(true) - $start) * 1000);

        $this->pushed    += $pushed;
        $this->deleted   += $deleted;
        $this->tablesDone[] = $table;

        if ($pushed > 0 || $deleted > 0) {
            SyncLog::write($this->pair->id, 'success',
                "Synced table '{$table}' — upserted: {$pushed}, deleted: {$deleted}",
                $pushed + $deleted, $table, $ms);
        }
    }

    // ── Upsert changed rows ──────────────────────────────────────────

    private function pushUpserted(string $table, string $lastSync): int
    {
        // Check if the table has an updated_at column
        if (!$this->columnExists($this->localPdo, $table, 'updated_at')) {
            SyncLog::write($this->pair->id, 'warning',
                "Table '{$table}' has no 'updated_at' column — cannot detect changes, skipping upsert.");
            return 0;
        }

        $stmt = $this->localPdo->prepare(
            "SELECT * FROM `{$table}` WHERE updated_at > :last ORDER BY updated_at ASC"
        );
        $stmt->execute([':last' => $lastSync]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) return 0;

        $count = 0;
        foreach ($rows as $row) {
            $conflict = $this->detectConflict($table, $row);

            if ($conflict && $this->pair->conflict_strategy === 'cloud_priority') {
                $this->conflicts++;
                SyncLog::write($this->pair->id, 'warning',
                    "Conflict on {$table} id={$row['id']} — cloud_priority, skipping local change.");
                continue;
            }

            if ($conflict && $this->pair->conflict_strategy === 'flag_review') {
                $this->conflicts++;
                SyncLog::write($this->pair->id, 'warning',
                    "Conflict on {$table} id={$row['id']} — flagged for manual review.");
                continue;
            }

            if ($conflict) {
                // last_write_wins or local_priority — both push local
                $this->conflicts++;
                SyncLog::write($this->pair->id, 'warning',
                    "Conflict on {$table} id={$row['id']} — resolved via {$this->pair->conflict_strategy}.");
            }

            $this->upsertRow($table, $row);
            $count++;
        }

        return $count;
    }

    // ── Delete soft-deleted rows ─────────────────────────────────────

    private function pushDeleted(string $table, string $lastSync): int
    {
        if (!$this->columnExists($this->localPdo, $table, 'deleted_at')) {
            return 0; // Table doesn't use soft deletes — skip silently
        }

        $stmt = $this->localPdo->prepare(
            "SELECT * FROM `{$table}` WHERE deleted_at IS NOT NULL AND deleted_at > :last"
        );
        $stmt->execute([':last' => $lastSync]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) return 0;

        $pkCol = $this->getPrimaryKey($this->localPdo, $table);
        $count = 0;

        foreach ($rows as $row) {
            $pkVal = $row[$pkCol] ?? null;
            if ($pkVal === null) continue;

            $del = $this->cloudPdo->prepare("DELETE FROM `{$table}` WHERE `{$pkCol}` = :pk");
            $del->execute([':pk' => $pkVal]);
            $count++;
        }

        return $count;
    }

    // ── Conflict detection ───────────────────────────────────────────

    /**
     * A conflict exists when the cloud row has been updated MORE RECENTLY
     * than the local row — meaning both sides changed since the last sync.
     */
    private function detectConflict(string $table, array $localRow): bool
    {
        if (empty($localRow['id']) || !$this->columnExists($this->cloudPdo, $table, 'updated_at')) {
            return false;
        }

        $stmt = $this->cloudPdo->prepare(
            "SELECT updated_at FROM `{$table}` WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $localRow['id']]);
        $cloudRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cloudRow) return false; // New row in local — no conflict

        $localTime = strtotime($localRow['updated_at'] ?? '0');
        $cloudTime = strtotime($cloudRow['updated_at'] ?? '0');
        $lastSync  = strtotime($this->pair->last_synced_at ?? '0');

        // Conflict = cloud was updated after the last sync too
        return $cloudTime > $lastSync && $localTime > $lastSync;
    }

    // ── UPSERT a single row into cloud ───────────────────────────────

    private function upsertRow(string $table, array $row): void
    {
        if (empty($row)) return;

        $columns     = array_keys($row);
        $colList     = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", $columns));

        // ON DUPLICATE KEY UPDATE — update all columns except PK
        $updates = implode(', ', array_map(
            fn($c) => "`{$c}` = VALUES(`{$c}`)",
            array_filter($columns, fn($c) => $c !== 'id')
        ));

        $sql  = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}";
        $stmt = $this->cloudPdo->prepare($sql);

        $params = [];
        foreach ($row as $col => $val) {
            $params[":{$col}"] = $val;
        }

        $stmt->execute($params);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function openConnection(DbConnection $conn): \PDO
    {
        $plain = DbConnection::decryptPassword($conn->password_encrypted);
        $dsn   = "mysql:host={$conn->host};port={$conn->port};dbname={$conn->dbname};charset=utf8mb4";

        return new \PDO($dsn, $conn->username, $plain, [
            \PDO::ATTR_TIMEOUT            => 10,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function resolveTables(): array
    {
        $configured = $this->pair->getTablesArray();

        if (in_array('*', $configured)) {
            // Fetch all table names from local DB
            $stmt = $this->localPdo->query("SHOW TABLES");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        return $configured;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $table]);
        return $stmt->rowCount() > 0;
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col"
        );
        $stmt->execute([':table' => $table, ':col' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function getPrimaryKey(\PDO $pdo, string $table): string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
             AND CONSTRAINT_NAME = 'PRIMARY' LIMIT 1"
        );
        $stmt->execute([':table' => $table]);
        return $stmt->fetchColumn() ?: 'id';
    }
}