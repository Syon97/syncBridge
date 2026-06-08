<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * DbConnection — registered local/cloud database credential
 *
 * @property int    $id
 * @property string $label
 * @property string $type              local|cloud
 * @property string $host
 * @property int    $port
 * @property string $dbname
 * @property string $username
 * @property string $password_encrypted
 * @property int    $is_reachable
 * @property string $last_tested_at
 * @property string $notes
 * @property string $created_at
 * @property string $updated_at
 */
class DbConnection extends ActiveRecord
{
    // Encryption key — store this in params.php, NOT hardcoded in production
    const ENCRYPT_KEY = 'SYNCBRIDGE_SECRET_KEY_32BYTES!!!';  // exactly 32 chars
    const ENCRYPT_CIPHER = 'AES-256-CBC';

    // Virtual attribute — plain password from form
    public $password_plain;

    public static function tableName(): string
    {
        return 'db_connections';
    }

    public function rules(): array
    {
        return [
            [['label', 'type', 'host', 'dbname', 'username'], 'required'],
            [['label'], 'string', 'max' => 100],
            [['label'], 'unique'],
            [['type'], 'in', 'range' => ['local', 'cloud']],
            [['host'], 'string', 'max' => 255],
            [['port'], 'integer', 'min' => 1, 'max' => 65535],
            [['port'], 'default', 'value' => 3306],
            [['dbname', 'username'], 'string', 'max' => 100],
            [['password_plain'], 'string', 'max' => 255],
            [['notes'], 'string'],
            [['is_reachable'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'label'          => 'Connection Label',
            'type'           => 'Type',
            'host'           => 'Host',
            'port'           => 'Port',
            'dbname'         => 'Database Name',
            'username'       => 'Username',
            'password_plain' => 'Password',
            'notes'          => 'Notes',
            'is_reachable'   => 'Reachable',
            'last_tested_at' => 'Last Tested',
        ];
    }

    // ----------------------------------------------------------------
    // Encryption helpers
    // ----------------------------------------------------------------

    public static function encryptPassword(string $plain): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPT_CIPHER));
        $encrypted = openssl_encrypt($plain, self::ENCRYPT_CIPHER, self::ENCRYPT_KEY, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    public static function decryptPassword(string $stored): string
    {
        $decoded = base64_decode($stored);
        [$iv, $encrypted] = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted, self::ENCRYPT_CIPHER, self::ENCRYPT_KEY, 0, $iv);
    }

    // ----------------------------------------------------------------
    // Lifecycle
    // ----------------------------------------------------------------

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) return false;

        // Encrypt password only when a new plain password is provided
        if (!empty($this->password_plain)) {
            $this->password_encrypted = self::encryptPassword($this->password_plain);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Live connection test
    // ----------------------------------------------------------------

    /**
     * Attempt a real PDO connection to the target database.
     * Updates is_reachable and last_tested_at.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array
    {
        try {
            $plain = self::decryptPassword($this->password_encrypted);
            $dsn   = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            $pdo   = new \PDO($dsn, $this->username, $plain, [
                \PDO::ATTR_TIMEOUT            => 5,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            // Quick smoke-test query
            $pdo->query('SELECT 1');

            $this->is_reachable   = 1;
            $this->last_tested_at = date('Y-m-d H:i:s');
            $this->save(false);

            return ['success' => true, 'message' => "Connected to {$this->dbname} on {$this->host} successfully."];

        } catch (\PDOException $e) {
            $this->is_reachable   = 0;
            $this->last_tested_at = date('Y-m-d H:i:s');
            $this->save(false);

            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function getSyncPairsAsLocal(): \yii\db\ActiveQuery
    {
        return $this->hasMany(SyncPair::class, ['local_conn_id' => 'id']);
    }

    public function getSyncPairsAsCloud(): \yii\db\ActiveQuery
    {
        return $this->hasMany(SyncPair::class, ['cloud_conn_id' => 'id']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function getTypeBadge(): string
    {
        return $this->type === 'local' ? 'LOCAL' : 'CLOUD';
    }

    public function getStatusLabel(): string
    {
        return $this->is_reachable ? 'Reachable' : 'Unreachable';
    }
}