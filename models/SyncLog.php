<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * SyncLog — audit trail for every sync operation
 *
 * @property int    $id
 * @property int    $pair_id
 * @property string $level    info|success|warning|error
 * @property string $message
 * @property int    $records_affected
 * @property string $table_name
 * @property int    $duration_ms
 * @property string $created_at
 */
class SyncLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'sync_logs';
    }

    public function rules(): array
    {
        return [
            [['message'], 'required'],
            [['level'], 'in', 'range' => ['info', 'success', 'warning', 'error']],
            [['level'], 'default', 'value' => 'info'],
            [['pair_id', 'records_affected', 'duration_ms'], 'integer'],
            [['message'], 'string'],
            [['table_name'], 'string', 'max' => 100],
        ];
    }

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(SyncPair::class, ['id' => 'pair_id']);
    }

    // Convenience factory
    public static function write(int $pairId = null, string $level = 'info', string $message = '', int $records = 0, string $table = null, int $ms = null): self
    {
        $log = new self([
            'pair_id'          => $pairId,
            'level'            => $level,
            'message'          => $message,
            'records_affected' => $records,
            'table_name'       => $table,
            'duration_ms'      => $ms,
        ]);
        $log->save(false);
        return $log;
    }

    public function getLevelBadgeClass(): string
    {
        switch ($this->level) {
            case 'success':
                return 'success';
            case 'warning':
                return 'warning';
            case 'error':
                return 'danger';
            default:
                return 'info';
        }
    }
}