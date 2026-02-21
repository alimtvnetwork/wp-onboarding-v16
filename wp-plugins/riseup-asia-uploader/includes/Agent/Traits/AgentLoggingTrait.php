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

use PDO;
use PDOException;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait AgentLoggingTrait {
    private const ACTION_INSERT_QUERY = <<<'SQL'
        INSERT INTO AgentActions
            (AgentSiteId, Action, TargetPlugin, Status, Details, ErrorMsg, CreatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    SQL;

    private const ACTION_COUNT_QUERY = 'SELECT COUNT(*) as total FROM AgentActions WHERE AgentSiteId = ?';

    private const ACTION_LIST_QUERY = <<<'SQL'
        SELECT * FROM AgentActions
        WHERE AgentSiteId = ?
        ORDER BY CreatedAt DESC
        LIMIT ? OFFSET ?
    SQL;

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

            return $this->insertActionRecord(
                $pdo,
                $agentId,
                sanitize_key($action),
                $plugin !== null ? sanitize_text_field($plugin) : null,
                sanitize_key($status),
                $details,
                $errorMsg !== null ? sanitize_text_field($errorMsg) : null,
            );
        } catch (PDOException $e) {
            return ErrorResponse::logAndReturnFalse($this->fileLogger, $e, 'Failed to log agent action');
        }
    }

    private function insertActionRecord(
        PDO $pdo,
        int $agentId,
        string $action,
        ?string $plugin,
        string $status,
        ?array $details,
        ?string $errorMsg,
    ): int {
        $stmt = $pdo->prepare(self::ACTION_INSERT_QUERY);

        $stmt->execute(array(
            $agentId,
            $action,
            $plugin,
            $status,
            $details ? json_encode($details) : null,
            $errorMsg,
            DateHelper::nowUtc(),
        ));

        return (int) $pdo->lastInsertId();
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
                return array($totalKey => 0, $actionsKey => array());
            }

            $total = $this->countAgentActions($pdo, $agentId);
            $actions = $this->fetchAgentActions($pdo, $agentId, $limit, $offset);
            $this->decodeActionDetails($actions);

            return array($totalKey => $total, $actionsKey => $actions);
        } catch (PDOException $e) {
            $this->fileLogger->logException($e, 'Failed to get action history');

            return array($totalKey => 0, $actionsKey => array());
        }
    }

    private function countAgentActions(PDO $pdo, int $agentId): int {
        $stmt = $pdo->prepare(self::ACTION_COUNT_QUERY);
        $stmt->execute(array($agentId));

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)[ResponseKeyType::Total->value];
    }

    private function fetchAgentActions(
        PDO $pdo,
        int $agentId,
        int $limit,
        int $offset,
    ): array {
        $stmt = $pdo->prepare(self::ACTION_LIST_QUERY);
        $stmt->execute(array(
            $agentId,
            $limit,
            $offset,
        ));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function decodeActionDetails(array &$actions): void {
        foreach ($actions as &$action) {
            if (BooleanHelpers::hasValue($action['Details'])) {
                $action['Details'] = json_decode($action['Details'], true);
            }
        }
    }
}
