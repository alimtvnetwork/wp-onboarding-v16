package site

import (
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// executeBootstrapUpload uploads the uploader plugin to the remote site.
func (s *Service) executeBootstrapUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	availability, _ := client.CheckRiseupAsiaAvailable()
	isUploaderReady :=
		availability.IsAvailable() &&
			availability.HasNamespace()

	if isUploaderReady {
		input := bootstrapUploaderInput{
			SiteId:    id,
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
	SiteId    int64
	Client    *wordpress.Client
	ZipPath   string
	Namespace string
}

// bootstrapViaUploader updates via an existing Riseup Asia Uploader.
func (s *Service) bootstrapViaUploader(input bootstrapUploaderInput) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapInfo(input.SiteId, fmt.Sprintf("Riseup Asia Uploader found (%s), updating...", input.Namespace))

	uploadInput := wordpress.UploadInput{
		ZipPath:      input.ZipPath,
		Slug:         "riseup-asia-uploader",
		IsActivate:   true,
		UploadSource: uploadsource.RestAPI,
	}

	return s.executeUploaderUpload(input.SiteId, input.Client, uploadInput)
}

// executeUploaderUpload performs the upload and wraps errors.
func (s *Service) executeUploaderUpload(siteID int64, client *wordpress.Client, input wordpress.UploadInput) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	result, err := client.UploadPluginViaUploader(input)
	if err != nil {
		s.logBootstrapError(siteID, fmt.Sprintf("Upload failed: %v", err))

		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	return result, nil
}

// bootstrapViaOnboard installs via the Onboard plugin for first-time setup.
func (s *Service) bootstrapViaOnboard(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapInfo(id, "First-time installation - checking for Onboard plugin")

	isOnboardUnavailable := !s.checkOnboardAvailable(client)

	if isOnboardUnavailable {
		s.logBootstrapError(id, "No upload helper plugin found.")

		return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload helper plugin available on site.")
	}

	return s.uploadViaOnboard(id, client, zipPath)
}

// uploadViaOnboard performs the actual upload via the Onboard plugin.
func (s *Service) uploadViaOnboard(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapInfo(id, "Using Onboard plugin for installation")

	result, err := client.UploadPluginViaOnboard(zipPath, true)
	if err != nil {
		s.logBootstrapError(id, fmt.Sprintf("Upload failed: %v", err))

		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	return result, nil
}

// finalizeBootstrap logs success and returns the result.
func (s *Service) finalizeBootstrap(id int64, site models.Site, uploadResult *wordpress.UploaderUploadResult) *BootstrapResult {
	s.logBootstrapDeploySuccess(id, site, uploadResult.Activated)

	return buildBootstrapResult(id, site, uploadResult)
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
		SiteId:  id,
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
