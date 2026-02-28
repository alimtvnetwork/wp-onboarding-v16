package site

import (
	"context"
	"encoding/json"
	"fmt"
	"os"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
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
func (s *Service) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*BootstrapResult, error) {
	bctx, err := s.initBootstrapContext(ctx, id, uploaderPath)
	if err != nil {
		return nil, err
	}

	return s.bootstrapUploadAndFinalize(id, bctx, uploaderPath)
}

// bootstrapUploadAndFinalize creates the ZIP, uploads, and returns the result.
func (s *Service) bootstrapUploadAndFinalize(id int64, bctx *bootstrapContext, uploaderPath string) (*BootstrapResult, error) {
	zipPath, err := s.prepareBootstrapZip(id, uploaderPath)
	if err != nil {
		return nil, err
	}
	defer os.Remove(zipPath)

	uploadResult, err := s.executeBootstrapUpload(id, bctx.Client, zipPath)
	if err != nil {
		return nil, err
	}

	return s.finalizeBootstrap(id, bctx.Site, uploadResult)
}

// initBootstrapContext loads the site, decrypts credentials, and creates a WordPress client.
func (s *Service) initBootstrapContext(ctx context.Context, id int64, uploaderPath string) (*bootstrapContext, error) {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	s.logBootstrapStart(id, site)

	return s.buildBootstrapClient(id, site)
}

// buildBootstrapClient decrypts credentials and creates a WordPress client.
func (s *Service) buildBootstrapClient(id int64, site models.Site) (*bootstrapContext, error) {
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
	contextDetails := toJson(SiteContextDetails{
		SiteId:   id,
		SiteName: site.Name,
		SiteUrl:  site.Url,
	})
	s.broadcastBootstrapLog(bootstrapLogInput{
		Level:   loglevel.Info,
		SiteID:  id,
		Message: "Starting Riseup Asia Uploader deployment",
		Details: contextDetails,
	})
}

// buildProgressCallback creates a progress callback for WordPress client operations.
func (s *Service) buildProgressCallback(id int64, siteName string) func(string, string, string, wordpress.ProgressDetails) {
	return func(step, status, message string, details wordpress.ProgressDetails) {
		logDetails := toJson(BootstrapLogDetails{
			SiteId:   id,
			SiteName: siteName,
			Step:     step,
			Status:   status,
			Details:  details,
		})
		s.broadcastBootstrapLog(bootstrapLogInput{
			Level:   loglevel.Info,
			SiteID:  id,
			Message: fmt.Sprintf("[%s] %s", step, message),
			Details: logDetails,
		})
	}
}

// prepareBootstrapZip creates the uploader ZIP archive.
func (s *Service) prepareBootstrapZip(id int64, uploaderPath string) (string, error) {
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
	s.broadcastBootstrapLog(bootstrapLogInput{
		Level:   loglevel.Info,
		SiteID:  id,
		Message: "Creating plugin ZIP archive",
		Details: toJson(ZipCreationDetails{SiteId: id, Path: uploaderPath}),
	})
}

// logBootstrapError broadcasts a bootstrap error log entry.
func (s *Service) logBootstrapError(id int64, message string) {
	s.broadcastBootstrapLog(bootstrapLogInput{
		Level:   loglevel.Error,
		SiteID:  id,
		Message: message,
		Details: toJson(SiteIdDetail{SiteId: id}),
	})
}

// logBootstrapInfo broadcasts a bootstrap info log entry.
func (s *Service) logBootstrapInfo(id int64, message string) {
	s.broadcastBootstrapLog(bootstrapLogInput{
		Level:   loglevel.Info,
		SiteID:  id,
		Message: message,
		Details: toJson(SiteIdDetail{SiteId: id}),
	})
}

// executeBootstrapUpload uploads the uploader plugin to the remote site.
func (s *Service) executeBootstrapUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, error) {
	availability, _ := client.CheckRiseupAsiaAvailable()
	isUploaderReady :=
		availability.IsAvailable() &&
		availability.HasNamespace()

	if isUploaderReady {
		input := bootstrapUploaderInput{
			SiteID:    id,
			Client:    client,
			ZipPath:   zipPath,
			Namespace: availability.Namespace,
		}

		return s.bootstrapViaUploader(input)
	}

	return s.bootstrapViaOnboard(id, client, zipPath)
}

