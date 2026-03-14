<?php
/**
 * AdminFeedbackAjaxTrait — AJAX handlers for feedback/report submission.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.6.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Logging\FileLogger;
use Throwable;

trait AdminFeedbackAjaxTrait {

    private const FEEDBACK_MAX_ATTACHMENTS = 3;
    private const FEEDBACK_MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
    private const FEEDBACK_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const FEEDBACK_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * AJAX handler: Check if feedback sending is ready (support email configured).
     */
    public function ajaxCheckFeedbackReady(): void {
        check_ajax_referer(NonceType::Feedback->value, 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $settings = $this->getSupportSettings();
        $hasSupportEmail = (is_string($settings['support_email']) && strlen(trim($settings['support_email'])) > 0);

        wp_send_json_success(array(
            'ready'        => $hasSupportEmail,
            'fallback_url' => $settings['fallback_url'] ?? '',
            'settings_url' => admin_url('admin.php?page=' . \RiseupAsia\Enums\AdminPageType::Settings->value),
        ));
    }

    /**
     * AJAX handler: Send feedback/report email with optional attachments.
     */
    public function ajaxSendFeedback(): void {
        check_ajax_referer(NonceType::Feedback->value, 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $logger = FileLogger::getInstance();

        try {
            $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
            $body = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
            $includeSystemInfo = !empty($_POST['include_system_info']);

            $isSubjectEmpty = (strlen(trim($subject)) === 0);
            if ($isSubjectEmpty) {
                wp_send_json_error(array(ResponseKeyType::Message->value => 'Subject is required.'));
            }

            $isBodyTooShort = (strlen(trim($body)) < 20);
            if ($isBodyTooShort) {
                wp_send_json_error(array(ResponseKeyType::Message->value => 'Description must be at least 20 characters.'));
            }

            $settings = $this->getSupportSettings();
            $hasSupportEmail = (is_string($settings['support_email']) && strlen(trim($settings['support_email'])) > 0);
            if (!$hasSupportEmail) {
                wp_send_json_error(array(ResponseKeyType::Message->value => 'Support email not configured. Please configure it in Settings.'));
            }

            // Build email body
            $emailBody = $this->buildFeedbackBody($subject, $body, $includeSystemInfo);

            // Process screenshot attachments
            $attachments = $this->processFeedbackAttachments($logger);

            // Process log zip attachment if requested
            $includeLogs = !empty($_POST['include_logs']);
            $logZipPath = null;
            if ($includeLogs) {
                $logZipPath = $this->buildLogZipAttachment($logger);
                if ($logZipPath !== null) {
                    $attachments[] = $logZipPath;
                }
            }

            // Build subject line
            $siteName = get_bloginfo('name');
            $emailSubject = PluginConfigType::LogPrefix->value . ' Feedback: ' . $subject . ' — ' . $siteName;

            $headers = array('Content-Type: text/plain; charset=UTF-8');
            $recipient = trim($settings['support_email']);

            $wasSent = wp_mail($recipient, $emailSubject, $emailBody, $headers, $attachments);

            // Clean up temp files
            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            if (!$wasSent) {
                $logger->error('Feedback email failed to send', array('to' => $recipient, 'subject' => $emailSubject));
                wp_send_json_error(array(
                    ResponseKeyType::Message->value => 'Failed to send email. Please ensure your WordPress site has email sending configured (e.g., GoSMTP, WP Mail SMTP).',
                ));
            }

            $logger->info('Feedback email sent successfully', array('to' => $recipient, 'subject' => $emailSubject));
            wp_send_json_success(array(ResponseKeyType::Message->value => 'Feedback sent successfully!'));

        } catch (Throwable $e) {
            $logger->error('Feedback send error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error(array(ResponseKeyType::Message->value => 'Error sending feedback: ' . $e->getMessage()));
        }
    }

    /**
     * Build the email body for feedback.
     */
    private function buildFeedbackBody(string $subject, string $body, bool $includeSystemInfo): string {
        $lines = array();
        $lines[] = 'Subject: ' . $subject;
        $lines[] = str_repeat('=', 50);
        $lines[] = '';
        $lines[] = $body;
        $lines[] = '';

        if ($includeSystemInfo) {
            $lines[] = str_repeat('-', 50);
            $lines[] = 'System Information';
            $lines[] = str_repeat('-', 50);
            $lines[] = 'Site URL:        ' . get_site_url();
            $lines[] = 'Plugin Version:  ' . PluginConfigType::Version->value;
            $lines[] = 'PHP Version:     ' . phpversion();
            $lines[] = 'WordPress:       ' . get_bloginfo('version');
            $lines[] = 'Active Theme:    ' . wp_get_theme()->get('Name');
            $lines[] = 'Timestamp:       ' . DateHelper::nowUtc();
            $lines[] = 'Admin Email:     ' . get_option('admin_email');
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 50);
        $lines[] = 'This feedback was sent from the ' . PluginConfigType::Name->value . ' plugin.';

        return implode("\n", $lines);
    }

    /**
     * Process uploaded screenshot attachments.
     *
     * @return string[] Array of file paths for wp_mail attachments.
     */
    private function processFeedbackAttachments(FileLogger $logger): array {
        $attachments = array();

        $hasFiles = (!empty($_FILES['screenshots']) && !empty($_FILES['screenshots']['name'][0]));
        if (!$hasFiles) {
            return $attachments;
        }

        $files = $_FILES['screenshots'];
        $fileCount = min(count($files['name']), self::FEEDBACK_MAX_ATTACHMENTS);

        for ($i = 0; $i < $fileCount; $i++) {
            $hasError = ($files['error'][$i] !== UPLOAD_ERR_OK);
            if ($hasError) {
                $logger->warning('Feedback attachment upload error', array('index' => $i, 'error' => $files['error'][$i]));
                continue;
            }

            $isTooLarge = ($files['size'][$i] > self::FEEDBACK_MAX_FILE_SIZE);
            if ($isTooLarge) {
                $logger->warning('Feedback attachment too large', array('index' => $i, 'size' => $files['size'][$i]));
                continue;
            }

            // Validate extension
            $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $isInvalidExtension = (!in_array($extension, self::FEEDBACK_ALLOWED_EXTENSIONS, true));
            if ($isInvalidExtension) {
                $logger->warning('Feedback attachment invalid extension', array('index' => $i, 'ext' => $extension));
                continue;
            }

            // Validate MIME type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($files['tmp_name'][$i]);
            $isInvalidMime = (!in_array($mimeType, self::FEEDBACK_ALLOWED_TYPES, true));
            if ($isInvalidMime) {
                $logger->warning('Feedback attachment invalid MIME', array('index' => $i, 'mime' => $mimeType));
                continue;
            }

            // Move to temp dir
            $tmpDir = get_temp_dir();
            $safeName = sanitize_file_name($files['name'][$i]);
            $tmpPath = $tmpDir . 'riseup_feedback_' . wp_generate_uuid4() . '_' . $safeName;

            $isMoved = move_uploaded_file($files['tmp_name'][$i], $tmpPath);
            if ($isMoved) {
                $attachments[] = $tmpPath;
            } else {
                $logger->warning('Feedback attachment move failed', array('index' => $i));
            }
        }

        return $attachments;
    }

    /**
     * Get support settings from the database.
     *
     * @return array{support_email: string, fallback_url: string}
     */
    private function getSupportSettings(): array {
        $defaults = array(
            'support_email' => '',
            'fallback_url'  => '',
        );

        $stored = get_option(OptionNameType::SupportSettings->value, array());
        $isStoredEmpty = (!is_array($stored) || count($stored) === 0);
        if ($isStoredEmpty) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }
}
