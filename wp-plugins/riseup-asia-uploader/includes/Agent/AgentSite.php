<?php
/**
 * AgentSite — Readonly model for AgentSites rows.
 *
 * Provides a typed, immutable representation of an agent site record.
 * Use fromRow() as the canonical mapper for TypedQuery operations.
 *
 * @package RiseupAsia\Agent
 * @since   2.0.0
 */

namespace RiseupAsia\Agent;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AgentFieldType;
use RiseupAsia\Enums\AgentStatusType;

final readonly class AgentSite {

    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public string $username,
        public ?string $redirectUrl,
        public ?string $redirectResolved,
        public ?string $redirectResolvedAt,
        public string $status,
        public ?string $lastSync,
        public ?string $lastError,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $appPassword = null,
    ) {
    }

    /**
     * Create an AgentSite from a database row.
     * Use as the mapper closure for TypedQuery::queryOne / queryMany.
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row, ?string $decryptedPassword = null): self {
        return new self(
            id:                 (int) $row['Id'],
            name:               $row['Name'],
            url:                $row['Url'],
            username:           $row['Username'],
            redirectUrl:        $row['RedirectUrl'] ?? null,
            redirectResolved:   $row['RedirectResolved'] ?? null,
            redirectResolvedAt: $row['RedirectResolvedAt'] ?? null,
            status:             $row['Status'] ?? AgentStatusType::Pending->value,
            lastSync:           $row['LastSync'] ?? null,
            lastError:          $row['LastError'] ?? null,
            createdAt:          $row['CreatedAt'],
            updatedAt:          $row['UpdatedAt'] ?? null,
            appPassword:        $decryptedPassword,
        );
    }

    /**
     * Convert to associative array for backward-compatible API responses.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array {
        $data = [
            'id'                              => $this->id,
            AgentFieldType::Name->value        => $this->name,
            AgentFieldType::Url->value         => $this->url,
            AgentFieldType::Username->value    => $this->username,
            AgentFieldType::RedirectUrl->value => $this->redirectUrl,
            AgentFieldType::Status->value      => $this->status,
            AgentFieldType::LastSync->value     => $this->lastSync,
            AgentFieldType::LastError->value    => $this->lastError,
            'created_at'                       => $this->createdAt,
            'updated_at'                       => $this->updatedAt,
        ];

        if ($this->appPassword !== null) {
            $data[AgentFieldType::AppPassword->value] = $this->appPassword;
        }

        return $data;
    }

    /**
     * Check if the redirect cache is missing or unresolved.
     */
    public function isInvalidRedirect(): bool {
        return empty($this->redirectResolved) || empty($this->redirectResolvedAt);
    }
}
