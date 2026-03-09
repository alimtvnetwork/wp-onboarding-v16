<?php
/**
 * WorkerSetupTrait — snapshot directory preparation, root DB init, and helpers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait WorkerSetupTrait {
    private function prepareSnapshotDir(array $config): array {
        $resolved = $this->resolveSnapshotConfig($config);
        $this->logSnapshotStart($resolved);

        $snapshotDir = $this->buildSnapshotDirPath($resolved);
        $isDirCreateFailed = (PathHelper::makeDirectory($snapshotDir, true) === false);

        if ($isDirCreateFailed) {
            return ResultHelper::error('Failed to create snapshot directory');
        }

        return $this->buildSnapshotDirResult($snapshotDir, $resolved);
    }

    private function resolveSnapshotConfig(array $config): array {
        $hasPoolSize = !empty($config[ResponseKeyType::Settings->value]['worker_pool_size'] ?? null);

        if ($hasPoolSize) {
            $this->setPoolSize($config[ResponseKeyType::Settings->value]['worker_pool_size']);
        }

        return array(
            ResponseKeyType::Title->value => $config[ResponseKeyType::Title->value] ?? (SnapshotConfigType::DefaultTitle . ' ' . DateHelper::nowCompactDatetime()),
            ResponseKeyType::Scope->value => $config[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value,
            ResponseKeyType::Type->value  => $config[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value,
        );
    }

    private function logSnapshotStart(array $resolved): void {
        $this->log(LogLevelType::Info->value, 'Starting per-table snapshot', array(
            ResponseKeyType::Title->value    => $resolved[ResponseKeyType::Title->value],
            ResponseKeyType::Scope->value    => $resolved[ResponseKeyType::Scope->value],
            ResponseKeyType::Type->value     => $resolved[ResponseKeyType::Type->value],
            ResponseKeyType::PoolSize->value => $this->poolSize,
        ));
    }

    private function buildSnapshotDirPath(array $resolved): string {
        $baseDir = $this->getSnapshotsBaseDir();
        $dirName = DateHelper::nowDateOnly() . '_' . $resolved[ResponseKeyType::Type->value] . '_' . sanitize_title($resolved[ResponseKeyType::Title->value]);

        return $baseDir . '/' . $dirName;
    }

    private function buildSnapshotDirResult(string $snapshotDir, array $resolved): array {
        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotDir->value => $snapshotDir,
            ResponseKeyType::DirName->value     => basename($snapshotDir),
            ResponseKeyType::Title->value       => $resolved[ResponseKeyType::Title->value],
            ResponseKeyType::Scope->value       => $resolved[ResponseKeyType::Scope->value],
            ResponseKeyType::Type->value        => $resolved[ResponseKeyType::Type->value],
        ));
    }

    private function initRootDb(string $snapshotDir, array $config): PDO {
        $rootPdo = $this->rootDb->create($snapshotDir . '/' . SnapshotConfigType::RootDbFilename);
        $this->rootDb->populateMetadata($rootPdo, array(
            ResponseKeyType::Title->value    => $config[ResponseKeyType::Title->value] ?? SnapshotConfigType::DefaultTitle,
            ResponseKeyType::Type->value     => $config[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value,
            ResponseKeyType::Settings->value => $config[ResponseKeyType::Settings->value] ?? null,
        ));

        return $rootPdo;
    }

    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $analysis = $this->rootDb->populateDependencies($rootPdo, $config[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value);

        $this->log(LogLevelType::Info->value, 'Export order determined', array(
            ResponseKeyType::Tables->value   => count($analysis[ResponseKeyType::SeedOrder->value]),
            ResponseKeyType::PoolSize->value => $this->poolSize,
        ));

        return $analysis[ResponseKeyType::SeedOrder->value];
    }

    private function getSnapshotsBaseDir(): string {
        $base = PathHelper::getSnapshotsDir();
        PathHelper::makeDirectory($base, true);

        return $base;
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = $this->formatLogMessage($message, $context);
        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        $this->dispatchLogLevel($level, $full);
    }

    private function formatLogMessage(string $message, array $context): string {
        $full = '[SNAPSHOT] [WORKER] ' . $message;
        $hasContext = !empty($context);

        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        return $full;
    }

    private function dispatchLogLevel(string $level, string $message): void {
        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($message); break;
            case LogLevelType::Error->value: $this->logger->error($message); break;
            default:                         $this->logger->info($message);
        }
    }

    private function logError(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Error->value, $message, $context);
    }

    private function logWarn(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Warn->value, $message, $context);
    }
}
