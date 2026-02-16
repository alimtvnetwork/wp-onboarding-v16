# Trait Decomposition Map

> **Status**: 100% compliant — all 229 PHP files pass the 200-line file limit and 15-logic-line function limit.
> **Updated**: 2026-02-16

## Known Exemptions

| File | Lines | Reason |
|------|-------|--------|
| `riseup-asia-uploader.php` | 261 | Architectural shell with ~20 trait `use` statements; non-decomposable |
| `constants.php` | 927 | Pure `define()` declarations with no logic |

## Snapshot Domain (`includes/Snapshot/`)

- **SnapshotProviderInterface** → `SnapshotProviderHelpersTrait` + `SnapshotProviderLockTrait`
- **SnapshotProviderNative** → `NativeSnapshotCreateTrait` (→ `NativeSnapshotExecTrait`) + `NativeSnapshotCrudTrait` + `NativeSnapshotRecordTrait` + `NativeTableExportTrait` (→ `NativeTableExportConvertTrait`)
- **SnapshotProviderUpdraft** → `UpdraftCrudTrait`
- **SnapshotManager** → `ManagerCoreTrait` + `ManagerExportTrait` + `ManagerImportTrait` (→ `ManagerImportValidationTrait` + `ManagerImportRecordTrait`) + `ManagerRestoreTrait` (→ `ManagerRestoreValidationTrait`) + `ManagerSettingsTrait` + `ManagerTableRestoreTrait`
- **SnapshotWorker** → `WorkerExecuteTrait` + `WorkerBatchTrait` (→ `WorkerBatchExportTrait` + `WorkerBatchProcessTrait`) + `WorkerJobTrait` (→ `WorkerJobLifecycleTrait` + `WorkerJobProgressTrait`) + `WorkerProgressTrait` + `WorkerSetupTrait` + `WorkerTableExportTrait`
- **SnapshotOrchestrator** → `OrchestratorBackupTrait` + `OrchestratorHelpersTrait` + `OrchestratorPluginTrait` + `OrchestratorRegistrationTrait` + `OrchestratorZipTrait`
- **RestoreEngine** → `RestoreTableTrait` (→ `RestoreSqliteValidationTrait`) + `RestoreGraphTrait` + `RestoreIncrementalTrait` + `RestoreHelperTrait` + `RestoreValidationTrait`
- **SnapshotImport** → `ImportExecutionTrait` (→ `ImportExecutionFileTrait`) + `ImportValidationTrait`
- **SnapshotScheduler** → `SchedulerConfigTrait` + `SchedulerCronTrait` + `SchedulerExecutorTrait` + `SchedulerTimingTrait` + `SchedulerTriggerTrait`
- **SnapshotCleaner** → `CleanerDeletionTrait` + `CleanerOrphanTrait` + `CleanerRetentionTrait` + `CleanerStorageTrait` + `CleanerHelperTrait`
- **SnapshotDetector** → `DetectorProviderTrait` + `DetectorSettingsTrait` + `DetectorValidationTrait`
- **SnapshotExporter** → `ExporterBuildTrait` (→ `ExporterBuildCollectTrait`) + `ExporterHelpersTrait` + `ExporterPublicApiTrait`
- **DependencyAnalyzer** → `AnalyzerGraphTrait` + `AnalyzerQueryTrait`
- **IncrementalBackup** → `IncrementalCoreTrait` + `IncrementalDeltaTrait` + `IncrementalDiscoveryTrait` + `IncrementalExportTrait` + `IncrementalRegistrationTrait`

## Core Infrastructure (`includes/`)

