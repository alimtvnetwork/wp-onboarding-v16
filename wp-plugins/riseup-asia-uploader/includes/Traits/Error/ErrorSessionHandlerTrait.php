<?php
/**
 * ErrorSessionHandlerTrait — error session retrieval, parsing, and enrichment.
 *
 * @package RiseupAsia\Traits\Error
 */

namespace RiseupAsia\Traits\Error;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use PDO;
use Throwable;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Database\Database;
use RiseupAsia\Enums\TableType;

trait ErrorSessionHandlerTrait {

    /** Handle error-sessions endpoint. */
    public function handleErrorSessions(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Error sessions endpoint called');

            $pdo = Database::getInstance()->getPdo();
            $isPdoMissing = ($pdo === null);
            if ($isPdoMissing) {
                return $this->errorResponse('Database not available (PDO/pdo_sqlite extension may not be installed)', HttpStatusType::ServerError->value);
            }

            $isTableMissing = ($this->isTableExists($pdo, 'error_sessions') === false);
            if ($isTableMissing) {
                return EnvelopeBuilder::success('error_sessions table does not exist yet (migration v9 not applied)')
                    ->autoDetectRequestedAt()->setResults(array())->toResponse();
            }

            $query   = $this->buildErrorSessionQuery($request);
            $total   = $this->countErrorSessions($pdo, $query);
            $rows    = $this->fetchErrorSessions($pdo, $query);
            $entries = $this->enrichErrorEntries($rows);

            return EnvelopeBuilder::success()
                ->autoDetectRequestedAt()->setResults($entries)
                ->setPagination($total, $query['limit'], $query['limit'] > 0 ? (int) floor($query['offset'] / $query['limit']) + 1 : 1)
                ->toResponse();
        }, 'error_sessions');
    }

    /** Check if a table exists in SQLite. */
    private function isTableExists(PDO $pdo, string $table): bool {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");

        return (bool) $check->fetchColumn();
    }

    /** Build query parameters for error sessions listing. */
    private function buildErrorSessionQuery(WP_REST_Request $request): array {
        $level    = sanitize_text_field($request->get_param('level') ?: '');
        $search   = sanitize_text_field($request->get_param('search') ?: '');
        $since_id = (int) ($request->get_param('since_id') ?: 0);
        $limit    = max(1, min(1000, (int) ($request->get_param('limit') ?: 100)));
        $offset   = max(0, (int) ($request->get_param('offset') ?: 0));

        $where  = array();
        $params = array();
        if (BooleanHelpers::hasValue($level))  { $where[] = 'Level = ?';      $params[] = strtoupper($level); }
        if (BooleanHelpers::hasValue($search)) { $where[] = 'Message LIKE ?'; $params[] = '%' . $search . '%'; }
        if ($since_id > 0)   { $where[] = 'Id > ?';         $params[] = $since_id; }

        $hasWhereClause = BooleanHelpers::hasValue($where);
        return array(
            'where_sql' => $hasWhereClause ? 'WHERE ' . implode(' AND ', $where) : '',
            'params' => $params, 'limit' => $limit, 'offset' => $offset,
        );
    }

    /** Count total error sessions matching the query. */
    private function countErrorSessions(PDO $pdo, array $query): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TableType::ErrorSessions->value . " {$query['where_sql']}");
        $stmt->execute($query['params']);

        return (int) $stmt->fetchColumn();
    }

    /** Fetch error sessions matching the query. */
    private function fetchErrorSessions(PDO $pdo, array $query): array {
        $sql = "SELECT * FROM " . TableType::ErrorSessions->value . " {$query['where_sql']} ORDER BY Id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($query['params'], array($query['limit'], $query['offset'])));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Enrich raw error session rows with parsed context and stack trace frames. */
    private function enrichErrorEntries(array $rows): array {
        $entries = array();
        foreach ($rows as $row) {
            $entry = array(
                'id' => (int) $row['Id'], 'level' => $row['Level'], 'message' => $row['Message'],
                'file' => $row['File'], 'fileBase' => $row['File'] ? basename($row['File']) : null,
                'line' => $row['Line'] ? (int) $row['Line'] : null, 'stackTrace' => $row['StackTrace'],
                'context' => $this->parseContextJson($row['ContextJson'] ?? ''), 'created_at' => $row['CreatedAt'],
            );
            if (BooleanHelpers::hasValue($row['StackTrace'])) {
                $entry['stackTraceFrames'] = $this->parseStackTraceString($row['StackTrace']);
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /** Parse a JSON context string safely. */
    private function parseContextJson(string $json): mixed {
        if (empty($json)) { return null; }
        $decoded = json_decode($json, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $json;
    }

    /** Count errors with id > lastSeenId. */
    private function countUnseenErrors(PDO $pdo, int $lastSeenId): int {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($lastSeenId));

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Parse a PHP stack trace string into structured frames. */
    private function parseStackTraceString(string $traceString): array {
        $frames = array();
        foreach (explode("\n", $traceString) as $line) {
            $frame = $this->parseTraceFrame(trim($line));
            if ($frame !== null) {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    /** Parse a single stack trace line into a frame. */
    private function parseTraceFrame(string $line): ?array {
        $isLineUnparseable = (BooleanHelpers::isValueEmpty($line) || preg_match('/^#\d+\s+(.+?)\((\d+)\):\s*(.*)$/', $line, $m) === 0);
        if ($isLineUnparseable) {
            return null;
        }

        $func_part = $m[3];
        $class = '';
        $function = $func_part;
        if (strpos($func_part, '->') !== false) {
            list($class, $function) = explode('->', $func_part, 2);
        } elseif (strpos($func_part, '::') !== false) {
            list($class, $function) = explode('::', $func_part, 2);
        }

        return array(
            'file' => $m[1], 'fileBase' => basename($m[1]),
            'line' => (int) $m[2], 'function' => rtrim($function, '()'), 'class' => $class,
        );
    }
}
