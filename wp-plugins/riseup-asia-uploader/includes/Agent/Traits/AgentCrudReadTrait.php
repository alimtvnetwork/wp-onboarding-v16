<?php
/**
 * AgentCrudReadTrait — Read and list operations for agent sites.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentCrudReadTrait {

    /**
     * Get an agent site by ID.
     *
     * @param int  $id              Agent ID.
     * @param bool $include_password Whether to include decrypted password.
     * @return array|null Agent data or null.
     */
    public function getAgent($id, $include_password = false) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return null;
            }

            $stmt = $pdo->prepare("SELECT * FROM agent_sites WHERE id = ?");
            $stmt->execute(array((int) $id));
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($agent && $include_password) {
                $agent['app_password'] = $this->decrypt($agent['app_password_encrypted']);
            }

            if ($agent) {
                unset($agent['app_password_encrypted']);
            }

            return $agent;

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to get agent site');
            return null;
        }
    }

    /**
     * List all agent sites.
     *
     * @param array $filters Optional filters (status).
     * @param int   $limit   Max results.
     * @param int   $offset  Offset for pagination.
     * @return array Array with 'total' and 'agents'.
     */
    public function listAgents($filters = array(), $limit = 100, $offset = 0) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return array('total' => 0, 'agents' => array());
            }

            $query = $this->buildAgentListQuery($filters);
            $total = $this->countAgents($pdo, $query);
            $agents = $this->fetchAgents($pdo, $query, $limit, $offset);

            return array('total' => $total, 'agents' => $agents);

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to list agent sites');
            return array('total' => 0, 'agents' => array());
        }
    }

    /**
     * Build WHERE clause and params for agent listing.
     *
     * @param array $filters Filter options.
     * @return array{where_sql: string, params: array} Query components.
     */
    private function buildAgentListQuery(array $filters): array {
        $where = array();
        $params = array();

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        return array(
            'where_sql' => !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '',
            'params'    => $params,
        );
    }

    /**
     * Count agents matching the query.
     */
    private function countAgents(PDO $pdo, array $query): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_sites {$query['where_sql']}");
        $stmt->execute($query['params']);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Fetch agent records matching the query.
     */
    private function fetchAgents(PDO $pdo, array $query, int $limit, int $offset): array {
        $params = array_merge($query['params'], array((int) $limit, (int) $offset));
        $sql = "SELECT id, name, url, username, redirect_url, status, last_sync, last_error, created_at, updated_at 
                FROM agent_sites {$query['where_sql']} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
