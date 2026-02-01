/**
 * Spec Management Software - API Client Types
 * 
 * Auto-generated from OpenAPI 3.0 specification
 * Version: 1.0.0
 * Generated: 2026-01-28
 * 
 * Usage:
 *   import type { Project, User, Instruction } from '@/api/types';
 */

// =============================================================================
// Common Types
// =============================================================================

/** Standard API response envelope */
export interface ApiResponse<T> {
  success: boolean;
  data: T | null;
  error: string | null;
  meta: ResponseMeta;
}

/** Response metadata included in all API responses */
export interface ResponseMeta {
  requestId: string;
  timestamp: string;
  version: string;
}

/** Pagination metadata for list endpoints */
export interface PaginationMeta {
  total: number;
  limit: number;
  offset: number;
  hasMore: boolean;
}

/** Paginated response wrapper */
export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: ResponseMeta & PaginationMeta;
}

// =============================================================================
// Authentication Types
// =============================================================================

/** User registration request */
export interface RegisterRequest {
  username: string;
  email: string;
  password: string;
  displayName?: string;
}

/** Login credentials */
export interface LoginRequest {
  identifier: string;
  password: string;
  deviceInfo?: DeviceInfo;
}

/** Device information for session tracking */
export interface DeviceInfo {
  userAgent?: string;
  platform?: string;
}

/** Authentication response with user and tokens */
export interface AuthResponse {
  success: boolean;
  data: {
    user: User;
    tokens: TokenPair;
  } | null;
  error: string | null;
  meta: ResponseMeta;
}

/** JWT token pair */
export interface TokenPair {
  accessToken: string;
  refreshToken: string;
  expiresIn: number;
  tokenType: 'Bearer';
}

/** Token refresh request */
export interface RefreshTokenRequest {
  refreshToken: string;
}

/** Logout request options */
export interface LogoutRequest {
  allDevices?: boolean;
}

/** User entity */
export interface User {
  id: string;
  username: string;
  email: string;
  displayName: string;
  role: UserRole;
  createdAt: string;
}
/** User role enum */
export enum UserRole {
  USER = 'user',
  ADMIN = 'admin',
}

/** Active session information */
export interface Session {
  id: string;
  deviceInfo: string;
  ipAddress: string;
  userAgent: string;
  lastActiveAt: string;
  createdAt: string;
  isCurrent: boolean;
}

// =============================================================================
// Project Types
// =============================================================================

/** Project entity */
export interface Project {
  id: string;
  name: string;
  slug: string;
  description: string;
  visibility: ProjectVisibility;
  ownerId: string;
  fileCount: number;
  createdAt: string;
  updatedAt: string;
}
/** Project visibility scope */
export enum ProjectVisibility {
  USER = 'user',
  GLOBAL = 'global',
}

/** Create project request */
export interface CreateProjectRequest {
  name: string;
  description?: string;
  visibility?: ProjectVisibility;
  category?: string;
  language?: string;
  framework?: string;
  tags?: string[];
}

/** Update project request */
export interface UpdateProjectRequest {
  name?: string;
  description?: string;
  visibility?: ProjectVisibility;
  category?: string;
  language?: string;
  framework?: string;
  tags?: string[];
}

/** List projects query parameters */
export interface ListProjectsParams {
  visibility?: ProjectVisibility;
  search?: string;
  limit?: number;
  offset?: number;
}

// =============================================================================
// File Types
// =============================================================================

/** File entity (without content) */
export interface File {
  id: string;
  projectId: string;
  path: string;
  name: string;
  contentHash: string;
  sizeBytes: number;
  createdAt: string;
  updatedAt: string;
}

/** File with content */
export interface FileWithContent extends File {
  content: string;
  mimeType: string;
}

/** Create file request */
export interface CreateFileRequest {
  path: string;
  content: string;
  createDirectories?: boolean;
}

/** Update file request with optimistic locking */
export interface UpdateFileRequest {
  content: string;
  expectedHash?: string;
}

/** Move/rename file request */
export interface MoveFileRequest {
  newPath: string;
}

/** Create directory request */
export interface CreateDirectoryRequest {
  path: string;
}

/** List files query parameters */
export interface ListFilesParams {
  path?: string;
  recursive?: boolean;
  includeDeleted?: boolean;
}

