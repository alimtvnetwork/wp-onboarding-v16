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
use RiseupAsia\Agent\AgentSite;
use RiseupAsia\Database\TypedQuery;
use RiseupAsia\Enums\FilterKeyType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AgentCrudReadTrait {

    private const AGENT_SELECT_QUERY = 'SELECT * FROM AgentSites WHERE Id = ?';

    private const AGENT_COUNT_QUERY = 'SELECT COUNT(*) as total FROM AgentSites';

    private const AGENT_LIST_QUERY = <<<'SQL'
        SELECT Id, Name, Url, Username, RedirectUrl, RedirectResolved,
               RedirectResolvedAt, Status, LastSync, LastError,
               CreatedAt, UpdatedAt
        FROM AgentSites
    SQL;

    public function getAgent(int $id, bool $includePassword = false): ?array {
        return $this->getAgentModel($id, $includePassword)?->toArray();
    }

    public function getAgentModel(int $id, bool $includePassword = false): ?AgentSite {
        $pdo = $this->db->getPdo();
        if ($pdo === null) {
            return null;
        }

        $query = new TypedQuery($pdo);
        $result = $query->queryOne(
            self::AGENT_SELECT_QUERY,
            [$id],
            fn(array $row): AgentSite => $this->mapAgentRow($row, $includePassword),
        );

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
            return [ResponseKeyType::Total->value => 0, ResponseKeyType::Agents->value => []];
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

            return [ResponseKeyType::Total->value => 0, ResponseKeyType::Agents->value => []];
        }

        $listParams = array_merge($where['params'], [$limit, $offset]);
        $listSql = self::AGENT_LIST_QUERY . " {$where['sql']} ORDER BY CreatedAt DESC LIMIT ? OFFSET ?";

        $listResult = $query->queryMany(
            $listSql,
            $listParams,
            AgentSite::fromRow(...),
        );

        if ($listResult->hasError()) {
            $this->fileLogger->logException($listResult->error(), 'Failed to list agent sites');

            return [ResponseKeyType::Total->value => 0, ResponseKeyType::Agents->value => []];
        }

        $agents = array_map(
            fn(AgentSite $site): array => $site->toArray(),
            $listResult->items(),
        );

        return [
            ResponseKeyType::Total->value  => $countResult->value() ?? 0,
            ResponseKeyType::Agents->value => $agents,
        ];
    }

    private function mapAgentRow(array $row, bool $includePassword): AgentSite {
        $password = $includePassword
            ? $this->decrypt($row['AppPasswordEncrypted'])
            : null;

        return AgentSite::fromRow($row, $password);
    }

    private function buildAgentWhereClause(array $filters): array {
        $conditions = [];
        $params = [];

        $hasStatusFilter = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Status->value);

        if ($hasStatusFilter) {
            $conditions[] = 'Status = ?';
            $params[] = $filters[FilterKeyType::Status->value];
        }

        $hasConditions = BooleanHelpers::hasValue($conditions);

        return [
            ResponseKeyType::Sql->value    => $hasConditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
            ResponseKeyType::Params->value => $params,
        ];
    }
}
