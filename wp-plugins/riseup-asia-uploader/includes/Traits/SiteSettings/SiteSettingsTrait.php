<?php
/**
 * SiteSettingsTrait — WordPress site settings read/update handlers.
 *
 * Provides REST API handlers for reading and updating site-level
 * WordPress options: search engine visibility, debug mode, PHP debug,
 * upload file size, and post size.
 *
 * @package RiseupAsia\Traits\SiteSettings
 * @since   2.31.0
 */

namespace RiseupAsia\Traits\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\DateHelper;

trait SiteSettingsTrait
{
    /**
     * Handle GET /site-settings — return current site settings.
     */
    public function handleGetSiteSettings(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('Site settings GET requested');

        return EnvelopeBuilder::success()
            ->setSingleResult($this->buildSiteSettingsPayload())
            ->toResponse();
    }

    /**
     * Handle PUT /site-settings — update site settings (partial).
     */
    public function handleUpdateSiteSettings(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $updated = [];
        $errors = [];

        // Search engine visibility (blog_public: 1 = visible, 0 = discouraged)
        if (array_key_exists('searchEngineVisible', $body)) {
            $value = (bool) $body['searchEngineVisible'];
            update_option('blog_public', $value ? '1' : '0');
            $updated['searchEngineVisible'] = $value;
            $this->fileLogger->info('Updated search engine visibility', ['value' => $value]);
        }

        // WP_DEBUG — requires wp-config.php modification
        if (array_key_exists('wpDebug', $body)) {
            $result = $this->updateWpConfigConstant('WP_DEBUG', (bool) $body['wpDebug']);
            if ($result) {
                $updated['wpDebug'] = (bool) $body['wpDebug'];
            } else {
                $errors[] = 'Failed to update WP_DEBUG — wp-config.php may not be writable';
            }
        }

        // WP_DEBUG_LOG
        if (array_key_exists('wpDebugLog', $body)) {
            $result = $this->updateWpConfigConstant('WP_DEBUG_LOG', (bool) $body['wpDebugLog']);
            if ($result) {
                $updated['wpDebugLog'] = (bool) $body['wpDebugLog'];
            } else {
                $errors[] = 'Failed to update WP_DEBUG_LOG';
            }
        }

        // WP_DEBUG_DISPLAY
        if (array_key_exists('wpDebugDisplay', $body)) {
            $result = $this->updateWpConfigConstant('WP_DEBUG_DISPLAY', (bool) $body['wpDebugDisplay']);
            if ($result) {
                $updated['wpDebugDisplay'] = (bool) $body['wpDebugDisplay'];
            } else {
                $errors[] = 'Failed to update WP_DEBUG_DISPLAY';
            }
        }

        // RISEUP_DEBUG_BOOT
        if (array_key_exists('riseupDebugBoot', $body)) {
            $result = $this->updateWpConfigConstant('RISEUP_DEBUG_BOOT', (bool) $body['riseupDebugBoot']);
            if ($result) {
                $updated['riseupDebugBoot'] = (bool) $body['riseupDebugBoot'];
            } else {
                $errors[] = 'Failed to update RISEUP_DEBUG_BOOT';
            }
        }

        // QUPLOAD_DEBUG_BOOT
        if (array_key_exists('quploadDebugBoot', $body)) {
            $result = $this->updateWpConfigConstant('QUPLOAD_DEBUG_BOOT', (bool) $body['quploadDebugBoot']);
            if ($result) {
                $updated['quploadDebugBoot'] = (bool) $body['quploadDebugBoot'];
            } else {
                $errors[] = 'Failed to update QUPLOAD_DEBUG_BOOT';
            }
        }

        // Upload max filesize (PHP ini — .htaccess or user.ini)
        if (array_key_exists('uploadMaxFilesize', $body)) {
            $val = $this->sanitizeSizeValue($body['uploadMaxFilesize']);
            if ($val !== null) {
                $this->updatePhpIniOverride('upload_max_filesize', $val);
                $updated['uploadMaxFilesize'] = $val;
            } else {
                $errors[] = 'Invalid uploadMaxFilesize value';
            }
        }

        // Post max size
        if (array_key_exists('postMaxSize', $body)) {
            $val = $this->sanitizeSizeValue($body['postMaxSize']);
            if ($val !== null) {
                $this->updatePhpIniOverride('post_max_size', $val);
                $updated['postMaxSize'] = $val;
            } else {
                $errors[] = 'Invalid postMaxSize value';
            }
        }

        // Memory limit
        if (array_key_exists('memoryLimit', $body)) {
            $val = $this->sanitizeSizeValue($body['memoryLimit']);
            if ($val !== null) {
                $this->updatePhpIniOverride('memory_limit', $val);
                $updated['memoryLimit'] = $val;
            } else {
                $errors[] = 'Invalid memoryLimit value';
            }
        }

        $hasErrors = count($errors) > 0;

        $this->fileLogger->info('Site settings update complete', [
            'updated' => array_keys($updated),
            'errors'  => $errors,
        ]);

        $result = [
            ResponseKeyType::Success->value => true,
            'updated'  => $updated,
            'settings' => $this->buildSiteSettingsPayload(),
        ];

        if ($hasErrors) {
            $result['warnings'] = $errors;
        }

        return EnvelopeBuilder::success()
            ->setSingleResult($result)
            ->toResponse();
    }

