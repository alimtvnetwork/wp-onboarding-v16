<?php
/**
 * AgentLoggingTrait — Action logging and history retrieval for agent operations.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDOException;
use RiseupAsia\Database\Orm;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\DateHelper;

trait AgentLoggingTrait {

    private const AGENT_ACTIONS_TABLE = 'AgentActions';

    public function logAction(
        int $agentId,
        string $action,
        ?string $plugin = null,
        string $status = 'success', // StatusType::Success->value — PHP disallows enum in defaults
        ?array $details = null,
        ?string $errorMsg = null,
    ): int|false {
        try {
            $pdo = $this->db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return false;
            }

            $hasDetails = !empty($details);

            $result = Orm::forTable(self::AGENT_ACTIONS_TABLE)
                ->create()
                ->set('AgentSiteId', $agentId)
                ->set('Action', sanitize_key($action))
                ->set('TargetPlugin', $plugin !== null ? sanitize_text_field($plugin) : null)
                ->set('Status', sanitize_key($status))
                ->set('Details', $hasDetails ? json_encode($details) : null)
                ->set('ErrorMsg', $errorMsg !== null ? sanitize_text_field($errorMsg) : null)
                ->set('CreatedAt', DateHelper::nowUtc())
                ->save();

            return $result;
        } catch (PDOException $e) {
            return ErrorResponse::logAndReturnFalse($this->fileLogger, $e, 'Failed to log agent action');
        }
    }

    public function getActionHistory(
        int $agentId,
        int $limit = 50, // PaginationConfigType::DefaultLimit (PHP constraint: no enum in defaults)
        int $offset = 0,
    ): array {
        $totalKey = ResponseKeyType::Total->value;
        $actionsKey = ResponseKeyType::Actions->value;

        try {
            $pdo = $this->db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return [$totalKey => 0, $actionsKey => []];
            }

            $total = Orm::forTable(self::AGENT_ACTIONS_TABLE)
                ->where('AgentSiteId', $agentId)
                ->count();

            $actions = Orm::forTable(self::AGENT_ACTIONS_TABLE)
                ->where('AgentSiteId', $agentId)
                ->orderByDesc('CreatedAt')
                ->limit($limit)
                ->offset($offset)
                ->findMany();

            $this->decodeActionDetails($actions);

            return [$totalKey => $total, $actionsKey => $actions];
        } catch (PDOException $e) {
            $this->fileLogger->logException($e, 'Failed to get action history');

            return [$totalKey => 0, $actionsKey => []];
        }
    }

    private function decodeActionDetails(array &$actions): void {
        foreach ($actions as &$action) {
            if (!empty($action['Details'])) {
                $action['Details'] = json_decode($action['Details'], true);
            }
        }
    }
}
