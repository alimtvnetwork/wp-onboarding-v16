# Memory: architecture/wordpress-plugin/resilient-initialization
Updated: 2026-02-09

The WordPress companion plugin (v1.35.3+) implements a two-tier plugin discovery strategy in `find_plugin_file()`:

1. **Cache-busted `get_plugins()`**: Before querying, the method calls `wp_clean_plugins_cache(true)` to force WordPress to re-scan the filesystem, eliminating stale cache as a failure source.
2. **Filesystem fallback (`find_plugin_file_from_filesystem`)**: If `get_plugins()` returns empty or doesn't contain the requested slug, the method falls back to direct filesystem checks:
   - Directory-based plugins: checks `WP_PLUGIN_DIR/{slug}/{slug}.php`, then scans for any `.php` file with a `Plugin Name:` header.
   - Single-file plugins: checks `WP_PLUGIN_DIR/{slug}.php`.

Both tiers are wrapped in try-catch blocks catching `Throwable` with full diagnostic logging. This prevents 404 "Plugin not found" errors caused by WordPress cache staleness or early-loading race conditions.
