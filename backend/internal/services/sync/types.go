package sync

import "time"

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID      int64        `json:"pluginId"`
	SiteID        int64        `json:"siteId"`
	PluginName    string       `json:"pluginName"`
	SiteName      string       `json:"siteName"`
	Status        string       `json:"status"` // synced, pending, error
	TotalFiles    int          `json:"totalFiles"`
	ChangedFiles  int          `json:"changedFiles"`
	AddedFiles    int          `json:"addedFiles"`
	ModifiedFiles int          `json:"modifiedFiles"`
	DeletedFiles  int          `json:"deletedFiles"`
	Changes       []FileChange `json:"changes"`
	CheckedAt     time.Time    `json:"checkedAt"`
	Error         string       `json:"error,omitempty"`
}

// FileChange represents a detected file difference
type FileChange struct {
	Path        string    `json:"path"`
	ChangeType  string    `json:"type"` // added, modified, deleted
	LocalHash   string    `json:"localHash,omitempty"`
	RemoteHash  string    `json:"remoteHash,omitempty"`
	LocalSize   int64     `json:"localSize,omitempty"`
	RemoteSize  int64     `json:"remoteSize,omitempty"`
	LocalMTime  time.Time `json:"localMTime,omitempty"`
	RemoteMTime time.Time `json:"remoteMTime,omitempty"`
}

// SyncOptions configures sync behavior
type SyncOptions struct {
	IncludeUntracked bool `json:"includeUntracked"`
	ForceFullCheck   bool `json:"forceFullCheck"`
}

// BatchSyncResult holds results for multiple sites
type BatchSyncResult struct {
	PluginID int64        `json:"pluginId"`
	Results  []SyncResult `json:"results"`
	Summary  SyncSummary  `json:"summary"`
}

// SyncSummary aggregates sync status across sites
type SyncSummary struct {
	TotalSites   int `json:"totalSites"`
	SyncedSites  int `json:"syncedSites"`
	PendingSites int `json:"pendingSites"`
	ErrorSites   int `json:"errorSites"`
	TotalChanges int `json:"totalChanges"`
}
