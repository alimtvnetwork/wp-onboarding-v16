# Trait Decomposition Map

> **Status**: 100% compliant — all 229 PHP files pass the 200-line file limit and 15-logic-line function limit.
> **Updated**: 2026-02-13

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
- **RestoreEngine** → `RestoreTableTrait` (→ `RestoreSqliteValidationTrait`) + `RestoreGraphTrait` + `RestoreIncrementalTrait` + `RestoreUtilsTrait` + `RestoreValidationTrait`
- **SnapshotImport** → `ImportExecutionTrait` (→ `ImportExecutionFileTrait`) + `ImportValidationTrait`
- **SnapshotScheduler** → `SchedulerConfigTrait` + `SchedulerCronTrait` + `SchedulerExecutorTrait` + `SchedulerTimingTrait` + `SchedulerTriggerTrait`
- **SnapshotCleaner** → `CleanerDeletionTrait` + `CleanerOrphanTrait` + `CleanerRetentionTrait` + `CleanerStorageTrait` + `CleanerUtilsTrait`
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
- **PathUtils** → `PathUtilsCoreTrait` + `PathUtilsDirTrait` + `PathUtilsFileTrait`
- **EnvelopeBuilder** → `EnvelopeBuildTrait` + `EnvelopeFactoryTrait` + `EnvelopeSettersTrait`
- **BooleanHelpers** → `BooleanDomainTrait` (function: `is_func_exists`, `is_func_missing`; class: `is_class_exists`, `is_class_missing`, `is_class_not_loaded`; extension: `is_extension_loaded`, `is_extension_missing`; directory: `is_dir_exists`, `is_dir_missing`, `is_dir_writable`, `is_dir_readonly`, `is_not_directory`; file: `is_file_exists`, `is_file_missing`, `is_file_unreadable`, `is_not_regular_file`, `is_copy_failed`; list: `is_not_in_list`; database: `is_db_connected`, `is_db_disconnected`) + `BooleanValueTrait`
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
