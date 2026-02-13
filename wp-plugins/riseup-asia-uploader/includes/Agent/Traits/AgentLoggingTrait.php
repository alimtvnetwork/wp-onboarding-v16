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
     *
     * @param int         $agent_id    Agent ID.
     * @param string      $action      Action type.
     * @param string|null $plugin      Target plugin slug.
     * @param string      $status      Status (success/failed).
     * @param array|null  $details     Additional details.
     * @param string|null $error_msg   Error message if failed.
     * @return int|false Insert ID or false.
     */
    public function logAction($agent_id, $action, $plugin = null, $status = 'success', $details = null, $error_msg = null) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare("INSERT INTO agent_actions 
                (agent_site_id, action, target_plugin, status, details, error_msg, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                (int) $agent_id,
                $action,
                $plugin,
                $status,
                $details ? json_encode($details) : null,
                $error_msg,
                gmdate('Y-m-d\TH:i:s\Z'),
            ));

            return (int) $pdo->lastInsertId();

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to log agent action');
            return false;
        }
    }

    /**
     * Get action history for an agent.
     *
     * @param int $agent_id Agent ID.
     * @param int $limit    Max results.
     * @param int $offset   Offset.
     * @return array Array with 'total' and 'actions'.
     */
    public function getActionHistory($agent_id, $limit = 50, $offset = 0) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return array('total' => 0, 'actions' => array());
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_actions WHERE agent_site_id = ?");
            $stmt->execute(array((int) $agent_id));
            $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $pdo->prepare("SELECT * FROM agent_actions 
                WHERE agent_site_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->execute(array((int) $agent_id, (int) $limit, (int) $offset));
            $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->decodeActionDetails($actions);

            return array('total' => $total, 'actions' => $actions);

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to get action history');

            return array('total' => 0, 'actions' => array());
        }
    }

    /**
     * Decode JSON details in action records.
     *
     * @param array &$actions Action records reference.
     */
    private function decodeActionDetails(array &$actions) {
        foreach ($actions as &$action) {
            if (!empty($action['details'])) {
                $action['details'] = json_decode($action['details'], true);
            }
        }
    }
}
