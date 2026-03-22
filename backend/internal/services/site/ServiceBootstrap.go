package site

import (
	"context"
	"fmt"

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

// CreateUploaderZipOnce creates the uploader ZIP once for reuse across all sites.
func (s *Service) CreateUploaderZipOnce(uploaderPath string) (string, *apperror.AppError) {
	if uploaderPath == "" {
		uploaderPath = "../wp-plugins/riseup-asia-uploader"
	}

	s.logBootstrapInfo(0, fmt.Sprintf("Creating plugin ZIP archive from %s", uploaderPath))

	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		s.logBootstrapError(0, fmt.Sprintf("Failed to create ZIP: %v", err))

		return "", err
	}

	s.logBootstrapInfo(0, fmt.Sprintf("ZIP archive created: %s", pathutil.ForDisplay(zipPath)))

	return zipPath, nil
}

// BootstrapUploaderWithZip deploys using a pre-built ZIP and cross-upload strategy.
func (s *Service) BootstrapUploaderWithZip(ctx context.Context, id int64, zipPath string) (*BootstrapResult, *apperror.AppError) {
	bctx, err := s.initBootstrapContext(ctx, id, "")
	if err != nil {
		return nil, err
	}

	return s.crossUploadAndFinalize(id, bctx, zipPath)
}

// crossUploadAndFinalize uses cross-upload strategy: QUpload endpoint to upload Riseup Asia.
func (s *Service) crossUploadAndFinalize(id int64, bctx *bootstrapContext, zipPath string) (*BootstrapResult, *apperror.AppError) {
	uploadResult, uploadErr := s.executeCrossUpload(id, bctx.Client, zipPath)
	if uploadErr != nil {
		return nil, uploadErr
	}

	return s.finalizeBootstrap(id, bctx.Site, uploadResult), nil
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site (single-site mode)
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

	uploadResult, uploadErr := s.executeCrossUpload(id, bctx.Client, zipPath)
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

	callback := s.buildBootstrapProgressCallback(id, site.Name)
	client := s.wpClientFactory(site.Url, site.Username, string(decrypted), callback)

	return &bootstrapContext{Site: site, Client: client}, nil
}

// prepareBootstrapZip creates the uploader ZIP archive (single-site fallback).
func (s *Service) prepareBootstrapZip(id int64, uploaderPath string) (string, *apperror.AppError) {
	if uploaderPath == "" {
		uploaderPath = "../wp-plugins/riseup-asia-uploader"
	}

	s.logBootstrapZipStart(id, uploaderPath)

	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		s.logBootstrapError(id, fmt.Sprintf("Failed to create ZIP: %v", err))

		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP")
	}

	return zipPath, nil
}

// checkOnboardAvailable checks if the Onboard plugin is available
func (s *Service) checkOnboardAvailable(client *wordpress.Client) bool {
	result := client.CheckOnboardAvailable()
	if result.HasError() {
		return false
	}
	avail := result.Value()
	return avail != nil && avail.IsAvailable()
}
