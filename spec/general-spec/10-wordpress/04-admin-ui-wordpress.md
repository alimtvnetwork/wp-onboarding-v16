# 33. WordPress Admin UI

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 33.1 Overview

This document establishes standardized patterns for WordPress admin interface development, including menu registration, settings pages, asset management, screen options, and admin notices.

---

## 33.2 Admin Menu Registration

### Menu Structure Constants

```php
<?php
namespace PluginNamespace\Admin;

class MenuConfig
{
    /**
     * Menu configuration constants
     */
    public const MENU_SLUG = 'plugin-slug';
    public const MENU_POSITION = 30;
    public const MENU_ICON = 'dashicons-clipboard';
    public const CAPABILITY = 'manage_options';
    
    /**
     * Submenu definitions
     */
    public const SUBMENUS = [
        'dashboard' => [
            'title' => 'Dashboard',
            'slug' => 'plugin-slug',
            'capability' => 'manage_options',
            'callback' => 'renderDashboard'
        ],
        'items' => [
            'title' => 'Manage Items',
            'slug' => 'plugin-slug-items',
            'capability' => 'edit_posts',
            'callback' => 'renderItems'
        ],
        'settings' => [
            'title' => 'Settings',
            'slug' => 'plugin-slug-settings',
            'capability' => 'manage_options',
            'callback' => 'renderSettings'
        ],
        'tools' => [
            'title' => 'Tools',
            'slug' => 'plugin-slug-tools',
            'capability' => 'manage_options',
            'callback' => 'renderTools'
        ]
    ];
}
```

### Menu Registration Class

```php
<?php
namespace PluginNamespace\Admin;

use PluginNamespace\Utils\Logger;

class AdminMenu
{
    /**
     * Register admin menus
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPages']);
    }
    
    /**
     * Add all menu pages
     */
    public static function addMenuPages(): void
    {
        // Main menu
        add_menu_page(
            __('Plugin Name', 'plugin-slug'),           // Page title
            __('Plugin Name', 'plugin-slug'),           // Menu title
            MenuConfig::CAPABILITY,                      // Capability
            MenuConfig::MENU_SLUG,                       // Menu slug
            [AdminController::class, 'renderDashboard'], // Callback
            MenuConfig::MENU_ICON,                       // Icon
            MenuConfig::MENU_POSITION                    // Position
        );
        
        // Submenus
        foreach (MenuConfig::SUBMENUS as $key => $submenu) {
            add_submenu_page(
                MenuConfig::MENU_SLUG,                           // Parent slug
                __($submenu['title'], 'plugin-slug'),            // Page title
                __($submenu['title'], 'plugin-slug'),            // Menu title
                $submenu['capability'],                          // Capability
                $submenu['slug'],                                // Menu slug
                [AdminController::class, $submenu['callback']]   // Callback
            );
        }
        
        // Remove duplicate first submenu
        self::removeFirstSubmenuDuplicate();
    }
    
    /**
     * Remove the auto-created duplicate submenu item
     */
    private static function removeFirstSubmenuDuplicate(): void
    {
        global $submenu;
        
        $hasSubmenu = isset($submenu[MenuConfig::MENU_SLUG]);
        
        if ($hasSubmenu && count($submenu[MenuConfig::MENU_SLUG]) > 1) {
            $submenu[MenuConfig::MENU_SLUG][0][0] = __('Dashboard', 'plugin-slug');
        }
    }
    
    /**
     * Add admin bar menu item
     */
    public static function addAdminBarMenu(\WP_Admin_Bar $adminBar): void
    {
        $canAccess = current_user_can(MenuConfig::CAPABILITY);
        
        if (!$canAccess) {
            return;
        }
        
        $adminBar->add_node([
            'id' => 'plugin-slug-admin-bar',
            'title' => '<span class="ab-icon dashicons-clipboard"></span>' . 
                       __('Plugin Name', 'plugin-slug'),
            'href' => admin_url('admin.php?page=' . MenuConfig::MENU_SLUG),
            'meta' => ['title' => __('Plugin Name Dashboard', 'plugin-slug')]
        ]);
    }
}
```

### Menu Position Reference

| Position | Location |
|----------|----------|
| 2 | Dashboard |
| 4 | Separator |
| 5 | Posts |
| 10 | Media |
| 15 | Links |
| 20 | Pages |
| 25 | Comments |
| 59 | Separator |
| 60 | Appearance |
| 65 | Plugins |
| 70 | Users |
| 75 | Tools |
| 80 | Settings |
| 99 | Separator |

