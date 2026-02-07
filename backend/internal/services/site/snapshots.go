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
			WithContext("siteId", siteID)
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
			WithContext("siteId", siteID).
			WithContext("snapshotId", snapshotID)
	}

	return snapshot, nil
}

// CreateRemoteSnapshot triggers a new snapshot on a remote site.
func (s *Service) CreateRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (map[string]interface{}, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.CreateSnapshot(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to create snapshot").
			WithContext("siteId", siteID)
	}

	s.log.Info("Remote snapshot created", "siteId", siteID)
	return result, nil
}

// DeleteRemoteSnapshot removes a snapshot from a remote site.
func (s *Service) DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return err
	}

	if err := client.DeleteSnapshot(snapshotID); err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "failed to delete snapshot").
			WithContext("siteId", siteID).
			WithContext("snapshotId", snapshotID)
	}

	s.log.Info("Remote snapshot deleted", "siteId", siteID, "snapshotId", snapshotID)
	return nil
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote site.
func (s *Service) RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64, opts map[string]interface{}) (map[string]interface{}, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.RestoreSnapshot(snapshotID, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to restore snapshot").
			WithContext("siteId", siteID).
			WithContext("snapshotId", snapshotID)
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
			WithContext("siteId", siteID)
	}

	return settings, nil
}

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote site.
func (s *Service) UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings map[string]interface{}) (*wordpress.SnapshotSettings, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.UpdateSnapshotSettings(settings)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to update snapshot settings").
			WithContext("siteId", siteID)
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
			WithContext("siteId", siteID)
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
			WithContext("siteId", siteID)
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
			WithContext("siteId", siteID).
			WithContext("snapshotId", snapshotID)
	}

	s.log.Info("Remote snapshot export started", "siteId", siteID, "snapshotId", snapshotID)
	return resp, nil
}

// createWPClient is a helper that creates a WordPress client for a site.
func (s *Service) createWPClient(ctx context.Context, siteID int64) (*wordpress.Client, error) {
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return nil, err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	return s.wpClientFactory(site.URL, site.Username, string(password), nil), nil
}

// FullBackupRemoteSnapshot triggers an end-to-end full backup on a remote site.
func (s *Service) FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (map[string]interface{}, error) {
	client, err := s.createWPClient(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result, err := client.FullBackup(opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to trigger full backup").
			WithContext("siteId", siteID)
	}

	s.log.Info("Remote full backup triggered", "siteId", siteID)
	return result, nil
}
