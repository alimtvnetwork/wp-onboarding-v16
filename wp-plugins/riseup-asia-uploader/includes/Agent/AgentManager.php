<?php
/**
 * Riseup Asia Uploader - Agent Manager
 *
 * Manages agent sites for multi-site orchestration (master-agent architecture).
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/AgentCrudTrait.php';
require_once __DIR__ . '/Traits/AgentRemoteTrait.php';
require_once __DIR__ . '/Traits/AgentLoggingTrait.php';

/**
 * Class RiseupAgentManager
 *
 * Handles CRUD operations for agent sites and remote plugin control.
 */
class RiseupAgentManager {

    use AgentCrudTrait;
    use AgentRemoteTrait;
    use AgentLoggingTrait;

    /** @var string Encryption key for app passwords. */
    private $encryption_key;

    /** @var RiseupFileLogger */
    private $file_logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupAgentManager|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupAgentManager
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
        $this->db = RiseupDatabase::get_instance();
        $this->encryption_key = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
    }

    /**
     * Encrypt a string using AES-256-GCM.
     *
     * @param string $plaintext The plaintext to encrypt.
     * @return string Base64-encoded ciphertext with IV and tag.
     */
    private function encrypt($plaintext) {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a string encrypted with AES-256-GCM.
     *
     * @param string $encrypted Base64-encoded ciphertext.
     * @return string|false Decrypted plaintext or false on failure.
     */
    private function decrypt($encrypted) {
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
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }
}
