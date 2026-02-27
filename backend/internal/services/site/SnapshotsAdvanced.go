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
func (s *Service) ExportRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*http.Response, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	resp, err := client.ExportSnapshot(snapshotID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to export snapshot").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	s.log.Info("Remote snapshot export started", "siteId", siteID, "snapshotId", snapshotID)
	return resp, nil
}

// SnapshotZipDownload holds the response and metadata for a snapshot ZIP download.
type SnapshotZipDownload struct {
	Response *http.Response
	Meta     *wordpress.SnapshotDownloadResult
}

// DownloadSnapshotZip requests a cached ZIP build for a snapshot, then streams the ZIP file back.
func (s *Service) DownloadSnapshotZip(ctx context.Context, siteID, snapshotID int64) (*SnapshotZipDownload, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	meta, err := s.requestSnapshotMeta(client, siteID, snapshotID)
	if err != nil {
		return nil, err
	}

	return s.streamSnapshotFromMeta(streamSnapshotInput{
		Client:     client,
		SiteID:     siteID,
		SnapshotID: snapshotID,
		Meta:       meta,
	})
}

// streamSnapshotInput bundles parameters for streamSnapshotFromMeta.
type streamSnapshotInput struct {
	Client     *wordpress.Client
	SiteID     int64
	SnapshotID int64
	Meta       *wordpress.SnapshotDownloadResult
}

// streamSnapshotFromMeta streams the ZIP file from the download URL.
func (s *Service) streamSnapshotFromMeta(input streamSnapshotInput) (*SnapshotZipDownload, error) {
	zipResp, err := input.Client.StreamSnapshotZip(input.Meta.Url)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to stream snapshot ZIP").
			WithSiteId(input.SiteID).
			WithSnapshotId(input.SnapshotID).
			WithURL(input.Meta.Url)
	}

	s.log.Info("Remote snapshot ZIP download started", "siteId", input.SiteID, "snapshotId", input.SnapshotID, "cached", input.Meta.Cached)
	return &SnapshotZipDownload{Response: zipResp, Meta: input.Meta}, nil
}

// FullBackupRemoteSnapshot triggers an end-to-end full backup on a remote site.
func (s *Service) FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.FullBackup(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger full backup").
			WithSiteId(siteID)
	}

	s.log.Info("Remote full backup triggered", "siteId", siteID)
	return result, nil
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote site.
func (s *Service) IncrementalBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.IncrementalBackup(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger incremental backup").
			WithSiteId(siteID)
	}

	s.log.Info("Remote incremental backup triggered", "siteId", siteID)
	return result, nil
}

// ImportRemoteSnapshot uploads a ZIP file to import as a snapshot on a remote site.
func (s *Service) ImportRemoteSnapshot(ctx context.Context, siteID int64, zipPath string) (*wordpress.SnapshotImportResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.ImportSnapshot(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to import snapshot").
			WithSiteId(siteID)
	}

	s.log.Info("Remote snapshot imported", "siteId", siteID)
	return result, nil
}

// CleanupRemoteSnapshots triggers cleanup on a remote site.
func (s *Service) CleanupRemoteSnapshots(ctx context.Context, siteID int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.CleanupSnapshots(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger snapshot cleanup").
			WithSiteId(siteID)
	}

	s.log.Info("Remote snapshot cleanup triggered", "siteId", siteID)
	return result, nil
}
