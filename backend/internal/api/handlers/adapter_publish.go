// Package handlers - Publish and Backup service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/publish"
)

// PublishServiceInterface defines publish service methods
type PublishServiceInterface interface {
	Publish(ctx context.Context, pluginID, siteID int64, opts any) (*publish.PublishResult, error)
	PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (*publish.PublishResult, error)
	PreviewPublish(ctx context.Context, pluginID, siteID int64) (*publish.PublishPreviewResult, error)
	GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (*publish.FileDiffResult, error)
}

// BackupServiceInterface defines backup service methods
type BackupServiceInterface interface {
	List(ctx context.Context, pluginID int64) ([]models.Backup, error)
	Create(ctx context.Context, pluginID, siteID int64) (*models.Backup, error)
	Restore(ctx context.Context, backupID int64) error
	Delete(ctx context.Context, backupID int64) error
}

// PublishServiceAdapter wraps *publish.Service to implement PublishServiceInterface
type PublishServiceAdapter struct {
	*publish.Service
}

func (a *PublishServiceAdapter) Publish(ctx context.Context, pluginID, siteID int64, opts any) (*publish.PublishResult, error) {
	return a.Service.Publish(ctx, pluginID, siteID, opts)
}

func (a *PublishServiceAdapter) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (*publish.PublishResult, error) {
	return a.Service.PublishFiles(ctx, pluginID, siteID, files)
}

func (a *PublishServiceAdapter) PreviewPublish(ctx context.Context, pluginID, siteID int64) (*publish.PublishPreviewResult, error) {
	return a.Service.PreviewPublish(ctx, pluginID, siteID)
}

func (a *PublishServiceAdapter) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (*publish.FileDiffResult, error) {
	return a.Service.GetFileDiff(ctx, pluginID, siteID, filePath)
}

// BackupServiceAdapter wraps *backup.Service to implement BackupServiceInterface
type BackupServiceAdapter struct {
	*backup.Service
}

func (a *BackupServiceAdapter) List(ctx context.Context, pluginID int64) ([]models.Backup, error) {
	return a.Service.List(ctx, pluginID)
}

func (a *BackupServiceAdapter) Create(ctx context.Context, pluginID, siteID int64) (*models.Backup, error) {
	return a.Service.Create(ctx, pluginID)
}

func (a *BackupServiceAdapter) Restore(ctx context.Context, backupID int64) error {
	_, err := a.Service.Restore(ctx, backupID)
	return err
}

func (a *BackupServiceAdapter) Delete(ctx context.Context, backupID int64) error {
	return a.Service.Delete(ctx, backupID)
}
