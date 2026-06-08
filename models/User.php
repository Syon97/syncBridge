<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model — replaces the default Yii2 basic template User.php
 * Implements IdentityInterface for session-based auth.
 *
 * @property int    $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property string $access_token
 * @property string $role           admin|operator
 * @property int    $status         1=active, 0=inactive
 * @property string $last_login_at
 * @property string $created_at
 * @property string $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_ACTIVE   = 1;
    const STATUS_INACTIVE = 0;

    const ROLE_ADMIN    = 'admin';
    const ROLE_OPERATOR = 'operator';

    // Virtual attributes for forms
    public string $password        = '';
    public string $password_repeat = '';

    public static function tableName(): string
    {
        return 'users';
    }

    // ----------------------------------------------------------------
    // IdentityInterface
    // ----------------------------------------------------------------

    public static function findIdentity($id): ?self
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return static::findOne(['access_token' => $token, 'status' => self::STATUS_ACTIVE]);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    // ----------------------------------------------------------------
    // Finders
    // ----------------------------------------------------------------

    public static function findByUsername(string $username): ?self
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findByEmail(string $email): ?self
    {
        return static::findOne(['email' => $email, 'status' => self::STATUS_ACTIVE]);
    }

    // ----------------------------------------------------------------
    // Password
    // ----------------------------------------------------------------

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function generateAccessToken(): void
    {
        $this->access_token = Yii::$app->security->generateRandomString(64);
    }

    // ----------------------------------------------------------------
    // Validation rules
    // ----------------------------------------------------------------

    public function rules(): array
    {
        return [
            [['username', 'email'], 'required'],
            [['username'], 'string', 'min' => 3, 'max' => 100],
            [['username'], 'match', 'pattern' => '/^[a-zA-Z0-9_\-]+$/',
                'message' => 'Only letters, numbers, underscores and dashes.'],
            [['username'], 'unique'],
            [['email'], 'email'],
            [['email'], 'unique'],
            [['role'], 'in', 'range' => [self::ROLE_ADMIN, self::ROLE_OPERATOR]],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],

            // Password fields (only required on create)
            [['password'], 'required', 'on' => 'create'],
            [['password'], 'string', 'min' => 8],
            [['password_repeat'], 'compare', 'compareAttribute' => 'password',
                'message' => 'Passwords do not match.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username'        => 'Username',
            'email'           => 'Email',
            'password'        => 'Password',
            'password_repeat' => 'Confirm password',
            'role'            => 'Role',
            'status'          => 'Status',
            'last_login_at'   => 'Last login',
        ];
    }

    // ----------------------------------------------------------------
    // Lifecycle
    // ----------------------------------------------------------------

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) return false;

        if ($insert) {
            $this->generateAuthKey();
        }

        if (!empty($this->password)) {
            $this->setPassword($this->password);
        }

        return true;
    }

    public function recordLogin(): void
    {
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getRoleBadgeClass(): string
    {
        return $this->role === self::ROLE_ADMIN ? 'danger' : 'secondary';
    }

    public function getStatusLabel(): string
    {
        return $this->status === self::STATUS_ACTIVE ? 'Active' : 'Inactive';
    }

    public function getApiTokens(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ApiToken::class, ['user_id' => 'id']);
    }
}