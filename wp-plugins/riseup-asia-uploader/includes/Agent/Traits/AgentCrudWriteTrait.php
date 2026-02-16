<?php
/**
 * AgentCrudWriteTrait — Add, update, remove operations for agent sites.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOException;
use WP_Error;
use RiseupAsia\ErrorHandling\ErrorResponse;

trait AgentCrudWriteTrait {

    public function addAgent(array $data): int|WP_Error {
        $this->fileLogger->info('Adding agent site', array('name' => $data['name'], 'url' => $data['url']));

        $validationError = $this->validateAgentData($data);
        if ($validationError) {
            return $validationError;
        }

        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            return $this->insertAgentRecord($pdo, $data);
        } catch (PDOException $e) {
            return ErrorResponse::logAndReturnWpError($this->fileLogger, $e, 'Failed to add agent site', 'db_error');
        }
    }

    private function validateAgentData(array $data): ?WP_Error {
        $hasAllFields = !empty($data['name']) && !empty($data['url']) && !empty($data['username']) && !empty($data['app_password']);
        if ($hasAllFields) {
            return null;
        }

        return new WP_Error('missing_fields', 'Name, URL, username, and application password are required');
    }

    private function insertAgentRecord(PDO $pdo, array $data): int {
        $stmt = $pdo->prepare(
            "INSERT INTO agent_sites (name, url, username, app_password_encrypted, redirect_url, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)"
        );

        $stmt->execute(array(
            sanitize_text_field($data['name']),
            esc_url_raw($this->normalizeUrl($data['url'])),
            sanitize_user($data['username']),
            $this->encrypt($data['app_password']),
            isset($data['redirect_url']) ? esc_url_raw($data['redirect_url']) : null,
            gmdate('Y-m-d\TH:i:s\Z'),
        ));

        $agentId = (int) $pdo->lastInsertId();
        $this->fileLogger->info('Agent site added', array('id' => $agentId));

        return $agentId;
    }

    public function updateAgent(int $id, array $data): bool|WP_Error {
        $this->fileLogger->info('Updating agent site', array('id' => $id));

        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            return $this->executeAgentUpdate($pdo, $id, $data);
        } catch (PDOException $e) {
            return ErrorResponse::logAndReturnWpError($this->fileLogger, $e, 'Failed to update agent site', 'db_error');
        }
    }

    private function executeAgentUpdate(
        PDO $pdo,
        int $id,
        array $data,
    ): bool|WP_Error {
        $update = $this->buildUpdateSets($data);
        if (empty($update['sets'])) {
            return new WP_Error('no_data', 'No fields to update');
        }

        $update['sets'][] = 'updated_at = ?';
        $update['params'][] = gmdate('Y-m-d\TH:i:s\Z');
        $update['params'][] = $id;

        $sql = "UPDATE agent_sites SET " . implode(', ', $update['sets']) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($update['params']);

        $this->fileLogger->info('Agent site updated', array('id' => $id));

        return true;
    }

    private function buildUpdateSets(array $data): array {
        $sets = array();
        $params = array();

        $this->applyFieldMap($data, $sets, $params);
        $this->applyPasswordField($data, $sets, $params);

        return array('sets' => $sets, 'params' => $params);
    }

    private function applyFieldMap(
        array $data,
        array &$sets,
        array &$params,
    ): void {
        $fieldMap = array(
            'name'         => fn($v) => array('name = ?', sanitize_text_field($v)),
            'url'          => fn($v) => array('url = ?', esc_url_raw($this->normalizeUrl($v))),
            'username'     => fn($v) => array('username = ?', sanitize_user($v)),
            'redirect_url' => fn($v) => array('redirect_url = ?', esc_url_raw($v)),
            'status'       => fn($v) => array('status = ?', sanitize_key($v)),
            'last_sync'    => fn($v) => array('last_sync = ?', $v),
            'last_error'   => fn($v) => array('last_error = ?', $v),
        );

        foreach ($fieldMap as $field => $transform) {
            if (!isset($data[$field])) {
                continue;
            }

            $result = $transform($data[$field]);
            $sets[] = $result[0];
            $params[] = $result[1];
        }
    }

    private function applyPasswordField(
        array $data,
        array &$sets,
        array &$params,
    ): void {
        $hasPassword = isset($data['app_password']) && !empty($data['app_password']);
        if (!$hasPassword) {
            return;
        }

        $sets[] = 'app_password_encrypted = ?';
        $params[] = $this->encrypt($data['app_password']);
    }

    public function removeAgent(int $id): bool|WP_Error {
        $this->fileLogger->info('Removing agent site', array('id' => $id));

        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }

            $stmt = $pdo->prepare("DELETE FROM agent_sites WHERE id = ?");
            $stmt->execute(array($id));

            $this->fileLogger->info('Agent site removed', array('id' => $id));

            return true;
        } catch (PDOException $e) {
            return ErrorResponse::logAndReturnWpError($this->fileLogger, $e, 'Failed to remove agent site', 'db_error');
        }
    }
}
