<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * SyncPair — links a local DB connection to a cloud DB connection
 *
 * @property int    $id
 * @property string $label
 * @property int    $local_conn_id
 * @property int    $cloud_conn_id
 * @property string $tables_json
 * @property int    $interval_minutes
 * @property string $conflict_strategy
 * @property string $status
 * @property string $last_synced_at
 * @property string $last_error
 * @property string $created_at
 * @property string $updated_at
 */
class SyncPair extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'sync_pairs';
    }

    public function rules(): array
    {
        return [
            [['local_conn_id', 'cloud_conn_id'], 'required'],
            [['local_conn_id', 'cloud_conn_id'], 'integer'],
            [['local_conn_id'], 'exist', 'targetClass' => DbConnection::class, 'targetAttribute' => 'id'],
            [['cloud_conn_id'], 'exist', 'targetClass' => DbConnection::class, 'targetAttribute' => 'id'],
            [['local_conn_id', 'cloud_conn_id'], 'validateDifferentConnections'],
            [['local_conn_id', 'cloud_conn_id'], 'validateConnectionTypes'],
            [['label'], 'string', 'max' => 150],
            [['tables_json'], 'string'],
            [['tables_json'], 'default', 'value' => '["*"]'],
            [['interval_minutes'], 'integer', 'min' => 1, 'max' => 1440],
            [['interval_minutes'], 'default', 'value' => 5],
            [['conflict_strategy'], 'in', 'range' => ['last_write_wins', 'local_priority', 'cloud_priority', 'flag_review']],
            [['status'], 'in', 'range' => ['active', 'paused', 'error']],
        ];
    }

    public function validateDifferentConnections($attribute)
    {
        if ($this->local_conn_id && $this->cloud_conn_id && $this->local_conn_id == $this->cloud_conn_id) {
            $this->addError($attribute, 'Local and cloud connections must be different.');
        }
    }

    public function validateConnectionTypes($attribute)
    {
        if ($this->localConn && $this->localConn->type !== 'local') {
            $this->addError('local_conn_id', 'Selected connection is not marked as LOCAL type.');
        }
        if ($this->cloudConn && $this->cloudConn->type !== 'cloud') {
            $this->addError('cloud_conn_id', 'Selected connection is not marked as CLOUD type.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'label'             => 'Pair Label',
            'local_conn_id'     => 'Local Database',
            'cloud_conn_id'     => 'Cloud Database',
            'tables_json'       => 'Tables to Sync',
            'interval_minutes'  => 'Sync Interval (minutes)',
            'conflict_strategy' => 'Conflict Strategy',
            'status'            => 'Status',
            'last_synced_at'    => 'Last Synced',
        ];
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function getLocalConn(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DbConnection::class, ['id' => 'local_conn_id']);
    }

    public function getCloudConn(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DbConnection::class, ['id' => 'cloud_conn_id']);
    }

    public function getLogs(): \yii\db\ActiveQuery
    {
        return $this->hasMany(SyncLog::class, ['pair_id' => 'id'])->orderBy(['created_at' => SORT_DESC]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------->

    public function getTablesArray(): array
    {
        $decoded = json_decode($this->tables_json, true);
        return is_array($decoded) ? $decoded : ['*'];
    }

    public function getTablesDisplay(): string
    {
        $tables = $this->getTablesArray();
        return in_array('*', $tables) ? 'All tables' : implode(', ', $tables);
    }

    public function getIntervalOptions(): array
    {
        return [5 => 'Every 5 min', 10 => 'Every 10 min', 15 => 'Every 15 min', 30 => 'Every 30 min', 60 => 'Every hour'];
    }

    public function getConflictOptions(): array
    {
        return [
            'last_write_wins' => 'Last-write wins',
            'local_priority'  => 'Local takes priority',
            'cloud_priority'  => 'Cloud takes priority',
            'flag_review'     => 'Flag for review',
        ];
    }

    public function getStatusBadgeClass(): string
    {
        switch ($this->status) {
            case 'active':
                return 'success';
            case 'paused':
                return 'warning';
            case 'error':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    public function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'active':
                return '● Syncing';
            case 'paused':
                return 'Paused';
            case 'error':
                return '● Error';
            default:
                return 'Unknown';
        }
    }

     /**
     * Relation: all jobs for this pair
     */
    public function getJobs(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\app\models\SyncJob::class, ['pair_id' => 'id'])
                    ->orderBy(['created_at' => SORT_DESC]);
    }
 
    /**
     * Most recent job
     */
    public function getLastJob(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\app\models\SyncJob::class, ['pair_id' => 'id'])
                    ->orderBy(['created_at' => SORT_DESC]);
    }
 
    /**
     * Is this pair due to run right now?
     */
    public function isDue(): bool
    {
        if ($this->status !== 'active') return false;
        if (!$this->next_sync_at)       return true;   // never run yet
        return strtotime($this->next_sync_at) <= time();
    }
 
    /**
     * Schedule next run based on interval
     */
    public function scheduleNext(): void
    {
        $this->next_sync_at = date('Y-m-d H:i:s',
            strtotime("+{$this->interval_minutes} minutes"));
        $this->save(false);
    }
}