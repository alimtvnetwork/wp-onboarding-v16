// Snapshot proxy methods for site service - proxies to WordPress plugin REST API
package site

import (
	"context"

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