/** Get file query parameters */
export interface GetFileParams {
  includeContent?: boolean;
  version?: string;
}

/** Directory listing response */
export interface DirectoryListing {
  path: string;
  items: DirectoryItem[];
  totalFiles: number;
  totalDirectories: number;
}

/** Directory item type */
export enum DirectoryItemType {
  FILE = 'file',
  DIRECTORY = 'directory',
}

/** Directory item (file or folder) */
export interface DirectoryItem {
  type: DirectoryItemType;
  name: string;
  path: string;
  id?: string;
  sizeBytes?: number;
  fileCount?: number;
  updatedAt: string;
}

/** Conflict response for optimistic locking failures */
export interface ConflictResponse {
  success: false;
  error: string;
  data: {
    currentHash: string;
    expectedHash: string;
    currentContent: string;
  };
}

/** File deletion response */
export interface FileDeleteResponse {
  id: string;
  deletedAt: string;
  permanent: boolean;
  recoveryDeadline?: string;
}

// =============================================================================
// Instruction Types
// =============================================================================

/** Instruction entity */
export interface Instruction {
  id: string;
  projectId: string;
  rawTranscription: string;
  proofreadText: string;
  instructionText: string;
  scope: InstructionScope;
  targetFilePath?: string;
  status: InstructionStatus;
  executionMode: ExecutionMode;
  createdAt: string;
  updatedAt: string;
}

/** Instruction with full details */
export interface InstructionDetails extends Instruction {
  planMarkdown?: string;
  planJson?: Record<string, unknown>;
  tasks: InstructionTask[];
  thinkingModelId?: string;
  writingModelId?: string;
  voiceModelId?: string;
  codingModelId?: string;
}
/** Instruction scope types */
export enum InstructionScope {
  GLOBAL = 'global',
  BACKEND = 'backend',
  FRONTEND = 'frontend',
  FILE = 'file',
}

/** Instruction status workflow */
export enum InstructionStatus {
  TRANSCRIBED = 'transcribed',
  PROOFREADING = 'proofreading',
  PROOFREAD = 'proofread',
  PLANNING = 'planning',
  PLANNED = 'planned',
  REVIEWING = 'reviewing',
  READY = 'ready',
  EXECUTING = 'executing',
  COMPLETED = 'completed',
  FAILED = 'failed',
  CANCELLED = 'cancelled',
}

/** Execution mode for instructions */
export enum ExecutionMode {
  AUTOMATIC = 'automatic',
  APPROVAL = 'approval',
}

/** Create instruction from text */
export interface CreateInstructionRequest {
  text: string;
  scope?: InstructionScope;
  targetFilePath?: string;
  executionMode?: ExecutionMode;
  modelOverrides?: ModelOverrides;
}

/** Update instruction request */
export interface UpdateInstructionRequest {
  instructionText?: string;
  scope?: InstructionScope;
  targetFilePath?: string;
  executionMode?: ExecutionMode;
}

/** List instructions query parameters */
export interface ListInstructionsParams {
  status?: InstructionStatus;
  scope?: InstructionScope;
}

/** Instruction task entity */
export interface InstructionTask {
  id: string;
  instructionId: string;
  parentTaskId: string | null;
  title: string;
  description: string;
  taskType: TaskType;
  modelCategory: ModelCategory;
  targetFilePath: string;
  status: TaskStatus;
  sortOrder: number;
  dependsOn: string[];
  createdAt: string;
  completedAt: string | null;
}
/** Task types */
export enum TaskType {
  CREATE = 'create',
  UPDATE = 'update',
  DELETE = 'delete',
  REFACTOR = 'refactor',
  REVIEW = 'review',
  VERIFY = 'verify',
}

/** Task status workflow */
export enum TaskStatus {
  PENDING = 'pending',
  BLOCKED = 'blocked',
  READY = 'ready',
  IN_PROGRESS = 'in_progress',
  COMPLETED = 'completed',
  FAILED = 'failed',
  SKIPPED = 'skipped',
}

// =============================================================================
// Idea Types
// =============================================================================

