<?php
/**
 * Admin Pages Trait
 *
 * Rendering methods for admin pages (logs, settings, agents, snapshots).
 *
 * @package RiseupAsia\Admin\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Database\Database;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Update\UpdateResolver;
use RiseupAsia\Snapshot\SnapshotFactory;

trait AdminPagesTrait {

    /** Render the logs page. */
    public function renderLogsPage() {
        $filters = $this->buildLogFilters();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = PaginationConfigType::DefaultLimit->value;
        $offset = ($page - 1) * $perPage;

        $db = Database::getInstance();
        $result = $db->queryTransactions($filters, $perPage, $offset);
        $logs = $result['logs'];
        $total = $result['total'];
        $totalPages = ceil($total / $perPage);

        $actionLabels = $this->getActionLabels();

        include dirname(__FILE__) . '/../../templates/admin-logs.php';
    }

    /** Build log filters from query parameters. */
    private function buildLogFilters(): array {
        $keys = array(
            'action' => 'filter_action', 'user' => 'filter_user', 'status' => 'filter_status',
            'plugin' => 'filter_plugin', 'from' => 'filter_from', 'to' => 'filter_to',
            'triggered_by' => 'filter_triggered_by', 'source_machine' => 'filter_source_machine',
            'upload_source' => 'filter_upload_source',
        );

        $filters = array();
        foreach ($keys as $key => $param) {
            $filters[$key] = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
        }

        return $filters;
    }

    /** Get action label map for display. */
    private function getActionLabels(): array {
        return array(
            'upload_initiated' => 'Upload Initiated',
            'upload'          => 'Plugin Upload',
            'upload_active'   => 'Upload & Activate',
            'enable'          => 'Plugin Enable',
            'disable'         => 'Plugin Disable',
            'delete'          => 'Plugin Delete',
            'file_replace'    => 'File Replace',
            'file_delete'     => 'File Delete',
            'sync'            => 'Sync Check',
            'post_create'     => 'Post Create',
            'post_update'     => 'Post Update',
            'category_create' => 'Category Create',
            'media_upload'    => 'Media Upload',
            'auth_failed'     => 'Auth Failed',
            'export_self'     => 'Export Self',
        );
    }

    /** Render the settings page. */
    public function renderSettingsPage() {
        $settings = self::getSettings();
        $updateSettings = UpdateResolver::getInstance()->getSettings();

        $detector = SnapshotFactory::detector();
        $snapshotSettings = $detector->getSettings();
        $snapshotProviders = $detector->detectAvailableProviders();

        $endpointGroups = $this->buildEndpointGroups();
        $endpointsMeta = $this->flattenEndpointGroups($endpointGroups);

        include dirname(__FILE__) . '/../../templates/admin-settings.php';
    }

    /** Build endpoint group metadata for display. */
    private function buildEndpointGroups(): array {
        return array(
            'core' => array(
                'label' => __('Core Operations', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-admin-tools',
                'endpoints' => array(
                    'status'       => array('label' => 'Status Check', 'desc' => 'Returns plugin status and version'),
                    'upload'       => array('label' => 'Plugin Upload', 'desc' => 'Upload and install plugins'),
                    'plugins'      => array('label' => 'List Plugins', 'desc' => 'List all installed plugins'),
                    'plugin_files' => array('label' => 'Plugin Files', 'desc' => 'List files in a plugin'),
                    'plugin_file'  => array('label' => 'File Content', 'desc' => 'Get file content from plugin'),
                    'export_self'  => array('label' => 'Export Self', 'desc' => 'Export this plugin as ZIP'),
                ),
            ),
            'content' => array(
                'label' => __('Content Management', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-edit-page',
                'endpoints' => array(
                    'posts'      => array('label' => 'Blog Posts', 'desc' => 'Create and manage posts'),
                    'categories' => array('label' => 'Categories', 'desc' => 'Create and manage categories'),
                ),
            ),
            'monitoring' => array(
                'label' => __('Monitoring & Logs', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-chart-area',
                'endpoints' => array(
                    'logs'       => array('label' => 'Logs API', 'desc' => 'Fetch transaction logs'),
                    'logs_stats' => array('label' => 'Logs Stats', 'desc' => 'Get log statistics'),
                    'error_logs' => array('label' => 'Error Logs', 'desc' => 'Fetch error log sessions'),
                ),
            ),
            'backup' => array(
                'label' => __('Backups & Snapshots', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-database',
                'endpoints' => array(
                    'snapshots' => array('label' => 'Snapshots', 'desc' => 'Database snapshot operations and scheduling'),
                ),
            ),
            'docs' => array(
                'label' => __('Documentation', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-media-document',
                'endpoints' => array(
                    'openapi' => array('label' => 'OpenAPI Spec', 'desc' => 'API documentation endpoint'),
                ),
            ),
        );
    }

    /** Flatten endpoint groups for backward compatibility. */
    private function flattenEndpointGroups(array $groups): array {
        $endpointsMeta = array();
        foreach ($groups as $group) {
            foreach ($group['endpoints'] as $key => $meta) {
                $endpointsMeta[$key] = $meta;
            }
        }

        return $endpointsMeta;
    }

    /** Render the agent sites page. */
    public function renderAgentsPage() {
        include dirname(__FILE__) . '/../../templates/admin-agents.php';
    }

    /** Render the snapshots page. */
    public function renderSnapshotsPage() {
        include dirname(__FILE__) . '/../../templates/admin-snapshots.php';
    }
}
