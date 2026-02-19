<?php
/**
 * AgentCrudReadTrait — Read and list operations for agent sites.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Database\TypedQuery;
use RiseupAsia\Helpers\BooleanHelpers;

trait AgentCrudReadTrait {

    private const AGENT_SELECT_QUERY = 'SELECT * FROM agent_sites WHERE id = ?';

    private const AGENT_COUNT_QUERY = 'SELECT COUNT(*) as total FROM agent_sites';

    private const AGENT_LIST_QUERY = <<<'SQL'
        SELECT id, name, url, username, redirect_url, status, 
               last_sync, last_error, created_at, updated_at
        FROM agent_sites
    SQL;

    public function getAgent(int $id, bool $includePassword = false): ?array {
        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return null;
        }

        $query = new TypedQuery($pdo);
        $mapper = fn(array $row): array => $this->mapAgentRow($row, $includePassword);
        $result = $query->queryOne(self::AGENT_SELECT_QUERY, [$id], $mapper);

        if ($result->hasError()) {
            $this->fileLogger->logException($result->error(), 'Failed to get agent site');
            return null;
        }

        return $result->value();
    }

    public function listAgents(
        array $filters = [],
        int $limit = 100,
        int $offset = 0,
    ): array {
        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return ['total' => 0, 'agents' => []];
        }

        $query = new TypedQuery($pdo);
        $where = $this->buildAgentWhereClause($filters);

        $countResult = $query->queryOne(
            self::AGENT_COUNT_QUERY . " {$where['sql']}",
            $where['params'],
            fn(array $row): int => (int) $row['total'],
        );

        if ($countResult->hasError()) {
            $this->fileLogger->logException($countResult->error(), 'Failed to list agent sites');
            return ['total' => 0, 'agents' => []];
        }

        $listParams = array_merge($where['params'], [$limit, $offset]);
        $listSql = self::AGENT_LIST_QUERY . " {$where['sql']} ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $listResult = $query->queryMany(
            $listSql,
            $listParams,
            fn(array $row): array => $row,
        );

        if ($listResult->hasError()) {
            $this->fileLogger->logException($listResult->error(), 'Failed to list agent sites');
            return ['total' => 0, 'agents' => []];
        }

        return [
            'total'  => $countResult->value() ?? 0,
            'agents' => $listResult->items(),
        ];
    }

    private function mapAgentRow(array $row, bool $includePassword): array {
        if ($includePassword) {
            $row['app_password'] = $this->decrypt($row['app_password_encrypted']);
        }

        unset($row['app_password_encrypted']);

        return $row;
    }

    private function buildAgentWhereClause(array $filters): array {
        $conditions = [];
        $params = [];

        $hasStatusFilter = BooleanHelpers::hasValue($filters['status'] ?? null);
        if ($hasStatusFilter) {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $hasConditions = BooleanHelpers::hasValue($conditions);

        return [
            'sql'    => $hasConditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}
