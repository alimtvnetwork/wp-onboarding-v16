<?php
/**
 * AdminMailer — Sends throttled admin email notifications.
 *
 * Uses wp_mail() to send error reports to the WordPress admin email.
 * Throttled via a transient to prevent email flooding.
 *
 * @package RiseupAsia\Notification
 * @since   2.2.0
 */

namespace RiseupAsia\Notification;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;

class AdminMailer {

    private const THROTTLE_TRANSIENT = 'riseup_last_error_email';
    private const DEFAULT_THROTTLE_MINUTES = 60;
    private const LOG_PREFIX = '[Riseup Asia] AdminMailer: ';

    /**
     * Send a boot error report email to the site admin.
     *
     * @param array<int, array{context: string, message: string, timestamp: string}> $errors
     * @return bool True if email was sent, false if throttled or disabled.
     */
    public function sendBootErrorReport(array $errors): bool {
        $hasNoErrors = (count($errors) === 0);
        if ($hasNoErrors) {
            return false;
        }

        $settings = $this->getNotificationSettings();
        $isDisabled = ($settings['enabled'] === false);
        if ($isDisabled) {
            return false;
        }

        $isThrottled = $this->isThrottled();
        if ($isThrottled) {
            return false;
        }

        $recipient = $this->resolveRecipient($settings);
        $subject = $this->buildSubject();
        $body = $this->buildBody($errors);

        $wasSent = wp_mail($recipient, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);

        if ($wasSent) {
            $throttleSeconds = (int) $settings['throttle_minutes'] * 60;
            set_transient(self::THROTTLE_TRANSIENT, time(), $throttleSeconds);
        }

        return $wasSent;
    }

    /**
     * Check whether an email was recently sent (within throttle window).
     */
    private function isThrottled(): bool {
        $lastSent = get_transient(self::THROTTLE_TRANSIENT);

        return ($lastSent !== false);
    }

    /**
     * Resolve the notification settings from the database.
     *
     * @return array{enabled: bool, email: string, throttle_minutes: int}
     */
    private function getNotificationSettings(): array {
        $defaults = [
            'enabled'          => true,
            'email'            => '',
            'throttle_minutes' => self::DEFAULT_THROTTLE_MINUTES,
        ];

        $stored = get_option(OptionNameType::ErrorNotification->value, []);
        $isStoredEmpty = (is_array($stored) === false || count($stored) === 0);
        if ($isStoredEmpty) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    /**
     * Resolve the email recipient — custom email or fallback to admin_email.
     */
    private function resolveRecipient(array $settings): string {
        $hasCustomEmail = (is_string($settings['email']) && strlen(trim($settings['email'])) > 0);
        if ($hasCustomEmail) {
            return trim($settings['email']);
        }

        return get_option('admin_email');
    }

    /**
     * Build the email subject line.
     */
    private function buildSubject(): string {
        $siteName = get_bloginfo('name');

        return '[Riseup Asia] Plugin Boot Errors on ' . $siteName;
    }

    /**
     * Build the email body with error details and system info.
     *
     * @param array<int, array{context: string, message: string, timestamp: string}> $errors
     */
    private function buildBody(array $errors): string {
        $lines = [];
        $lines[] = 'Riseup Asia Uploader — Boot Error Report';
        $lines[] = str_repeat('=', 50);
        $lines[] = '';
        $lines[] = 'Site URL:       ' . get_site_url();
        $lines[] = 'Plugin Version: ' . PluginConfigType::Version->value;
        $lines[] = 'PHP Version:    ' . phpversion();
        $lines[] = 'WordPress:      ' . get_bloginfo('version');
        $lines[] = 'Timestamp:      ' . gmdate('Y-m-d\TH:i:s\Z');
        $lines[] = '';
        $lines[] = 'Errors (' . count($errors) . '):';
        $lines[] = str_repeat('-', 50);

        foreach ($errors as $index => $error) {
            $num = $index + 1;
            $lines[] = '';
            $lines[] = "#{$num} [{$error['context']}]";
            $lines[] = "   Time:    {$error['timestamp']}";
            $lines[] = "   Message: {$error['message']}";
        }

        $lines[] = '';
        $lines[] = str_repeat('-', 50);
        $lines[] = 'This is an automated message from the Riseup Asia Uploader plugin.';
        $lines[] = 'To disable these notifications, update the error notification settings in your plugin configuration.';

        return implode("\n", $lines);
    }
}