/** Idea entity */
export interface Idea {
  id: string;
  projectId: string;
  title: string;
  summary: string;
  rawTranscription: string;
  proofreadContent: string;
  status: IdeaStatus;
  priority: Priority;
  filePath: string;
  promotedToInstructionId: string | null;
  createdAt: string;
  updatedAt: string;
}
/** Idea status workflow */
export enum IdeaStatus {
  DRAFT = 'draft',
  REFINED = 'refined',
  PROMOTED = 'promoted',
  ARCHIVED = 'archived',
}

/** Priority levels */
export enum Priority {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  CRITICAL = 'critical',
}

/** Create idea from text */
export interface CreateIdeaRequest {
  content: string;
  title?: string;
  priority?: Priority;
}

/** Update idea request */
export interface UpdateIdeaRequest {
  title?: string;
  content?: string;
  status?: Exclude<IdeaStatus, 'promoted'>;
  priority?: Priority;
}

/** Promote idea to instruction request */
export interface PromoteIdeaRequest {
  scope?: InstructionScope;
  targetFilePath?: string;
  executionMode?: ExecutionMode;
}

/** Promote idea response */
export interface PromoteIdeaResponse {
  idea: Idea;
  instruction: Instruction;
}

/** List ideas query parameters */
export interface ListIdeasParams {
  status?: IdeaStatus;
  priority?: Priority;
}

// =============================================================================
// AI Types
// =============================================================================

/** AI model entity */
export interface AIModel {
  id: string;
  displayName: string;
  fileName: string;
  category: ModelCategory;
  modelPath: string;
  fileSizeBytes: number;
  isEnabled: boolean;
  lastScannedAt: string;
}
/** Model category for different AI tasks */
export enum ModelCategory {
  THINKING = 'thinking',
  WRITING = 'writing',
  VOICE = 'voice',
  CODING = 'coding',
}

/** Model overrides for instruction generation */
export interface ModelOverrides {
  thinkingModelId?: string;
  writingModelId?: string;
  voiceModelId?: string;
  codingModelId?: string;
}

/** List models query parameters */
export interface ListModelsParams {
  category?: ModelCategory;
}

/** Model scan result */
export interface ModelScanResult {
  discovered: number;
  added: number;
  updated: number;
}

/** AI content generation request */
export interface GenerateRequest {
  prompt: string;
  systemPrompt?: string;
  category?: Exclude<ModelCategory, 'voice'>;
  modelId?: string;
  maxTokens?: number;
  temperature?: number;
  projectId?: string;
}

/** AI generation response */
export interface GenerateResponse {
  text: string;
  tokensUsed: number;
  durationMs: number;
  modelUsed: string;
}

/** Audio transcription result */
export interface TranscriptionResult {
  text: string;
  confidence: number;
  durationMs: number;
  languageCode: string;
}

// =============================================================================
// RAG Types
// =============================================================================

/** RAG context query request */
export interface RAGQueryRequest {
  query: string;
  topK?: number;
  includeRecent?: boolean;
  recentCount?: number;
  tokenBudget?: number;
}

/** RAG query response */
export interface RAGQueryResponse {
  chunks: RAGChunk[];
  recentArtifacts: RecentArtifact[];
  totalTokens: number;
  assembledContext: string;
}

/** RAG chunk from vector search */
export interface RAGChunk {
  id: string;
  filePath: string;
  content: string;
  similarity: number;
  tokenCount: number;
}

/** Recently modified artifact */
export interface RecentArtifact {
  id: string;
  filePath: string;
  title: string;
  updatedAt: string;
}

/** RAG reindex request */
export interface ReindexRequest {
  paths?: string[];
}

/** Reindex job response */
export interface ReindexJobResponse {
  jobId: string;
  estimatedFiles: number;
}

/** RAG index status */
export interface RAGIndexStatus {
  totalFiles: number;
  indexedFiles: number;
  totalChunks: number;
  lastIndexedAt: string;
}

// =============================================================================
// History Types
// =============================================================================

/** Snapshot entity */
export interface Snapshot {
  id: string;
  projectId: string;
  label: string;
  message: string;
  fileCount: number;
  gitCommitHash: string;
  createdById: string;
  createdAt: string;
}

/** Snapshot with file details */
export interface SnapshotDetails extends Snapshot {
  files: SnapshotFile[];
}

/** File in snapshot */
export interface SnapshotFile {
  path: string;
  contentHash: string;
  sizeBytes: number;
}

