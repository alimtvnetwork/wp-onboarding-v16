package site

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// RemotePlugin represents a plugin installed on a remote WordPress site
type RemotePlugin struct {
	Plugin      string `json:"plugin"`      // external key (WordPress REST API)
	Slug        string `json:"slug"`        // external key
	Name        string `json:"name"`        // external key
	Version     string `json:"version"`     // external key
	Status      string `json:"status"`      // external key
	Author      string `json:"author"`      // external key
	Description string `json:"description"` // external key
	PluginURI   string `json:"pluginUri"`   // external key
	TextDomain  string `json:"textDomain"`  // external key
}

// RemotePluginsResult wraps remote plugins with cache metadata
type RemotePluginsResult struct {
	Plugins   []RemotePlugin
	FromCache bool
	CachedAt  *time.Time `json:",omitempty"`
	ExpiresAt *time.Time `json:",omitempty"`
}

// GetRemotePlugins fetches all plugins installed on a remote WordPress site (with caching)
func (s *Service) GetRemotePlugins(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	return s.GetRemotePluginsWithCache(ctx, siteId, false)
}

// GetRemotePluginsWithCache fetches remote plugins with optional cache bypass
func (s *Service) GetRemotePluginsWithCache(ctx context.Context, siteId int64, forceRefresh bool) ([]RemotePlugin, error) {
	if s.cacheEnabled && !forceRefresh {
		cached, err := s.getRemotePluginsFromCache(ctx, siteId)
		if err == nil && cached != nil {
			s.log.Debug("Remote plugins loaded from cache", "siteId", siteId, "count", len(cached))
			return cached, nil
		}
	}

	plugins, err := s.fetchRemotePlugins(ctx, siteId)
	if err != nil {
		return nil, err
	}

	if s.cacheEnabled {
		if err := s.cacheRemotePlugins(ctx, siteId, plugins); err != nil {
			s.log.Warn("Failed to cache remote plugins", "siteId", siteId, "error", err)
		}
	}

	return plugins, nil
}

// fetchRemotePlugins fetches plugins directly from the remote WordPress site.
func (s *Service) fetchRemotePlugins(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)

	uploaderPlugins, uploaderErr := client.ListPluginsViaUploader()
	if uploaderErr == nil {
		plugins := make([]RemotePlugin, 0, len(uploaderPlugins))
		for _, p := range uploaderPlugins {
			if p.File == "" && p.Slug == "" {
				s.log.Warn("Skipping remote plugin with empty file and slug", "name", p.Name, "siteId", siteId)
				continue
			}

			slug := p.Slug
			if slug == "" {
				slug = p.File
				if idx := strings.Index(p.File, "/"); idx > 0 {
					slug = p.File[:idx]
				}
			}

			pluginFile := p.File
			if pluginFile == "" {
				pluginFile = slug + "/" + slug + ".php"
				s.log.Warn("Remote plugin missing file path, derived from slug", "slug", slug, "derivedFile", pluginFile, "siteId", siteId)
			}

			status := "inactive"
			if p.Active {
				status = "active"
			}
			plugins = append(plugins, RemotePlugin{
				Plugin: pluginFile, Slug: slug, Name: p.Name, Version: p.Version,
				Status: status, Author: p.Author, Description: p.Description,
			})
		}
		s.log.Debug("Remote plugins fetched via Uploader API", "siteId", siteId, "count", len(plugins))
		return plugins, nil
	}

	s.log.Warn("Riseup Asia Uploader API unavailable on remote site", "siteId", siteId, "siteUrl", site.Url, "error", uploaderErr)
	return nil, apperror.Wrap(uploaderErr, apperror.ErrWPPluginList, "Riseup Asia Uploader is not available on this site.")
}

// getRemotePluginsFromCache retrieves cached plugins if not expired.
func (s *Service) getRemotePluginsFromCache(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	type cacheRow struct {
		PluginsJson string
		ExpiresAt   string
	}
	result := dbutil.QueryOne[cacheRow](ctx, s.dbu, cacheSelectQuery, func(row *sql.Row) (cacheRow, error) {
		var r cacheRow
		err := row.Scan(&r.PluginsJson, &r.ExpiresAt)
		return r, err
	}, siteId)

	if result.HasError() {
		return nil, result.AppError()
	}
	if result.IsEmpty() {
		return nil, nil
	}

	var plugins []RemotePlugin
	if err := json.Unmarshal([]byte(result.Value().PluginsJson), &plugins); err != nil {
		return nil, err
	}
	return plugins, nil
}

// cacheRemotePlugins stores plugins in the cache.
func (s *Service) cacheRemotePlugins(ctx context.Context, siteId int64, plugins []RemotePlugin) error {
	pluginsJson, err := json.Marshal(plugins)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to marshal remote plugins for cache")
	}

	expiresAt := time.Now().Add(time.Duration(s.cacheTTLMinutes) * time.Minute)
	res := dbutil.Exec(ctx, s.dbu, cacheUpsertQuery, siteId, string(pluginsJson), expiresAt.Format("2006-01-02 15:04:05"))
	if res.HasError() {
		return res.AppError()
	}
	return nil
}

// ForceSyncRemotePlugins clears cache and fetches fresh data.
func (s *Service) ForceSyncRemotePlugins(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	if err := s.InvalidateRemotePluginsCache(ctx, siteId); err != nil {
		s.log.Warn("Failed to invalidate cache before force sync", "siteId", siteId, "error", err)
	}
	return s.GetRemotePluginsWithCache(ctx, siteId, true)
}

// InvalidateRemotePluginsCache removes cached plugins for a site.
func (s *Service) InvalidateRemotePluginsCache(ctx context.Context, siteId int64) error {
	res := dbutil.Exec(ctx, s.dbu, cacheDeleteQuery, siteId)
	if res.HasError() {
		return res.AppError()
	}
	s.log.Debug("Remote plugins cache invalidated", "siteId", siteId)
	return nil
}

// GetRemotePluginsCacheStatus returns cache status for a site
func (s *Service) GetRemotePluginsCacheStatus(ctx context.Context, siteId int64) (bool, *time.Time, *time.Time, error) {
	query := `SELECT CachedAt, ExpiresAt FROM RemotePluginsCache WHERE SiteId = ?`
	var cachedAtStr, expiresAtStr string
	err := s.db.QueryRowContext(ctx, query, siteId).Scan(&cachedAtStr, &expiresAtStr)
	if err != nil {
		if err == sql.ErrNoRows {
			return false, nil, nil, nil
		}
		return false, nil, nil, err
	}

	cachedAtVal := parseTime(cachedAtStr)
	expiresAtVal := parseTime(expiresAtStr)
	isValid := !expiresAtVal.IsZero() && expiresAtVal.After(time.Now())

	var cachedAtPtr, expiresAtPtr *time.Time
	if !cachedAtVal.IsZero() {
		cachedAtPtr = &cachedAtVal
	}
	if !expiresAtVal.IsZero() {
		expiresAtPtr = &expiresAtVal
	}

	return isValid, cachedAtPtr, expiresAtPtr, nil
}
