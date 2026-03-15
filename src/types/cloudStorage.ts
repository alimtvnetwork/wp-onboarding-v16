// Cloud Storage types — matches Go backend CloudStorageTypes.go

export type CloudStorageProvider = 'GitHub' | 'GitLab' | 'GoogleDrive';

// ── Phase 5A: Repo selection mode ───────────────────────────────
export type RepoSelectionMode = 'create' | 'existing';

export interface CloudStorageRepository {
  Name: string;
  FullName: string;
  IsPrivate: boolean;
  DefaultBranch: string;
  UpdatedAt: string;
}

export interface CloudStorageBranch {
  Name: string;
  IsDefault: boolean;
  LastCommitSha: string;
  LastCommitDate: string;
}

// ── Phase 5B: Backup strategy types ─────────────────────────────
export type BackupStrategyType = 'full_only' | 'full_and_incremental';
export type BackupScheduleType = 'hourly' | 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'manual';
export type CloudStorageBackupType = 'full' | 'incremental';
export type CloudStorageBackupStatus = 'pending' | 'uploading' | 'success' | 'failed';

export interface CloudStorageBackupHistoryRecord {
  Id: number;
  AccountId: number;
  BackupType: CloudStorageBackupType;
  FileName: string;
  RemotePath: string;
  RemoteUrl: string;
  CommitSha: string;
  BranchName: string;
  BaseFullBackupId: number | null;
  FileSizeBytes: number;
  TablesChanged: string;
  RowsChanged: number;
  Duration: number;
  Status: CloudStorageBackupStatus;
  ErrorMessage: string;
  CreatedAt: string;
}

export interface CloudStorageBackupHistoryListResponse {
  BackupHistory: CloudStorageBackupHistoryRecord[];
  Total: number;
  Page: number;
  PerPage: number;
}

// ── Core account types ──────────────────────────────────────────

export interface CloudStorageAccount {
  Id: number;
  Provider: CloudStorageProvider;
  AccountLabel: string;
  Username: string;
  Email: string;
  TokenMask: string;
  BaseUrl: string;
  RepoName: string;
  RepoOwner: string;
  RepoSelectionMode: RepoSelectionMode;
  DefaultBranch: string;
  FolderId: string;
  FolderName: string;
  IsActive: boolean;
  LastUsedAt: string;
  LastError: string;
  CreatedAt: string;
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
  IsEnabled: boolean;
  AutoBackupEnabled: boolean;
  DefaultAccountId: number | null;
  RetentionCount: number;
  RotationEnabled: boolean;
  BackupPrefix: string;
  BackupType: BackupStrategyType;
  FullBackupSchedule: BackupScheduleType;
  IncrementalBackupSchedule: BackupScheduleType;
  FullBackupDayOfWeek: number;
  FullBackupTimeUtc: string;
  IncrementalBackupTimeUtc: string;
}

export interface CloudStorageTestResult {
  Success: boolean;
  ConnectionStatus?: string;
  Username?: string;
  Message?: string;
  Error?: string;
}

export interface CloudStorageFileInfo {
  Name: string;
  Path: string;
  Size: number;
  CreatedAt?: string;
  RemoteUrl?: string;
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
