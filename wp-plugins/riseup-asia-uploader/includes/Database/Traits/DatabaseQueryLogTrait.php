<?php
/**
 * DatabaseQueryLogTrait — Transaction logging and enhanced context.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseQueryLogTrait {

    /**
     * Log a transaction using ORM.
     *
     * @param string      $action      Action type.
     * @param string|null $plugin_slug Plugin slug.
     * @param int|null    $post_id     Post ID.
     * @param string      $user_login  WordPress username.
     * @param int|null    $user_id     WordPress user ID.
     * @param string      $ip_address  Client IP.
     * @param array       $details     Additional details.
     * @param string      $status      Status.
     * @param string|null $error_msg   Error message.
     * @param array       $enhanced    Enhanced fields.
     * @return int|false Insert ID or false.
     */
    public function log_transaction(
        $action,
        $plugin_slug = null,
        $post_id = null,
        $user_login = '',
        $user_id = null,
        $ip_address = '',
        $details = array(),
        $status = self::STATUS_SUCCESS,
        $error_msg = null,
        $enhanced = array()
    ) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready, cannot log transaction');
            return false;
        }

        try {
            $this->file_logger->debug('Logging transaction', array(
                'action' => $action, 'status' => $status, 'enhanced' => $enhanced,
            ));

            $record = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->create()
                ->set('action', $action)
                ->set('plugin_slug', $plugin_slug)
                ->set('post_id', $post_id)
                ->set('user_login', $user_login)
                ->set('user_id', $user_id)
                ->set('ip_address', $ip_address)
                ->set('details', !empty($details) ? json_encode($details) : null)
                ->set('status', $status)
                ->set('error_msg', $error_msg)
                ->set('created_at', gmdate('Y-m-d\TH:i:s\Z'));

            $this->applyEnhancedFields($record, $enhanced);
            $result = $record->save();
            $this->file_logger->info('Transaction logged', array('id' => $result));

            return $result;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to log transaction');
            return false;
        }
    }

    /**
     * Apply enhanced metadata fields to a transaction record.
     */
    private function applyEnhancedFields($record, array $enhanced) {
        $string_fields = array('plugin_file', 'triggered_by', 'source_machine', 'plugin_version', 'upload_source');
        foreach ($string_fields as $field) {
            if (!empty($enhanced[$field])) {
                $record->set($field, $enhanced[$field]);
            }
        }

        if (!empty($enhanced['agent_site_id'])) {
            $record->set('agent_site_id', (int) $enhanced['agent_site_id']);
        }

        if (isset($enhanced['was_active'])) {
            $record->set('was_active', $enhanced['was_active'] ? 1 : 0);
        }
    }

    /**
     * Log a transaction with enhanced context (convenience wrapper).
     */
    public function log_enhanced_transaction($params) {
        return $this->log_transaction(
            $params['action'] ?? '',
            $params['plugin_slug'] ?? null,
            $params['post_id'] ?? null,
            $params['user_login'] ?? '',
            $params['user_id'] ?? null,
            $params['ip_address'] ?? '',
            $params['details'] ?? array(),
            $params['status'] ?? self::STATUS_SUCCESS,
            $params['error_msg'] ?? null,
            array(
                'plugin_file'    => $params['plugin_file'] ?? null,
                'was_active'     => $params['was_active'] ?? null,
                'triggered_by'   => $params['triggered_by'] ?? null,
                'agent_site_id'  => $params['agent_site_id'] ?? null,
                'plugin_version' => $params['plugin_version'] ?? null,
                'upload_source'  => $params['upload_source'] ?? null,
            )
        );
    }

    /**
     * Get transaction by ID.
     */
    public function get_transaction($id) {
        if (!$this->is_ready()) {
            return null;
        }

        try {
            $log = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->find_one((int) $id);

            if ($log && !empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }

            return $log;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to get transaction');

            return null;
        }
    }
}
