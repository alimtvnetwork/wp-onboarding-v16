// Barrel re-export — all 44 consumers import from "@/lib/api" which resolves here.
// No import changes needed anywhere in the codebase.

// Types
export type {
  ApiResponse,
  ApiError,
  ApiMethod,
  ApiCallMeta,
  EnvelopeStatus,
  EnvelopeAttributes,
  EnvelopeNavigation,
  EnvelopeErrors,
  EnvelopeMethodFrame,
  EnvelopeMethodsStack,
  EnvelopeMeta,
  Site,
  Plugin,
  PluginMapping,
  PluginVersion,
  FileChange,
  SyncResult,
  Backup,
  RemotePlugin,
  RemotePluginFile,
  RemotePluginFilesResult,
  ErrorLog,
  SessionSummary,
  SessionInfo,
  SessionStackFrame,
  SessionDiagnostics,
  FilePreview,
  PublishPreview,
  Settings,
  ErrorHistoryInput,
  ErrorHistoryRecord,
  ErrorHistoryListResponse,
  ErrorHistoryStats,
  SnapshotRecord,
  SnapshotSettings,
  SnapshotProviderInfo,
  AvailableTable,
  PublishHistoryEntry,
  PublishHistoryStats,
  SnapshotSchedule,
  SnapshotInterval,
  RequestSessionRecord,
  RequestSessionListResponse,
} from './types';

// Envelope utilities
export { isEnvelope, parseEnvelope, looksLikeJson } from './envelope';
export type { RawEnvelope } from './envelope';

// Client utilities
export { ApiClientError, isApiClientError, requireSuccess, request, getApiDiagnostics } from './client';

// API methods object
export { api } from './methods';
