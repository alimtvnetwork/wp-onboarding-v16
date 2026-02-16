<?php
/**
 * Riseup Asia Uploader - Agent Manager
 *
 * Manages agent sites for multi-site orchestration (master-agent architecture).
 *
 * @package RiseupAsia\Agent
 * @since   1.8.0
 */

namespace RiseupAsia\Agent;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Agent\Traits\AgentCrudTrait;
use RiseupAsia\Agent\Traits\AgentRemoteTrait;
use RiseupAsia\Agent\Traits\AgentLoggingTrait;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Database\Database;
use RiseupAsia\ErrorHandling\ErrorResponse;

class AgentManager {

    use AgentCrudTrait;
    use AgentRemoteTrait;
    use AgentLoggingTrait;

    private string $encryptionKey;
    private FileLogger $fileLogger;
    private Database $db;
    private static ?AgentManager $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->db = Database::getInstance();
        $this->encryptionKey = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
    }

    private function encrypt(string $plaintext): string {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $encrypted): string|false {
        $data = base64_decode($encrypted);

        if (strlen($data) < 28) {
            return false;
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }
}
