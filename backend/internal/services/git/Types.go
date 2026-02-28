// Package git — type definitions for git and build results.
package git

import "time"

// PullResult represents the outcome of a git pull operation
type PullResult struct {
	PluginId     int64
	PluginName   string
	IsSuccess    bool
	Branch       string
	CommitHash   string `json:",omitempty"`
	CommitMsg    string `json:",omitempty"`
	FilesChanged int
	Insertions   int
	Deletions    int
	Duration     int64
	Output       string    `json:",omitempty"`
	Error        string    `json:",omitempty"`
	PulledAt     time.Time
}

// BuildResult represents the outcome of a build command
type BuildResult struct {
	PluginId   int64
	PluginName string
	IsSuccess  bool
	Command    string
	ExitCode   int
	Output     string
	Error      string    `json:",omitempty"`
	Duration   int64
	BuiltAt    time.Time
}

// BatchPullResult holds results for multiple plugins
type BatchPullResult struct {
	Results   []PullResult
	Succeeded int
	Failed    int
	Duration  int64
}

// PullAndBuildResult holds the combined outcome of a pull followed by an optional build
type PullAndBuildResult struct {
	Pull  PullResult
	Build *BuildResult `json:",omitempty"`
}

// StatusResult represents git status information
type StatusResult struct {
	PluginId   int64
	Branch     string
	Ahead      int
	Behind     int
	Staged     int
	Modified   int
	Untracked  int
	HasChanges bool
	LastCommit string `json:",omitempty"`
}

// CommitResult represents git commit result
type CommitResult struct {
	PluginId   int64
	IsSuccess  bool
	CommitHash string
	Message    string `json:",omitempty"`
}

// PushResult represents git push result
type PushResult struct {
	PluginId  int64
	IsSuccess bool
	Pushed    int
	Message   string `json:",omitempty"`
}

// PluginGitConfig holds git configuration for a plugin
type PluginGitConfig struct {
	PluginId     int64
	GitEnabled   bool
	Branch       string
	GitRemoteUrl string
	BuildEnabled bool
	BuildCommand string
}
