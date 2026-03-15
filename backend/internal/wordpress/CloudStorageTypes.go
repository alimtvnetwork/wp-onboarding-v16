// Package wordpress — cloud storage request/response types.
package wordpress

// CloudStorageAccount represents a cloud storage account in API responses.
type CloudStorageAccount struct {
	Id           int    `json:"Id"`
	Provider     string `json:"Provider"`
	AccountLabel string `json:"AccountLabel"`
	Username     string `json:"Username,omitempty"`
	Email        string `json:"Email,omitempty"`
	TokenMask    string `json:"TokenMask"`
	BaseUrl      string `json:"BaseUrl,omitempty"`
	RepoName     string `json:"RepoName,omitempty"`
	RepoOwner    string `json:"RepoOwner,omitempty"`
	FolderId     string `json:"FolderId,omitempty"`
	FolderName   string `json:"FolderName,omitempty"`
	IsActive     bool   `json:"IsActive"`
	LastUsedAt   string `json:"LastUsedAt,omitempty"`
	LastError    string `json:"LastError,omitempty"`
	CreatedAt    string `json:"CreatedAt"`
}

// CloudStorageAccountCreateRequest is the request body for creating an account.
type CloudStorageAccountCreateRequest struct {
	Provider     string `json:"Provider"`
	AccountLabel string `json:"AccountLabel"`
	Username     string `json:"Username,omitempty"`
	Email        string `json:"Email,omitempty"`
	AccessToken  string `json:"AccessToken"`
	RefreshToken string `json:"RefreshToken,omitempty"`
	BaseUrl      string `json:"BaseUrl,omitempty"`
	RepoName     string `json:"RepoName,omitempty"`
	RepoOwner    string `json:"RepoOwner,omitempty"`
	FolderId     string `json:"FolderId,omitempty"`
	FolderName   string `json:"FolderName,omitempty"`
}

// CloudStorageAccountUpdateRequest is the request body for updating an account.
type CloudStorageAccountUpdateRequest struct {
	AccountLabel string `json:"AccountLabel,omitempty"`
	Username     string `json:"Username,omitempty"`
	Email        string `json:"Email,omitempty"`
	AccessToken  string `json:"AccessToken,omitempty"`
	RefreshToken string `json:"RefreshToken,omitempty"`
	BaseUrl      string `json:"BaseUrl,omitempty"`
	RepoName     string `json:"RepoName,omitempty"`
	RepoOwner    string `json:"RepoOwner,omitempty"`
	FolderId     string `json:"FolderId,omitempty"`
	FolderName   string `json:"FolderName,omitempty"`
	IsActive     *bool  `json:"IsActive,omitempty"`
}

// CloudStorageSettings represents per-provider settings.
type CloudStorageSettings struct {
	IsEnabled         bool  `json:"IsEnabled"`
	AutoBackupEnabled bool  `json:"AutoBackupEnabled"`
	DefaultAccountId  *int  `json:"DefaultAccountId"`
	RetentionCount    int   `json:"RetentionCount"`
	RotationEnabled   bool  `json:"RotationEnabled"`
	BackupPrefix      string `json:"BackupPrefix"`
}

// CloudStorageUploadRequest is the request body for uploading a backup.
type CloudStorageUploadRequest struct {
	AccountId  int    `json:"AccountId"`
	FilePath   string `json:"FilePath"`
	RemotePath string `json:"RemotePath"`
}

// CloudStorageUploadResult represents the outcome of a cloud upload.
type CloudStorageUploadResult struct {
	RemotePath string  `json:"RemotePath"`
	RemoteUrl  string  `json:"RemoteUrl"`
	Bytes      int64   `json:"Bytes"`
	Duration   float64 `json:"Duration"`
}

// CloudStorageFileInfo represents a remote file listing entry.
type CloudStorageFileInfo struct {
	Name      string `json:"Name"`
	Path      string `json:"Path"`
	Size      int64  `json:"Size"`
	CreatedAt string `json:"CreatedAt,omitempty"`
	RemoteUrl string `json:"RemoteUrl,omitempty"`
}
