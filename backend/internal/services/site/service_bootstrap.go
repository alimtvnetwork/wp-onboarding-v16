package site

import (
	"archive/zip"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"

	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// BootstrapResult represents the result of bootstrapping the uploader to a site
type BootstrapResult struct {
	IsSuccess   bool
	SiteId      int64
	SiteName    string
	Message     string
	IsActivated bool
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func (s *Service) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*BootstrapResult, error) {
	site, client, err := s.initBootstrapContext(ctx, id, uploaderPath)
	if err != nil {
		return nil, err
	}

	zipPath, err := s.prepareBootstrapZip(id, uploaderPath)
	if err != nil {
		return nil, err
	}
	defer os.Remove(zipPath)

	uploadResult, err := s.executeBootstrapUpload(id, client, zipPath)
	if err != nil {
		return nil, err
	}

	return s.finalizeBootstrap(id, site, uploadResult)
}

// initBootstrapContext loads the site, decrypts credentials, and creates a WordPress client.
func (s *Service) initBootstrapContext(ctx context.Context, id int64, uploaderPath string) (models.Site, *wordpress.Client, error) {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return models.Site{}, nil, result.AppError()
	}
	site := result.Value()

	s.broadcastBootstrapLog(loglevel.Info, id, "Starting Riseup Asia Uploader deployment", toJson(SiteContextDetails{SiteId: id, SiteName: site.Name, SiteUrl: site.Url}))

	decrypted, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return models.Site{}, nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}

	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		s.broadcastBootstrapLog(loglevel.Info, id, fmt.Sprintf("[%s] %s", step, message), toJson(BootstrapLogDetails{SiteId: id, SiteName: site.Name, Step: step, Status: status, Details: details}))
	}
	client := s.wpClientFactory(site.Url, site.Username, string(decrypted), progressCallback)
	return site, client, nil
}

// prepareBootstrapZip creates the uploader ZIP archive.
func (s *Service) prepareBootstrapZip(id int64, uploaderPath string) (string, error) {
	if uploaderPath == "" {
		uploaderPath = "plugins-uploader-helper"
	}

	s.broadcastBootstrapLog(loglevel.Info, id, "Creating plugin ZIP archive", toJson(ZipCreationDetails{SiteId: id, Path: uploaderPath}))

	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		s.broadcastBootstrapLog(loglevel.Error, id, fmt.Sprintf("Failed to create ZIP: %v", err), toJson(SiteIdDetail{SiteId: id}))
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP")
	}
	return zipPath, nil
}

// executeBootstrapUpload uploads the uploader plugin to the remote site.
func (s *Service) executeBootstrapUpload(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, error) {
	available, namespace, _ := client.CheckRiseupAsiaAvailable()
	if available && namespace != "" {
		return s.bootstrapViaUploader(id, client, zipPath, namespace)
	}
	return s.bootstrapViaOnboard(id, client, zipPath)
}

// bootstrapViaUploader updates via an existing Riseup Asia Uploader.
func (s *Service) bootstrapViaUploader(id int64, client *wordpress.Client, zipPath, namespace string) (*wordpress.UploaderUploadResult, error) {
	s.broadcastBootstrapLog(loglevel.Info, id, fmt.Sprintf("Riseup Asia Uploader found (%s), updating...", namespace), toJson(SiteIdDetail{SiteId: id}))
	result, err := client.UploadPluginViaUploader(zipPath, "riseup-asia-uploader", true, uploadsource.RestAPI)
	if err != nil {
		s.broadcastBootstrapLog(loglevel.Error, id, fmt.Sprintf("Upload failed: %v", err), toJson(SiteIdDetail{SiteId: id}))
		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}
	return result, nil
}

// bootstrapViaOnboard installs via the Onboard plugin for first-time setup.
func (s *Service) bootstrapViaOnboard(id int64, client *wordpress.Client, zipPath string) (*wordpress.UploaderUploadResult, error) {
	s.broadcastBootstrapLog(loglevel.Info, id, "First-time installation - checking for Onboard plugin", toJson(SiteIdDetail{SiteId: id}))
	if !s.checkOnboardAvailable(client) {
		s.broadcastBootstrapLog(loglevel.Error, id, "No upload helper plugin found.", toJson(SiteIdDetail{SiteId: id}))
		return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload helper plugin available on site.")
	}

	s.broadcastBootstrapLog(loglevel.Info, id, "Using Onboard plugin for installation", toJson(SiteIdDetail{SiteId: id}))
	result, err := client.UploadPluginViaOnboard(zipPath, true)
	if err != nil {
		s.broadcastBootstrapLog(loglevel.Error, id, fmt.Sprintf("Upload failed: %v", err), toJson(SiteIdDetail{SiteId: id}))
		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}
	return result, nil
}

