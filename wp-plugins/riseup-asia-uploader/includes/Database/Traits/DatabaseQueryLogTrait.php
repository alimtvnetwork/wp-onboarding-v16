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

use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TableType;

trait DatabaseQueryLogTrait {

    /**
     * Log a transaction using ORM.
     *
     * Uses 10 params due to database record mapping — justified utility exception.
     */
    public function logTransaction(
        string $action,
        ?string $pluginSlug = null,
        ?int $postId = null,
        string $userLogin = '',
        ?int $userId = null,
        string $ipAddress = '',
        array $details = array(),
        string $status = 'success',
        ?string $errorMsg = null,
        array $enhanced = array()
    ): int|false {
        if (!$this->isReady()) {
            $this->fileLogger->warn('Database not ready, cannot log transaction');
            return false;
        }

        try {
            $this->fileLogger->debug('Logging transaction', array(
                'action' => $action, 'status' => $status, 'enhanced' => $enhanced,
            ));

            $record = $this->buildTransactionRecord($action, $pluginSlug, $postId, $userLogin, $userId, $ipAddress, $details, $status, $errorMsg);
            $this->applyEnhancedFields($record, $enhanced);

            $result = $record->save();
            $this->fileLogger->info('Transaction logged', array('id' => $result));

            return $result;
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnFalse($this->fileLogger, $e, 'Failed to log transaction');
        }
    }

    private function buildTransactionRecord(string $action, ?string $pluginSlug, ?int $postId, string $userLogin, ?int $userId, string $ipAddress, array $details, string $status, ?string $errorMsg) {
        return RiseupORM::forTable(TableType::Transactions->value)
            ->create()
            ->set('action', $action)
            ->set('plugin_slug', $pluginSlug)
            ->set('post_id', $postId)
            ->set('user_login', $userLogin)
            ->set('user_id', $userId)
            ->set('ip_address', $ipAddress)
            ->set('details', !empty($details) ? json_encode($details) : null)
            ->set('status', $status)
            ->set('error_msg', $errorMsg)
            ->set('created_at', gmdate('Y-m-d\TH:i:s\Z'));
    }

    private function applyEnhancedFields($record, array $enhanced): void {
        $stringFields = array('plugin_file', 'triggered_by', 'source_machine', 'plugin_version', 'upload_source');
        foreach ($stringFields as $field) {
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

    public function logEnhancedTransaction(array $params): int|false {
        return $this->logTransaction(
            $params['action'] ?? '',
            $params['plugin_slug'] ?? null,
            $params['post_id'] ?? null,
            $params['user_login'] ?? '',
            $params['user_id'] ?? null,
            $params['ip_address'] ?? '',
            $params['details'] ?? array(),
            $params['status'] ?? StatusType::Success->value,
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

    public function getTransaction(int $id): ?array {
        if (!$this->isReady()) {
            return null;
        }

        try {
            $log = RiseupORM::forTable(TableType::Transactions->value)
                ->findOne($id);

            if ($log && !empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }

            return $log;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get transaction');
            return null;
        }
    }
}
