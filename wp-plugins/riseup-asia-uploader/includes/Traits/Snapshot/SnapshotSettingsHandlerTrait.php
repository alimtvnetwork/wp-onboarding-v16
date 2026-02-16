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
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\DependencyAnalyzer;

trait SnapshotSettingsHandlerTrait {

    /** Handle getting snapshot settings. */
    public function handleGetSnapshotSettings(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            return new WP_REST_Response(array('success' => true, 'settings' => $manager->getSettings()), 200);
        }, 'get_snapshot_settings');
    }

    /** Handle updating snapshot settings. */
    public function handleUpdateSnapshotSettings(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->fileLogger->info('Updating snapshot settings', array('keys' => array_keys($body)));
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $updated = $manager->updateSettings($body);
            $this->logger->logPluginAction('snapshot_settings_update', 'snapshot', StatusType::Success->value, array('keys' => array_keys($body)));
            return new WP_REST_Response(array('success' => true, 'settings' => $updated), 200);
        }, 'update_snapshot_settings');
    }

    /** Handle listing snapshot providers. */
    public function handleListSnapshotProviders(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            return new WP_REST_Response(array('success' => true, 'providers' => $manager->getProviders()), 200);
        }, 'list_snapshot_providers');
    }

    /** Handle listing available database tables. */
    public function handleListSnapshotTables(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() {
            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            return new WP_REST_Response(array('success' => true, 'tables' => $manager->getAvailableTables()), 200);
        }, 'list_snapshot_tables');
    }

    /** Handle dependency analysis request. */
    public function handleAnalyzeDependencies(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body['scope']) ? $body['scope'] : 'all';
            $analyzer = DependencyAnalyzer::getInstance($this->fileLogger);
            $analysis = $analyzer->analyze($scope);
            return new WP_REST_Response(array(
                'success' => true, 'tables' => $analysis['tables'], 'dependencies' => $analysis['dependencies'],
                'seed_order' => $analysis['seed_order'], 'table_count' => $analysis['table_count'], 'dep_count' => $analysis['dep_count'],
            ), 200);
        }, 'analyze_dependencies');
    }
}