---

## 33.3 Asset Enqueueing

### Admin Assets Class

```php
<?php
namespace PluginNamespace\Admin;

use PluginNamespace\Utils\Logger;

class AdminAssets
{
    /**
     * Register asset hooks
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueueAssets(string $hookSuffix): void
    {
        // Only load on plugin pages
        $isPluginPage = self::isPluginAdminPage($hookSuffix);
        
        if (!$isPluginPage) {
            return;
        }
        
        try {
            self::enqueueStyles();
            self::enqueueScripts();
            self::localizeScripts();
        } catch (\Throwable $e) {
            Logger::error('Failed to enqueue admin assets', [
                'file' => __FILE__,
                'action' => 'enqueueAssets',
                'hook' => $hookSuffix,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Check if current page is a plugin admin page
     */
    private static function isPluginAdminPage(string $hookSuffix): bool
    {
        $pluginPages = [
            'toplevel_page_plugin-slug',
            'plugin-name_page_plugin-slug-items',
            'plugin-name_page_plugin-slug-settings',
            'plugin-name_page_plugin-slug-tools'
        ];
        
        return in_array($hookSuffix, $pluginPages, true);
    }
    
    /**
     * Enqueue stylesheets
     */
    private static function enqueueStyles(): void
    {
        // Main admin styles
        wp_enqueue_style(
            'plugin-slug-admin',
            PLUGIN_SLUG_URL . 'assets/css/admin.css',
            [],
            PLUGIN_SLUG_VERSION
        );
        
        // Page-specific styles (if needed)
        $currentPage = $_GET['page'] ?? '';
        
        $isSettingsPage = ($currentPage === 'plugin-slug-settings');
        if ($isSettingsPage) {
            wp_enqueue_style(
                'plugin-slug-settings',
                PLUGIN_SLUG_URL . 'assets/css/settings.css',
                ['plugin-slug-admin'],
                PLUGIN_SLUG_VERSION
            );
        }
    }
    
    /**
     * Enqueue scripts
     */
    private static function enqueueScripts(): void
    {
        // Dependencies
        wp_enqueue_script('jquery');
        
        // Main admin script
        wp_enqueue_script(
            'plugin-slug-admin',
            PLUGIN_SLUG_URL . 'assets/js/admin.js',
            ['jquery'],
            PLUGIN_SLUG_VERSION,
            true // Load in footer
        );
        
        // Media uploader (if needed)
        $needsMediaUploader = self::pageNeedsMediaUploader();
        if ($needsMediaUploader) {
            wp_enqueue_media();
        }
        
        // Code editor (for settings with code)
        $needsCodeEditor = self::pageNeedsCodeEditor();
        if ($needsCodeEditor) {
            $settings = wp_enqueue_code_editor(['type' => 'text/css']);
            
            $hasCodeEditor = ($settings !== false);
            if ($hasCodeEditor) {
                wp_add_inline_script(
                    'code-editor',
                    'jQuery(function() { wp.codeEditor.initialize("custom-css-field", ' . 
                    wp_json_encode($settings) . '); });'
                );
            }
        }
    }
    
    /**
     * Localize scripts with data
     */
    private static function localizeScripts(): void
    {
        wp_localize_script('plugin-slug-admin', 'pluginSlugAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('plugin-slug/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxNonce' => wp_create_nonce('plugin_slug_admin'),
            'userId' => get_current_user_id(),
            'isDebug' => defined('WP_DEBUG') && WP_DEBUG,
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'plugin-slug'),
                'saving' => __('Saving...', 'plugin-slug'),
                'saved' => __('Saved!', 'plugin-slug'),
                'error' => __('An error occurred.', 'plugin-slug'),
                'loading' => __('Loading...', 'plugin-slug')
            ]
        ]);
    }
    
    /**
     * Check if current page needs media uploader
     */
    private static function pageNeedsMediaUploader(): bool
    {
        $currentPage = $_GET['page'] ?? '';
        $pagesWithMedia = ['plugin-slug-items', 'plugin-slug-settings'];
        
        return in_array($currentPage, $pagesWithMedia, true);
    }
    
    /**
     * Check if current page needs code editor
     */
    private static function pageNeedsCodeEditor(): bool
    {
        $currentPage = $_GET['page'] ?? '';
        return $currentPage === 'plugin-slug-settings';
    }
}
```

