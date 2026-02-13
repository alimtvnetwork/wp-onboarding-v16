<?php
/**
 * Agent Logging Trait
 *
 * Action logging and history retrieval for agent operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentLoggingTrait {

    /**
     * Log an agent action.
     */
    public function logAction($agent_id, $action, $plugin = null, $status = 'success', $details = null, $error_msg = null) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return false;
            }

            return $this->insertActionRecord($pdo, $agent_id, $action, $plugin, $status, $details, $error_msg);
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to log agent action');
            return false;
        }
    }

    /** Insert an action record into the database. */
    private function insertActionRecord(PDO $pdo, int $agent_id, string $action, ?string $plugin, string $status, ?array $details, ?string $error_msg): int {
        $stmt = $pdo->prepare("INSERT INTO agent_actions 
            (agent_site_id, action, target_plugin, status, details, error_msg, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $agent_id, $action, $plugin, $status,
            $details ? json_encode($details) : null,
            $error_msg, gmdate('Y-m-d\TH:i:s\Z'),
        ));

        return (int) $pdo->lastInsertId();
    }

    /**
     * Get action history for an agent.
     */
    public function getActionHistory($agent_id, $limit = 50, $offset = 0) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return array('total' => 0, 'actions' => array());
            }

            $total = $this->countAgentActions($pdo, $agent_id);
            $actions = $this->fetchAgentActions($pdo, $agent_id, $limit, $offset);
            $this->decodeActionDetails($actions);

            return array('total' => $total, 'actions' => $actions);
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to get action history');
            return array('total' => 0, 'actions' => array());
        }
    }

    /** Count total actions for an agent. */
    private function countAgentActions(PDO $pdo, int $agent_id): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_actions WHERE agent_site_id = ?");
        $stmt->execute(array($agent_id));

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /** Fetch action records for an agent. */
    private function fetchAgentActions(PDO $pdo, int $agent_id, int $limit, int $offset): array {
        $stmt = $pdo->prepare("SELECT * FROM agent_actions 
            WHERE agent_site_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?");
        $stmt->execute(array($agent_id, $limit, $offset));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Decode JSON details in action records.
     */
    private function decodeActionDetails(array &$actions) {
        foreach ($actions as &$action) {
            if (!empty($action['details'])) {
                $action['details'] = json_decode($action['details'], true);
            }
        }
    }
}