/** Create snapshot request */
export interface CreateSnapshotRequest {
  message?: string;
}

/** List snapshots query parameters */
export interface ListSnapshotsParams {
  fileId?: string;
  limit?: number;
}

/** Restore snapshot request */
export interface RestoreSnapshotRequest {
  files?: string[];
}

/** Diff query parameters */
export interface GetDiffParams {
  from: string;
  to: string;
}

/** Diff result */
export interface DiffResult {
  fromVersion: string;
  toVersion: string;
  hunks: DiffHunk[];
  additions: number;
  deletions: number;
}

/** Diff hunk */
export interface DiffHunk {
  oldStart: number;
  oldLines: number;
  newStart: number;
  newLines: number;
  lines: string[];
}

// =============================================================================
// Export Types
// =============================================================================

/** Export options */
export interface ExportOptions {
  includeHistory?: boolean;
  includeIdeas?: boolean;
  includeInstructions?: boolean;
  selectedFiles?: string[];
}
/** Export job status */
export enum ExportStatus {
  PROCESSING = 'processing',
  COMPLETED = 'completed',
  FAILED = 'failed',
}

/** Export job response */
export interface ExportJobResponse {
  exportId: string;
  status: ExportStatus;
  estimatedSize?: number;
  estimatedFiles?: number;
}

/** Export status response */
export interface ExportStatusResponse {
  exportId: string;
  status: ExportStatus;
  progress: number;
  downloadUrl?: string;
  expiresAt?: string;
}

// =============================================================================
// Import Types
// =============================================================================
/** Import file types */
export enum ImportFileType {
  ZIP = 'zip',
  MARKDOWN = 'markdown',
  PRD = 'prd',
}

/** Import job status */
export enum ImportStatus {
  PROCESSING = 'processing',
  COMPLETED = 'completed',
  FAILED = 'failed',
}

/** Import options */
export interface ImportOptions {
  projectName?: string;
  visibility?: ProjectVisibility;
}

/** Import job response */
export interface ImportJobResponse {
  importId: string;
  status: string;
  detectedType: ImportFileType;
}

/** Import status response */
export interface ImportStatusResponse {
  importId: string;
  status: ImportStatus;
  progress: number;
  projectId?: string;
  errors?: string[];
}

// =============================================================================
// Config Types
// =============================================================================

/** LLaMA server configuration */
export interface LlamaConfig {
  serverPath: string;
  host: string;
  port: number;
  modelsDir: string;
  contextSize: number;
  gpuLayers: number;
}

/** LLaMA config update request */
export interface LlamaConfigUpdate {
  serverPath?: string;
  host?: string;
  port?: number;
  modelsDir?: string;
  contextSize?: number;
  gpuLayers?: number;
}
/** User theme preference */
export enum ThemePreference {
  LIGHT = 'light',
  DARK = 'dark',
  SYSTEM = 'system',
}

/** User preferences */
export interface UserPreferences {
  theme: ThemePreference;
  defaultThinkingModelId?: string;
  defaultWritingModelId?: string;
  defaultVoiceModelId?: string;
  defaultCodingModelId?: string;
  instructionExecutionMode: ExecutionMode;
}

/** User preferences update */
export interface UserPreferencesUpdate {
  theme?: ThemePreference;
  defaultThinkingModelId?: string;
  defaultWritingModelId?: string;
  defaultVoiceModelId?: string;
  defaultCodingModelId?: string;
  instructionExecutionMode?: ExecutionMode;
}

// =============================================================================
// API Endpoint Types
// =============================================================================