### Asset Versioning Strategy

```php
<?php
/**
 * Version assets based on file modification time (development)
 * or plugin version (production)
 */
class AssetVersion
{
    public static function get(string $filePath): string
    {
        $isDebug = defined('WP_DEBUG') && WP_DEBUG;
        
        if ($isDebug) {
            $fullPath = PLUGIN_SLUG_PATH . $filePath;
            $fileExists = file_exists($fullPath);
            
            if ($fileExists) {
                return (string) filemtime($fullPath);
            }
        }
        
        return PLUGIN_SLUG_VERSION;
    }
}

// Usage
wp_enqueue_style(
    'plugin-slug-admin',
    PLUGIN_SLUG_URL . 'assets/css/admin.css',
    [],
    AssetVersion::get('assets/css/admin.css')
);
```

---

## 33.4 Settings Page Implementation

### Settings Registration

```php
<?php
namespace PluginNamespace\Admin;

class SettingsPage
{
    /**
     * Settings sections and fields configuration
     */
    private const SETTINGS_CONFIG = [
        'general' => [
            'title' => 'General Settings',
            'fields' => [
                'enable_feature' => [
                    'title' => 'Enable Feature',
                    'type' => 'checkbox',
                    'default' => true,
                    'description' => 'Enable the main plugin feature.'
                ],
                'items_per_page' => [
                    'title' => 'Items Per Page',
                    'type' => 'number',
                    'default' => 20,
                    'min' => 5,
                    'max' => 100,
                    'description' => 'Number of items to display per page.'
                ],
                'default_status' => [
                    'title' => 'Default Status',
                    'type' => 'select',
                    'default' => 'draft',
                    'options' => [
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived'
                    ],
                    'description' => 'Default status for new items.'
                ]
            ]
        ],
        'email' => [
            'title' => 'Email Settings',
            'fields' => [
                'from_name' => [
                    'title' => 'From Name',
                    'type' => 'text',
                    'default' => '',
                    'placeholder' => 'Site Name',
                    'description' => 'Name shown in email "From" field.'
                ],
                'from_email' => [
                    'title' => 'From Email',
                    'type' => 'email',
                    'default' => '',
                    'placeholder' => 'admin@example.com',
                    'description' => 'Email address for outgoing emails.'
                ],
                'enable_notifications' => [
                    'title' => 'Enable Notifications',
                    'type' => 'checkbox',
                    'default' => true,
                    'description' => 'Send email notifications for important events.'
                ]
            ]
        ],
        'advanced' => [
            'title' => 'Advanced Settings',
            'fields' => [
                'debug_mode' => [
                    'title' => 'Debug Mode',
                    'type' => 'checkbox',
                    'default' => false,
                    'description' => 'Enable verbose logging for debugging.'
                ],
                'cache_ttl' => [
                    'title' => 'Cache Duration',
                    'type' => 'number',
                    'default' => 3600,
                    'min' => 0,
                    'max' => 86400,
                    'description' => 'Cache duration in seconds (0 to disable).'
                ]
            ]
        ]
    ];
    
    /**
     * Register settings
     */
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
    }
    
    /**
     * Register all settings sections and fields
     */
    public static function registerSettings(): void
    {
        foreach (self::SETTINGS_CONFIG as $sectionKey => $section) {
            $sectionId = "plugin_slug_{$sectionKey}";
            
            // Register section
            add_settings_section(
                $sectionId,
                __($section['title'], 'plugin-slug'),
                [self::class, 'renderSectionDescription'],
                'plugin-slug-settings'
            );
            
            // Register fields
            foreach ($section['fields'] as $fieldKey => $field) {
                $optionName = "plugin_slug_{$fieldKey}";
                
                // Register setting
                register_setting(
                    'plugin_slug_settings',
                    $optionName,
                    [
                        'type' => self::getSettingType($field['type']),
                        'default' => $field['default'],
                        'sanitize_callback' => self::getSanitizeCallback($field['type'])
                    ]
                );
                
                // Add field
                add_settings_field(
                    $optionName,
                    __($field['title'], 'plugin-slug'),
                    [self::class, 'renderField'],
                    'plugin-slug-settings',
                    $sectionId,
                    [
                        'field_key' => $fieldKey,
                        'option_name' => $optionName,
                        'field_config' => $field
                    ]
                );
            }
        }
    }
    
    /**
     * Get WordPress setting type from field type
     */
    private static function getSettingType(string $fieldType): string
    {
        return match ($fieldType) {
            'checkbox' => 'boolean',
            'number' => 'integer',
            default => 'string'
        };
    }
    
    /**
     * Get sanitize callback for field type
     */
    private static function getSanitizeCallback(string $fieldType): callable
    {
        return match ($fieldType) {
            'checkbox' => fn($v) => (bool) $v,
            'number' => 'absint',
            'email' => 'sanitize_email',
            'url' => 'esc_url_raw',
            'textarea' => 'sanitize_textarea_field',
            default => 'sanitize_text_field'
        };
    }
    
    /**
     * Render section description
     */
    public static function renderSectionDescription(array $args): void
    {
        $sectionId = $args['id'];
        $descriptions = [
            'plugin_slug_general' => 'Configure basic plugin behavior.',
            'plugin_slug_email' => 'Configure email notification settings.',
            'plugin_slug_advanced' => 'Advanced configuration options. Use with caution.'
        ];
        
        $hasDescription = isset($descriptions[$sectionId]);
        if ($hasDescription) {
            echo '<p class="description">' . esc_html__($descriptions[$sectionId], 'plugin-slug') . '</p>';
        }
    }
    
    /**
     * Render individual field
     */
    public static function renderField(array $args): void
    {
        $optionName = $args['option_name'];
        $config = $args['field_config'];
        $value = get_option($optionName, $config['default']);
        
        switch ($config['type']) {
            case 'checkbox':
                self::renderCheckbox($optionName, $value, $config);
                break;
            case 'number':
                self::renderNumber($optionName, $value, $config);
                break;
            case 'select':
                self::renderSelect($optionName, $value, $config);
                break;
            case 'textarea':
                self::renderTextarea($optionName, $value, $config);
                break;
            case 'email':
            case 'url':
            case 'text':
            default:
                self::renderText($optionName, $value, $config);
                break;
        }
        
        // Description
        $hasDescription = !empty($config['description']);
        if ($hasDescription) {
            echo '<p class="description">' . esc_html__($config['description'], 'plugin-slug') . '</p>';
        }
    }
    
    /**
     * Render checkbox field
     */
    private static function renderCheckbox(string $name, $value, array $config): void
    {
        ?>
        <label>
            <input 
                type="checkbox" 
                name="<?php echo esc_attr($name); ?>" 
                value="1" 
                <?php checked($value, true); ?>
            />
            <?php echo esc_html($config['label'] ?? 'Enable'); ?>
        </label>
        <?php
    }
    
    /**
     * Render number field
     */
    private static function renderNumber(string $name, $value, array $config): void
    {
        ?>
        <input 
            type="number" 
            name="<?php echo esc_attr($name); ?>" 
            value="<?php echo esc_attr($value); ?>"
            class="small-text"
            min="<?php echo esc_attr($config['min'] ?? 0); ?>"
            max="<?php echo esc_attr($config['max'] ?? ''); ?>"
        />
        <?php
    }
    
    /**
     * Render select field
     */
    private static function renderSelect(string $name, $value, array $config): void
    {
        ?>
        <select name="<?php echo esc_attr($name); ?>">
            <?php foreach ($config['options'] as $optValue => $optLabel): ?>
                <option 
                    value="<?php echo esc_attr($optValue); ?>" 
                    <?php selected($value, $optValue); ?>
                >
                    <?php echo esc_html($optLabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Render text field
     */
    private static function renderText(string $name, $value, array $config): void
    {
        $type = $config['type'] ?? 'text';
        ?>
        <input 
            type="<?php echo esc_attr($type); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>"
        />
        <?php
    }
    
    /**
     * Render textarea field
     */
    private static function renderTextarea(string $name, $value, array $config): void
    {
        ?>
        <textarea 
            name="<?php echo esc_attr($name); ?>"
            class="large-text"
            rows="<?php echo esc_attr($config['rows'] ?? 5); ?>"
            placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>"
        ><?php echo esc_textarea($value); ?></textarea>
        <?php
    }
}
```

