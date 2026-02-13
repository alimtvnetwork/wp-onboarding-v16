<?php
/**
 * EnvelopeBuildTrait — envelope assembly and WP_REST_Response output.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait EnvelopeBuildTrait {

    /**
     * Build the envelope array.
     *
     * @return array
     */
    public function build() {
        $result_count = count($this->results);

        $envelope = array(
            'Status' => array(
                'IsSuccess' => $this->is_success,
                'IsFailed'  => !$this->is_success,
                'Code'      => $this->code,
                'Message'   => $this->message,
                'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ),
            'Attributes' => array(
                'RequestedAt'        => $this->requested_at,
                'RequestDelegatedAt' => $this->delegated_at,
                'HasAnyErrors'       => $this->has_errors,
                'IsSingle'           => ($result_count === 1),
                'IsMultiple'         => ($result_count > 1),
                'TotalRecords'       => $this->total_records > 0 ? $this->total_records : $result_count,
                'PerPage'            => $this->per_page,
                'TotalPages'         => $this->total_pages,
                'CurrentPage'        => $this->current_page,
            ),
            'Results' => $this->results,
        );

        if ($this->navigation !== null) {
            $envelope['Navigation'] = $this->navigation;
        }

        if ($this->errors !== null) {
            $envelope['Errors'] = $this->errors;
        }

        if ($this->methods_stack !== null) {
            $envelope['MethodsStack'] = $this->methods_stack;
        }

        return $envelope;
    }

    /**
     * Build and return as a WP_REST_Response.
     *
     * @return WP_REST_Response
     */
    public function toResponse() {
        return new WP_REST_Response($this->build(), $this->code);
    }
}
