package site

import (
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// executeCrossUpload uses cross-upload strategy: always prefer QUpload endpoint for Riseup Asia.
// This prevents the self-update 500 error (a plugin can't replace itself while running).
func (s *Service) executeCrossUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapEndpointCheck(id, "QUpload", "Checking cross-upload endpoint (preferred)")

	qAvailResult := client.CheckQUploadAvailable()
	isQUploadReady :=
		!qAvailResult.HasError() &&
			qAvailResult.Value().IsAvailable() &&
			qAvailResult.Value().HasNamespace()

	if isQUploadReady {
		s.logBootstrapEndpointCheck(id, "QUpload", fmt.Sprintf("Cross-upload endpoint ready (%s)", qAvailResult.Value().Namespace))

		return s.uploadViaQUpload(id, client, zipPath)
	}

	if qAvailResult.HasError() {
		s.logBootstrapWarn(id, fmt.Sprintf("QUpload endpoint unavailable: %v", qAvailResult.AppError()))
	} else {
		s.logBootstrapWarn(id, "QUpload not installed — falling back to self-upload (Riseup Asia endpoint)")
	}

	return s.bootstrapViaSelfUpload(id, client, zipPath)
}

// bootstrapViaSelfUpload falls back to uploading via Riseup Asia's own endpoint (last resort).
func (s *Service) bootstrapViaSelfUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapEndpointCheck(id, "Riseup Asia Uploader", "Checking self-upload endpoint (fallback)")

	availResult := client.CheckRiseupAsiaAvailable()
	isReady :=
		!availResult.HasError() &&
			availResult.Value().IsAvailable() &&
			availResult.Value().HasNamespace()

	if !isReady {
		s.logBootstrapError(id, "No upload endpoint available. Checked QUpload and Riseup Asia endpoints.")

		return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload endpoint available on site")
	}

	s.logBootstrapWarn(id, fmt.Sprintf("Using self-upload via %s (risk of 500 error)", availResult.Value().Namespace))

	return s.executeUploaderUpload(id, client, wordpress.UploadInput{
		ZipPath:      zipPath,
		Slug:         "riseup-asia-uploader",
		IsActivate:   true,
		UploadSource: uploadsource.RestApi,
	})
}

// uploadViaQUpload performs the actual upload via the QUpload plugin (cross-upload).
func (s *Service) uploadViaQUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	s.logBootstrapInfo(id, "Uploading Riseup Asia via QUpload endpoint (cross-upload)")

	result := client.UploadPluginViaQUpload(wordpress.UploadInput{
		ZipPath:      zipPath,
		Slug:         "riseup-asia-uploader",
		IsActivate:   true,
		UploadSource: uploadsource.RestApi,
	})
	if result.HasError() {
		s.logBootstrapError(id, fmt.Sprintf("Cross-upload failed: %v", result.AppError()))

		return nil, apperror.Wrap(result.AppError(), apperror.ErrWPUploadFailed, "cross-upload via QUpload failed")
	}

	return result.Value(), nil
}

// executeUploaderUpload performs the upload and wraps errors.
func (s *Service) executeUploaderUpload(siteId int64, client *wordpress.Client, input wordpress.UploadInput) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	result := client.UploadPluginViaUploader(input)
	if result.HasError() {
		s.logBootstrapError(siteId, fmt.Sprintf("Upload failed: %v", result.AppError()))

		return nil, apperror.Wrap(result.AppError(), apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	return result.Value(), nil
}

// logBootstrapWarn broadcasts a bootstrap warning log entry.
func (s *Service) logBootstrapWarn(id int64, message string) {
	warnLog := bootstrapLogInput{
		Level:   loglevel.Warn,
		SiteId:  id,
		Message: message,
		Details: toJson(SiteIdDetail{SiteId: id}),
	}
	s.broadcastBootstrapLog(warnLog)
}

// logBootstrapEndpointCheck broadcasts explicit endpoint-check logs for deployment diagnostics.
func (s *Service) logBootstrapEndpointCheck(id int64, helperName, message string) {
	checkLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: fmt.Sprintf("[%s] %s", helperName, message),
		Details: toJson(EndpointCheckDetail{SiteId: id, Helper: helperName}),
	}
	s.broadcastBootstrapLog(checkLog)
}

// EndpointCheckDetail is a typed struct for endpoint check log details.
type EndpointCheckDetail struct {
	SiteId int64
	Helper string
}

// finalizeBootstrap logs success and returns the result.
func (s *Service) finalizeBootstrap(id int64, site models.Site, uploadResult *wordpress.UploaderUploadResult) *BootstrapResult {
	s.logBootstrapDeploySuccess(id, site, uploadResult.Activated)

	return buildBootstrapResult(id, site, uploadResult)
}

// logBootstrapDeploySuccess broadcasts and logs the successful deployment.
func (s *Service) logBootstrapDeploySuccess(id int64, site models.Site, isActivated bool) {
	uploaderDeploy := UploaderDeployDetails{
		SiteId:      id,
		SiteName:    site.Name,
		IsActivated: isActivated,
	}
	deployLog := bootstrapLogInput{
		Level:   loglevel.Info,
		SiteId:  id,
		Message: "Riseup Asia Uploader deployed successfully",
		Details: toJson(uploaderDeploy),
	}
	s.broadcastBootstrapLog(deployLog)
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
