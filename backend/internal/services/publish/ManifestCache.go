// Package publish — in-memory TTL cache for remote file manifests.
package publish

import (
	"fmt"
	"sync"
	"time"

	"wp-plugin-publish/internal/wordpress"
)

// manifestCacheEntry holds a cached remote manifest with expiration.
type manifestCacheEntry struct {
	Files     []wordpress.RemoteFile
	ExpiresAt time.Time
}

// ManifestCache provides thread-safe TTL caching of remote file manifests.
type ManifestCache struct {
	mu      sync.RWMutex
	entries map[string]manifestCacheEntry
	ttl     time.Duration
}

// NewManifestCache creates a new cache with the given TTL.
func NewManifestCache(ttl time.Duration) *ManifestCache {
	return &ManifestCache{
		entries: make(map[string]manifestCacheEntry),
		ttl:     ttl,
	}
}

// cacheKey builds a unique key for a plugin+site combination.
func cacheKey(pluginId, siteId int64) string {
	return fmt.Sprintf("%d:%d", pluginId, siteId)
}

// Get returns cached manifest files if present and not expired.
func (c *ManifestCache) Get(pluginId, siteId int64) ([]wordpress.RemoteFile, bool) {
	c.mu.RLock()
	defer c.mu.RUnlock()

	entry, isFound := c.entries[cacheKey(pluginId, siteId)]
	if !isFound {
		return nil, false
	}

	isExpired := time.Now().After(entry.ExpiresAt)
	if isExpired {
		return nil, false
	}

	return entry.Files, true
}

// Set stores manifest files in the cache with TTL expiration.
func (c *ManifestCache) Set(pluginId, siteId int64, files []wordpress.RemoteFile) {
	c.mu.Lock()
	defer c.mu.Unlock()

	c.entries[cacheKey(pluginId, siteId)] = manifestCacheEntry{
		Files:     files,
		ExpiresAt: time.Now().Add(c.ttl),
	}
}

// Invalidate removes a specific cache entry.
func (c *ManifestCache) Invalidate(pluginId, siteId int64) {
	c.mu.Lock()
	defer c.mu.Unlock()

	delete(c.entries, cacheKey(pluginId, siteId))
}

// InvalidatePlugin removes all cache entries for a plugin.
func (c *ManifestCache) InvalidatePlugin(pluginId int64) {
	c.mu.Lock()
	defer c.mu.Unlock()

	prefix := fmt.Sprintf("%d:", pluginId)
	for key := range c.entries {
		if len(key) >= len(prefix) && key[:len(prefix)] == prefix {
			delete(c.entries, key)
		}
	}
}
