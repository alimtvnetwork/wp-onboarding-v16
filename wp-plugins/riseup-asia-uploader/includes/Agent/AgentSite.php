<?php
/**
 * AgentSite — Readonly model for agent_sites rows.
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
            id:                 (int) $row['id'],
            name:               $row['name'],
            url:                $row['url'],
            username:           $row['username'],
            redirectUrl:        $row['redirect_url'] ?? null,
            redirectResolved:   $row['redirect_resolved'] ?? null,
            redirectResolvedAt: $row['redirect_resolved_at'] ?? null,
            status:             $row['status'] ?? 'pending',
            lastSync:           $row['last_sync'] ?? null,
            lastError:          $row['last_error'] ?? null,
            createdAt:          $row['created_at'],
            updatedAt:          $row['updated_at'] ?? null,
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
            'id'           => $this->id,
            'name'         => $this->name,
            'url'          => $this->url,
            'username'     => $this->username,
            'redirect_url' => $this->redirectUrl,
            'status'       => $this->status,
            'last_sync'    => $this->lastSync,
            'last_error'   => $this->lastError,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
        ];

        if ($this->appPassword !== null) {
            $data['app_password'] = $this->appPassword;
        }

        return $data;
    }
}
