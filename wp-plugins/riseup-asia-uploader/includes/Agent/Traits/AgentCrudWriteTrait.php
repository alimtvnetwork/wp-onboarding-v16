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

use WP_Error;
use RiseupAsia\Database\TypedQuery;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AgentCrudWriteTrait {

    private const AGENT_INSERT_QUERY = <<<'SQL'
        INSERT INTO agent_sites (name, url, username, app_password_encrypted, redirect_url, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', ?)
    SQL;

    private const AGENT_DELETE_QUERY = 'DELETE FROM agent_sites WHERE id = ?';

    public function addAgent(array $data): int|WP_Error {
        $this->fileLogger->info('Adding agent site', ['name' => $data['name'], 'url' => $data['url']]);

        $validationError = $this->validateAgentData($data);
        if ($validationError) {
            return $validationError;
        }

        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Database not available');
        }

        $query = new TypedQuery($pdo);
        $result = $query->exec(self::AGENT_INSERT_QUERY, [
            sanitize_text_field($data['name']),
            esc_url_raw($this->normalizeUrl($data['url'])),
            sanitize_user($data['username']),
            $this->encrypt($data['app_password']),
            isset($data['redirect_url']) ? esc_url_raw($data['redirect_url']) : null,
            gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        if ($result->hasError()) {
            $this->fileLogger->logException($result->error(), 'Failed to add agent site');
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Failed to add agent site');
        }

        $agentId = $result->lastInsertId();
        $this->fileLogger->info('Agent site added', ['id' => $agentId]);

        return $agentId;
    }

    public function updateAgent(int $id, array $data): bool|WP_Error {
        $this->fileLogger->info('Updating agent site', ['id' => $id]);

        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Database not available');
        }

        $update = $this->buildUpdateSets($data);
        if (empty($update['sets'])) {
            return new WP_Error(WpErrorCodeType::NoData->value, 'No fields to update');
        }

        $update['sets'][] = 'updated_at = ?';
        $update['params'][] = gmdate('Y-m-d\TH:i:s\Z');
        $update['params'][] = $id;

        $sql = 'UPDATE agent_sites SET ' . implode(', ', $update['sets']) . ' WHERE id = ?';

        $query = new TypedQuery($pdo);
        $result = $query->exec($sql, $update['params']);

        if ($result->hasError()) {
            $this->fileLogger->logException($result->error(), 'Failed to update agent site');
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Failed to update agent site');
        }

        $this->fileLogger->info('Agent site updated', ['id' => $id]);

        return true;
    }

    public function removeAgent(int $id): bool|WP_Error {
        $this->fileLogger->info('Removing agent site', ['id' => $id]);

        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Database not available');
        }

        $query = new TypedQuery($pdo);
        $result = $query->exec(self::AGENT_DELETE_QUERY, [$id]);

        if ($result->hasError()) {
            $this->fileLogger->logException($result->error(), 'Failed to remove agent site');
            return new WP_Error(WpErrorCodeType::DatabaseError->value, 'Failed to remove agent site');
        }

        $this->fileLogger->info('Agent site removed', ['id' => $id]);

        return true;
    }

    private function validateAgentData(array $data): ?WP_Error {
        $hasAllFields = BooleanHelpers::hasValue($data['name'] ?? null)
            && BooleanHelpers::hasValue($data['url'] ?? null)
            && BooleanHelpers::hasValue($data['username'] ?? null)
            && BooleanHelpers::hasValue($data['app_password'] ?? null);

        if ($hasAllFields) {
            return null;
        }

        return new WP_Error(WpErrorCodeType::MissingFields->value, 'Name, URL, username, and application password are required');
    }

    private function buildUpdateSets(array $data): array {
        $sets = [];
        $params = [];

        $this->applyFieldMap($data, $sets, $params);
        $this->applyPasswordField($data, $sets, $params);

        return ['sets' => $sets, 'params' => $params];
    }

    private function applyFieldMap(
        array $data,
        array &$sets,
        array &$params,
    ): void {
        $fieldMap = [
            'name'         => fn($v) => ['name = ?', sanitize_text_field($v)],
            'url'          => fn($v) => ['url = ?', esc_url_raw($this->normalizeUrl($v))],
            'username'     => fn($v) => ['username = ?', sanitize_user($v)],
            'redirect_url' => fn($v) => ['redirect_url = ?', esc_url_raw($v)],
            'status'       => fn($v) => ['status = ?', sanitize_key($v)],
            'last_sync'    => fn($v) => ['last_sync = ?', $v],
            'last_error'   => fn($v) => ['last_error = ?', $v],
        ];

        foreach ($fieldMap as $field => $transform) {
            if (!array_key_exists($field, $data)) {
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
        $hasPassword = isset($data['app_password']) && BooleanHelpers::hasValue($data['app_password']);
        if (!$hasPassword) {
            return;
        }

        $sets[] = 'app_password_encrypted = ?';
        $params[] = $this->encrypt($data['app_password']);
    }
}
