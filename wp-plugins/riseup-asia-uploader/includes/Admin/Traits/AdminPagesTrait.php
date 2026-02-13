<?php
/**
 * Admin Pages Trait
 *
 * Rendering methods for admin pages (logs, settings, agents, snapshots).
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AdminPagesTrait {

    /**
     * Render the logs page.
     */
    public function render_logs_page() {
        $filters = $this->buildLogFilters();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $db = RiseupDatabase::get_instance();
        $result = $db->query_transactions($filters, $per_page, $offset);
        $logs = $result['logs'];
        $total = $result['total'];
        $total_pages = ceil($total / $per_page);

        $action_labels = $this->getActionLabels();

        include dirname(__FILE__) . '/../../templates/admin-logs.php';
    }

    /**
     * Build log filters from query parameters.
     *
     * @return array Filter values.
     */
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

    /**
     * Get action label map for display.
     *
     * @return array Action labels.
     */
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

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        $update_settings = RiseupUpdateResolver::get_instance()->get_settings();

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotFactory.php';
        $detector = RiseupSnapshotFactory::detector();
        $snapshot_settings = $detector->getSettings();
        $snapshot_providers = $detector->detectAvailableProviders();

        $endpoint_groups = $this->buildEndpointGroups();
        $endpoints_meta = $this->flattenEndpointGroups($endpoint_groups);

        include dirname(__FILE__) . '/../../templates/admin-settings.php';
    }

    /**
     * Build endpoint group metadata for display.
     *
     * @return array Endpoint groups.
     */
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

    /**
     * Flatten endpoint groups for backward compatibility.
     *
     * @param array $groups Endpoint groups.
     * @return array Flat endpoint metadata.
     */
    private function flattenEndpointGroups(array $groups): array {
        $endpoints_meta = array();
        foreach ($groups as $group) {
            foreach ($group['endpoints'] as $key => $meta) {
                $endpoints_meta[$key] = $meta;
            }
        }

        return $endpoints_meta;
    }

    /**
     * Render the agent sites page.
     */
    public function render_agents_page() {
        include dirname(__FILE__) . '/../../templates/admin-agents.php';
    }

    /**
     * Render the snapshots page.
     */
    public function render_snapshots_page() {
        include dirname(__FILE__) . '/../../templates/admin-snapshots.php';
    }
}
