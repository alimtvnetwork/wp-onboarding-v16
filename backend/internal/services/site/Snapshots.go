// Snapshot proxy methods for site service - proxies to WordPress plugin REST API
package site

import (
	"context"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteSnapshots lists all snapshots on a remote WordPress site.
func (s *Service) GetRemoteSnapshots(ctx context.Context, siteId int64) ([]wordpress.SnapshotRecord, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	snapshotsResult := client.GetSnapshots()
	if snapshotsResult.HasError() {
		return nil, apperror.Wrap(snapshotsResult.AppError(), apperror.ErrWPConnection, "failed to fetch snapshots").
			WithSiteId(siteId)
	}
	snapshots := snapshotsResult.Value()

	isSnapshotsEmpty := snapshots == nil

	if isSnapshotsEmpty {
		snapshots = []wordpress.SnapshotRecord{}
	}

	s.log.Debug("Remote snapshots fetched", "siteId", siteId, "count", len(snapshots))
	return snapshots, nil
}

// GetRemoteSnapshot returns a specific snapshot from a remote site.
func (s *Service) GetRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRecord, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	snapshotResult := client.GetSnapshot(snapshotId)
	if snapshotResult.HasError() {
		return nil, apperror.Wrap(snapshotResult.AppError(), apperror.ErrWPConnection, "failed to fetch snapshot").
			WithSiteId(siteId).
			WithSnapshotId(snapshotId)
	}
	snapshot := snapshotResult.Value()

	return &snapshot, nil
}

// CreateRemoteSnapshot triggers a new snapshot on a remote site.
func (s *Service) CreateRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	createResult := client.CreateSnapshot(opts)
	if createResult.HasError() {
		return nil, apperror.Wrap(createResult.AppError(), apperror.ErrWPConnection, "failed to create snapshot").
			WithSiteId(siteId)
	}
	result := createResult.Value()

	s.log.Info("Remote snapshot created", "siteId", siteId)
	return &result, nil
}

// DeleteRemoteSnapshot removes a snapshot from a remote site.
func (s *Service) DeleteRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) *apperror.AppError {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrWPConnection, "failed to create WordPress client for snapshot deletion").
			WithSiteId(siteId)
	}

	err := client.DeleteSnapshot(snapshotId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "failed to delete snapshot").
			WithSiteId(siteId).
			WithSnapshotId(snapshotId)
	}

	s.log.Info("Remote snapshot deleted", "siteId", siteId, "snapshotId", snapshotId)
	return nil
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote site.
func (s *Service) RestoreRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRestoreResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	restoreResult := client.RestoreSnapshot(snapshotId)
	if restoreResult.HasError() {
		return nil, apperror.Wrap(restoreResult.AppError(), apperror.ErrWPConnection, "failed to restore snapshot").
			WithSiteId(siteId).
			WithSnapshotId(snapshotId)
	}
	result := restoreResult.Value()

	s.log.Info("Remote snapshot restored", "siteId", siteId, "snapshotId", snapshotId)
	return &result, nil
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote site.
func (s *Service) GetRemoteSnapshotSettings(ctx context.Context, siteId int64) (*wordpress.SnapshotSettings, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	settingsResult := client.GetSnapshotSettings()
	if settingsResult.HasError() {
		return nil, apperror.Wrap(settingsResult.AppError(), apperror.ErrWPConnection, "failed to fetch snapshot settings").
			WithSiteId(siteId)
	}
	settings := settingsResult.Value()

	return &settings, nil
}

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote site.
func (s *Service) UpdateRemoteSnapshotSettings(ctx context.Context, siteId int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	updateResult := client.UpdateSnapshotSettings(settings)
	if updateResult.HasError() {
		return nil, apperror.Wrap(updateResult.AppError(), apperror.ErrWPConnection, "failed to update snapshot settings").
			WithSiteId(siteId)
	}
	result := updateResult.Value()

	s.log.Info("Remote snapshot settings updated", "siteId", siteId)
	return &result, nil
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote site.
func (s *Service) GetRemoteSnapshotProviders(ctx context.Context, siteId int64) ([]wordpress.SnapshotProvider, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	providersResult := client.GetSnapshotProviders()
	if providersResult.HasError() {
		return nil, apperror.Wrap(providersResult.AppError(), apperror.ErrWPConnection, "failed to fetch snapshot providers").
			WithSiteId(siteId)
	}

	return providersResult.Value(), nil
}

// GetRemoteAvailableTables returns the list of database tables available for snapshotting.
func (s *Service) GetRemoteAvailableTables(ctx context.Context, siteId int64) ([]wordpress.AvailableTable, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	tables, err := client.GetAvailableTables()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch available tables").
			WithSiteId(siteId)
	}

	s.log.Debug("Remote available tables fetched", "siteId", siteId, "count", len(tables))
	return tables, nil
}

// createWPClient is a helper that creates a WordPress client for a site.
func (s *Service) createWPClient(ctx context.Context, siteId int64) (*wordpress.Client, *apperror.AppError) {
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
