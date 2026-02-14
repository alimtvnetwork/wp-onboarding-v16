<?php
/**
 * ManagerCoreTrait — snapshot CRUD, provider delegation, and logging.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait ManagerCoreTrait {

    /** Get the active snapshot provider. */
    public function getProvider() {
        $provider_id = $this->detector->getActiveProvider();
        return $this->detector->getProviderInstance($provider_id, $this->logger, $this->db);
    }

    /** Create a new snapshot. */
    public function createSnapshot($options = array()) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available', 'code' => ERR_PROVIDER_NOT_AVAILABLE);
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot', array(
            'provider' => $provider->getProviderId(),
            'scope' => isset($options['scope']) ? $options['scope'] : 'default',
        ));

        return $provider->createSnapshot($options);
    }

    /** Delete a snapshot. */
    public function deleteSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->deleteSnapshot($snapshot_id);
    }

    /** Get snapshot details. */
    public function getSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return null;
        }

        return $provider->getSnapshot($snapshot_id);
    }

    /** List all snapshots. */
    public function listSnapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value);

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /** Get available providers and their status. */
    public function getProviders() {
        return $this->detector->getAvailableProviders();
    }

    /** Get available database tables. */
    public function getAvailableTables() {
        $provider = $this->getProvider();
        if (!$provider) {
            return array();
        }

        return $provider->getAvailableTables();
    }

    /** Log a message with manager context. */
    private function log($level, $message, $context = array()) {
        $full = '[SNAPSHOT] [MANAGER] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        $levelEnum = LogLevelType::from($level);
        $method = strtolower($levelEnum->value);
        $this->logger->$method($full);
    }
}