### Settings Page Template

```php
<?php
namespace PluginNamespace\Admin;

class AdminController
{
    /**
     * Render settings page
     */
    public static function renderSettings(): void
    {
        // Check permissions
        $canManage = current_user_can('manage_options');
        
        if (!$canManage) {
            wp_die(__('You do not have permission to access this page.', 'plugin-slug'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('plugin_slug_settings'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('plugin_slug_settings');
                do_settings_sections('plugin-slug-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
```

---

## 33.5 Admin Notices

### Notice Manager

```php
<?php
namespace PluginNamespace\Admin;

class AdminNotices
{
    /**
     * Notice types
     */
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';
    
    /**
     * Register notice hooks
     */
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'displayNotices']);
    }
    
    /**
     * Add a notice to be displayed
     */
    public static function add(
        string $message,
        string $type = self::TYPE_INFO,
        bool $isDismissible = true
    ): void {
        $notices = get_transient('plugin_slug_admin_notices') ?: [];
        
        $notices[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $isDismissible
        ];
        
        set_transient('plugin_slug_admin_notices', $notices, 60);
    }
    
    /**
     * Display all queued notices
     */
    public static function displayNotices(): void
    {
        // Display transient notices
        $notices = get_transient('plugin_slug_admin_notices');
        $hasNotices = !empty($notices);
        
        if ($hasNotices) {
            foreach ($notices as $notice) {
                self::renderNotice(
                    $notice['message'],
                    $notice['type'],
                    $notice['dismissible']
                );
            }
            delete_transient('plugin_slug_admin_notices');
        }
        
        // Display persistent notices (e.g., setup incomplete)
        self::displayPersistentNotices();
    }
    
    /**
     * Display persistent admin notices
     */
    private static function displayPersistentNotices(): void
    {
        // Check if on plugin page
        $currentScreen = get_current_screen();
        $isPluginPage = strpos($currentScreen->id ?? '', 'plugin-slug') !== false;
        
        if (!$isPluginPage) {
            return;
        }
        
        // Setup incomplete notice
        $isSetupComplete = get_option('plugin_slug_setup_complete', false);
        
        if (!$isSetupComplete) {
            self::renderNotice(
                sprintf(
                    __('Plugin setup is incomplete. <a href="%s">Complete setup</a>', 'plugin-slug'),
                    admin_url('admin.php?page=plugin-slug-settings')
                ),
                self::TYPE_WARNING,
                false
            );
        }
        
        // Debug mode warning
        $isDebugMode = get_option('plugin_slug_debug_mode', false);
        
        if ($isDebugMode) {
            self::renderNotice(
                __('Debug mode is enabled. Disable it in production.', 'plugin-slug'),
                self::TYPE_WARNING,
                true
            );
        }
    }
    
    /**
     * Render a single notice
     */
    private static function renderNotice(
        string $message,
        string $type,
        bool $isDismissible
    ): void {
        $classes = ['notice', "notice-{$type}"];
        
        if ($isDismissible) {
            $classes[] = 'is-dismissible';
        }
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
        <?php
    }
    
    /**
     * Shorthand methods
     */
    public static function success(string $message, bool $dismissible = true): void
    {
        self::add($message, self::TYPE_SUCCESS, $dismissible);
    }
    
    public static function error(string $message, bool $dismissible = true): void
    {
        self::add($message, self::TYPE_ERROR, $dismissible);
    }
    
    public static function warning(string $message, bool $dismissible = true): void
    {
        self::add($message, self::TYPE_WARNING, $dismissible);
    }
    
    public static function info(string $message, bool $dismissible = true): void
    {
        self::add($message, self::TYPE_INFO, $dismissible);
    }
}
```

