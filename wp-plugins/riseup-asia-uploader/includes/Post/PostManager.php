<?php
/**
 * Riseup Asia Uploader - Post Manager
 *
 * @package RiseupAsia\Post
 * @since   1.4.0
 */

namespace RiseupAsia\Post;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Post\Traits\PostCrudTrait;
use RiseupAsia\Post\Traits\PostQueryTrait;
use RiseupAsia\Post\Traits\CategoryTrait;
use RiseupAsia\Enums\PostStatusType;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Logging\Logger;
use RiseupAsia\ErrorHandling\ErrorResponse;

class PostManager {

    use PostCrudTrait;
    use PostQueryTrait;
    use CategoryTrait;

    private Logger $logger;
    private FileLogger $fileLogger;
    private static ?PostManager $instance = null;

    public static function getInstance(): PostManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->logger = Logger::getInstance();
        $this->fileLogger->info('Post manager initialized');
    }

    private function validatePostStatus(string $status): string {
        $validStatuses = PostStatusType::validValues();
        return in_array($status, $validStatuses, true) ? $status : PostStatusType::Draft->value;
    }
}

class_alias(PostManager::class, 'RiseupPostManager');
