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
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\FilterKeyType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Update\UpdateResolver;
use RiseupAsia\Snapshot\SnapshotFactory;
use RiseupAsia\Traits\Log\LogValueTrait;

trait AdminPagesTrait {
    use LogValueTrait;

    /** Render the logs page. */
    public function renderLogsPage() {
        $filters = $this->buildLogFilters();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = PaginationConfigType::DefaultLimit->value;
        $offset = ($page - 1) * $perPage;

        $db = Database::getInstance();
        $result = $db->queryTransactions($filters, $perPage, $offset);
        $logs = $result[ResponseKeyType::Logs->value];
        $total = $result[ResponseKeyType::Total->value];
        $totalPages = ceil($total / $perPage);

        $actionLabels = $this->getActionLabels();

        include dirname(__FILE__, 4) . '/templates/admin-logs.php';
    }

    /** Build log filters from query parameters. */
    private function buildLogFilters(): array {
        $keys = [
            FilterKeyType::Action->value        => 'filter_action',
            FilterKeyType::User->value          => 'filter_user',
            FilterKeyType::Status->value        => 'filter_status',
            FilterKeyType::Plugin->value        => 'filter_plugin',
            FilterKeyType::From->value          => 'filter_from',
            FilterKeyType::To->value            => 'filter_to',
            FilterKeyType::TriggeredBy->value   => 'filter_triggered_by',
            FilterKeyType::SourceMachine->value => 'filter_source_machine',
            FilterKeyType::UploadSource->value  => 'filter_upload_source',
        ];

        $filters = [];

        foreach ($keys as $key => $param) {
            $filters[$key] = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
        }

        return $filters;
    }

    /** Get action label map for display. */
    private function getActionLabels(): array {
        return [
            ActionType::UploadInitiated->value => 'Upload Initiated',
            ActionType::Upload->value          => 'Plugin Upload',
            ActionType::UploadActive->value    => 'Upload & Activate',
            ActionType::Enable->value          => 'Plugin Enable',
            ActionType::Disable->value         => 'Plugin Disable',
            ActionType::Delete->value          => 'Plugin Delete',
            ActionType::FileReplace->value     => 'File Replace',
            ActionType::FileDelete->value      => 'File Delete',
            ActionType::Sync->value            => 'Sync Check',
            ActionType::PostCreate->value      => 'Post Create',
            ActionType::PostUpdate->value      => 'Post Update',
            ActionType::CategoryCreate->value  => 'Category Create',
            ActionType::MediaUpload->value     => 'Media Upload',
            ActionType::AuthFailed->value      => 'Auth Failed',
            ActionType::ExportSelf->value      => 'Export Self',
        ];
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

        $supportSettings = get_option(\RiseupAsia\Enums\OptionNameType::SupportSettings->value, [
            'support_email' => '',
            'fallback_url'  => '',
        ]);

        include dirname(__FILE__, 4) . '/templates/admin-settings.php';
    }

    /** Build endpoint group metadata for display. */
    private function buildEndpointGroups(): array {
        $pluginSlug = PluginConfigType::Slug->value;

        return [
            'core' => [
                'label' => __('Core Operations', $pluginSlug),
                'icon'  => 'dashicons-admin-tools',
                'endpoints' => [
                    'status'       => ['label' => 'Status Check', 'desc' => 'Returns plugin status and version'],
                    'upload'       => ['label' => 'Plugin Upload', 'desc' => 'Upload and install plugins'],
                    'plugins'      => ['label' => 'List Plugins', 'desc' => 'List all installed plugins'],
                    'plugin_files' => ['label' => 'Plugin Files', 'desc' => 'List files in a plugin'],
                    'plugin_file'  => ['label' => 'File Content', 'desc' => 'Get file content from plugin'],
                    'export_self'  => ['label' => 'Export Self', 'desc' => 'Export this plugin as ZIP'],
                ],
            ],
            'content' => [
                'label' => __('Content Management', $pluginSlug),
                'icon'  => 'dashicons-edit-page',
                'endpoints' => [
                    'posts'      => ['label' => 'Blog Posts', 'desc' => 'Create and manage posts'],
                    'categories' => ['label' => 'Categories', 'desc' => 'Create and manage categories'],
                ],
            ],
            'monitoring' => [
                'label' => __('Monitoring & Logs', $pluginSlug),
                'icon'  => 'dashicons-chart-area',
                'endpoints' => [
                    'logs'           => ['label' => 'Logs API', 'desc' => 'Fetch transaction logs'],
                    'logs_stats'     => ['label' => 'Logs Stats', 'desc' => 'Get log statistics'],
                    'logs_status'    => ['label' => 'Remote Logs Status', 'desc' => 'Get remote log file metadata and counters'],
                    'logs_clear'     => ['label' => 'Remote Logs Clear', 'desc' => 'Request secure two-step log clearing token'],
                    'logs_confirm'   => ['label' => 'Remote Logs Confirm', 'desc' => 'Confirm and execute two-step log clearing'],
                    'logs_email'     => ['label' => 'Remote Logs Email', 'desc' => 'Send log files via email attachments'],
                    'error_logs'     => ['label' => 'Error Logs', 'desc' => 'Fetch error log sessions'],
                    'error_sessions' => ['label' => 'Error Sessions', 'desc' => 'Fetch grouped error sessions and stack traces'],
                ],
            ],
            'backup' => [
                'label' => __('Backups & Snapshots', $pluginSlug),
                'icon'  => 'dashicons-database',
                'endpoints' => [
                    'snapshots' => ['label' => 'Snapshots', 'desc' => 'Database snapshot operations and scheduling'],
                ],
            ],
            'docs' => [
                'label' => __('Documentation', $pluginSlug),
                'icon'  => 'dashicons-media-document',
                'endpoints' => [
                    'openapi' => ['label' => 'OpenAPI Spec', 'desc' => 'API documentation endpoint'],
                ],
            ],
        ];
    }

    /** Flatten endpoint groups for backward compatibility. */
    private function flattenEndpointGroups(array $groups): array {
        $endpointsMeta = [];

        foreach ($groups as $group) {
            foreach ($group['endpoints'] as $key => $meta) {
                $endpointsMeta[$key] = $meta;
            }
        }

        return $endpointsMeta;
    }

    /** Render the agent sites page. */
    public function renderAgentsPage() {
        include dirname(__FILE__, 4) . '/templates/admin-agents.php';
    }

    /** Render the snapshots page. */
    public function renderSnapshotsPage() {
        include dirname(__FILE__, 4) . '/templates/admin-snapshots.php';
    }

    /** Render the feedback page. */
    public function renderFeedbackPage() {
        include dirname(__FILE__, 4) . '/templates/admin-feedback.php';
    }
}
