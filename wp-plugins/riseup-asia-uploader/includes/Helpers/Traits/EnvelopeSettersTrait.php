<?php
/**
 * EnvelopeSettersTrait — fluent setters for envelope properties.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait EnvelopeSettersTrait {

    /** @return static */
    public function setResults(array $results) {
        $this->results = $results;
        return $this;
    }

    /** @return static */
    public function setSingleResult(array $item) {
        $this->results = array($item);
        return $this;
    }

    /** @return static */
    public function setRequestedAt($path) {
        $this->requested_at = $path;
        return $this;
    }

    /** @return static */
    public function autoDetectRequestedAt() {
        if (isset($_SERVER['REQUEST_URI'])) {
            $this->requested_at = $_SERVER['REQUEST_URI'];
        }
        return $this;
    }

    /** @return static */
    public function setDelegatedAt($url) {
        $this->delegated_at = $url;
        return $this;
    }

    /** @return static */
    public function setPagination($total_records, $per_page, $current_page) {
        $this->total_records = $total_records;
        $this->per_page = $per_page;
        $this->current_page = $current_page;
        $this->total_pages = ($per_page > 0) ? (int) ceil($total_records / $per_page) : 0;
        return $this;
    }

    /** @return static */
    public function setNavigation($next_page = null, $prev_page = null, $closer_links = array()) {
        $this->navigation = array(
            'NextPage'     => $next_page,
            'PrevPage'     => $prev_page,
            'CloserLinks'  => $closer_links,
        );
        return $this;
    }

    /** @return static */
    public function setMethodsStack(array $backend_stack, array $frontend_stack = array()) {
        $this->methods_stack = array(
            'Backend'  => $backend_stack,
            'Frontend' => $frontend_stack,
        );
        return $this;
    }

    /** @return static */
    public function setErrors(array $errors) {
        $this->errors = $errors;
        $this->has_errors = true;
        return $this;
    }
}
