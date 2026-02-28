package site

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// BootstrapResult represents the result of bootstrapping the uploader to a site
type BootstrapResult struct {
	IsSuccess   bool
	SiteId      int64
	SiteName    string
	Message     string
	IsActivated bool
}

// bootstrapContext holds the site and WordPress client for bootstrap operations.
type bootstrapContext struct {
	Site   models.Site
	Client *wordpress.Client
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func (s *Service) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*BootstrapResult, *apperror.AppError) {
	bctx, err := s.initBootstrapContext(ctx, id, uploaderPath)
	if err != nil {
		return nil, err
	}

	return s.bootstrapUploadAndFinalize(id, bctx, uploaderPath)
}

// bootstrapUploadAndFinalize creates the ZIP, uploads, and returns the result.
func (s *Service) bootstrapUploadAndFinalize(id int64, bctx *bootstrapContext, uploaderPath string) (*BootstrapResult, *apperror.AppError) {
	zipPath, err := s.prepareBootstrapZip(id, uploaderPath)
	if err != nil {
		return nil, err
	}
	defer pathutil.RemoveFileUnchecked(zipPath)

	uploadResult, uploadErr := s.executeBootstrapUpload(id, bctx.Client, zipPath)
	if uploadErr != nil {
		return nil, uploadErr
	}

	return s.finalizeBootstrap(id, bctx.Site, uploadResult), nil
}

// initBootstrapContext loads the site, decrypts credentials, and creates a WordPress client.
func (s *Service) initBootstrapContext(ctx context.Context, id int64, uploaderPath string) (*bootstrapContext, *apperror.AppError) {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	s.logBootstrapStart(id, site)

	return s.buildBootstrapClient(id, site)
}

// buildBootstrapClient decrypts credentials and creates a WordPress client.
func (s *Service) buildBootstrapClient(id int64, site models.Site) (*bootstrapContext, *apperror.AppError) {
	decrypted, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}

	callback := s.buildProgressCallback(id, site.Name)
	client := s.wpClientFactory(site.Url, site.Username, string(decrypted), callback)

	return &bootstrapContext{Site: site, Client: client}, nil
}

// logBootstrapStart broadcasts the initial deployment log entry.
func (s *Service) logBootstrapStart(id int64, site models.Site) {
	siteContext := SiteContextDetails{
		SiteId:   id,
		SiteName: site.Name,
		SiteUrl:  site.Url,
	}
	contextDetails := toJson(siteContext)
	startLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: "Starting Riseup Asia Uploader deployment",
		Details: contextDetails,
	}
	s.broadcastBootstrapLog(startLog)
}

// buildProgressCallback creates a progress callback for WordPress client operations.
func (s *Service) buildProgressCallback(id int64, siteName string) func(string, string, string, wordpress.ProgressDetails) {
	return func(step, status, message string, details wordpress.ProgressDetails) {
		bootstrapDetails := BootstrapLogDetails{
			SiteId:   id,
			SiteName: siteName,
			Step:     step,
			Status:   status,
			Details:  details,
		}
		logDetails := toJson(bootstrapDetails)
		progressLog := bootstrapLogInput{
			Level:   loglevel.Info,
			SiteId:  id,
			Message: fmt.Sprintf("[%s] %s", step, message),
			Details: logDetails,
		}
		s.broadcastBootstrapLog(progressLog)
	}
}

// prepareBootstrapZip creates the uploader ZIP archive.
func (s *Service) prepareBootstrapZip(id int64, uploaderPath string) (string, *apperror.AppError) {
	isUploaderPathEmpty := uploaderPath == ""

	if isUploaderPathEmpty {
		uploaderPath = "plugins-uploader-helper"
	}

	s.logBootstrapZipStart(id, uploaderPath)

	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		s.logBootstrapError(id, fmt.Sprintf("Failed to create ZIP: %v", err))

		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP")
	}

	return zipPath, nil
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

// checkOnboardAvailable checks if the Onboard plugin is available
func (s *Service) checkOnboardAvailable(client *wordpress.Client) bool {
	resp, err := client.CheckOnboardAvailable()
	return err == nil && resp
}
