<?php
/**
 * SelfUpdateHealthCheck — Post-activation health verification for self-updates.
 *
 * Inspects BootErrorCollector and validates critical runtime state after
 * the new version has been activated. Returns structured diagnostics
 * for the REST API response and rollback decision.
 *
 * @package RiseupAsia\Update
 * @since   2.4.0
 */

namespace RiseupAsia\Update;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\ErrorHandling\BootErrorCollector;
use RiseupAsia\Logging\FileLogger;

class SelfUpdateHealthCheck
{
    private FileLogger $fileLogger;

    /** @var array<int, array{code: string, message: string}> Collected health check issues. */
    private array $issues = array();

    public function __construct(FileLogger $fileLogger)
    {
        $this->fileLogger = $fileLogger;
    }

    /**
     * Run all post-activation health checks.
     *
     * @return bool True if healthy, false if critical issues found.
     */
    public function check(): bool
    {
        $this->issues = array();

        $this->fileLogger->info('Running post-activation health check');

        $this->checkBootErrors();
        $this->checkCriticalClasses();
        $this->checkCriticalFunctions();

        $hasIssues = !empty($this->issues);

        if ($hasIssues) {
            $this->fileLogger->error('Post-activation health check failed', array(
                'issueCount' => count($this->issues),
                'issues'     => $this->issues,
            ));

            return false;
        }

        $this->fileLogger->info('Post-activation health check passed');

        return true;
    }

    /**
     * Get structured diagnostics for REST API responses.
     *
     * @return array{Healthy: bool, IssueCount: int, Issues: array<int, array{code: string, message: string}>, BootErrors: array}
     */
    public function getDiagnostics(): array
    {
        $bootErrors = array();

        $collector = BootErrorCollector::getInstance();

        if ($collector->hasErrors()) {
            $bootErrors = $collector->getErrors();
        }

        return array(
            ResponseKeyType::Healthy->value    => empty($this->issues),
            ResponseKeyType::IssueCount->value => count($this->issues),
            ResponseKeyType::Issues->value     => $this->issues,
            ResponseKeyType::BootErrors->value => $bootErrors,
        );
    }

    /**
     * Check if BootErrorCollector captured any errors during activation.
     */
    private function checkBootErrors(): void
    {
        $collector = BootErrorCollector::getInstance();

        if ($collector->hasErrors() === false) {
            return;
        }

        $errors = $collector->getErrors();

        foreach ($errors as $error) {
            $this->addIssue(SelfUpdateStatusType::BootErrorDetected, 'Boot error [' . $error['context'] . ']: ' . $error['message']);
        }

        $this->fileLogger->warn('BootErrorCollector has errors after activation', array(
            'count'  => count($errors),
            'errors' => $errors,
        ));
    }

    /**
     * Verify that critical classes are still available after activation.
     *
     * Covers: core shell, logging, error handling, database, post management,
     * update resolution, helpers, and notification subsystems.
     */
    private function checkCriticalClasses(): void
    {
        $criticalClasses = array(
            // Core
            'RiseupAsia\\Core\\Plugin',

            // Logging
            'RiseupAsia\\Logging\\FileLogger',
            'RiseupAsia\\Logging\\Logger',

            // Error handling
            'RiseupAsia\\ErrorHandling\\BootErrorCollector',
            'RiseupAsia\\ErrorHandling\\FatalErrorHandler',

            // Database
            'RiseupAsia\\Database\\Database',

            // Domain managers
            'RiseupAsia\\Post\\PostManager',
            'RiseupAsia\\Update\\UpdateResolver',

            // Helpers
            'RiseupAsia\\Helpers\\PathHelper',
            'RiseupAsia\\Helpers\\InitHelpers',
            'RiseupAsia\\Helpers\\EnvelopeBuilder',

            // Notification
            'RiseupAsia\\Notification\\AdminMailer',

            // Enums (boot-critical)
            'RiseupAsia\\Enums\\PluginConfigType',
            'RiseupAsia\\Enums\\ResponseKeyType',
            'RiseupAsia\\Enums\\HookType',
        );

        foreach ($criticalClasses as $className) {
            if (class_exists($className, false) === false) {
                $this->addIssue(SelfUpdateStatusType::CriticalClassMissing, 'Critical class not loaded after activation: ' . $className);
            }
        }
    }

    /**
     * Verify that critical WordPress hooks are still registered after activation.
     *
     * Checks REST API route registration, plugin lifecycle hooks,
     * and the error response enrichment filter.
     */
    private function checkCriticalFunctions(): void
    {
        $requiredActions = array(
            'rest_api_init'      => 'REST API route registration',
            'activated_plugin'   => 'Plugin activation lifecycle hook',
            'deactivated_plugin' => 'Plugin deactivation lifecycle hook',
        );

        foreach ($requiredActions as $hook => $description) {
            $hasHook = has_action($hook);

            if ($hasHook === false) {
                $this->addIssue(SelfUpdateStatusType::RestHookMissing, $description . ' (' . $hook . ') has no registered handlers after activation');
            }
        }

        // Check the REST response enrichment filter
        $hasFilter = has_filter('rest_post_dispatch');

        if ($hasFilter === false) {
            $this->addIssue(SelfUpdateStatusType::RestHookMissing, 'rest_post_dispatch filter has no registered handlers after activation');
        }
    }

    /**
     * Record a typed health check issue.
     */
    private function addIssue(SelfUpdateStatusType $code, string $message): void
    {
        $this->issues[] = array(
            'code'    => $code->value,
            'message' => $message,
        );
    }
}
