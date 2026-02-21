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
use RiseupAsia\Enums\AgentFieldType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait AgentCrudWriteTrait {
    // Literal 'Pending' required in SQL heredoc; matches agent status domain value
    private const AGENT_INSERT_QUERY = <<<'SQL'
        INSERT INTO AgentSites (Name, Url, Username, AppPasswordEncrypted, RedirectUrl, Status, CreatedAt)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?)
    SQL;

    private const AGENT_DELETE_QUERY = 'DELETE FROM AgentSites WHERE Id = ?';

    public function addAgent(array $data): int|WP_Error {
        $nameKey = AgentFieldType::Name->value;
        $urlKey = AgentFieldType::Url->value;

        $this->fileLogger->info('Adding agent site', [$nameKey => $data[$nameKey], $urlKey => $data[$urlKey]]);

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
            sanitize_text_field($data[$nameKey]),
            esc_url_raw($this->normalizeUrl($data[$urlKey])),
            sanitize_user($data[AgentFieldType::Username->value]),
            $this->encrypt($data[AgentFieldType::AppPassword->value]),
            isset($data[AgentFieldType::RedirectUrl->value]) ? esc_url_raw($data[AgentFieldType::RedirectUrl->value]) : null,
            DateHelper::nowUtc(),
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
        $setsKey = ResponseKeyType::Sets->value;
        $paramsKey = ResponseKeyType::Params->value;

        if (empty($update[$setsKey])) {
            return new WP_Error(WpErrorCodeType::NoData->value, 'No fields to update');
        }

        $update[$setsKey][] = 'UpdatedAt = ?';
        $update[$paramsKey][] = DateHelper::nowUtc();
        $update[$paramsKey][] = $id;

        $sql = 'UPDATE AgentSites SET ' . implode(', ', $update[$setsKey]) . ' WHERE Id = ?';

        $query = new TypedQuery($pdo);
        $result = $query->exec($sql, $update[$paramsKey]);

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
        $hasAllFields = BooleanHelpers::hasFilterValue($data, AgentFieldType::Name->value)
            && BooleanHelpers::hasFilterValue($data, AgentFieldType::Url->value)
            && BooleanHelpers::hasFilterValue($data, AgentFieldType::Username->value)
            && BooleanHelpers::hasFilterValue($data, AgentFieldType::AppPassword->value);

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

        return [ResponseKeyType::Sets->value => $sets, ResponseKeyType::Params->value => $params];
    }

    private function applyFieldMap(
        array $data,
        array &$sets,
        array &$params,
    ): void {
        $fieldMap = [
            AgentFieldType::Name->value        => fn($v) => ['Name = ?', sanitize_text_field($v)],
            AgentFieldType::Url->value         => fn($v) => ['Url = ?', esc_url_raw($this->normalizeUrl($v))],
            AgentFieldType::Username->value    => fn($v) => ['Username = ?', sanitize_user($v)],
            AgentFieldType::RedirectUrl->value => fn($v) => ['RedirectUrl = ?', esc_url_raw($v)],
            AgentFieldType::Status->value      => fn($v) => ['Status = ?', sanitize_key($v)],
            AgentFieldType::LastSync->value    => fn($v) => ['LastSync = ?', $v],
            AgentFieldType::LastError->value   => fn($v) => ['LastError = ?', $v],
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
        $passwordKey = AgentFieldType::AppPassword->value;
        $hasPassword = BooleanHelpers::hasFilterValue($data, $passwordKey);

        if (!$hasPassword) {
            return;
        }

        $sets[] = 'AppPasswordEncrypted = ?';
        $params[] = $this->encrypt($data[$passwordKey]);
    }
}