// bootstrapUploaderInput bundles parameters for bootstrapViaUploader.
type bootstrapUploaderInput struct {
	SiteID    int64
	Client    *wordpress.Client
	ZipPath   string
	Namespace string
}

// bootstrapViaUploader updates via an existing Riseup Asia Uploader.
func (s *Service) bootstrapViaUploader(input bootstrapUploaderInput) (*wordpress.UploaderUploadResult, error) {
	s.logBootstrapInfo(input.SiteID, fmt.Sprintf("Riseup Asia Uploader found (%s), updating...", input.Namespace))

	uploadInput := wordpress.UploadInput{
		ZipPath:      input.ZipPath,
		Slug:         "riseup-asia-uploader",
		IsActivate:   true,
		UploadSource: uploadsource.RestAPI,
	}

	return s.executeUploaderUpload(input.SiteID, input.Client, uploadInput)
}

// executeUploaderUpload performs the upload and wraps errors.
func (s *Service) executeUploaderUpload(siteID int64, client *wordpress.Client, input wordpress.UploadInput) (*wordpress.UploaderUploadResult, error) {
	result, err := client.UploadPluginViaUploader(input)
	if err != nil {
		s.logBootstrapError(siteID, fmt.Sprintf("Upload failed: %v", err))

		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	return result, nil
}

// bootstrapViaOnboard installs via the Onboard plugin for first-time setup.
func (s *Service) bootstrapViaOnboard(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, error) {
	s.logBootstrapInfo(id, "First-time installation - checking for Onboard plugin")

	isOnboardUnavailable := !s.checkOnboardAvailable(client)

	if isOnboardUnavailable {
		s.logBootstrapError(id, "No upload helper plugin found.")

		return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload helper plugin available on site.")
	}

	return s.uploadViaOnboard(id, client, zipPath)
}

// uploadViaOnboard performs the actual upload via the Onboard plugin.
func (s *Service) uploadViaOnboard(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, error) {
	s.logBootstrapInfo(id, "Using Onboard plugin for installation")

	result, err := client.UploadPluginViaOnboard(zipPath, true)
	if err != nil {
		s.logBootstrapError(id, fmt.Sprintf("Upload failed: %v", err))

		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	return result, nil
}

// finalizeBootstrap logs success and returns the result.
func (s *Service) finalizeBootstrap(id int64, site models.Site, uploadResult *wordpress.UploaderUploadResult) (*BootstrapResult, error) {
	s.logBootstrapDeploySuccess(id, site, uploadResult.Activated)

	return buildBootstrapResult(id, site, uploadResult), nil
}

// logBootstrapDeploySuccess broadcasts and logs the successful deployment.
func (s *Service) logBootstrapDeploySuccess(id int64, site models.Site, isActivated bool) {
	deployDetails := toJson(UploaderDeployDetails{
		SiteId:      id,
		SiteName:    site.Name,
		IsActivated: isActivated,
	})
	s.broadcastBootstrapLog(bootstrapLogInput{
		Level:   loglevel.Info,
		SiteID:  id,
		Message: "Riseup Asia Uploader deployed successfully",
		Details: deployDetails,
	})
	s.log.Info("Successfully bootstrapped Riseup Asia Uploader to site",
		"siteId", id, "siteName", site.Name, "siteUrl", site.Url, "activated", isActivated)
}

// buildBootstrapResult constructs the final BootstrapResult.
func buildBootstrapResult(id int64, site models.Site, uploadResult *wordpress.UploaderUploadResult) *BootstrapResult {
	return &BootstrapResult{
		IsSuccess:   true,
		SiteId:      id,
		SiteName:    site.Name,
		Message:     "Riseup Asia Uploader deployed successfully",
		IsActivated: uploadResult.Activated,
	}
}

// bootstrapLogInput bundles parameters for broadcastBootstrapLog.
type bootstrapLogInput struct {
	Level   loglevel.Variant
	SiteID  int64
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
