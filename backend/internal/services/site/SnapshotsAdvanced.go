// Package site — advanced snapshot operations (export, backup, import, cleanup)
package site

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ExportRemoteSnapshot streams a snapshot ZIP from a remote site.
// Returns the raw HTTP response; caller must close the body.
func (s *Service) ExportRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*http.Response, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	resp, err := client.ExportSnapshot(snapshotId)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to export snapshot").
			WithSiteId(siteId).
			WithSnapshotId(snapshotId)
	}

	s.log.Info("Remote snapshot export started", "siteId", siteId, "snapshotId", snapshotId)

	return resp, nil
}

// SnapshotZipDownload holds the response and metadata for a snapshot ZIP download.
type SnapshotZipDownload struct {
	Response *http.Response
	Meta     *wordpress.SnapshotDownloadResult
}

// DownloadSnapshotZip requests a cached ZIP build for a snapshot, then streams the ZIP file back.
func (s *Service) DownloadSnapshotZip(ctx context.Context, siteId, snapshotId int64) (*SnapshotZipDownload, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	meta, metaErr := s.requestSnapshotMeta(client, siteId, snapshotId)
	if metaErr != nil {

		return nil, metaErr
	}

	return s.streamSnapshotFromMeta(streamSnapshotInput{
		Client:     client,
		SiteId:     siteId,
		SnapshotId: snapshotId,
		Meta:       meta,
	})
}

// streamSnapshotInput bundles parameters for streamSnapshotFromMeta.
type streamSnapshotInput struct {
	Client     *wordpress.Client
	SiteId     int64
	SnapshotId int64
	Meta       *wordpress.SnapshotDownloadResult
}

// streamSnapshotFromMeta streams the ZIP file from the download URL.
func (s *Service) streamSnapshotFromMeta(input streamSnapshotInput) (*SnapshotZipDownload, *apperror.AppError) {
	zipResp, err := input.Client.StreamSnapshotZip(input.Meta.Url)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to stream snapshot ZIP").
			WithSiteId(input.SiteId).
			WithSnapshotId(input.SnapshotId).
			WithUrl(input.Meta.Url)
	}

	s.log.Info("Remote snapshot ZIP download started", "siteId", input.SiteId, "snapshotId", input.SnapshotId, "cached", input.Meta.Cached)

	return &SnapshotZipDownload{Response: zipResp, Meta: input.Meta}, nil
}

// FullBackupRemoteSnapshot triggers an end-to-end full backup on a remote site.
func (s *Service) FullBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	result, err := client.FullBackup(opts)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger full backup").
			WithSiteId(siteId)
	}

	s.log.Info("Remote full backup triggered", "siteId", siteId)

	return result, nil
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote site.
func (s *Service) IncrementalBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	result, err := client.IncrementalBackup(opts)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger incremental backup").
			WithSiteId(siteId)
	}

	s.log.Info("Remote incremental backup triggered", "siteId", siteId)

	return result, nil
}

// ImportRemoteSnapshot uploads a ZIP file to import as a snapshot on a remote site.
func (s *Service) ImportRemoteSnapshot(ctx context.Context, siteId int64, zipPath string) (*wordpress.SnapshotImportResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	result, err := client.ImportSnapshot(zipPath)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to import snapshot").
			WithSiteId(siteId)
	}

	s.log.Info("Remote snapshot imported", "siteId", siteId)

	return result, nil
}

// CleanupRemoteSnapshots triggers cleanup on a remote site.
func (s *Service) CleanupRemoteSnapshots(ctx context.Context, siteId int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {

		return nil, appErr
	}

	result, err := client.CleanupSnapshots(opts)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger snapshot cleanup").
			WithSiteId(siteId)
	}

	s.log.Info("Remote snapshot cleanup triggered", "siteId", siteId)

	return result, nil
}
