package site

import (
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
)

// bootstrapLogInput bundles parameters for broadcastBootstrapLog.
type bootstrapLogInput struct {
	Level   loglevel.Variant
	SiteId  int64
	Message string
	Details json.RawMessage
}

// broadcastBootstrapLog sends a bootstrap log entry via WebSocket if hub is available.
func (s *Service) broadcastBootstrapLog(input bootstrapLogInput) {
	if s.wsHub != nil {
		s.wsHub.BroadcastLog(input.Level.Lower(), input.Message, input.Details)
	}
}

// logBootstrapStart broadcasts the initial deployment log entry.
func (s *Service) logBootstrapStart(id int64, site models.Site) {
	siteContext := SiteContextDetails{
		SiteId:   id,
		SiteName: site.Name,
		SiteUrl:  site.Url,
	}
	startLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: "Starting Riseup Asia Uploader deployment",
		Details: toJson(siteContext),
	}
	s.broadcastBootstrapLog(startLog)
}

// buildBootstrapProgressCallback creates a progress callback for WordPress client operations.
func (s *Service) buildBootstrapProgressCallback(id int64, siteName string) func(wordpress.ProgressEvent) {
	return func(event wordpress.ProgressEvent) {
		bootstrapDetails := BootstrapLogDetails{
			SiteId:   id,
			SiteName: siteName,
			Step:     event.Step,
			Status:   event.Status,
			Details:  event.Details,
		}
		progressLog := bootstrapLogInput{
			Level:   loglevel.Info,
			SiteId:  id,
			Message: fmt.Sprintf("[%s] %s", event.Step, event.Message),
			Details: toJson(bootstrapDetails),
		}
		s.broadcastBootstrapLog(progressLog)
	}
}

// logBootstrapZipStart broadcasts the ZIP creation log entry.
func (s *Service) logBootstrapZipStart(id int64, uploaderPath string) {
	zipStartLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: "Creating plugin ZIP archive",
		Details: toJson(ZipCreationDetails{SiteId: id, Path: uploaderPath}),
	}
	s.broadcastBootstrapLog(zipStartLog)
}

// logBootstrapError broadcasts a bootstrap error log entry.
func (s *Service) logBootstrapError(id int64, message string) {
	errorLog := bootstrapLogInput{
		Level:   loglevel.Error,
		SiteId:  id,
		Message: message,
		Details: toJson(SiteIdDetail{SiteId: id}),
	}
	s.broadcastBootstrapLog(errorLog)
}

// logBootstrapInfo broadcasts a bootstrap info log entry.
func (s *Service) logBootstrapInfo(id int64, message string) {
	infoLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: message,
		Details: toJson(SiteIdDetail{SiteId: id}),
	}
	s.broadcastBootstrapLog(infoLog)
}
