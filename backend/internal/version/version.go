// Package version provides application version information loaded from version.json
package version

import (
	"encoding/json"
	"os"

	"wp-plugin-publish/pkg/pathutil"
)

// Info holds version information from version.json
type Info struct {
	AppName       string `json:"appName"`       // external key (version.json)
	Version       string `json:"version"`       // external key (version.json)
	ReleaseDate   string `json:"releaseDate"`   // external key (version.json)
	GitCommit     string `json:"gitCommit,omitempty"`     // external key (version.json)
	BuildTime     string `json:"buildTime,omitempty"`     // external key (version.json)
	ScriptVersion string `json:"scriptVersion,omitempty"` // external key (version.json)
}

// Default returns fallback version info if version.json cannot be loaded
func Default() *Info {
	return &Info{
		AppName: "WP Plugin Publish",
		Version: "0.0.0",
	}
}

// Load reads version info from the specified version.json file
func Load(frontendDistDir string) (*Info, error) {
	versionFile, err := pathutil.Join(frontendDistDir, "version.json")
	if err != nil {
		return Default(), nil
	}

	// Try frontend/dist first, then fall back to public/
	if _, err := os.Stat(versionFile); os.IsNotExist(err) {
		// Try current directory public folder
		versionFile = "public/version.json"
		if _, err := os.Stat(versionFile); os.IsNotExist(err) {
			// Try relative to working directory
			versionFile = "frontend/dist/version.json"
		}
	}

	file, err := os.Open(versionFile)
	if err != nil {
		return Default(), nil // Return defaults if file not found
	}
	defer file.Close()

	var info Info
	if err := json.NewDecoder(file).Decode(&info); err != nil {
		return Default(), nil // Return defaults on parse error
	}

	return &info, nil
}

// String returns a formatted version string for logging
func (i *Info) String() string {
	s := i.AppName + " v" + i.Version
	if i.GitCommit != "" {
		// Show short commit hash
		commit := i.GitCommit
		if len(commit) > 7 {
			commit = commit[:7]
		}
		s += " (" + commit + ")"
	}
	return s
}
