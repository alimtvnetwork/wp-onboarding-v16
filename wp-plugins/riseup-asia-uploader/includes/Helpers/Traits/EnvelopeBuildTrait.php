<?php
/**
 * EnvelopeBuildTrait — envelope assembly and WP_REST_Response output.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;
use RiseupAsia\Helpers\DateHelper;

trait EnvelopeBuildTrait {
    public function build() {
        $envelope = array(
            'Status'     => $this->buildStatusBlock(),
            'Attributes' => $this->buildAttributesBlock(),
            'Results'    => $this->results,
        );

        $this->appendOptionalSections($envelope);

        return $envelope;
    }

    private function buildStatusBlock(): array {
        return array(
            'IsSuccess' => $this->isSuccess,
            'IsFailed'  => !$this->isSuccess,
            'Code'      => $this->code,
            'Message'   => $this->message,
            'Timestamp' => DateHelper::nowUtc(),
        );
    }

    private function buildAttributesBlock(): array {
        $resultCount = count($this->results);

        return array(
            'RequestedAt' => $this->requestedAt,
            'RequestDelegatedAt' => $this->delegatedAt,
            'HasAnyErrors' => $this->hasErrors,
            'IsSingle' => ($resultCount === 1),
            'IsMultiple' => ($resultCount > 1),
            'TotalRecords' => $this->totalRecords > 0 ? $this->totalRecords : $resultCount,
            'PerPage' => $this->perPage,
            'TotalPages' => $this->totalPages,
            'CurrentPage' => $this->currentPage,
        );
    }

    private function appendOptionalSections(array &$envelope) {
        if ($this->navigation !== null) { $envelope['Navigation'] = $this->navigation; }
        if ($this->errors !== null) { $envelope['Errors'] = $this->errors; }
        if ($this->methodsStack !== null) { $envelope['MethodsStack'] = $this->methodsStack; }
    }

    public function toResponse() {
        return new WP_REST_Response($this->build(), $this->code);
    }
}
