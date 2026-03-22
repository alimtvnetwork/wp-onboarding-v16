// Cloud Storage types — matches Go backend CloudStorageTypes.go
// Keys are camelCase (transformed from Go PascalCase by transformKeys)

export type CloudStorageProvider = 'GitHub' | 'GitLab' | 'GoogleDrive';

// ── Phase 5A: Repo selection mode ───────────────────────────────
export type RepoSelectionMode = 'create' | 'existing';

export interface CloudStorageRepository {
  name: string;
  fullName: string;
  isPrivate: boolean;
  defaultBranch: string;
  updatedAt: string;
}

export interface CloudStorageBranch {
  name: string;
  isDefault: boolean;
  lastCommitSha: string;
  lastCommitDate: string;
}

// ── Phase 5B: Backup strategy types ─────────────────────────────
export type BackupStrategyType = 'full_only' | 'full_and_incremental';
export type BackupScheduleType = 'hourly' | 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'manual';
export type CloudStorageBackupType = 'full' | 'incremental';
export type CloudStorageBackupStatus = 'pending' | 'uploading' | 'success' | 'failed';

export interface CloudStorageBackupHistoryRecord {
  id: number;
  accountId: number;
  backupType: CloudStorageBackupType;
  fileName: string;
  remotePath: string;
  remoteUrl: string;
  commitSha: string;
  branchName: string;
  baseFullBackupId: number | null;
  fileSizeBytes: number;
  tablesChanged: string;
  rowsChanged: number;
  duration: number;
  status: CloudStorageBackupStatus;
  errorMessage: string;
  createdAt: string;
}

export interface CloudStorageBackupHistoryListResponse {
  backupHistory: CloudStorageBackupHistoryRecord[];
  total: number;
  page: number;
  perPage: number;
}

// ── Core account types ──────────────────────────────────────────

export interface CloudStorageAccount {
  id: number;
  provider: CloudStorageProvider;
  accountLabel: string;
  username: string;
  email: string;
  tokenMask: string;
  baseUrl: string;
  repoName: string;
  repoOwner: string;
  repoSelectionMode: RepoSelectionMode;
  defaultBranch: string;
  folderId: string;
  folderName: string;
  isActive: boolean;
  lastUsedAt: string;
  lastError: string;
  createdAt: string;
}

export interface CloudStorageAccountCreateRequest {
  Provider: CloudStorageProvider;
  AccountLabel: string;
  Username?: string;
  Email?: string;
  AccessToken: string;
  RefreshToken?: string;
  BaseUrl?: string;
  RepoName?: string;
  RepoOwner?: string;
  RepoSelectionMode?: RepoSelectionMode;
  DefaultBranch?: string;
  FolderId?: string;
  FolderName?: string;
}

export interface CloudStorageAccountUpdateRequest {
  AccountLabel?: string;
  Username?: string;
  Email?: string;
  AccessToken?: string;
  RefreshToken?: string;
  BaseUrl?: string;
  RepoName?: string;
  RepoOwner?: string;
  RepoSelectionMode?: RepoSelectionMode;
  DefaultBranch?: string;
  FolderId?: string;
  FolderName?: string;
  IsActive?: boolean;
}

export interface CloudStorageSettings {
  isEnabled: boolean;
  autoBackupEnabled: boolean;
  defaultAccountId: number | null;
  retentionCount: number;
  rotationEnabled: boolean;
  backupPrefix: string;
  backupType: BackupStrategyType;
  fullBackupSchedule: BackupScheduleType;
  incrementalBackupSchedule: BackupScheduleType;
  fullBackupDayOfWeek: number;
  fullBackupTimeUtc: string;
  incrementalBackupTimeUtc: string;
}

export interface CloudStorageTestResult {
  success: boolean;
  connectionStatus?: string;
  username?: string;
  message?: string;
  error?: string;
}

export interface CloudStorageFileInfo {
  name: string;
  path: string;
  size: number;
  createdAt?: string;
  remoteUrl?: string;
}

export const PROVIDER_CONFIG: Record<CloudStorageProvider, {
  label: string;
  tokenPrefix: string;
  tokenPlaceholder: string;
  tokenHelp: string;
  supportsBaseUrl: boolean;
  usesRepo: boolean;
  usesFolder: boolean;
  authType: 'pat' | 'oauth';
  fields: { key: string; label: string; placeholder: string; help: string; required: boolean }[];
}> = {
  GitHub: {
    label: 'GitHub',
    tokenPrefix: 'ghp_',
    tokenPlaceholder: 'ghp_xxxxxxxxxxxxxxxxxxxx',
    tokenHelp: 'Generate at github.com → Settings → Developer settings → Personal access tokens',
    supportsBaseUrl: false,
    usesRepo: true,
    usesFolder: false,
    authType: 'pat',
    fields: [
      { key: 'Username', label: 'Username', placeholder: 'octocat', help: 'Your GitHub username', required: false },
      { key: 'RepoOwner', label: 'Repository Owner', placeholder: 'octocat', help: 'Owner of the backup repo (user or org)', required: false },
    ],
  },
  GitLab: {
    label: 'GitLab',
    tokenPrefix: 'glpat-',
    tokenPlaceholder: 'glpat-xxxxxxxxxxxxxxxxxxxx',
    tokenHelp: 'Generate at gitlab.com → Edit Profile → Access Tokens (scope: api)',
    supportsBaseUrl: true,
    usesRepo: true,
    usesFolder: false,
    authType: 'pat',
    fields: [
      { key: 'Username', label: 'Username', placeholder: 'john.doe', help: 'Your GitLab username', required: false },
      { key: 'BaseUrl', label: 'Base URL', placeholder: 'https://gitlab.com', help: 'Leave blank for gitlab.com, or enter your self-hosted URL', required: false },
      { key: 'RepoOwner', label: 'Namespace', placeholder: 'john.doe', help: 'Your username or group path (e.g., my-org/sub-group)', required: false },
    ],
  },
  GoogleDrive: {
    label: 'Google Drive',
    tokenPrefix: 'ya29.',
    tokenPlaceholder: 'Connected via OAuth',
    tokenHelp: 'Connect via Google OAuth (Phase 3)',
    supportsBaseUrl: false,
    usesRepo: false,
    usesFolder: true,
    authType: 'oauth',
    fields: [
      { key: 'Email', label: 'Google Email', placeholder: 'user@gmail.com', help: 'Google account email', required: false },
      { key: 'FolderName', label: 'Folder Name', placeholder: 'WordPress Backups', help: 'Google Drive folder for backups', required: false },
    ],
  },
};

// ── Backup schedule display helpers ─────────────────────────────

export const BACKUP_STRATEGY_LABELS: Record<BackupStrategyType, string> = {
  full_only: 'Full backups only',
  full_and_incremental: 'Full + Incremental backups',
};

export const BACKUP_SCHEDULE_LABELS: Record<BackupScheduleType, string> = {
  hourly: 'Hourly',
  daily: 'Daily',
  weekly: 'Weekly',
  biweekly: 'Bi-weekly',
  monthly: 'Monthly',
  manual: 'Manual only',
};

export const DAY_OF_WEEK_LABELS = [
  'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
] as const;
