<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Snapshot\Traits\UpdraftCrudTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotProviderUpdraft extends SnapshotProviderInterface {
    use UpdraftCrudTrait;

    protected string $provider_id = 'updraft';
    protected string $provider_name = 'UpdraftPlus';
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
        $is_premium = defined('UPDRAFTPLUS_VERSION') && strpos(UPDRAFTPLUS_VERSION, 'premium') !== false;
        return array(
            'full_site' => true, 'database_only' => true,
            'selective' => $is_premium, 'scheduled' => true,
            'restore' => true, 'export' => true, 'import' => true,
        );
    }
}

class_alias(SnapshotProviderUpdraft::class, 'RiseupSnapshotProviderUpdraft');