    /**
     * Build the complete site settings payload.
     */
    private function buildSiteSettingsPayload(): array
    {
        return [
            'searchEngineVisible' => (get_option('blog_public', '1') === '1'),
            'wpDebug'            => defined('WP_DEBUG') && WP_DEBUG,
            'wpDebugLog'         => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'wpDebugDisplay'     => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'riseupDebugBoot'    => defined('RISEUP_DEBUG_BOOT') && RISEUP_DEBUG_BOOT,
            'quploadDebugBoot'   => defined('QUPLOAD_DEBUG_BOOT') && QUPLOAD_DEBUG_BOOT,
            'uploadMaxFilesize'  => ini_get('upload_max_filesize'),
            'postMaxSize'        => ini_get('post_max_size'),
            'memoryLimit'        => ini_get('memory_limit'),
            'maxExecutionTime'   => (int) ini_get('max_execution_time'),
            'maxInputVars'       => (int) ini_get('max_input_vars'),
            'wpConfigWritable'   => $this->isWpConfigWritable(),
            'htaccessWritable'   => is_writable(ABSPATH . '.htaccess'),
            'phpVersion'         => PHP_VERSION,
            'wpVersion'          => get_bloginfo('version'),
            'siteUrl'            => get_site_url(),
            'homeUrl'            => home_url(),
            'isMultisite'        => is_multisite(),
            'timezone'           => wp_timezone_string(),
            'activeTheme'        => get_stylesheet(),
            'serverSoftware'     => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field($_SERVER['SERVER_SOFTWARE']) : 'unknown',
        ];
    }

    /**
     * Update a constant in wp-config.php.
     */
    private function updateWpConfigConstant(string $constant, bool $value): bool
    {
        $configPath = $this->findWpConfigPath();
        if ($configPath === null) {
            return false;
        }

        $isNotWritable = !is_writable($configPath);
        if ($isNotWritable) {
            $this->fileLogger->warning('wp-config.php is not writable', ['path' => $configPath]);
            return false;
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return false;
        }

        $valueStr = $value ? 'true' : 'false';

        // Pattern to match existing define
        $pattern = "/define\s*\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*[^)]+\)/";

        if (preg_match($pattern, $content)) {
            // Replace existing
            $replacement = "define('" . $constant . "', " . $valueStr . ")";
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            // Add before "That's all, stop editing" or before the require line
            $insertBefore = "/\/\*\s*That's all/";
            $insertLine = "\ndefine('" . $constant . "', " . $valueStr . ");\n";

            if (preg_match($insertBefore, $content)) {
                $content = preg_replace($insertBefore, $insertLine . "/* That's all", $content);
            } else {
                // Fallback: insert before require_once ABSPATH
                $content = preg_replace(
                    "/(require_once\s*\(\s*ABSPATH)/",
                    $insertLine . "$1",
                    $content,
                );
            }
        }

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Find wp-config.php path (could be one level up).
     */
    private function findWpConfigPath(): ?string
    {
        $path = ABSPATH . 'wp-config.php';
        if (file_exists($path)) {
            return $path;
        }

        $path = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Check if wp-config.php is writable.
     */
    private function isWpConfigWritable(): bool
    {
        $path = $this->findWpConfigPath();
        return $path !== null && is_writable($path);
    }

    /**
     * Sanitize a PHP size value (e.g., "128M", "2G", "512K").
     */
    private function sanitizeSizeValue($input): ?string
    {
        $input = trim((string) $input);

        // Accept formats like "128M", "2G", "512K", or plain numbers (bytes)
        if (preg_match('/^\d+[KMG]?$/i', $input)) {
            return strtoupper($input);
        }

        return null;
    }

    /**
     * Update PHP ini overrides via .user.ini or .htaccess.
     */
    private function updatePhpIniOverride(string $directive, string $value): bool
    {
        // Try .user.ini first (PHP-FPM / FastCGI)
        $userIniPath = ABSPATH . '.user.ini';
        if ($this->updateIniFile($userIniPath, $directive, $value)) {
            return true;
        }

        // Fallback to .htaccess (Apache mod_php)
        $htaccessPath = ABSPATH . '.htaccess';
        return $this->updateHtaccessPhpValue($htaccessPath, $directive, $value);
    }

    /**
     * Update a directive in a .user.ini file.
     */
    private function updateIniFile(string $path, string $directive, string $value): bool
    {
        $content = file_exists($path) ? file_get_contents($path) : '';
        if ($content === false) {
            $content = '';
        }

        $line = $directive . ' = ' . $value;
        $pattern = '/^' . preg_quote($directive, '/') . '\s*=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content = rtrim($content) . "\n" . $line . "\n";
        }

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Update a php_value directive in .htaccess.
     */
    private function updateHtaccessPhpValue(string $path, string $directive, string $value): bool
    {
        $isNotWritable = !is_writable($path);
        if ($isNotWritable) {
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $line = 'php_value ' . $directive . ' ' . $value;
        $pattern = '/^php_value\s+' . preg_quote($directive, '/') . '\s+.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content = $line . "\n" . $content;
        }

        return file_put_contents($path, $content) !== false;
    }
}
