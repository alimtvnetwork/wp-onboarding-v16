<?php
/**
 * ErrorLogTrait — error log and error session retrieval handlers.
 *
 * Extracted from riseup-asia-uploader.php (lines 3641–3976).
 *
 * @package RiseupAsiaUploader
 */

trait ErrorLogTrait {

    /**
     * Handle error-logs endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
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

            return RiseupEnvelopeBuilder::success()
                ->autoDetectRequestedAt()
                ->setSingleResult($result)
                ->toResponse();
        }, 'error_logs');
    }

    /**
     * Resolve log retrieval settings from admin defaults and query param overrides.
     *
     * @param WP_REST_Request $request Request object.
     * @return array Resolved settings.
     */
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

    /**
     * Handle error-sessions endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_error_sessions($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Error sessions endpoint called');

            $pdo = RiseupDatabase::get_instance()->get_pdo();
            if (!$pdo) {
                return $this->error_response('Database not available (PDO/pdo_sqlite extension may not be installed)', HTTP_SERVER_ERROR);
            }
            if (!$this->isTableExists($pdo, 'error_sessions')) {
                return RiseupEnvelopeBuilder::success('error_sessions table does not exist yet (migration v9 not applied)')
                    ->autoDetectRequestedAt()->setResults(array())->toResponse();
            }

            $query   = $this->buildErrorSessionQuery($request);
            $total   = $this->countErrorSessions($pdo, $query);
            $rows    = $this->fetchErrorSessions($pdo, $query);
            $entries = $this->enrichErrorEntries($rows);

            return RiseupEnvelopeBuilder::success()
                ->autoDetectRequestedAt()
                ->setResults($entries)
                ->setPagination($total, $query['limit'], $query['limit'] > 0 ? (int) floor($query['offset'] / $query['limit']) + 1 : 1)
                ->toResponse();
        }, 'error_sessions');
    }

    /**
     * Check if a table exists in the SQLite database.
     *
     * @param PDO    $pdo   Database connection.
     * @param string $table Table name.
     * @return bool True if table exists.
     */
    private function isTableExists(PDO $pdo, string $table): bool {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
        return (bool) $check->fetchColumn();
    }

    /**
     * Build query parameters for error sessions listing.
     *
     * @param WP_REST_Request $request Request object.
     * @return array Query components: where_sql, params, limit, offset.
     */
    private function buildErrorSessionQuery($request): array {
        $level    = sanitize_text_field($request->get_param('level') ?: '');
        $search   = sanitize_text_field($request->get_param('search') ?: '');
        $since_id = (int) ($request->get_param('since_id') ?: 0);
        $limit    = max(1, min(1000, (int) ($request->get_param('limit') ?: 100)));
        $offset   = max(0, (int) ($request->get_param('offset') ?: 0));

        $where  = array();
        $params = array();
        if (!empty($level))  { $where[] = 'level = ?';      $params[] = strtoupper($level); }
        if (!empty($search)) { $where[] = 'message LIKE ?'; $params[] = '%' . $search . '%'; }
        if ($since_id > 0)   { $where[] = 'id > ?';         $params[] = $since_id; }

        return array(
            'where_sql' => !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '',
            'params'    => $params,
            'limit'     => $limit,
            'offset'    => $offset,
        );
    }

    /**
     * Count total error sessions matching the query.
     *
     * @param PDO   $pdo   Database connection.
     * @param array $query Query components.
     * @return int Total count.
     */
    private function countErrorSessions(PDO $pdo, array $query): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM error_sessions {$query['where_sql']}");
        $stmt->execute($query['params']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch error sessions matching the query.
     *
     * @param PDO   $pdo   Database connection.
     * @param array $query Query components.
     * @return array Raw rows.
     */
    private function fetchErrorSessions(PDO $pdo, array $query): array {
        $sql = "SELECT * FROM error_sessions {$query['where_sql']} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($query['params'], array($query['limit'], $query['offset'])));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enrich raw error session rows with parsed context and stack trace frames.
     *
     * @param array $rows Raw database rows.
     * @return array Enriched entries.
     */
    private function enrichErrorEntries(array $rows): array {
        $entries = array();
        foreach ($rows as $row) {
            $entry = array(
                'id'         => (int) $row['id'],
                'level'      => $row['level'],
                'message'    => $row['message'],
                'file'       => $row['file'],
                'fileBase'   => $row['file'] ? basename($row['file']) : null,
                'line'       => $row['line'] ? (int) $row['line'] : null,
                'stackTrace' => $row['stack_trace'],
                'context'    => $this->parseContextJson($row['context_json'] ?? ''),
                'created_at' => $row['created_at'],
            );
            if (!empty($row['stack_trace'])) {
                $entry['stackTraceFrames'] = $this->parse_stack_trace_string($row['stack_trace']);
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Parse a JSON context string safely.
     *
     * @param string $json JSON string.
     * @return mixed Decoded value, raw string on failure, or null if empty.
     */
    private function parseContextJson(string $json) {
        if (empty($json)) {
            return null;
        }
        $decoded = json_decode($json, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $json;
    }

    /**
     * Count errors with id > last_seen_id.
     *
     * @param PDO $pdo         Database connection.
     * @param int $last_seen_id Last seen error ID.
     * @return int
     */
    private function count_unseen_errors($pdo, $last_seen_id) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($last_seen_id));
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Parse a PHP stack trace string into structured frames.
     *
     * @param string $trace_string Stack trace as a string.
     * @return array Array of frame objects.
     */
    private function parse_stack_trace_string($trace_string) {
        $frames = array();
        $lines  = explode("\n", $trace_string);

        foreach ($lines as $line) {
            $frame = $this->parseTraceFrame(trim($line));
            if ($frame !== null) {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    /**
     * Parse a single stack trace line into a frame.
     *
     * @param string $line Trimmed trace line.
     * @return array|null Frame object or null if not parseable.
     */
    private function parseTraceFrame(string $line): ?array {
        if (empty($line)) {
            return null;
        }

        if (!preg_match('/^#\d+\s+(.+?)\((\d+)\):\s*(.*)$/', $line, $m)) {
            return null;
        }

        $func_part = $m[3];
        $class    = '';
        $function = $func_part;
        if (strpos($func_part, '->') !== false) {
            list($class, $function) = explode('->', $func_part, 2);
        } elseif (strpos($func_part, '::') !== false) {
            list($class, $function) = explode('::', $func_part, 2);
        }

        return array(
            'file'     => $m[1],
            'fileBase' => basename($m[1]),
            'line'     => (int) $m[2],
            'function' => rtrim($function, '()'),
            'class'    => $class,
        );
    }

    /**
     * Read the last N lines of a log file.
     *
     * @param string $file_path Path to the log file.
     * @param int    $max_lines Maximum number of lines to return.
     * @return array Log data with content, line count, file size, and path info.
     */
    private function read_log_tail($file_path, $max_lines) {
        $result = array(
            'exists'     => false,
            'file'       => basename($file_path),
            'path'       => $file_path,
            'content'    => '',
            'lines'      => 0,
            'total_size' => 0,
            'truncated'  => false,
        );

        $isFileUnreadable = RiseupBooleanHelpers::is_file_missing($file_path) || !is_readable($file_path);
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

        if ($total_lines > $max_lines) {
            $lines = array_slice($all_lines, -$max_lines);
        } else {
            $lines = $all_lines;
        }

        $result['lines']       = count($lines);
        $result['total_lines'] = $total_lines;
        $result['content']     = implode("\n", $lines);

        return $result;
    }
}
