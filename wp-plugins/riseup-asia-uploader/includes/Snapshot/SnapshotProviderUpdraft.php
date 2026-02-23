<?php
/**
 * Riseup Asia Uploader - UpdraftPlus Snapshot Provider
 *
 * @package RiseupAsia\Snapshot
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Snapshot\Traits\UpdraftCrudTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotProviderUpdraft extends SnapshotProviderInterface {
    use UpdraftCrudTrait;

    protected string $providerId = SnapshotProviderType::Updraft->value;
    protected string $providerName = 'UpdraftPlus';
    private mixed $updraft = null;

    public function __construct(FileLogger $logger, Database $db) {
        parent::__construct($logger, $db);

        if (class_exists('UpdraftPlus')) {
            global $updraftplus;
            $this->updraft = $updraftplus;
        }
    }

    public function isAvailable(): bool {
        return class_exists('UpdraftPlus') || isset($GLOBALS['updraftplus']);
    }

    public function getCapabilities(): array {
        $isPremium = defined('UPDRAFTPLUS_VERSION') && strpos(UPDRAFTPLUS_VERSION, 'premium') !== false;

        return array(
            'fullSite' => true,
            'databaseOnly' => true,
            'selective' => $isPremium,
            'scheduled' => true,
            'restore' => true,
            'export' => true,
            'import' => true,
        );
    }
}
