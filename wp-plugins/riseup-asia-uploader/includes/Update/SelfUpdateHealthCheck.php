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

use RiseupAsia\ErrorHandling\BootErrorCollector;
use RiseupAsia\Logging\FileLogger;

class SelfUpdateHealthCheck
{
    private FileLogger $fileLogger;

    /** @var array<string> Collected health check warnings/errors. */
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
     * @return array{healthy: bool, issueCount: int, issues: array<string>, bootErrors: array}
     */
    public function getDiagnostics(): array
    {
        $bootErrors = array();

        $collector = BootErrorCollector::getInstance();

        if ($collector->hasErrors()) {
            $bootErrors = $collector->getErrors();
        }

        return array(
            'healthy'    => empty($this->issues),
            'issueCount' => count($this->issues),
            'issues'     => $this->issues,
            'bootErrors' => $bootErrors,
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
            $this->issues[] = 'Boot error [' . $error['context'] . ']: ' . $error['message'];
        }

        $this->fileLogger->warn('BootErrorCollector has errors after activation', array(
            'count'  => count($errors),
            'errors' => $errors,
        ));
    }

    /**
     * Verify that critical classes are still available after activation.
     */
    private function checkCriticalClasses(): void
    {
        $criticalClasses = array(
            'RiseupAsia\\Core\\Plugin',
            'RiseupAsia\\Logging\\FileLogger',
            'RiseupAsia\\ErrorHandling\\BootErrorCollector',
            'RiseupAsia\\Database\\Database',
        );

        foreach ($criticalClasses as $className) {
            if (class_exists($className, false) === false) {
                $this->issues[] = 'Critical class not loaded after activation: ' . $className;
            }
        }
    }

    /**
     * Verify that critical WordPress integration functions are still registered.
     */
    private function checkCriticalFunctions(): void
    {
        $hasRestRoutes = has_action('rest_api_init');

        if ($hasRestRoutes === false) {
            $this->issues[] = 'rest_api_init hook has no registered handlers after activation';
        }
    }
}
