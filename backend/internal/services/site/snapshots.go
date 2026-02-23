// Snapshot proxy methods for site service - proxies to WordPress plugin REST API
package site

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteSnapshots lists all snapshots on a remote WordPress site.
func (s *Service) GetRemoteSnapshots(ctx context.Context, siteID int64) ([]wordpress.SnapshotRecord, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	snapshots, err := client.GetSnapshots()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch snapshots").
			WithSiteId(siteID)
	}

	if snapshots == nil {
		snapshots = []wordpress.SnapshotRecord{}
	}

	s.log.Debug("Remote snapshots fetched", "siteId", siteID, "count", len(snapshots))
	return snapshots, nil
}

// GetRemoteSnapshot returns a specific snapshot from a remote site.
func (s *Service) GetRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRecord, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	snapshot, err := client.GetSnapshot(snapshotID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch snapshot").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	return snapshot, nil
}

// CreateRemoteSnapshot triggers a new snapshot on a remote site.
func (s *Service) CreateRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.CreateSnapshot(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to create snapshot").
			WithSiteId(siteID)
	}

	s.log.Info("Remote snapshot created", "siteId", siteID)
	return result, nil
}

// DeleteRemoteSnapshot removes a snapshot from a remote site.
func (s *Service) DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "failed to create WordPress client for snapshot deletion").
			WithSiteId(siteID)
	}

	if err := client.DeleteSnapshot(snapshotID); err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "failed to delete snapshot").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	s.log.Info("Remote snapshot deleted", "siteId", siteID, "snapshotId", snapshotID)
	return nil
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote site.
func (s *Service) RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRestoreResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.RestoreSnapshot(snapshotID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to restore snapshot").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	s.log.Info("Remote snapshot restored", "siteId", siteID, "snapshotId", snapshotID)
	return result, nil
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote site.
func (s *Service) GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (*wordpress.SnapshotSettings, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	settings, err := client.GetSnapshotSettings()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch snapshot settings").
			WithSiteId(siteID)
	}

	return settings, nil
}

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote site.
func (s *Service) UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.UpdateSnapshotSettings(settings)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to update snapshot settings").
			WithSiteId(siteID)
	}

	s.log.Info("Remote snapshot settings updated", "siteId", siteID)
	return result, nil
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote site.
func (s *Service) GetRemoteSnapshotProviders(ctx context.Context, siteID int64) ([]wordpress.SnapshotProvider, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	providers, err := client.GetSnapshotProviders()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch snapshot providers").
			WithSiteId(siteID)
	}

	return providers, nil
}

// GetRemoteAvailableTables returns the list of database tables available for snapshotting.
func (s *Service) GetRemoteAvailableTables(ctx context.Context, siteID int64) ([]wordpress.AvailableTable, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	tables, err := client.GetAvailableTables()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch available tables").
			WithSiteId(siteID)
	}

	s.log.Debug("Remote available tables fetched", "siteId", siteID, "count", len(tables))
	return tables, nil
}

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

// DownloadSnapshotZip requests a cached ZIP build for a snapshot, then streams the ZIP file back.
// The Go proxy fetches the download URL from WordPress and pipes the binary response to the caller.
func (s *Service) DownloadSnapshotZip(ctx context.Context, siteID, snapshotID int64) (*http.Response, *wordpress.SnapshotDownloadResult, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, nil, err
	}

	// Step 1: Request cached ZIP metadata + download URL from WordPress
	meta, err := client.DownloadSnapshotZip(snapshotID)
	if err != nil {
		return nil, nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to request snapshot download").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	if meta.URL == "" {
		return nil, nil, apperror.New(apperror.ErrInternal, "no download URL in response").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID)
	}

	// Step 2: Stream the ZIP file from the download URL
	zipResp, err := client.StreamSnapshotZip(meta.URL)
	if err != nil {
		return nil, nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to stream snapshot ZIP").
			WithSiteId(siteID).
			WithSnapshotId(snapshotID).
			WithURL(meta.URL)
	}

	s.log.Info("Remote snapshot ZIP download started", "siteId", siteID, "snapshotId", snapshotID, "cached", meta.Cached)
	return zipResp, meta, nil
}

// createWPClient is a helper that creates a WordPress client for a site.
func (s *Service) createWPClient(ctx context.Context, siteId int64) (*wordpress.Client, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	return s.wpClientFactory(site.Url, site.Username, string(password), nil), nil
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
