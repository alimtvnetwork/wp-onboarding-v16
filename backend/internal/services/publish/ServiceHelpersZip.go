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
		s.logCleanupKeep(input, "publish_failed", "Keeping temp ZIP for debugging (publish failed)")

		return
	}

	if input.IsKeepZipFiles {
		s.logCleanupKeep(input, "user_setting", "Keeping temp ZIP (user setting)")

		return
	}

	s.removeZipFile(input)
}

// logCleanupKeep logs that a ZIP is being kept rather than removed.
func (s *Service) logCleanupKeep(input cleanupZipInput, reason, message string) {
	keepLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Cleanup,
		Message:  fmt.Sprintf("%s: %s", message, input.ZipPath),
		Details:  toDetails(CleanupDetails{ZipPath: input.ZipPath, Reason: reason, IsKeepZipFiles: input.IsKeepZipFiles}),
	}
	s.broadcastDetailedLog(keepLog)
}

// removeZipFile logs the removal and deletes the ZIP file.
func (s *Service) removeZipFile(input cleanupZipInput) {
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

	s.broadcastZipCreatedLog(input, fi.Info.Size(), zipEntries)
	s.logZipEntries(input.PluginId, input.SiteId, zipEntries)
}

// broadcastZipCreatedLog sends the ZIP creation log entry.
func (s *Service) broadcastZipCreatedLog(input logZipInput, zipSize int64, zipEntries []string) {
	zipLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(input.ZipPath), zipSize),
		Details: toDetails(ZipCreatedDetails{
			ZipPath:      input.ZipPath,
			ZipSize:      zipSize,
			FileCount:    input.FileCount,
			ZipStructure: zipEntries,
		}),
	}
	s.broadcastDetailedLog(zipLog)
}

// logZipEntries logs individual zip entries (up to 20).
func (s *Service) logZipEntries(pluginId, siteId int64, entries []string) {
	maxShow := resolveMaxZipEntries(len(entries))

	s.logZipEntryLines(pluginId, siteId, entries, maxShow)
	s.logZipEntryOverflow(pluginId, siteId, len(entries))
}

// resolveMaxZipEntries returns the number of entries to show (capped at 20).
func resolveMaxZipEntries(total int) int {
	if total < 20 {
		return total
	}

	return 20
}

// logZipEntryLines logs individual zip entry lines.
func (s *Service) logZipEntryLines(pluginId, siteId int64, entries []string, maxShow int) {
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
}

// logZipEntryOverflow logs the count of remaining entries beyond 20.
func (s *Service) logZipEntryOverflow(pluginId, siteId int64, total int) {
	if total <= 20 {
		return
	}

	moreLog := DetailedLogInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Level:    loglevel.Debug,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("  ... and %d more files", total-20),
	}
	s.broadcastDetailedLog(moreLog)
}
