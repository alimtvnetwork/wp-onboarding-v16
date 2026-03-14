<?php
/**
 * LogClearingTrait — Two-step remote log clearing with machine validation.
 *
 * Step 1 (DELETE /logs/clear): Validates machine, generates 60s token.
 * Step 2 (POST /logs/clear/confirm): Consumes token, executes deletion.
 *
 * @package QUpload\Traits\Log
 * @since   2.12.0
 */

namespace QUpload\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Logging\FileLogger;

trait LogClearingTrait
{
    private const CLEAR_TOKEN_TTL_SECONDS = 60;
    private const CLEAR_TOKEN_LENGTH = 32;
    private const MAX_CLEARS_PER_HOUR = 3;

    // ── Step 1: Request Token ─────────────────────────────────────────

    /** Handle DELETE /logs/clear — validate machine, issue confirmation token. */
    public function handleLogsClearRequest(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Logs clear request endpoint called', ['endpoint' => 'logs/clear']);
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $machineError = $this->validateMachineHeader($machineName);

        if ($machineError !== null) {
            return $machineError;
        }

        $isRateLimited = $this->isLogClearRateLimited($machineName);

        if ($isRateLimited) {
            return $this->buildLogErrorResponse('Rate limit exceeded (max ' . self::MAX_CLEARS_PER_HOUR . '/hour)', 'rate_limited', HttpStatusType::TooManyRequests);
        }

        return $this->issueClearToken($machineName);
    }

    /** Generate a clear token, store it as a transient, and return the response. */
    private function issueClearToken(string $machineName): WP_REST_Response {
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

        return $this->buildClearTokenResponse($token, $namespace);
    }

    /** Build the WP_REST_Response for a newly issued clear token. */
    private function buildClearTokenResponse(string $token, string $namespace): WP_REST_Response {
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

    /** Handle POST /logs/clear/confirm — validate token, execute log clearing. */
    public function handleLogsClearConfirm(WP_REST_Request $request): WP_REST_Response {
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $isMachineMissing = empty($machineName);

        if ($isMachineMissing) {
            return $this->buildLogErrorResponse('X-Riseup-Source-Machine header is required', 'machine_header_missing', HttpStatusType::BadRequest);
        }

        $body = $request->get_json_params();
        $token = $body['token'] ?? '';
        $tokenError = $this->validateStoredClearToken($machineName, $token);

        if ($tokenError !== null) {
            return $tokenError;
        }

        return $this->executeClearConfirm($machineName);
    }

    /** Validate the clear token against stored transient data. */
    private function validateStoredClearToken(string $machineName, string $token): ?WP_REST_Response {
        $isTokenMissing = empty($token);

        if ($isTokenMissing) {
            return $this->buildLogErrorResponse('Token is required in request body', 'token_missing', HttpStatusType::BadRequest);
        }

        $transientKey = $this->buildClearTokenKey($machineName);
        $storedData = get_transient($transientKey);
        $isTokenExpired = ($storedData === false);

        if ($isTokenExpired) {
            return $this->buildLogErrorResponse('Token expired or not found', 'token_expired', HttpStatusType::Gone);
        }

        $isTokenMismatch = ($storedData['token'] !== $token);
        $isMachineMismatch = (strtolower($storedData['machine']) !== strtolower($machineName));

        if ($isTokenMismatch) {
            return $this->buildLogErrorResponse('Invalid token', 'token_invalid', HttpStatusType::Forbidden);
        }

        if ($isMachineMismatch) {
            return $this->buildLogErrorResponse('Machine name mismatch', 'machine_mismatch', HttpStatusType::Forbidden);
        }

        return null;
    }

    /** Consume the token, execute clearing, and return the result. */
    private function executeClearConfirm(string $machineName): WP_REST_Response {
        $transientKey = $this->buildClearTokenKey($machineName);
        delete_transient($transientKey);
        $this->incrementLogClearCount($machineName);

        $clearResult = $this->executeLogClearing();
        $clientIp = $this->resolveClientIp();

        $this->fileLogger->info('Logs cleared remotely', array(
            'machine' => $machineName,
            'ip'      => $clientIp,
            'cleared' => $clearResult,
        ));

        return $this->buildClearSuccessResponse($machineName, $clientIp, $clearResult);
    }

    /** Build the success response after log clearing. */
    private function buildClearSuccessResponse(string $machineName, string $clientIp, array $clearResult): WP_REST_Response {
        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'cleared'                       => $clearResult,
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

    /** Execute the actual log file deletion. */
    private function executeLogClearing(): array {
        $logger = FileLogger::getInstance();
        $logger->clearAllLogFiles();

        return array(
            'log_file'        => true,
            'error_file'      => true,
            'stacktrace_file' => true,
        );
    }

    // ── Machine Validation ────────────────────────────────────────────

    /** Validate the machine header — returns error response or null. Shared with LogEmailTrait. */
    private function validateMachineHeader(?string $machineName): ?WP_REST_Response {
        $isMachineMissing = empty($machineName);

        if ($isMachineMissing) {
            return $this->buildLogErrorResponse('X-Riseup-Source-Machine header is required', 'machine_header_missing', HttpStatusType::BadRequest);
        }

        $isMachineApproved = $this->isMachineApproved($machineName);

        if ($isMachineApproved === false) {
            $this->fileLogger->warn('Log action rejected: machine not approved', array('machine' => $machineName));

            return $this->buildLogErrorResponse('Machine not in approved list', 'machine_not_approved', HttpStatusType::Forbidden);
        }

        return null;
    }

    /** Check if a machine name is in the approved list (case-insensitive). */
    private function isMachineApproved(string $machineName): bool {
        $settings = get_option(PluginConfigType::SettingsGroup->value, array());
        $approvedMachines = $settings['approved_machines'] ?? array();
        $hasNoApprovedMachines = empty($approvedMachines);

        if ($hasNoApprovedMachines) {
            return false;
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

    // ── Shared Helpers ────────────────────────────────────────────────

    /** Build a standardized error response. Shared with LogEmailTrait. */
    private function buildLogErrorResponse(string $error, string $code, HttpStatusType $status, string $message = ''): WP_REST_Response {
        $data = array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => $error,
            'code'                          => $code,
        );

        $hasMessage = ($message !== '');

        if ($hasMessage) {
            $data['message'] = $message;
        }

        return new WP_REST_Response($data, $status->value);
    }

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