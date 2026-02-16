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
use RiseupAsia\ErrorHandling\ErrorResponse;

trait AgentLoggingTrait {

    public function logAction(
        int $agentId,
        string $action,
        ?string $plugin = null,
        string $status = 'success',
        ?array $details = null,
        ?string $errorMsg = null,
    ): int|false {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return false;
            }

            return $this->insertActionRecord($pdo, $agentId, $action, $plugin, $status, $details, $errorMsg);
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
        $stmt = $pdo->prepare("INSERT INTO agent_actions 
            (agent_site_id, action, target_plugin, status, details, error_msg, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $agentId, $action, $plugin, $status,
            $details ? json_encode($details) : null,
            $errorMsg, gmdate('Y-m-d\TH:i:s\Z'),
        ));

        return (int) $pdo->lastInsertId();
    }

    public function getActionHistory(
        int $agentId,
        int $limit = 50,
        int $offset = 0,
    ): array {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return array('total' => 0, 'actions' => array());
            }

            $total = $this->countAgentActions($pdo, $agentId);
            $actions = $this->fetchAgentActions($pdo, $agentId, $limit, $offset);
            $this->decodeActionDetails($actions);

            return array('total' => $total, 'actions' => $actions);
        } catch (PDOException $e) {
            $this->fileLogger->logException($e, 'Failed to get action history');

            return array('total' => 0, 'actions' => array());
        }
    }

    private function countAgentActions(PDO $pdo, int $agentId): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_actions WHERE agent_site_id = ?");
        $stmt->execute(array($agentId));

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    private function fetchAgentActions(
        PDO $pdo,
        int $agentId,
        int $limit,
        int $offset,
    ): array {
        $stmt = $pdo->prepare("SELECT * FROM agent_actions 
            WHERE agent_site_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?");
        $stmt->execute(array($agentId, $limit, $offset));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function decodeActionDetails(array &$actions): void {
        foreach ($actions as &$action) {
            if (!empty($action['details'])) {
                $action['details'] = json_decode($action['details'], true);
            }
        }
    }
}
