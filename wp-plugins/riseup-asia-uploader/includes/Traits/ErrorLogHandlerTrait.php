<?php
/**
 * ErrorLogHandlerTrait — error log retrieval and log tail reading.
 *
 * @package RiseupAsiaUploader
 */

trait ErrorLogHandlerTrait {

    /** Handle error-logs endpoint. */
    public function handle_error_logs($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Error logs endpoint called');
            $settings = $this->resolveLogSettings($request);
            $result = array('version' => PLUGIN_VERSION, 'settings' => $settings);

            if ($settings['include_error_log']) {
                $result['error_log'] = $this->read_log_tail($this->file_logger->get_error_file(), $settings['max_lines']);
            }
            if ($settings['include_full_log']) {
                $result['full_log'] = $this->read_log_tail($this->file_logger->get_log_file(), $settings['max_lines']);
            }
            if ($settings['include_stacktrace']) {
                $result['stacktrace_log'] = $this->read_log_tail($this->file_logger->get_stacktrace_file(), $settings['max_lines']);
            }

            return RiseupEnvelopeBuilder::success()->autoDetectRequestedAt()->setSingleResult($result)->toResponse();
        }, 'error_logs');
    }

    /** Resolve log retrieval settings from admin defaults and query param overrides. */
    private function resolveLogSettings($request): array {
        $settings     = RiseupAdmin::get_settings();
        $log_settings = isset($settings['log_retrieval']) ? $settings['log_retrieval'] : array();

        $resolved = array(
            'include_error_log'  => isset($log_settings['include_error_log']) ? (bool) $log_settings['include_error_log'] : true,
            'include_full_log'   => isset($log_settings['include_full_log']) ? (bool) $log_settings['include_full_log'] : false,
            'include_stacktrace' => isset($log_settings['include_stacktrace']) ? (bool) $log_settings['include_stacktrace'] : true,
            'max_lines'          => isset($log_settings['max_lines']) ? (int) $log_settings['max_lines'] : 500,
        );

        foreach (array('include_error_log', 'include_full_log', 'include_stacktrace') as $key) {
            if ($request->get_param($key) !== null) {
                $resolved[$key] = (bool) $request->get_param($key);
            }
        }
        if ($request->get_param('max_lines') !== null) {
            $resolved['max_lines'] = max(10, min(5000, (int) $request->get_param('max_lines')));
        }

        return $resolved;
    }

    /** Read the last N lines of a log file. */
    private function read_log_tail($file_path, $max_lines) {
        $result = array(
            'exists' => false, 'file' => basename($file_path), 'path' => $file_path,
            'content' => '', 'lines' => 0, 'total_size' => 0, 'truncated' => false,
        );

        $isFileUnreadable = RiseupBooleanHelpers::is_file_unreadable($file_path);
        if ($isFileUnreadable) {
            return $result;
        }

        $result['exists']     = true;
        $result['total_size'] = filesize($file_path);

        $all_lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($all_lines === false) {
            $result['content'] = 'Failed to read file';
            return $result;
        }

        $total_lines = count($all_lines);
        $result['truncated'] = ($total_lines > $max_lines);
        $lines = ($total_lines > $max_lines) ? array_slice($all_lines, -$max_lines) : $all_lines;

        $result['lines']       = count($lines);
        $result['total_lines'] = $total_lines;
        $result['content']     = implode("\n", $lines);

        return $result;
    }
}