/** API endpoint paths organized by domain */
export const API_PATHS = {
  // Auth
  AUTH_REGISTER: '/auth/register',
  AUTH_LOGIN: '/auth/login',
  AUTH_REFRESH: '/auth/refresh',
  AUTH_LOGOUT: '/auth/logout',
  AUTH_SESSIONS: '/auth/sessions',
  AUTH_SESSION: (sessionId: string) => `/auth/sessions/${sessionId}`,

  // Projects
  PROJECTS: '/projects',
  PROJECT: (projectId: string) => `/projects/${projectId}`,

  // Files
  PROJECT_FILES: (projectId: string) => `/projects/${projectId}/files`,
  PROJECT_FILE: (projectId: string, fileId: string) => `/projects/${projectId}/files/${fileId}`,
  PROJECT_FILE_MOVE: (projectId: string, fileId: string) => `/projects/${projectId}/files/${fileId}/move`,
  PROJECT_FILE_DIFF: (projectId: string, fileId: string) => `/projects/${projectId}/files/${fileId}/diff`,
  PROJECT_DIRECTORIES: (projectId: string) => `/projects/${projectId}/directories`,

  // Instructions
  PROJECT_INSTRUCTIONS: (projectId: string) => `/projects/${projectId}/instructions`,
  PROJECT_INSTRUCTION_VOICE: (projectId: string) => `/projects/${projectId}/instructions/voice`,
  PROJECT_INSTRUCTION: (projectId: string, instructionId: string) => `/projects/${projectId}/instructions/${instructionId}`,
  PROJECT_INSTRUCTION_APPROVE: (projectId: string, instructionId: string) => `/projects/${projectId}/instructions/${instructionId}/approve`,
  PROJECT_INSTRUCTION_CANCEL: (projectId: string, instructionId: string) => `/projects/${projectId}/instructions/${instructionId}/cancel`,
  PROJECT_INSTRUCTION_TASKS: (projectId: string, instructionId: string) => `/projects/${projectId}/instructions/${instructionId}/tasks`,

  // Ideas
  PROJECT_IDEAS: (projectId: string) => `/projects/${projectId}/ideas`,
  PROJECT_IDEA_VOICE: (projectId: string) => `/projects/${projectId}/ideas/voice`,
  PROJECT_IDEA: (projectId: string, ideaId: string) => `/projects/${projectId}/ideas/${ideaId}`,
  PROJECT_IDEA_PROMOTE: (projectId: string, ideaId: string) => `/projects/${projectId}/ideas/${ideaId}/promote`,

  // AI
  AI_MODELS: '/ai/models',
  AI_MODELS_SCAN: '/ai/models/scan',
  AI_GENERATE: '/ai/generate',
  AI_GENERATE_STREAM: '/ai/generate/stream',
  AI_TRANSCRIBE: '/ai/transcribe',

  // RAG
  PROJECT_RAG_QUERY: (projectId: string) => `/projects/${projectId}/rag/query`,
  PROJECT_RAG_REINDEX: (projectId: string) => `/projects/${projectId}/rag/reindex`,
  PROJECT_RAG_STATUS: (projectId: string) => `/projects/${projectId}/rag/status`,

  // History
  PROJECT_SNAPSHOTS: (projectId: string) => `/projects/${projectId}/snapshots`,
  PROJECT_SNAPSHOT: (projectId: string, snapshotId: string) => `/projects/${projectId}/snapshots/${snapshotId}`,
  PROJECT_SNAPSHOT_RESTORE: (projectId: string, snapshotId: string) => `/projects/${projectId}/snapshots/${snapshotId}/restore`,

  // Export
  PROJECT_EXPORT: (projectId: string) => `/projects/${projectId}/export`,
  EXPORT_STATUS: (exportId: string) => `/exports/${exportId}/status`,
  EXPORT_DOWNLOAD: (exportId: string) => `/exports/${exportId}/download`,

  // Import
  IMPORT: '/import',
  IMPORT_STATUS: (importId: string) => `/import/${importId}/status`,

  // Config
  CONFIG_LLAMA: '/config/llama',
  CONFIG_USER_PREFERENCES: '/config/user/preferences',
} as const;

// =============================================================================
// React Query Key Factory
// =============================================================================

