<?php
/**
 * Riseup Asia Uploader - UpdraftPlus Snapshot Provider
 *
 * Integrates with UpdraftPlus plugin for database backups.
 * Shell class — logic delegated to UpdraftCrudTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SnapshotProviderInterface.php';
require_once dirname(__FILE__) . '/Traits/UpdraftCrudTrait.php';

use RiseupAsia\Enums\SnapshotProviderType;

/**
 * UpdraftPlus Snapshot Provider.
 */
class RiseupSnapshotProviderUpdraft extends RiseupSnapshotProviderInterface {

    use UpdraftCrudTrait;

    /** @var string Provider ID — literal used because ->value is not a constant expression. */
    protected string $provider_id = 'updraft';

    protected string $provider_name = 'UpdraftPlus';

    private mixed $updraft = null;

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        parent::__construct($logger, $db);

        if (class_exists('UpdraftPlus')) {
            global $updraftplus;
            $this->updraft = $updraftplus;
        }
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if UpdraftPlus is installed and active.
     */
    public function isAvailable(): bool {
        return class_exists('UpdraftPlus') || isset($GLOBALS['updraftplus']);
    }

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities array.
     */
    public function getCapabilities(): array {
        $is_premium = defined('UPDRAFTPLUS_VERSION') &&
                      strpos(UPDRAFTPLUS_VERSION, 'premium') !== false;

        return array(
            'full_site' => true, 'database_only' => true,
            'selective' => $is_premium, 'scheduled' => true,
            'restore' => true, 'export' => true, 'import' => true,
        );
    }
}
