<?php
/**
 * Plugin constants (default values).
 *
 * These are the DEFAULT values. They can be overridden by:
 * 1. Database settings (if database exists)
 * 2. Environment variables (highest priority)
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug & Logging Configuration.
if (!defined('ONBOARD_DEBUG_LOGGING')) {
    define('ONBOARD_DEBUG_LOGGING', true); // Set to false to disable debug logging
}
if (!defined('ONBOARD_ERROR_LOGGING')) {
    define('ONBOARD_ERROR_LOGGING', true); // Set to false to disable error logging
}

// Demo/Fallback Website URL.
if (!defined('ONBOARD_DEMO_WEBSITE')) {
    define('ONBOARD_DEMO_WEBSITE', 'https://riseup-asia.com');
}

// API Configuration.
if (!defined('ONBOARD_API_NAMESPACE')) {
    define('ONBOARD_API_NAMESPACE', 'onboard-plugin');
}
if (!defined('ONBOARD_API_VERSION')) {
    define('ONBOARD_API_VERSION', 'v1');
}

// Token TTL (Time To Live) in seconds.
if (!defined('ONBOARD_ACCESS_TOKEN_TTL')) {
    define('ONBOARD_ACCESS_TOKEN_TTL', 3600); // 1 hour
}
if (!defined('ONBOARD_REFRESH_TOKEN_TTL')) {
    define('ONBOARD_REFRESH_TOKEN_TTL', 2592000); // 30 days
}
if (!defined('ONBOARD_MUTATION_TOKEN_TTL')) {
    define('ONBOARD_MUTATION_TOKEN_TTL', 1200); // 20 minutes
}
if (!defined('ONBOARD_AUTH_CODE_TTL')) {
    define('ONBOARD_AUTH_CODE_TTL', 600); // 10 minutes
}

// Rate Limiting.
if (!defined('ONBOARD_RATE_LIMIT_AUTH_REQUESTS')) {
    define('ONBOARD_RATE_LIMIT_AUTH_REQUESTS', 5); // per hour
}
if (!defined('ONBOARD_RATE_LIMIT_MUTATION_REQUESTS')) {
    define('ONBOARD_RATE_LIMIT_MUTATION_REQUESTS', 10); // per hour
}

// Backup & Snapshot Configuration.
if (!defined('ONBOARD_SNAPSHOT_RETENTION_COUNT')) {
    define('ONBOARD_SNAPSHOT_RETENTION_COUNT', 5);
}
if (!defined('ONBOARD_SNAPSHOT_CLEANUP_INTERVAL')) {
    define('ONBOARD_SNAPSHOT_CLEANUP_INTERVAL', 'daily');
}
if (!defined('ONBOARD_AUTO_BACKUP_ENABLED')) {
    define('ONBOARD_AUTO_BACKUP_ENABLED', true);
}

// Temp/Upload Configuration.
if (!defined('ONBOARD_TEMP_CLEANUP_DAYS')) {
    define('ONBOARD_TEMP_CLEANUP_DAYS', 2);
}
if (!defined('ONBOARD_TEMP_SIZE_WARNING')) {
    define('ONBOARD_TEMP_SIZE_WARNING', 536870912); // 500MB
}
if (!defined('ONBOARD_MAX_UPLOAD_SIZE')) {
    define('ONBOARD_MAX_UPLOAD_SIZE', 104857600); // 100MB
}

// Security Configuration.
if (!defined('ONBOARD_REQUIRE_HTTPS')) {
    define('ONBOARD_REQUIRE_HTTPS', false);
}
if (!defined('ONBOARD_IP_WHITELIST_ENABLED')) {
    define('ONBOARD_IP_WHITELIST_ENABLED', true);
}
if (!defined('ONBOARD_IP_AUTO_APPROVE')) {
    define('ONBOARD_IP_AUTO_APPROVE', false);
}
if (!defined('ONBOARD_TOKEN_ENCRYPTION')) {
    define('ONBOARD_TOKEN_ENCRYPTION', true);
}

// Logging.
if (!defined('ONBOARD_LOG_LEVEL')) {
    define('ONBOARD_LOG_LEVEL', 'info');
}
if (!defined('ONBOARD_AUDIT_LOG_RETENTION_DAYS')) {
    define('ONBOARD_AUDIT_LOG_RETENTION_DAYS', 365);
}

// Feature Flags.
if (!defined('ONBOARD_ENABLE_OAUTH')) {
    define('ONBOARD_ENABLE_OAUTH', true);
}
if (!defined('ONBOARD_ENABLE_API')) {
    define('ONBOARD_ENABLE_API', true);
}
if (!defined('ONBOARD_ENABLE_BACKUP')) {
    define('ONBOARD_ENABLE_BACKUP', true);
}
if (!defined('ONBOARD_ENABLE_AUDIT_LOGS')) {
    define('ONBOARD_ENABLE_AUDIT_LOGS', true);
}

// Backup Triggers.
if (!defined('ONBOARD_BACKUP_TRIGGER_UPLOAD')) {
    define('ONBOARD_BACKUP_TRIGGER_UPLOAD', true);
}
if (!defined('ONBOARD_BACKUP_TRIGGER_ENABLE')) {
    define('ONBOARD_BACKUP_TRIGGER_ENABLE', true);
}
if (!defined('ONBOARD_BACKUP_TRIGGER_DISABLE')) {
    define('ONBOARD_BACKUP_TRIGGER_DISABLE', true);
}
if (!defined('ONBOARD_BACKUP_TRIGGER_DELETE')) {
    define('ONBOARD_BACKUP_TRIGGER_DELETE', true);
}