---

## 33.6 Screen Options

### Screen Options Implementation

```php
<?php
namespace PluginNamespace\Admin;

class ScreenOptions
{
    /**
     * Register screen options
     */
    public static function register(): void
    {
        add_action('load-toplevel_page_plugin-slug', [self::class, 'addDashboardScreenOptions']);
        add_action('load-plugin-name_page_plugin-slug-items', [self::class, 'addItemsScreenOptions']);
        
        add_filter('set-screen-option', [self::class, 'saveScreenOption'], 10, 3);
    }
    
    /**
     * Add screen options for dashboard
     */
    public static function addDashboardScreenOptions(): void
    {
        $option = 'per_page';
        $args = [
            'label' => __('Items per page', 'plugin-slug'),
            'default' => 20,
            'option' => 'plugin_slug_dashboard_per_page'
        ];
        
        add_screen_option($option, $args);
    }
    
    /**
     * Add screen options for items list
     */
    public static function addItemsScreenOptions(): void
    {
        $option = 'per_page';
        $args = [
            'label' => __('Items per page', 'plugin-slug'),
            'default' => 20,
            'option' => 'plugin_slug_items_per_page'
        ];
        
        add_screen_option($option, $args);
    }
    
    /**
     * Save screen option value
     */
    public static function saveScreenOption($status, string $option, $value)
    {
        $pluginOptions = [
            'plugin_slug_dashboard_per_page',
            'plugin_slug_items_per_page'
        ];
        
        $isPluginOption = in_array($option, $pluginOptions, true);
        
        if ($isPluginOption) {
            return absint($value);
        }
        
        return $status;
    }
    
    /**
     * Get screen option value
     */
    public static function getPerPage(string $option, int $default = 20): int
    {
        $userId = get_current_user_id();
        $perPage = get_user_meta($userId, $option, true);
        
        $hasValue = !empty($perPage);
        
        return $hasValue ? absint($perPage) : $default;
    }
}
```

