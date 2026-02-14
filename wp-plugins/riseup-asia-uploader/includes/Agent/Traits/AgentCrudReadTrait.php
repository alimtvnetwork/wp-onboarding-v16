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

    public function getAgent(int $id, bool $includePassword = false): ?array {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return null;
            }

            $stmt = $pdo->prepare("SELECT * FROM agent_sites WHERE id = ?");
            $stmt->execute(array($id));
            $agent = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($agent && $includePassword) {
                $agent['app_password'] = $this->decrypt($agent['app_password_encrypted']);
            }

            if ($agent) {
                unset($agent['app_password_encrypted']);
            }

            return $agent;
        } catch (\PDOException $e) {
            $this->fileLogger->logException($e, 'Failed to get agent site');
            return null;
        }
    }

    public function listAgents(array $filters = array(), int $limit = 100, int $offset = 0): array {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return array('total' => 0, 'agents' => array());
            }

            $query = $this->buildAgentListQuery($filters);
            $total = $this->countAgents($pdo, $query);
            $agents = $this->fetchAgents($pdo, $query, $limit, $offset);

            return array('total' => $total, 'agents' => $agents);
        } catch (\PDOException $e) {
            $this->fileLogger->logException($e, 'Failed to list agent sites');
            return array('total' => 0, 'agents' => array());
        }
    }

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

    private function countAgents(\PDO $pdo, array $query): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_sites {$query['where_sql']}");
        $stmt->execute($query['params']);

        return (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }

    private function fetchAgents(\PDO $pdo, array $query, int $limit, int $offset): array {
        $params = array_merge($query['params'], array($limit, $offset));
        $sql = "SELECT id, name, url, username, redirect_url, status, last_sync, last_error, created_at, updated_at 
                FROM agent_sites {$query['where_sql']} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
