// Package handlers provides the remote dedup registry HTTP handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteDedupRegistry fetches the dedup registry status from both plugin namespaces
var GetRemoteDedupRegistry = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (*wordpress.DedupRegistryResult, *apperror.AppError) {
		return Services.SiteService.GetRemoteDedupRegistry(ctx, siteId)
	},
)

// ClearRemoteDedupRegistry clears the dedup registry on both plugin namespaces
var ClearRemoteDedupRegistry = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (*wordpress.DedupRegistryClearResult, *apperror.AppError) {
		return Services.SiteService.ClearRemoteDedupRegistry(ctx, siteId)
	},
)

// Ensure net/http is used (ClearRemoteDedupRegistry uses handleSiteActionById which needs it)
var _ http.Handler