/** Query key factory for React Query caching */
export const queryKeys = {
  // Auth
  auth: {
    all: ['auth'] as const,
    sessions: () => [...queryKeys.auth.all, 'sessions'] as const,
  },

  // Projects
  projects: {
    all: ['projects'] as const,
    lists: () => [...queryKeys.projects.all, 'list'] as const,
    list: (params: ListProjectsParams) => [...queryKeys.projects.lists(), params] as const,
    details: () => [...queryKeys.projects.all, 'detail'] as const,
    detail: (id: string) => [...queryKeys.projects.details(), id] as const,
  },

  // Files
  files: {
    all: (projectId: string) => ['projects', projectId, 'files'] as const,
    lists: (projectId: string) => [...queryKeys.files.all(projectId), 'list'] as const,
    list: (projectId: string, params: ListFilesParams) => [...queryKeys.files.lists(projectId), params] as const,
    details: (projectId: string) => [...queryKeys.files.all(projectId), 'detail'] as const,
    detail: (projectId: string, fileId: string) => [...queryKeys.files.details(projectId), fileId] as const,
  },

  // Instructions
  instructions: {
    all: (projectId: string) => ['projects', projectId, 'instructions'] as const,
    lists: (projectId: string) => [...queryKeys.instructions.all(projectId), 'list'] as const,
    list: (projectId: string, params: ListInstructionsParams) => [...queryKeys.instructions.lists(projectId), params] as const,
    details: (projectId: string) => [...queryKeys.instructions.all(projectId), 'detail'] as const,
    detail: (projectId: string, id: string) => [...queryKeys.instructions.details(projectId), id] as const,
    tasks: (projectId: string, id: string) => [...queryKeys.instructions.detail(projectId, id), 'tasks'] as const,
  },

  // Ideas
  ideas: {
    all: (projectId: string) => ['projects', projectId, 'ideas'] as const,
    lists: (projectId: string) => [...queryKeys.ideas.all(projectId), 'list'] as const,
    list: (projectId: string, params: ListIdeasParams) => [...queryKeys.ideas.lists(projectId), params] as const,
    details: (projectId: string) => [...queryKeys.ideas.all(projectId), 'detail'] as const,
    detail: (projectId: string, id: string) => [...queryKeys.ideas.details(projectId), id] as const,
  },

  // AI
  ai: {
    all: ['ai'] as const,
    models: () => [...queryKeys.ai.all, 'models'] as const,
    modelsByCategory: (category: ModelCategory) => [...queryKeys.ai.models(), category] as const,
  },

  // RAG
  rag: {
    all: (projectId: string) => ['projects', projectId, 'rag'] as const,
    status: (projectId: string) => [...queryKeys.rag.all(projectId), 'status'] as const,
  },

  // Snapshots
  snapshots: {
    all: (projectId: string) => ['projects', projectId, 'snapshots'] as const,
    lists: (projectId: string) => [...queryKeys.snapshots.all(projectId), 'list'] as const,
    list: (projectId: string, params: ListSnapshotsParams) => [...queryKeys.snapshots.lists(projectId), params] as const,
    details: (projectId: string) => [...queryKeys.snapshots.all(projectId), 'detail'] as const,
    detail: (projectId: string, id: string) => [...queryKeys.snapshots.details(projectId), id] as const,
  },

  // Config
  config: {
    all: ['config'] as const,
    llama: () => [...queryKeys.config.all, 'llama'] as const,
    userPreferences: () => [...queryKeys.config.all, 'userPreferences'] as const,
  },
} as const;

// =============================================================================
// Type Guards
// =============================================================================

/** Check if response indicates success */
export function isSuccessResponse<T>(response: ApiResponse<T>): response is ApiResponse<T> & { data: T } {
  return response.success && response.data !== null;
}

/** Check if response is a conflict error */
export function isConflictResponse(response: unknown): response is ConflictResponse {
  return (
    typeof response === 'object' &&
    response !== null &&
    'success' in response &&
    response.success === false &&
    'data' in response &&
    typeof response.data === 'object' &&
    response.data !== null &&
    'currentHash' in response.data
  );
}

/** Check if instruction is in editable state */
export function isEditableInstruction(status: InstructionStatus): boolean {
  const editableStatuses: InstructionStatus[] = [
    InstructionStatus.TRANSCRIBED,
    InstructionStatus.PROOFREAD,
    InstructionStatus.PLANNED,
    InstructionStatus.REVIEWING,
  ];
  return editableStatuses.includes(status);
}

/** Check if instruction is in terminal state */
export function isTerminalInstruction(status: InstructionStatus): boolean {
  const terminalStatuses: InstructionStatus[] = [
    InstructionStatus.COMPLETED,
    InstructionStatus.FAILED,
    InstructionStatus.CANCELLED,
  ];
  return terminalStatuses.includes(status);
}

/** Check if task is actionable */
export function isActionableTask(status: TaskStatus): boolean {
  return status === TaskStatus.READY || status === TaskStatus.IN_PROGRESS;
}