- **Database** → `DatabaseConnectionTrait` + `DatabaseQueryTrait` (→ `DatabaseQueryLogTrait` + `DatabaseQuerySearchTrait`) + `DatabaseMigrationsEarlyTrait` (→ `V1V3` + `V4V5`) + `DatabaseMigrationsLateTrait` (→ `V6V8` + `V9V11`)
- **Orm** → `OrmMutationTrait` + `OrmQueryTrait` + `OrmWhereTrait`
- **RootDb** → `RootDbRegistrationTrait` + `RootDbSchemaTrait`
- **FileCache** → `FileCacheScanTrait` + `FileCacheStoreTrait`
- **Admin** → 11 traits (Ajax, Menu, Settings, Error handling)
- **AgentManager** → `AgentCrudTrait` (→ `AgentCrudWriteTrait` + `AgentCrudReadTrait`) + `AgentLoggingTrait` + `AgentRemoteTrait` (→ `AgentRemoteCoreTrait` + `AgentRemoteActionTrait`)
- **FileLogger** → `LoggerActionsTrait` + `LoggerContextTrait` + `LoggerDedupTrait` + `LoggerFormatTrait` + `LoggerLevelMethodsTrait` + `LoggerPathTrait` + `LoggerWriteTrait`
- **PostManager** → `CategoryTrait` + `PostCrudTrait` + `PostQueryTrait`
- **PathHelper** → `PathHelperCoreTrait` + `PathHelperDirTrait` + `PathHelperFileTrait`
- **EnvelopeBuilder** → `EnvelopeBuildTrait` + `EnvelopeFactoryTrait` + `EnvelopeSettersTrait`
- **BooleanHelpers** → `BooleanDomainTrait` (function: `isFuncExists`, `isFuncMissing`; class: `isClassExists`, `isClassMissing`, `isClassNotLoaded`; extension: `isExtensionLoaded`, `isExtensionMissing`; list: `isNotInList`; database: `isDbConnected`, `isDbDisconnected` — 10 guards total) + `BooleanValueTrait`
- **InitHelpers** → `InitDirTrait` + `InitStartupTrait`
- **UpdateResolver** → `UpdateResolverHooksTrait` + `UpdateResolverUrlTrait` + `UpdateResolverWpHooksTrait`
- **UploadIgnore** → `UploadIgnorePatternTrait`

## Plugin Shell Traits (`riseup-asia-uploader.php`)

19 domain traits composed into the `RiseupAsia` class:

- `LifecycleHooksTrait`, `RouteRegistrationTrait`, `PluginRoutesTrait`, `InvalidRouteTrait`
- `AuthTrait` (→ `AuthCredentialTrait` + `AuthPermissionTrait`)
- `StatusHandlerTrait` (→ `StatusOpsTrait` + `StatusPayloadTrait`)
- `UploadPipelineTrait` (→ `UploadParserTrait` + `UploadExtractionTrait` → `UploadZipTrait` + `UploadInstallTrait` → `UploadInstallExtractTrait` + `UploadInstallActivateTrait`)
- `PluginListTrait`, `PluginExportTrait`, `PostHandlerTrait`
- `PluginLifecycleTrait` (→ `PluginLifecycleActionsTrait` + `PluginLifecycleDeleteTrait` + `PluginLifecycleEnableTrait` + `PluginLifecycleHelpersTrait`)
- `SyncHandlerTrait` (→ `SyncManifestTrait` + `SyncPushTrait`)
- `ResponseTrait`
- `ErrorLogTrait` (→ `ErrorLogHandlerTrait` + `ErrorSessionHandlerTrait`)
- `AgentHandlerTrait` (→ `AgentHandlerActionTrait` + `AgentHandlerCrudTrait`)
- `SnapshotCrudTrait` (→ `SnapshotCrudCreateTrait` + `SnapshotCrudListTrait` + `SnapshotCrudMutateTrait` + `SnapshotCrudRestoreTrait`)
- `SnapshotExportTrait` (→ `SnapshotExportHandlerTrait`)
- `SnapshotBackupTrait` (→ `SnapshotBackupExecTrait` + `SnapshotBackupHandlerTrait` + `SnapshotBackupOpsTrait`)
- `FileSystemTrait` (→ `FileSystemPluginTrait` + `FileSystemDirTrait`)
- `SnapshotImportStreamTrait`, `SnapshotSettingsHandlerTrait`, `SnapshotRouteRegistrationTrait`, `PluginRouteRegistrationTrait`
