<?php
/**
 * SnapshotSettingsHandlerTrait — settings, providers, tables, dependencies handlers.
 *
 * @package RiseupAsia\Traits\Snapshot
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\DependencyAnalyzer;

trait SnapshotSettingsHandlerTrait {

    /** Handle getting snapshot settings. */
    public function handleGetSnapshotSettings(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => true,
                    'settings' => $manager->getSettings(),
                ),
                HttpStatusType::Ok->value,
            );
        }, 'get_snapshot_settings');
    }

    /** Handle updating snapshot settings. */
    public function handleUpdateSnapshotSettings(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->fileLogger->info('Updating snapshot settings', array('keys' => array_keys($body)));
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $updated = $manager->updateSettings($body);
            $this->logger->logPluginAction(ActionType::SnapshotSettingsUpdate->value, LogCategoryType::Snapshot->value, StatusType::Success->value, array('keys' => array_keys($body)));

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => true,
                    'settings' => $updated,
                ),
                HttpStatusType::Ok->value,
            );
        }, 'update_snapshot_settings');
    }

    /** Handle listing snapshot providers. */
    public function handleListSnapshotProviders(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => true,
                    'providers' => $manager->getProviders(),
                ),
                HttpStatusType::Ok->value,
            );
        }, 'list_snapshot_providers');
    }

    /** Handle listing available database tables. */
    public function handleListSnapshotTables(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Tables->value => $manager->getAvailableTables(),
                ),
                HttpStatusType::Ok->value,
            );
        }, 'list_snapshot_tables');
    }

    /** Handle dependency analysis request. */
    public function handleAnalyzeDependencies(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body[ResponseKeyType::Scope->value]) ? $body[ResponseKeyType::Scope->value] : 'all';
            $analyzer = DependencyAnalyzer::getInstance($this->fileLogger);
            $analysis = $analyzer->analyze($scope);

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Tables->value => $analysis[ResponseKeyType::Tables->value],
                    'dependencies' => $analysis['dependencies'],
                    ResponseKeyType::SeedOrder->value => $analysis[ResponseKeyType::SeedOrder->value],
                    ResponseKeyType::TableCount->value => $analysis[ResponseKeyType::TableCount->value],
                    ResponseKeyType::DepCount->value => $analysis[ResponseKeyType::DepCount->value],
                ),
                HttpStatusType::Ok->value,
            );
        }, 'analyze_dependencies');
    }
}
