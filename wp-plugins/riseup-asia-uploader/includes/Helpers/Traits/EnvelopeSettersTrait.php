<?php
/**
 * EnvelopeSettersTrait — fluent setters for envelope properties.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait EnvelopeSettersTrait {

    public function setResults(array $results): static { $this->results = $results; return $this; }
    public function setSingleResult(array $item): static { $this->results = array($item); return $this; }
    public function setRequestedAt(string $path): static { $this->requested_at = $path; return $this; }

    public function autoDetectRequestedAt(): static {
        if (isset($_SERVER['REQUEST_URI'])) { $this->requested_at = $_SERVER['REQUEST_URI']; }
        return $this;
    }

    public function setDelegatedAt(string $url): static { $this->delegated_at = $url; return $this; }

    public function setPagination(
        int $totalRecords,
        int $perPage,
        int $currentPage,
    ): static {
        $this->total_records = $totalRecords;
        $this->per_page = $perPage;
        $this->current_page = $currentPage;
        $this->total_pages = ($perPage > 0) ? (int) ceil($totalRecords / $perPage) : 0;
        return $this;
    }

    public function setNavigation(
        ?string $nextPage = null,
        ?string $prevPage = null,
        array $closerLinks = array(),
    ): static {
        $this->navigation = array('NextPage' => $nextPage, 'PrevPage' => $prevPage, 'CloserLinks' => $closerLinks);
        return $this;
    }

    public function setMethodsStack(array $backendStack, array $frontendStack = array()): static {
        $this->methods_stack = array('Backend' => $backendStack, 'Frontend' => $frontendStack);
        return $this;
    }

    public function setErrors(array $errors): static {
        $this->errors = $errors;
        $this->has_errors = true;
        return $this;
    }
}
