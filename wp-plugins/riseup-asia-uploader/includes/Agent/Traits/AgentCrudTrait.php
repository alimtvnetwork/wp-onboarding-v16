<?php
/**
 * Agent CRUD Trait
 *
 * Create, read, update, delete operations for agent sites.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentCrudTrait {

    /**
     * Add a new agent site.
     *
     * @param array $data Agent data (name, url, username, app_password, redirect_url).
     * @return int|WP_Error Agent ID on success, WP_Error on failure.
     */
    public function addAgent($data) {
        $this->file_logger->info('Adding agent site', array('name' => $data['name'], 'url' => $data['url']));

        if (empty($data['name']) || empty($data['url']) || empty($data['username']) || empty($data['app_password'])) {
            return new WP_Error('missing_fields', 'Name, URL, username, and application password are required');
        }

        $url = $this->normalizeUrl($data['url']);
        $encrypted_password = $this->encrypt($data['app_password']);

        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            $stmt = $pdo->prepare("INSERT INTO agent_sites 
                (name, url, username, app_password_encrypted, redirect_url, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?)");

            $stmt->execute(array(
                sanitize_text_field($data['name']),
                esc_url_raw($url),
                sanitize_user($data['username']),
                $encrypted_password,
                isset($data['redirect_url']) ? esc_url_raw($data['redirect_url']) : null,
                gmdate('Y-m-d\TH:i:s\Z'),
            ));

            $agent_id = (int) $pdo->lastInsertId();
            $this->file_logger->info('Agent site added', array('id' => $agent_id));

            return $agent_id;

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to add agent site');
            return new WP_Error('db_error', 'Failed to save agent site: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing agent site.
     *
     * @param int   $id   Agent ID.
     * @param array $data Updated data.
     * @return bool|WP_Error True on success.
     */
    public function updateAgent($id, $data) {
        $this->file_logger->info('Updating agent site', array('id' => $id));

        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            $update = $this->buildUpdateSets($data);
            if (empty($update['sets'])) {
                return new WP_Error('no_data', 'No fields to update');
            }

            $update['sets'][] = 'updated_at = ?';
            $update['params'][] = gmdate('Y-m-d\TH:i:s\Z');
            $update['params'][] = (int) $id;

            $sql = "UPDATE agent_sites SET " . implode(', ', $update['sets']) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update['params']);

            $this->file_logger->info('Agent site updated', array('id' => $id));

            return true;

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to update agent site');

            return new WP_Error('db_error', 'Failed to update agent site: ' . $e->getMessage());
        }
    }

    /**
     * Build SET clauses and params from update data.
     *
     * @param array $data Update data.
     * @return array{sets: string[], params: array} SET clauses and params.
     */
    private function buildUpdateSets(array $data): array {
        $sets = array();
        $params = array();

        $field_map = array(
            'name'         => fn($v) => array('name = ?', sanitize_text_field($v)),
            'url'          => fn($v) => array('url = ?', esc_url_raw($this->normalizeUrl($v))),
            'username'     => fn($v) => array('username = ?', sanitize_user($v)),
            'redirect_url' => fn($v) => array('redirect_url = ?', esc_url_raw($v)),
            'status'       => fn($v) => array('status = ?', sanitize_key($v)),
            'last_sync'    => fn($v) => array('last_sync = ?', $v),
            'last_error'   => fn($v) => array('last_error = ?', $v),
        );

        foreach ($field_map as $field => $transform) {
            if (isset($data[$field])) {
                $result = $transform($data[$field]);
                $sets[] = $result[0];
                $params[] = $result[1];
            }
        }

        if (isset($data['app_password']) && !empty($data['app_password'])) {
            $sets[] = 'app_password_encrypted = ?';
            $params[] = $this->encrypt($data['app_password']);
        }

        return array('sets' => $sets, 'params' => $params);
    }

    /**
     * Remove an agent site.
     *
     * @param int $id Agent ID.
     * @return bool|WP_Error True on success.
     */
    public function removeAgent($id) {
        $this->file_logger->info('Removing agent site', array('id' => $id));

        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            $stmt = $pdo->prepare("DELETE FROM agent_sites WHERE id = ?");
            $stmt->execute(array((int) $id));

            $this->file_logger->info('Agent site removed', array('id' => $id));
            return true;

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to remove agent site');
            return new WP_Error('db_error', 'Failed to remove agent site: ' . $e->getMessage());
        }
    }

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