// finalizeBootstrap logs success and returns the result.
func (s *Service) finalizeBootstrap(id int64, site models.Site, uploadResult *wordpress.UploaderUploadResult) (*BootstrapResult, error) {
	s.broadcastBootstrapLog(loglevel.Info, id, "Riseup Asia Uploader deployed successfully", toJson(UploaderDeployDetails{SiteId: id, SiteName: site.Name, IsActivated: uploadResult.Activated}))
	s.log.Info("Successfully bootstrapped Riseup Asia Uploader to site", "siteId", id, "siteName", site.Name, "siteUrl", site.Url, "activated", uploadResult.Activated)
	return &BootstrapResult{IsSuccess: true, SiteId: id, SiteName: site.Name, Message: "Riseup Asia Uploader deployed successfully", IsActivated: uploadResult.Activated}, nil
}

// broadcastBootstrapLog sends a bootstrap log entry via WebSocket if hub is available.
func (s *Service) broadcastBootstrapLog(level loglevel.Variant, siteId int64, message string, details json.RawMessage) {
	if s.wsHub != nil {
		s.wsHub.BroadcastLog(level.Lower(), message, details)
	}
}

// checkOnboardAvailable checks if the Onboard plugin is available
func (s *Service) checkOnboardAvailable(client *wordpress.Client) bool {
	resp, err := client.CheckOnboardAvailable()
	return err == nil && resp
}

// createUploaderZip creates a ZIP file of the uploader plugin
func (s *Service) createUploaderZip(uploaderPath string) (string, error) {
	absUploaderPath, err := pathutil.ToAbsolute(uploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve uploader path").WithPath(uploaderPath)
	}

	info, err := os.Stat(absUploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSNotFound, "uploader path not found").WithPath(pathutil.ForDisplay(absUploaderPath))
	}
	if !info.IsDir() {
		return "", apperror.New(apperror.ErrFSInvalid, "uploader path is not a directory").WithPath(pathutil.ForDisplay(absUploaderPath))
	}

	tempFile, err := os.CreateTemp("", "riseup-asia-uploader-*.zip")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp file for uploader ZIP")
	}
	tempPath := tempFile.Name()

	zipWriter := zip.NewWriter(tempFile)
	ziputil.RegisterBestCompression(zipWriter)

	baseName := filepath.Base(absUploaderPath)
	err = filepath.Walk(absUploaderPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		relPath, _ := filepath.Rel(absUploaderPath, path)
		if relPath == "." {
			return nil
		}
		if shouldSkipFile(relPath) {
			if info.IsDir() {
				return filepath.SkipDir
			}
			return nil
		}
		if info.IsDir() {
			return nil
		}
		zipPath := baseName + "/" + filepath.ToSlash(relPath)
		writer, err := zipWriter.Create(zipPath)
		if err != nil {
			return err
		}
		file, err := os.Open(path)
		if err != nil {
			return err
		}
		defer file.Close()
		_, err = io.Copy(writer, file)
		return err
	})

	zipWriter.Close()
	tempFile.Close()

	if err != nil {
		os.Remove(tempPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP").WithPath(pathutil.ForDisplay(absUploaderPath))
	}

	return tempPath, nil
}

// shouldSkipFile checks if a file should be skipped when creating the uploader ZIP
func shouldSkipFile(relPath string) bool {
	relPath = filepath.ToSlash(relPath)
	parts := strings.Split(relPath, "/")
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != ".uploadignore" {
			return true
		}
	}
	skipPatterns := []string{"node_modules", "vendor", "tests", "phpunit.xml", "phpunit.xml.dist", "composer.lock"}
	for _, pattern := range skipPatterns {
		if relPath == pattern || strings.HasPrefix(relPath, pattern+"/") {
			return true
		}
	}
	return false
}
