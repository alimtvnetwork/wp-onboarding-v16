<?php
/**
 * LogClearingTrait — Two-step remote log clearing with machine validation.
 *
 * Step 1 (DELETE /logs/clear): Validates machine, generates 60s token.
 * Step 2 (POST /logs/clear/confirm): Consumes token, executes deletion.
 *
 * Includes DB clearing (Transactions + ErrorSessions) and post-deletion audit entry.
 *
 * @package RiseupAsia\Traits\Log
 * @since   1.60.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Database\Database;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Logging\FileLogger;

trait LogClearingTrait
{
    private const CLEAR_TOKEN_TTL_SECONDS = 60;
    private const CLEAR_TOKEN_LENGTH = 32;
    private const MAX_CLEARS_PER_HOUR = 3;

    // ── Step 1: Request Token ─────────────────────────────────────────

    /** Handle DELETE /logs/clear — validate machine, issue confirmation token. */
    public function handleLogsClearRequest(WP_REST_Request $request): WP_REST_Response {
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $isMachineMissing = empty($machineName);

        if ($isMachineMissing) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'X-Riseup-Source-Machine header is required',
                    'code'                          => 'machine_header_missing',
                ),
                HttpStatusType::BadRequest->value,
            );
        }

        $isMachineApproved = $this->isMachineApproved($machineName);

        if ($isMachineApproved === false) {
            $this->fileLogger->warn('Log clear rejected: machine not approved', array('machine' => $machineName));

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Machine not in approved list',
                    'code'                          => 'machine_not_approved',
                ),
                HttpStatusType::Forbidden->value,
            );
        }

        $isRateLimited = $this->isLogClearRateLimited($machineName);

        if ($isRateLimited) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Rate limit exceeded (max ' . self::MAX_CLEARS_PER_HOUR . '/hour)',
                    'code'                          => 'rate_limited',
                ),
                HttpStatusType::TooManyRequests->value,
            );
        }

        $token = bin2hex(random_bytes(self::CLEAR_TOKEN_LENGTH / 2));
        $transientKey = $this->buildClearTokenKey($machineName);
        $clientIp = $this->resolveClientIp();

        $tokenData = array(
            'token'        => $token,
            'machine'      => $machineName,
            'requested_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'requested_by' => $clientIp,
        );

        set_transient($transientKey, $tokenData, self::CLEAR_TOKEN_TTL_SECONDS);

        $this->fileLogger->info('Log clear token issued', array('machine' => $machineName, 'ip' => $clientIp));

        $namespace = PluginConfigType::apiFullNamespace();

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'confirmation_required'         => true,
                'confirm_endpoint'              => '/wp-json/' . $namespace . '/logs/clear/confirm',
                'token'                         => $token,
                'expires_in'                    => self::CLEAR_TOKEN_TTL_SECONDS,
                'message'                       => 'Confirmation required. Send POST to confirm_endpoint within 60 seconds.',
            ),
            HttpStatusType::Ok->value,
        );
    }

    // ── Step 2: Confirm and Execute ───────────────────────────────────

    /** Handle POST /logs/clear/confirm — validate token, execute log and DB clearing. */
    public function handleLogsClearConfirm(WP_REST_Request $request): WP_REST_Response {
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $isMachineMissing = empty($machineName);

        if ($isMachineMissing) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'X-Riseup-Source-Machine header is required',
                    'code'                          => 'machine_header_missing',
                ),
                HttpStatusType::BadRequest->value,
            );
        }

        $body = $request->get_json_params();
        $token = $body['token'] ?? '';
        $isTokenMissing = empty($token);

        if ($isTokenMissing) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Token is required in request body',
                    'code'                          => 'token_missing',
                ),
                HttpStatusType::BadRequest->value,
            );
        }

        $transientKey = $this->buildClearTokenKey($machineName);
        $storedData = get_transient($transientKey);
        $isTokenExpired = ($storedData === false);

        if ($isTokenExpired) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Token expired or not found',
                    'code'                          => 'token_expired',
                ),
                HttpStatusType::Gone->value,
            );
        }

        $isTokenMismatch = ($storedData['token'] !== $token);

        if ($isTokenMismatch) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Invalid token',
                    'code'                          => 'token_invalid',
                ),
                HttpStatusType::Forbidden->value,
            );
        }

        $isMachineMismatch = (strtolower($storedData['machine']) !== strtolower($machineName));

        if ($isMachineMismatch) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Machine name mismatch',
                    'code'                          => 'machine_mismatch',
                ),
                HttpStatusType::Forbidden->value,
            );
        }

        // Consume token (single-use)
        delete_transient($transientKey);

        // Increment rate limiter
        $this->incrementLogClearCount($machineName);

        // Execute the actual clearing (files + DB)
        $clearResult = $this->executeLogClearing();
        $dbClearResult = $this->executeDatabaseClearing();

        $clientIp = $this->resolveClientIp();
        $mergedResult = array_merge($clearResult, $dbClearResult);

        // Post-deletion audit entry
        $this->insertClearAuditEntry($machineName, $clientIp, $mergedResult);

        $this->fileLogger->info('Logs cleared remotely', array(
            'machine' => $machineName,
            'ip'      => $clientIp,
            'cleared' => $mergedResult,
        ));

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'cleared'                       => $mergedResult,
                'cleared_by'                    => array(
                    'machine'   => $machineName,
                    'ip'        => $clientIp,
                    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                ),
            ),
            HttpStatusType::Ok->value,
        );
    }

    // ── Clearing Execution ────────────────────────────────────────────

    /** Execute file-based log clearing. */
    private function executeLogClearing(): array {
        $logger = FileLogger::getInstance();
        $logger->clearAllLogFiles();

        return array(
            'log_file'        => true,
            'error_file'      => true,
            'stacktrace_file' => true,
        );
    }

    /** Truncate Transactions and ErrorSessions tables. */
    private function executeDatabaseClearing(): array {
        $result = array(
            'activity_log'    => false,
            'error_sessions'  => false,
        );

        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return $result;
            }

            $pdo->exec('DELETE FROM ' . TableType::Transactions->value);
            $result['activity_log'] = true;

            $pdo->exec('DELETE FROM ' . TableType::ErrorSessions->value);
            $result['error_sessions'] = true;
        } catch (\Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to clear database tables during remote log clear');
        }

        return $result;
    }

    /** Insert a post-deletion audit entry to record who cleared logs and when. */
    private function insertClearAuditEntry(string $machineName, string $clientIp, array $clearResult): void {
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return;
            }

            $now = DateHelper::nowUtc();
            $details = json_encode(array(
                'cleared_files'  => array('log.txt', 'error.txt', 'stacktrace.txt'),
                'cleared_tables' => array('Transactions', 'ErrorSessions'),
                'cleared_by_ip'  => $clientIp,
                'cleared_at'     => $now,
                'results'        => $clearResult,
            ), JSON_UNESCAPED_SLASHES);

            $stmt = $pdo->prepare(
                'INSERT INTO ' . TableType::Transactions->value .
                ' (Action, Status, SourceMachine, Details, CreatedAt) VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute(array(
                'logs_cleared',
                'success',
                $machineName,
                $details,
                $now,
            ));
        } catch (\Throwable $e) {
            // Silently fail — we're in the logger scope
        }
    }

    // ── Machine Validation ────────────────────────────────────────────

    /** Check if a machine name is in the approved list (case-insensitive, fail-closed). */
    private function isMachineApproved(string $machineName): bool {
        $settingsJson = $this->loadPluginSettings();
        $approvedMachines = $settingsJson['approved_machines'] ?? array();
        $hasNoApprovedMachines = empty($approvedMachines);

        if ($hasNoApprovedMachines) {
            return false; // Fail-closed
        }

        $lowerMachine = strtolower($machineName);

        foreach ($approvedMachines as $approved) {
            $isMatch = (strtolower($approved) === $lowerMachine);

            if ($isMatch) {
                return true;
            }
        }

        return false;
    }

    /** Load plugin settings from settings.json. */
    private function loadPluginSettings(): array {
        $pluginDir = dirname(__DIR__, 2);
        $settingsPath = $pluginDir . '/settings.json';
        $isSettingsExists = file_exists($settingsPath);

        if ($isSettingsExists === false) {
            return array();
        }

        $contents = @file_get_contents($settingsPath);
        $isReadFailed = ($contents === false);

        if ($isReadFailed) {
            return array();
        }

        $settings = json_decode($contents, true);
        $isDecodeFailed = !is_array($settings);

        if ($isDecodeFailed) {
            return array();
        }

        return $settings;
    }

    // ── Rate Limiting ─────────────────────────────────────────────────

    /** Check if a machine has exceeded the clear rate limit. */
    private function isLogClearRateLimited(string $machineName): bool {
        $rateKey = PluginConfigType::Slug->value . '_clear_rate_' . md5(strtolower($machineName));
        $count = (int) get_transient($rateKey);
        $isOverLimit = ($count >= self::MAX_CLEARS_PER_HOUR);

        return $isOverLimit;
    }

    /** Increment the clear count for rate limiting. */
    private function incrementLogClearCount(string $machineName): void {
        $rateKey = PluginConfigType::Slug->value . '_clear_rate_' . md5(strtolower($machineName));
        $count = (int) get_transient($rateKey);
        set_transient($rateKey, $count + 1, 3600);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Build the transient key for a clear token. */
    private function buildClearTokenKey(string $machineName): string {
        return PluginConfigType::Slug->value . '_clear_token_' . md5(strtolower($machineName));
    }

    /** Resolve the client IP address. */
    private function resolveClientIp(): string {
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $hasForwardedFor = !empty($forwardedFor);

        if ($hasForwardedFor) {
            $ips = explode(',', $forwardedFor);

            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
