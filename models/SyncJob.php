<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * SyncJob — one sync run attempt for a pair
 *
 * @property int    $id
 * @property int    $pair_id
 * @property string $status        pending|running|completed|failed
 * @property int    $attempt
 * @property string $tables_synced JSON array
 * @property int    $records_pushed
 * @property int    $records_deleted
 * @property int    $conflicts
 * @property string $error_message
 * @property string $started_at
 * @property string $completed_at
 * @property string $created_at
 */
class SyncJob extends ActiveRecord
{
    const STATUS_PENDING   = 'pending';
    const STATUS_RUNNING   = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED    = 'failed';

    const MAX_ATTEMPTS = 3;

    public static function tableName(): string
    {
        return 'sync_jobs';
    }

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(SyncPair::class, ['id' => 'pair_id']);
    }

    // ----------------------------------------------------------------
    // Factory — create a new pending job for a pair
    // ----------------------------------------------------------------
    public static function createForPair(int $pairId, int $attempt = 1): self
    {
        $job = new self([
            'pair_id' => $pairId,
            'status'  => self::STATUS_PENDING,
            'attempt' => $attempt,
        ]);
        $job->save(false);
        return $job;
    }

    public function markRunning(): void
    {
        $this->status     = self::STATUS_RUNNING;
        $this->started_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markCompleted(int $pushed, int $deleted, int $conflicts, array $tables): void
    {
        $this->status          = self::STATUS_COMPLETED;
        $this->records_pushed  = $pushed;
        $this->records_deleted = $deleted;
        $this->conflicts       = $conflicts;
        $this->tables_synced   = json_encode($tables);
        $this->completed_at    = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status        = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->completed_at  = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function getDurationSeconds(): ?int
    {
        if (!$this->started_at || !$this->completed_at) return null;
        return strtotime($this->completed_at) - strtotime($this->started_at);
    }

    public function getStatusBadgeClass(): string
    {
        switch ($this->status) {
            case 'completed':
                return 'success';
            case 'running':
                return 'primary';
            case 'failed':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}