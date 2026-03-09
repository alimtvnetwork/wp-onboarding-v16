<?php
/**
 * DatabaseQueryLogTrait — Transaction logging and enhanced context.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\Database\Orm;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\DateHelper;

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
        array $enhanced = array(),
    ): int|false {
        $isDbUnready = ($this->isReady() === false);

        if ($isDbUnready) {
            $this->fileLogger->warn('Database not ready, cannot log transaction');

            return false;
        }

        return $this->executeLogTransaction($action, $pluginSlug, $postId, $userLogin, $userId, $ipAddress, $details, $status, $errorMsg, $enhanced);
    }

    private function executeLogTransaction(
        string $action,
        ?string $pluginSlug,
        ?int $postId,
        string $userLogin,
        ?int $userId,
        string $ipAddress,
        array $details,
        string $status,
        ?string $errorMsg,
        array $enhanced,
    ): int|false {
        try {
            $record = $this->buildTransactionRecord($action, $pluginSlug, $postId, $userLogin, $userId, $ipAddress, $details, $status, $errorMsg);
            $this->applyEnhancedFields($record, $enhanced);

            $result = $record->save();
            $this->fileLogger->info('Transaction logged', array('id' => $result));

            return $result;
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnFalse($this->fileLogger, $e, 'Failed to log transaction');
        }
    }

    private function buildTransactionRecord(
        string $action,
        ?string $pluginSlug,
        ?int $postId,
        string $userLogin,
        ?int $userId,
        string $ipAddress,
        array $details,
        string $status,
        ?string $errorMsg,
    ) {
        $hasDetails = !empty($details);
        $detailsJson = $hasDetails ? json_encode($details) : null;

        return $this->createTransactionOrm($action, $pluginSlug, $postId, $userLogin, $userId, $ipAddress, $detailsJson, $status, $errorMsg);
    }

    private function createTransactionOrm(
        string $action,
        ?string $pluginSlug,
        ?int $postId,
        string $userLogin,
        ?int $userId,
        string $ipAddress,
        ?string $detailsJson,
        string $status,
        ?string $errorMsg,
    ) {
        return Orm::forTable(TableType::Transactions->value)
            ->create()
            ->set('Action', $action)
            ->set('PluginSlug', $pluginSlug)
            ->set('PostId', $postId)
            ->set('UserLogin', $userLogin)
            ->set('UserId', $userId)
            ->set('IpAddress', $ipAddress)
            ->set('Details', $detailsJson)
            ->set('Status', $status)
            ->set('ErrorMsg', $errorMsg)
            ->set('CreatedAt', DateHelper::nowUtc());
    }

    private function applyEnhancedFields($record, array $enhanced): void {
        $this->applyStringEnhancedFields($record, $enhanced);
        $this->applySpecialEnhancedFields($record, $enhanced);
    }

    private function applyStringEnhancedFields($record, array $enhanced): void {
        $fieldMap = array(
            'pluginFile'    => 'PluginFile',
            'triggeredBy'   => 'TriggeredBy',
            'sourceMachine' => 'SourceMachine',
            'pluginVersion' => 'PluginVersion',
            'uploadSource'  => 'UploadSource',
        );

        foreach ($fieldMap as $paramKey => $dbColumn) {
            $hasField = !empty($enhanced[$paramKey] ?? null);

            if ($hasField) {
                $record->set($dbColumn, $enhanced[$paramKey]);
            }
        }
    }

    private function applySpecialEnhancedFields($record, array $enhanced): void {
        $hasAgentSiteId = !empty($enhanced['agentSiteId'] ?? null);

        if ($hasAgentSiteId) {
            $record->set('AgentSiteId', (int) $enhanced['agentSiteId']);
        }

        if (isset($enhanced['wasActive'])) {
            $record->set('WasActive', $enhanced['wasActive'] ? 1 : 0);
        }
    }

    public function logEnhancedTransaction(array $params): int|false {
        return $this->logTransaction(
            $params['action'] ?? '',
            $params['pluginSlug'] ?? null,
            $params['postId'] ?? null,
            $params['userLogin'] ?? '',
            $params['userId'] ?? null,
            $params['ipAddress'] ?? '',
            $params['details'] ?? array(),
            $params['status'] ?? StatusType::Success->value,
            $params['errorMsg'] ?? null,
            $this->buildEnhancedArray($params),
        );
    }

    private function buildEnhancedArray(array $params): array {
        return array(
            'pluginFile'    => $params['pluginFile'] ?? null,
            'wasActive'     => $params['wasActive'] ?? null,
            'triggeredBy'   => $params['triggeredBy'] ?? null,
            'agentSiteId'   => $params['agentSiteId'] ?? null,
            'pluginVersion' => $params['pluginVersion'] ?? null,
            'uploadSource'  => $params['uploadSource'] ?? null,
        );
    }

    public function getTransaction(int $id): ?array {
        $isDbUnready = ($this->isReady() === false);

        if ($isDbUnready) {
            return null;
        }

        try {
            return $this->fetchAndDecodeTransaction($id);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get transaction');

            return null;
        }
    }

    private function fetchAndDecodeTransaction(int $id): ?array {
        $log = Orm::forTable(TableType::Transactions->value)->findOne($id);
        $hasLogDetails = $log && !empty($log['Details'] ?? null);

        if ($hasLogDetails) {
            $log['Details'] = json_decode($log['Details'], true);
        }

        return $log;
    }
}