---

## 33.7 Help Tabs

### Help Tab Implementation

```php
<?php
namespace PluginNamespace\Admin;

class HelpTabs
{
    /**
     * Register help tabs
     */
    public static function register(): void
    {
        add_action('load-toplevel_page_plugin-slug', [self::class, 'addDashboardHelp']);
        add_action('load-plugin-name_page_plugin-slug-settings', [self::class, 'addSettingsHelp']);
    }
    
    /**
     * Add help tabs for dashboard
     */
    public static function addDashboardHelp(): void
    {
        $screen = get_current_screen();
        
        $hasScreen = ($screen !== null);
        if (!$hasScreen) {
            return;
        }
        
        // Overview tab
        $screen->add_help_tab([
            'id' => 'plugin_slug_overview',
            'title' => __('Overview', 'plugin-slug'),
            'content' => self::getOverviewContent()
        ]);
        
        // Getting started tab
        $screen->add_help_tab([
            'id' => 'plugin_slug_getting_started',
            'title' => __('Getting Started', 'plugin-slug'),
            'content' => self::getGettingStartedContent()
        ]);
        
        // Help sidebar
        $screen->set_help_sidebar(self::getHelpSidebar());
    }
    
    /**
     * Get overview help content
     */
    private static function getOverviewContent(): string
    {
        ob_start();
        ?>
        <h3><?php esc_html_e('Dashboard Overview', 'plugin-slug'); ?></h3>
        <p><?php esc_html_e('This dashboard provides an overview of your plugin data and quick access to common tasks.', 'plugin-slug'); ?></p>
        <ul>
            <li><?php esc_html_e('View recent activity', 'plugin-slug'); ?></li>
            <li><?php esc_html_e('Access quick actions', 'plugin-slug'); ?></li>
            <li><?php esc_html_e('Monitor statistics', 'plugin-slug'); ?></li>
        </ul>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get getting started help content
     */
    private static function getGettingStartedContent(): string
    {
        ob_start();
        ?>
        <h3><?php esc_html_e('Getting Started', 'plugin-slug'); ?></h3>
        <ol>
            <li><?php esc_html_e('Configure your settings', 'plugin-slug'); ?></li>
            <li><?php esc_html_e('Create your first item', 'plugin-slug'); ?></li>
            <li><?php esc_html_e('Review the documentation', 'plugin-slug'); ?></li>
        </ol>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get help sidebar content
     */
    private static function getHelpSidebar(): string
    {
        ob_start();
        ?>
        <p><strong><?php esc_html_e('For more information:', 'plugin-slug'); ?></strong></p>
        <p>
            <a href="https://docs.example.com/plugin-slug" target="_blank">
                <?php esc_html_e('Documentation', 'plugin-slug'); ?>
            </a>
        </p>
        <p>
            <a href="https://example.com/support" target="_blank">
                <?php esc_html_e('Support', 'plugin-slug'); ?>
            </a>
        </p>
        <?php
        return ob_get_clean();
    }
}
```

---

## 33.8 Admin Page Templates

### Base Template Structure

