<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * ApiToken — REST API bearer tokens
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $token
 * @property string $label
 * @property string $last_used_at
 * @property string $expires_at
 * @property string $created_at
 */
class ApiToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'api_tokens';
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['label'], 'string', 'max' => 100],
            [['label'], 'default', 'value' => 'Default'],
            [['expires_at'], 'safe'],
        ];
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function generate(int $userId, string $label = 'Default'): self
    {
        $token        = new self();
        $token->user_id = $userId;
        $token->token   = Yii::$app->security->generateRandomString(64);
        $token->label   = $label;
        $token->save(false);
        return $token;
    }

    public function recordUsage(): void
    {
        $this->last_used_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) return false;
        return strtotime($this->expires_at) < time();
    }

    /**
     * Validate a raw token string — finds the token, checks expiry.
     */
    public static function validate(string $raw): ?self // Returns token record if valid, null if not
    {
        $token = static::findOne(['token' => $raw]);
        if (!$token || $token->isExpired()) return null;
        $token->recordUsage();
        return $token;
    }
}