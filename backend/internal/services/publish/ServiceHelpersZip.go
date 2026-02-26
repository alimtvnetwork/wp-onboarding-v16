package publish

import (
	"fmt"
	"os"
	"path/filepath"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	"wp-plugin-publish/pkg/pathutil"
)

// cleanupZipInput bundles parameters for cleanupZip.
type cleanupZipInput struct {
	PluginId        int64
	SiteId          int64
	ZipPath         string
	IsPublishFailed bool
	IsKeepZipFiles  bool
}

// cleanupZip handles ZIP file cleanup after publish
func (s *Service) cleanupZip(input cleanupZipInput) {
	if input.ZipPath == "" {
		return
	}

	if input.IsPublishFailed {
		keepLog := DetailedLogInput{
			PluginId: input.PluginId,
			SiteId:   input.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  fmt.Sprintf("Keeping temp ZIP for debugging (publish failed): %s", input.ZipPath),
			Details:  toDetails(CleanupDetails{ZipPath: input.ZipPath, Reason: "publish_failed"}),
		}
		s.broadcastDetailedLog(keepLog)

		return
	}

	if input.IsKeepZipFiles {
		keepLog := DetailedLogInput{
			PluginId: input.PluginId,
			SiteId:   input.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  fmt.Sprintf("Keeping temp ZIP (user setting): %s", input.ZipPath),
			Details:  toDetails(CleanupDetails{ZipPath: input.ZipPath, IsKeepZipFiles: true}),
		}
		s.broadcastDetailedLog(keepLog)

		return
	}

	removeLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Debug,
		Step:     publishstep.Cleanup,
		Message:  fmt.Sprintf("Removing temp ZIP: %s", input.ZipPath),
		Details:  toDetails(CleanupDetails{IsKeepZipFiles: input.IsKeepZipFiles}),
	}
	s.broadcastDetailedLog(removeLog)
	os.Remove(input.ZipPath)
}

// logZipInput bundles parameters for logZipCreated.
type logZipInput struct {
	PluginId  int64
	SiteId    int64
	ZipPath   string
	FileCount int
}

// logZipCreated logs the ZIP file creation details
func (s *Service) logZipCreated(input logZipInput) {
	fi, statErr := pathutil.StatFile(input.ZipPath)
	if statErr != nil {
		return
	}

	zipEntries := s.getZipStructure(input.ZipPath)

	zipLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(input.ZipPath), fi.Info.Size()),
		Details: toDetails(ZipCreatedDetails{
			ZipPath:      input.ZipPath,
			ZipSize:      fi.Info.Size(),
			FileCount:    input.FileCount,
			ZipStructure: zipEntries,
		}),
	}
	s.broadcastDetailedLog(zipLog)
	s.logZipEntries(input.PluginId, input.SiteId, zipEntries)
}

// logZipEntries logs individual zip entries (up to 20).
func (s *Service) logZipEntries(pluginId, siteId int64, entries []string) {
	maxShow := 20
	if len(entries) < maxShow {
		maxShow = len(entries)
	}
	for i := 0; i < maxShow; i++ {
		entryLog := DetailedLogInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Level:    loglevel.Debug,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("  └─ %s", entries[i]),
		}
		s.broadcastDetailedLog(entryLog)
	}
	if len(entries) > 20 {
		moreLog := DetailedLogInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Level:    loglevel.Debug,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("  ... and %d more files", len(entries)-20),
		}
		s.broadcastDetailedLog(moreLog)
	}
}
