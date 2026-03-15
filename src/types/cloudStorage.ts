// Cloud Storage types — matches Go backend CloudStorageTypes.go

export type CloudStorageProvider = 'GitHub' | 'GitLab' | 'GoogleDrive';

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
      { key: 'RepoName', label: 'Repository Name', placeholder: 'wp-backups', help: 'Repository to store backups (created if missing)', required: false },
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
      { key: 'RepoName', label: 'Project Name', placeholder: 'wp-backups', help: 'Project (repository) to store backups', required: false },
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
