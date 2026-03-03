package site

import (
	"context"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemotePluginFiles lists files in a remote plugin via the WordPress client.
func (s *Service) GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) ([]wordpress.RemoteFile, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := client.GetPluginFiles(ctx, pluginSlug)
	if result.HasError() {
		return nil, apperror.Wrap(result.AppError(), apperror.ErrWPConnection, "failed to list remote plugin files")
	}

	return result.Value(), nil
}

// GetRemotePluginFileContent retrieves the content of a specific file from a remote plugin.
func (s *Service) GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return "", appErr
	}

	result := client.GetPluginFileContent(ctx, pluginSlug, filePath)
	if result.HasError() {
		return "", apperror.Wrap(result.AppError(), apperror.ErrWPConnection, "failed to get remote plugin file content")
	}

	return result.Value(), nil
}