```php
<?php
// templates/admin/base.php
namespace PluginNamespace\Admin;

/**
 * Base admin page template
 */
function render_admin_page(string $title, callable $contentCallback, array $tabs = []): void
{
    ?>
    <div class="wrap plugin-slug-admin">
        <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
        
        <?php
        // Page action buttons
        do_action('plugin_slug_admin_page_actions');
        ?>
        
        <hr class="wp-header-end">
        
        <?php
        // Tabs navigation
        $hasTabs = !empty($tabs);
        if ($hasTabs) {
            render_admin_tabs($tabs);
        }
        ?>
        
        <div class="plugin-slug-content">
            <?php $contentCallback(); ?>
        </div>
    </div>
    <?php
}

/**
 * Render admin tabs
 */
function render_admin_tabs(array $tabs): void
{
    $currentTab = $_GET['tab'] ?? array_key_first($tabs);
    ?>
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <a 
                href="<?php echo esc_url(add_query_arg('tab', $tabKey)); ?>"
                class="nav-tab <?php echo $currentTab === $tabKey ? 'nav-tab-active' : ''; ?>"
            >
                <?php echo esc_html($tabLabel); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
```

### List Table Implementation

```php
<?php
namespace PluginNamespace\Admin;

use WP_List_Table;

class ItemsListTable extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'item',
            'plural' => 'items',
            'ajax' => false
        ]);
    }
    
    /**
     * Get columns
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', 'plugin-slug'),
            'status' => __('Status', 'plugin-slug'),
            'author' => __('Author', 'plugin-slug'),
            'date' => __('Date', 'plugin-slug')
        ];
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array
    {
        return [
            'title' => ['title', false],
            'status' => ['status', false],
            'date' => ['date', true]
        ];
    }
    
    /**
     * Prepare items
     */
    public function prepare_items(): void
    {
        $perPage = ScreenOptions::getPerPage('plugin_slug_items_per_page', 20);
        $currentPage = $this->get_pagenum();
        
        // Get items from database
        $items = $this->getItems($perPage, $currentPage);
        $totalItems = $this->getTotalItems();
        
        $this->items = $items;
        
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'total_pages' => ceil($totalItems / $perPage)
        ]);
    }
    
    /**
     * Column default
     */
    public function column_default($item, $columnName): string
    {
        return esc_html($item[$columnName] ?? '');
    }
    
    /**
     * Checkbox column
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="items[]" value="%d" />',
            $item['id']
        );
    }
    
    /**
     * Title column with actions
     */
    public function column_title($item): string
    {
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=plugin-slug-items&action=edit&id=' . $item['id']),
                __('Edit', 'plugin-slug')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=plugin-slug-items&action=delete&id=' . $item['id']),
                    'delete_item_' . $item['id']
                ),
                esc_js(__('Are you sure?', 'plugin-slug')),
                __('Delete', 'plugin-slug')
            )
        ];
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            admin_url('admin.php?page=plugin-slug-items&action=edit&id=' . $item['id']),
            esc_html($item['title']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Bulk actions
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'plugin-slug'),
            'activate' => __('Activate', 'plugin-slug'),
            'deactivate' => __('Deactivate', 'plugin-slug')
        ];
    }
}
```

---

## 33.9 Checklist

### Menu Registration
- [ ] Main menu registered with correct position and icon
- [ ] Submenus defined in centralized configuration
- [ ] Capabilities checked for each menu item
- [ ] First submenu duplicate removed/renamed
- [ ] Admin bar item added (optional)

### Asset Management
- [ ] Assets loaded only on plugin pages
- [ ] Scripts localized with nonces and i18n strings
- [ ] Version string uses file mtime (dev) or plugin version (prod)
- [ ] Media uploader enqueued only when needed
- [ ] Code editor enqueued only when needed

### Settings Pages
- [ ] Settings registered via Settings API
- [ ] All fields have sanitize callbacks
- [ ] Field types properly handled (checkbox, select, text, etc.)
- [ ] Default values defined for all settings
- [ ] Settings errors displayed properly

### Admin Notices
- [ ] Notice manager supports all types (success, error, warning, info)
- [ ] Notices are dismissible by default
- [ ] Persistent notices for important states
- [ ] Notices use transients for cross-request persistence

### Screen Options & Help
- [ ] Per-page options added for list views
- [ ] Screen option values saved and retrieved properly
- [ ] Help tabs added for main admin pages
- [ ] Help sidebar links to documentation

### Error Handling
- [ ] Asset enqueue wrapped in try-catch
- [ ] Errors logged with file, action, stack trace
- [ ] Permission checks on all admin pages

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Error logging
- [01-plugin-structure-wordpress.md](./01-plugin-structure-wordpress.md) - Plugin bootstrap
- [02-rest-api-wordpress.md](./02-rest-api-wordpress.md) - REST API for AJAX
- [05-sanitization-wordpress.md](./05-sanitization-wordpress.md) - Input sanitization
