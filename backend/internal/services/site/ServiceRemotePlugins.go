package site

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
	"wp-plugin-publish/pkg/wordpress"
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
func (s *Service) GetRemotePluginsWithCache(ctx context.Context, siteId int64, isForceRefresh bool) ([]RemotePlugin, error) {
	isUseCache := !isForceRefresh
	isCacheUsable :=
		s.isCacheEnabled &&
		isUseCache

	if isCacheUsable {
		if cached, err := s.getRemotePluginsFromCache(ctx, siteId); err == nil && cached != nil {
			s.log.Debug("Remote plugins loaded from cache", "siteId", siteId, "count", len(cached))
			return cached, nil
		}
	}

	return s.fetchAndCachePlugins(ctx, siteId)
}

// fetchAndCachePlugins fetches fresh plugins and stores them in cache.
func (s *Service) fetchAndCachePlugins(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	plugins, err := s.fetchRemotePlugins(ctx, siteId)
	if err != nil {
		return nil, err
	}

	if s.isCacheEnabled {
		err := s.cacheRemotePlugins(ctx, siteId, plugins)
		if err != nil {
			s.log.Warn("Failed to cache remote plugins", "siteId", siteId, "error", err)
		}
	}

	return plugins, nil
}

// fetchRemotePlugins fetches plugins directly from the remote WordPress site.
func (s *Service) fetchRemotePlugins(ctx context.Context, siteId int64) ([]RemotePlugin, error) {
	client, err := s.createWPClient(ctx, siteId)
	if err != nil {
		return nil, err
	}

	uploaderPlugins, uploaderErr := client.ListPluginsViaUploader()
	if uploaderErr != nil {
		site, _ := s.resolveRemoteSite(ctx, siteId)
		s.log.Warn("Riseup Asia Uploader API unavailable on remote site", "siteId", siteId, "siteUrl", site.Url, "error", uploaderErr)
		return nil, apperror.Wrap(uploaderErr, apperror.ErrWPPluginList, "Riseup Asia Uploader is not available on this site.")
	}

	plugins := s.convertUploaderPlugins(siteId, uploaderPlugins)
	s.log.Debug("Remote plugins fetched via Uploader API", "siteId", siteId, "count", len(plugins))
	return plugins, nil
}

// convertUploaderPlugins converts uploader plugin info to RemotePlugin slice.
func (s *Service) convertUploaderPlugins(siteId int64, uploaderPlugins []wordpress.UploaderPluginInfo) []RemotePlugin {
	plugins := make([]RemotePlugin, 0, len(uploaderPlugins))
	for _, p := range uploaderPlugins {
		rp := s.convertSingleUploaderPlugin(siteId, p)
		if rp != nil {
			plugins = append(plugins, *rp)
		}
	}
	return plugins
}

// convertSingleUploaderPlugin converts one UploaderPluginInfo to a RemotePlugin.
func (s *Service) convertSingleUploaderPlugin(siteId int64, p wordpress.UploaderPluginInfo) *RemotePlugin {
	isFileMissing := p.File == ""
	isSlugMissing := p.Slug == ""
	isUnidentifiable := isFileMissing && isSlugMissing

	if isUnidentifiable {
		s.log.Warn("Skipping remote plugin with empty file and slug", "name", p.Name, "siteId", siteId)

		return nil
	}

	slug := resolvePluginSlug(p)
	pluginFile := s.resolvePluginFile(siteId, p, slug)

	status := "inactive"
	if p.Active {
		status = "active"
	}

	return &RemotePlugin{
		Plugin: pluginFile, Slug: slug, Name: p.Name, Version: p.Version,
		Status: status, Author: p.Author, Description: p.Description,
	}
}

// resolvePluginSlug derives the slug from uploader plugin info.
func resolvePluginSlug(p wordpress.UploaderPluginInfo) string {
	if p.Slug != "" {
		return p.Slug
	}
	idx := strings.Index(p.File, "/")
	if idx > 0 {
		return p.File[:idx]
	}
	return p.File
}

// resolvePluginFile derives the plugin file path from slug and info.
func (s *Service) resolvePluginFile(siteId int64, p wordpress.UploaderPluginInfo, slug string) string {
	if p.File != "" {
		return p.File
	}
	derived := slug + "/" + slug + ".php"
	s.log.Warn("Remote plugin missing file path, derived from slug", "slug", slug, "derivedFile", derived, "siteId", siteId)
	return derived
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
	err := json.Unmarshal([]byte(result.Value().PluginsJson), &plugins)
	if err != nil {

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
	err := s.InvalidateRemotePluginsCache(ctx, siteId)
	if err != nil {
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

// CacheStatus holds the result of a cache status check.
type CacheStatus struct {
	IsValid   bool
	CachedAt  *time.Time
	ExpiresAt *time.Time
}

// cacheTimestampStrings holds raw timestamp strings from the database.
type cacheTimestampStrings struct {
	CachedAt  string
	ExpiresAt string
}

// GetRemotePluginsCacheStatus returns cache status for a site
func (s *Service) GetRemotePluginsCacheStatus(ctx context.Context, siteId int64) (*CacheStatus, error) {
	timestamps, err := s.queryCacheTimestamps(ctx, siteId)
	if err != nil {
		return nil, err
	}
	if timestamps.CachedAt == "" {
		result := &CacheStatus{}
		return result, nil
	}

	return parseCacheTimestamps(timestamps), nil
}

// queryCacheTimestamps fetches raw cache timestamps from the database.
func (s *Service) queryCacheTimestamps(ctx context.Context, siteId int64) (*cacheTimestampStrings, error) {
	query := `SELECT CachedAt, ExpiresAt FROM RemotePluginsCache WHERE SiteId = ?`
	var cachedAtStr, expiresAtStr string
	err := s.db.QueryRowContext(ctx, query, siteId).Scan(&cachedAtStr, &expiresAtStr)
	if err != nil {
		if err == sql.ErrNoRows {
			return &cacheTimestampStrings{}, nil
		}
		return nil, err
	}

	result := &cacheTimestampStrings{
		CachedAt:  cachedAtStr,
		ExpiresAt: expiresAtStr,
	}
	return result, nil
}

// parseCacheTimestamps converts timestamp strings to cache status.
func parseCacheTimestamps(timestamps *cacheTimestampStrings) *CacheStatus {
	cachedAtVal := parseTime(timestamps.CachedAt)
	expiresAtVal := parseTime(timestamps.ExpiresAt)
	isExpired :=
		expiresAtVal.IsZero() ||
		expiresAtVal.Before(time.Now())
	isStale := isExpired
	isValid := !isStale

	return &CacheStatus{
		IsValid:   isValid,
		CachedAt:  timeToPtr(cachedAtVal),
		ExpiresAt: timeToPtr(expiresAtVal),
	}
}

// timeToPtr returns a pointer to the time value, or nil if zero.
func timeToPtr(t time.Time) *time.Time {
	if t.IsZero() {
		return nil
	}

	return &t
}
