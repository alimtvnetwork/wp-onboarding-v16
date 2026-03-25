<?php
/**
 * Plugin Name: Category Generator for Area by Riseup Asia LLC
 * Plugin URI: https://riseup-asia.com/
 * Description: A powerful WordPress plugin to generate categories by cross-joining titles and areas with templated HTML descriptions, Yoast SEO integration, and Local Business Schema. Developed by MD Alim Ul Karim, founder of Riseup Asia LLC - a leading technology solutions company specializing in innovative web development, digital transformation, and enterprise software solutions.
 * Version: 2.3.0
 * Author: MD Alim Ul Karim
 * Author URI: https://riseup-asia.com/
 * License: GPL v2 or later
 * Text Domain: category-generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim <contact@riseup-asia.com>
 * @copyright 2024 Riseup Asia LLC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CG_PLUGIN_VERSION', '2.3.0');
define('CG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core classes
require_once CG_PLUGIN_PATH . 'includes/class-constants.php';
require_once CG_PLUGIN_PATH . 'includes/class-css.php';
require_once CG_PLUGIN_PATH . 'includes/class-database.php';
require_once CG_PLUGIN_PATH . 'includes/class-variables.php';
require_once CG_PLUGIN_PATH . 'includes/class-inner-templates.php';
require_once CG_PLUGIN_PATH . 'includes/class-import-export.php';
require_once CG_PLUGIN_PATH . 'includes/class-settings.php';
require_once CG_PLUGIN_PATH . 'includes/class-tests.php';
require_once CG_PLUGIN_PATH . 'includes/class-snapshot.php';

/**
 * Main Plugin Class
 */
class Category_Generator_Pro {
    
    private static $instance = null;
    private $db = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CG_Database::get_instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'show_whats_new_notice'));
        add_action('wp_ajax_cg_dismiss_whats_new', array($this, 'ajax_dismiss_whats_new'));
        
        // AJAX handlers
        add_action('wp_ajax_cg_generate_categories', array($this, 'ajax_generate_categories'));
        add_action('wp_ajax_cg_preview_combinations', array($this, 'ajax_preview_combinations'));
        add_action('wp_ajax_cg_get_category_history', array($this, 'ajax_get_category_history'));
        add_action('wp_ajax_cg_get_history_item', array($this, 'ajax_get_history_item'));
        add_action('wp_ajax_cg_save_business_profile', array($this, 'ajax_save_business_profile'));
        add_action('wp_ajax_cg_get_business_profile', array($this, 'ajax_get_business_profile'));
        add_action('wp_ajax_cg_get_business_profiles', array($this, 'ajax_get_business_profiles'));
        add_action('wp_ajax_cg_delete_business_profile', array($this, 'ajax_delete_business_profile'));
        
        // Template AJAX handlers
        add_action('wp_ajax_cg_get_templates', array($this, 'ajax_get_templates'));
        add_action('wp_ajax_cg_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_cg_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_cg_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_cg_duplicate_template', array($this, 'ajax_duplicate_template'));
        
        // Inner Templates AJAX handlers
        add_action('wp_ajax_cg_get_inner_templates', array($this, 'ajax_get_inner_templates'));
        add_action('wp_ajax_cg_get_inner_template', array($this, 'ajax_get_inner_template'));
        add_action('wp_ajax_cg_save_inner_template', array($this, 'ajax_save_inner_template'));
        add_action('wp_ajax_cg_delete_inner_template', array($this, 'ajax_delete_inner_template'));
        
        // Variables AJAX handlers
        add_action('wp_ajax_cg_get_variables', array($this, 'ajax_get_variables'));
        add_action('wp_ajax_cg_save_variable', array($this, 'ajax_save_variable'));
        add_action('wp_ajax_cg_delete_variable', array($this, 'ajax_delete_variable'));
        
        // Settings AJAX handlers
        add_action('wp_ajax_cg_get_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_cg_save_settings', array($this, 'ajax_save_settings'));
        
        // Import/Export AJAX handlers
        add_action('wp_ajax_cg_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_cg_import_data', array($this, 'ajax_import_data'));
        add_action('wp_ajax_cg_get_import_history', array($this, 'ajax_get_import_history'));
        
        // Tests AJAX handlers
        add_action('wp_ajax_cg_run_tests', array($this, 'ajax_run_tests'));
        
        // Saved Titles/Areas AJAX handlers
        add_action('wp_ajax_cg_save_titles', array($this, 'ajax_save_titles'));
        add_action('wp_ajax_cg_save_areas', array($this, 'ajax_save_areas'));
        add_action('wp_ajax_cg_get_saved_titles', array($this, 'ajax_get_saved_titles'));
        add_action('wp_ajax_cg_get_saved_areas', array($this, 'ajax_get_saved_areas'));
        
        // Reset database
        add_action('wp_ajax_cg_reset_database', array($this, 'ajax_reset_database'));
        
        // History inject
        add_action('wp_ajax_cg_inject_inner_template', array($this, 'ajax_inject_inner_template'));
        add_action('wp_ajax_cg_get_term_description', array($this, 'ajax_get_term_description'));
        
        // Template categories
        add_action('wp_ajax_cg_save_template_category', array($this, 'ajax_save_template_category'));
        add_action('wp_ajax_cg_delete_template_category', array($this, 'ajax_delete_template_category'));
        
        // Database backup/restore
        add_action('wp_ajax_cg_download_database', array($this, 'ajax_download_database'));
        add_action('wp_ajax_cg_restore_database', array($this, 'ajax_restore_database'));
        
        // Snapshot AJAX handlers
        add_action('wp_ajax_cg_create_snapshot', array($this, 'ajax_create_snapshot'));
        add_action('wp_ajax_cg_restore_snapshot', array($this, 'ajax_restore_snapshot'));
        add_action('wp_ajax_cg_delete_snapshot', array($this, 'ajax_delete_snapshot'));
        add_action('wp_ajax_cg_download_snapshot', array($this, 'ajax_download_snapshot'));
        add_action('wp_ajax_cg_get_recent_snapshots', array($this, 'ajax_get_recent_snapshots'));
        
        // Bulk delete history handlers
        add_action('wp_ajax_cg_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
        add_action('wp_ajax_cg_bulk_delete_history_and_categories', array($this, 'ajax_bulk_delete_history_and_categories'));
    }
    
    /**
     * Show "What's New" admin notice after plugin update
     */
    public function show_whats_new_notice() {
        $current_version = CG_PLUGIN_VERSION;
        $dismissed_version = get_option('cg_whats_new_dismissed', '0');
        
        // Don't show if already dismissed for this version
        if (version_compare($dismissed_version, $current_version, '>=')) {
            return;
        }
        
        // Only show on plugin pages or dashboard
        $screen = get_current_screen();
        if (!$screen) return;
        
        $allowed_screens = array('dashboard', 'plugins');
        $is_plugin_page = strpos($screen->id, 'category-generator') !== false;
        
        if (!in_array($screen->id, $allowed_screens) && !$is_plugin_page) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible cg-whats-new-notice" data-version="<?php echo esc_attr($current_version); ?>">
            <h3 style="margin-bottom: 8px;">
                <span class="dashicons dashicons-megaphone" style="color: #2271b1;"></span>
                <?php printf(__("What's New in Category Generator %s", 'category-generator'), $current_version); ?>
            </h3>
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 10px;">
                <div>
                    <strong>🗄️ Database Backup & Restore</strong>
                    <p style="margin: 4px 0 0 0; color: #646970;">Download or restore SQLite database directly</p>
                </div>
                <div>
                    <strong>📂 Template Categories</strong>
                    <p style="margin: 4px 0 0 0; color: #646970;">Organize templates in 3-level hierarchy</p>
                </div>
                <div>
                    <strong>💉 History Inject</strong>
                    <p style="margin: 4px 0 0 0; color: #646970;">Inject inner templates into existing categories</p>
                </div>
                <div>
                    <strong>🔒 Safe Reset</strong>
                    <p style="margin: 4px 0 0 0; color: #646970;">Confirmation dialog with export option</p>
                </div>
            </div>
            <p style="margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=cg-settings'); ?>" class="button button-primary button-small">
                    <?php _e('View Settings', 'category-generator'); ?>
                </a>
                <a href="#" class="button button-small cg-dismiss-whats-new" style="margin-left: 8px;">
                    <?php _e('Dismiss', 'category-generator'); ?>
                </a>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.cg-whats-new-notice').on('click', '.notice-dismiss, .cg-dismiss-whats-new', function(e) {
                e.preventDefault();
                var version = $('.cg-whats-new-notice').data('version');
                $.post(ajaxurl, {
                    action: 'cg_dismiss_whats_new',
                    version: version,
                    nonce: '<?php echo wp_create_nonce('cg_dismiss_whats_new'); ?>'
                });
                $('.cg-whats-new-notice').fadeOut();
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to dismiss What's New notice
     */
    public function ajax_dismiss_whats_new() {
        check_ajax_referer('cg_dismiss_whats_new', 'nonce');
        $version = sanitize_text_field($_POST['version'] ?? CG_PLUGIN_VERSION);
        update_option('cg_whats_new_dismissed', $version);
        wp_send_json_success();
    }
    
    public function ajax_save_template_category() {
        check_ajax_referer('cg_nonce', 'nonce');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $template_type = sanitize_text_field($_POST['template_type'] ?? 'all');
        
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Name is required'));
        }
        
        $id = $this->db->save_template_category($name, $parent_id, $template_type);
        wp_send_json_success(array('id' => $id));
    }
    
    public function ajax_delete_template_category() {
        check_ajax_referer('cg_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        $this->db->delete_template_category($id);
        wp_send_json_success();
    }
    
    /**
     * Download database file
     */
    public function ajax_download_database() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        $db_path = $this->db->get_db_path();
        
        if (!file_exists($db_path)) {
            wp_die(__('Database file not found', 'category-generator'));
        }
        
        // Close database connection before reading
        $this->db->close();
        
        $filename = 'category-generator-backup-' . date('Y-m-d-His') . '.db';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($db_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($db_path);
        exit;
    }
    
    /**
     * Restore database from uploaded file
     */
    public function ajax_restore_database() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!isset($_FILES['database_file']) || $_FILES['database_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('No file uploaded or upload error', 'category-generator')));
        }
        
        $uploaded_file = $_FILES['database_file']['tmp_name'];
        $original_name = $_FILES['database_file']['name'];
        
        // Validate file extension
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!in_array($ext, array('db', 'sqlite', 'sqlite3'))) {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a .db, .sqlite, or .sqlite3 file', 'category-generator')));
        }
        
        // Validate that it's a valid SQLite database
        try {
            $test_db = new SQLite3($uploaded_file);
            $test_db->query('SELECT 1');
            $test_db->close();
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Invalid SQLite database file', 'category-generator')));
        }
        
        $db_path = $this->db->get_db_path();
        
        // Close current database connection
        $this->db->close();
        
        // Create backup of current database
        $backup_path = $db_path . '.bak-' . date('Y-m-d-His');
        if (file_exists($db_path)) {
            copy($db_path, $backup_path);
        }
        
        // Replace database with uploaded file
        if (!move_uploaded_file($uploaded_file, $db_path)) {
            // Restore backup if move failed
            if (file_exists($backup_path)) {
                copy($backup_path, $db_path);
            }
            wp_send_json_error(array('message' => __('Failed to restore database', 'category-generator')));
        }
        
        wp_send_json_success(array('message' => __('Database restored successfully', 'category-generator')));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Category Generator', 'category-generator'),
            __('Category Generator', 'category-generator'),
            'manage_categories',
            'category-generator',
            array($this, 'render_admin_page'),
            'dashicons-category',
            30
        );
        
        add_submenu_page('category-generator', __('Generate', 'category-generator'), __('Generate', 'category-generator'), 'manage_categories', 'category-generator', array($this, 'render_admin_page'));
        add_submenu_page('category-generator', __('Snapshots', 'category-generator'), __('Snapshots', 'category-generator'), 'manage_categories', 'cg-snapshots', array($this, 'render_snapshots_page'));
        add_submenu_page('category-generator', __('History', 'category-generator'), __('History', 'category-generator'), 'manage_categories', 'cg-history', array($this, 'render_history_page'));
        add_submenu_page('category-generator', __('Templates', 'category-generator'), __('Templates', 'category-generator'), 'manage_categories', 'cg-templates', array($this, 'render_templates_page'));
        add_submenu_page('category-generator', __('Inner Templates', 'category-generator'), __('Inner Templates', 'category-generator'), 'manage_categories', 'cg-inner-templates', array($this, 'render_inner_templates_page'));
        add_submenu_page('category-generator', __('Business Profile', 'category-generator'), __('Business Profile', 'category-generator'), 'manage_categories', 'cg-business-profile', array($this, 'render_business_profile_page'));
        add_submenu_page('category-generator', __('Settings', 'category-generator'), __('Settings', 'category-generator'), 'manage_categories', 'cg-settings', array($this, 'render_settings_page'));
        add_submenu_page('category-generator', __('Test Cases', 'category-generator'), __('Test Cases', 'category-generator'), 'manage_categories', 'cg-tests', array($this, 'render_tests_page'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = array(
            'toplevel_page_category-generator',
            'category-generator_page_cg-snapshots',
            'category-generator_page_cg-history',
            'category-generator_page_cg-templates',
            'category-generator_page_cg-inner-templates',
            'category-generator_page_cg-business-profile',
            'category-generator_page_cg-settings',
            'category-generator_page_cg-tests'
        );
        
        if (!in_array($hook, $allowed_hooks)) {
            return;
        }
        
        // Enqueue Font Awesome 5
        wp_enqueue_style(
            'font-awesome-5',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        wp_enqueue_style(
            'category-generator-admin',
            CG_PLUGIN_URL . 'assets/css/admin.css',
            array('font-awesome-5'),
            CG_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'category-generator-admin',
            CG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CG_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('category-generator-admin', 'cgAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cg_nonce'),
            'currentPage' => $hook,
            'strings' => array(
                'generating' => __('Generating categories...', 'category-generator'),
                'success' => __('Categories created successfully!', 'category-generator'),
                'error' => __('An error occurred. Please try again.', 'category-generator'),
                'confirm' => __('Are you sure you want to create these categories?', 'category-generator'),
                'saved' => __('Saved successfully!', 'category-generator'),
                'deleted' => __('Deleted successfully!', 'category-generator'),
                'loading' => __('Loading...', 'category-generator'),
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        $html_templates = $this->db->get_html_templates();
        $meta_templates = $this->db->get_meta_templates();
        $schema_templates = $this->db->get_schema_templates();
        $business_profile = $this->db->get_business_profile();
        
        include CG_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        include CG_PLUGIN_PATH . 'templates/history-page.php';
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page() {
        $html_templates = $this->db->get_html_templates();
        $meta_templates = $this->db->get_meta_templates();
        $schema_templates = $this->db->get_schema_templates();
        include CG_PLUGIN_PATH . 'templates/templates-page.php';
    }
    
    public function render_inner_templates_page() {
        include CG_PLUGIN_PATH . 'templates/inner-templates-page.php';
    }
    
    public function render_settings_page() {
        $db = $this->db;
        include CG_PLUGIN_PATH . 'templates/settings-page.php';
    }
    
    public function render_tests_page() {
        include CG_PLUGIN_PATH . 'templates/tests-page.php';
    }
    
    public function render_snapshots_page() {
        include CG_PLUGIN_PATH . 'templates/snapshots-page.php';
    }
    
    public function render_business_profile_page() {
        $business_profile = $this->db->get_business_profile();
        include CG_PLUGIN_PATH . 'templates/business-profile-page.php';
    }
    
    /**
     * AJAX: Preview combinations with existence check
     */
    public function ajax_preview_combinations() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $titles = $this->parse_input(sanitize_textarea_field($_POST['titles'] ?? ''));
        $areas = $this->parse_input(sanitize_textarea_field($_POST['areas'] ?? ''));
        $format = sanitize_text_field($_POST['format'] ?? '{title} {area}');
        $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
        $create_parents = isset($_POST['create_parents']) && $_POST['create_parents'] === 'true';
        $make_children = isset($_POST['make_children']) && $_POST['make_children'] === 'true';
        
        if (empty($titles) || empty($areas)) {
            wp_send_json_error(array('message' => __('Please enter both titles and areas.', 'category-generator')));
        }
        
        $preview_data = array();
        $parent_categories = array();
        $child_categories = array();
        $existing_parents = 0;
        $new_parents = 0;
        $existing_children = 0;
        $new_children = 0;
        
        // Process each title as potential parent
        foreach ($titles as $title) {
            $title_exists = term_exists($title, $taxonomy);
            $parent_status = $title_exists ? 'exists' : 'new';
            
            if ($title_exists) {
                $existing_parents++;
            } else {
                $new_parents++;
            }
            
            $parent_categories[] = array(
                'name' => $title,
                'status' => $parent_status,
                'is_parent' => true
            );
            
            // Process children for this parent
            foreach ($areas as $area) {
                // Check for (S) notation - indicates sub-category
                $is_sub = false;
                $clean_area = $area;
                
                if (preg_match('/\(S\)\s*$/i', $area)) {
                    $is_sub = true;
                    $clean_area = trim(preg_replace('/\(S\)\s*$/i', '', $area));
                }
                
                // Determine if this should be a child
                $should_be_child = $make_children || $is_sub;
                
                $category_name = str_replace(
                    array('{title}', '{area}'),
                    array($title, $clean_area),
                    $format
                );
                
                $cat_exists = term_exists($category_name, $taxonomy);
                $cat_status = $cat_exists ? 'exists' : 'new';
                
                if ($cat_exists) {
                    $existing_children++;
                } else {
                    $new_children++;
                }
                
                $child_categories[] = array(
                    'name' => $category_name,
                    'title' => $title,
                    'area' => $clean_area,
                    'status' => $cat_status,
                    'is_parent' => false,
                    'will_be_child' => $should_be_child,
                    'parent_name' => $should_be_child ? $title : null,
                    'marked_with_s' => $is_sub
                );
            }
        }
        
        wp_send_json_success(array(
            'create_parents' => $create_parents,
            'make_children' => $make_children,
            'parent_categories' => $parent_categories,
            'child_categories' => $child_categories,
            'summary' => array(
                'total_parents' => count($titles),
                'new_parents' => $new_parents,
                'existing_parents' => $existing_parents,
                'total_children' => count($child_categories),
                'new_children' => $new_children,
                'existing_children' => $existing_children
            )
        ));
    }
    
    /**
     * AJAX: Generate categories with full features
     */
    public function ajax_generate_categories() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        // Get form data
        $titles = $this->parse_input(sanitize_textarea_field($_POST['titles'] ?? ''));
        $areas = $this->parse_input(sanitize_textarea_field($_POST['areas'] ?? ''));
        $format = sanitize_text_field($_POST['format'] ?? '{title} {area}');
        $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
        $static_parent_id = intval($_POST['parent_id'] ?? 0);
        
        // Options
        $create_parents = isset($_POST['create_parents']) && $_POST['create_parents'] === 'true';
        $make_children = isset($_POST['make_children']) && $_POST['make_children'] === 'true';
        $update_existing_meta = isset($_POST['update_existing_meta']) && $_POST['update_existing_meta'] === 'true';
        $include_schema = isset($_POST['include_schema']) && $_POST['include_schema'] === 'true';
        $use_global_schema = isset($_POST['use_global_schema']) && $_POST['use_global_schema'] === 'true';
        
        // Templates content
        $html_content = wp_kses_post($_POST['html_template'] ?? '');
        $meta_title_pattern = sanitize_text_field($_POST['meta_title_pattern'] ?? '');
        $meta_desc_pattern = sanitize_textarea_field($_POST['meta_description_pattern'] ?? '');
        $slug_pattern = sanitize_text_field($_POST['slug_pattern'] ?? '');
        $schema_content = $_POST['schema_template'] ?? '';
        
        if (empty($titles) || empty($areas)) {
            wp_send_json_error(array('message' => __('Please enter both titles and areas.', 'category-generator')));
        }
        
        // Auto-snapshot before generation if enabled
        $settings = CG_Settings::get_instance();
        if ($settings->get('auto_snapshot_before_generate', false)) {
            $snapshot = CG_Snapshot::get_instance();
            $snapshot->create_snapshot(
                sprintf(__('Before generation - %s', 'category-generator'), date('M j, H:i')),
                sprintf(__('Auto-snapshot before generating %d combinations', 'category-generator'), count($titles) * count($areas)),
                'auto'
            );
        }
        
        // Get business profile for placeholders
        $business_profile = $this->db->get_business_profile();
        
        $results = array(
            'parents_created' => array(),
            'parents_existed' => array(),
            'categories_created' => array(),
            'categories_existed' => array(),
            'meta_updated' => array(),
            'errors' => array()
        );
        
        // Track parent term IDs
        $parent_term_ids = array();
        
        // Step 1: Create parent categories if enabled
        if ($create_parents) {
            foreach ($titles as $title) {
                $existing = term_exists($title, $taxonomy);
                
                if ($existing) {
                    $parent_term_ids[$title] = is_array($existing) ? $existing['term_id'] : $existing;
                    $results['parents_existed'][] = $title;
                } else {
                    $parent_result = wp_insert_term(
                        $title,
                        $taxonomy,
                        array(
                            'slug' => sanitize_title($title),
                            'parent' => $static_parent_id
                        )
                    );
                    
                    if (!is_wp_error($parent_result)) {
                        $parent_term_ids[$title] = $parent_result['term_id'];
                        $results['parents_created'][] = array(
                            'id' => $parent_result['term_id'],
                            'name' => $title
                        );
                        
                        // Log to history
                        $this->db->insert_category_history(array(
                            'term_id' => $parent_result['term_id'],
                            'name' => $title,
                            'slug' => sanitize_title($title),
                            'title' => $title,
                            'area' => '',
                            'parent_id' => $static_parent_id,
                            'taxonomy' => $taxonomy,
                            'meta_title' => '',
                            'meta_description' => '',
                            'has_schema' => 0
                        ));
                    } else {
                        $results['errors'][] = sprintf(__('Error creating parent "%s": %s', 'category-generator'), $title, $parent_result->get_error_message());
                    }
                }
            }
        }
        
        // Step 2: Create cross-joined categories
        foreach ($titles as $title) {
            foreach ($areas as $area) {
                // Check for (S) notation
                $is_sub = false;
                $clean_area = $area;
                
                if (preg_match('/\(S\)\s*$/i', $area)) {
                    $is_sub = true;
                    $clean_area = trim(preg_replace('/\(S\)\s*$/i', '', $area));
                }
                
                // Determine parent ID for this category
                $should_be_child = $make_children || $is_sub;
                $parent_id = $static_parent_id;
                
                if ($should_be_child && isset($parent_term_ids[$title])) {
                    $parent_id = $parent_term_ids[$title];
                } elseif ($should_be_child) {
                    // Check if parent exists in WP
                    $parent_term = term_exists($title, $taxonomy);
                    if ($parent_term) {
                        $parent_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;
                    }
                }
                
                // Generate category name
                $category_name = str_replace(
                    array('{title}', '{area}'),
                    array($title, $clean_area),
                    $format
                );
                
                // Generate slug
                $slug = $this->generate_from_pattern($slug_pattern, $title, $clean_area, $category_name, $business_profile);
                if (empty($slug)) {
                    $slug = sanitize_title($category_name);
                } else {
                    $slug = sanitize_title($slug);
                }
                
                // Generate meta fields
                $meta_title = $this->generate_from_pattern($meta_title_pattern, $title, $clean_area, $category_name, $business_profile);
                $meta_description = $this->generate_from_pattern($meta_desc_pattern, $title, $clean_area, $category_name, $business_profile);
                
                // Get settings for class names
                $settings = CG_Settings::get_instance();
                $classes = $settings->get_class_settings();
                
                // Generate HTML description with proper div wrapper (NOT p tag)
                $html_body = $this->generate_description($html_content, $title, $clean_area, $category_name, $slug, $meta_title, $meta_description, $business_profile);
                
                // Process inner templates
                $inner_templates = CG_Inner_Templates::get_instance();
                $html_body = $inner_templates->process_content($html_body, array(
                    'title' => $title,
                    'area' => $clean_area,
                    'category' => $category_name,
                    'slug' => $slug,
                    'url' => home_url('/' . $slug . '/'),
                    'business_profile' => $business_profile
                ));
                
                // Wrap in div with proper class (NEVER p tag)
                $description = '<div class="' . esc_attr($classes['wrapper_class']) . '">' . $html_body . '</div>';
                
                // Generate schema if enabled - wrap in div
                if ($include_schema && !empty($schema_content)) {
                    $schema_json = $this->generate_schema($schema_content, $title, $clean_area, $category_name, $slug, $meta_description, $business_profile, $use_global_schema);
                    $description .= "\n\n<div class=\"" . esc_attr($classes['schema_wrapper_class']) . "\"><script type=\"application/ld+json\">\n" . $schema_json . "\n</script></div>";
                }
                
                // Check if category exists
                $existing = term_exists($category_name, $taxonomy);
                
                if ($existing) {
                    $term_id = is_array($existing) ? $existing['term_id'] : $existing;
                    $results['categories_existed'][] = $category_name;
                    
                    // Update Yoast meta if requested
                    if ($update_existing_meta && !empty($meta_title)) {
                        $this->update_yoast_meta($term_id, $taxonomy, $meta_title, $meta_description);
                        $results['meta_updated'][] = $category_name;
                    }
                } else {
                    // Create the category
                    $result = wp_insert_term(
                        $category_name,
                        $taxonomy,
                        array(
                            'description' => $description,
                            'slug' => $slug,
                            'parent' => $parent_id
                        )
                    );
                    
                    if (is_wp_error($result)) {
                        $results['errors'][] = sprintf(__('Error creating "%s": %s', 'category-generator'), $category_name, $result->get_error_message());
                    } else {
                        $term_id = $result['term_id'];
                        
                        // Update Yoast meta
                        if (!empty($meta_title)) {
                            $this->update_yoast_meta($term_id, $taxonomy, $meta_title, $meta_description);
                        }
                        
                        $results['categories_created'][] = array(
                            'id' => $term_id,
                            'name' => $category_name,
                            'slug' => $slug,
                            'parent' => $should_be_child ? $title : null
                        );
                        
                        // Log to history
                        $this->db->insert_category_history(array(
                            'term_id' => $term_id,
                            'name' => $category_name,
                            'slug' => $slug,
                            'title' => $title,
                            'area' => $clean_area,
                            'parent_id' => $parent_id,
                            'taxonomy' => $taxonomy,
                            'meta_title' => $meta_title,
                            'meta_description' => $meta_description,
                            'has_schema' => $include_schema ? 1 : 0
                        ));
                    }
                }
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Update Yoast SEO meta for a term - FIXED to properly save all Yoast fields
     */
    private function update_yoast_meta($term_id, $taxonomy, $meta_title, $meta_description, $focus_keyword = '') {
        // Generate focus keyword if not provided
        if (empty($focus_keyword)) {
            $settings = CG_Settings::get_instance();
            $pattern = $settings->get('yoast_focus_keyword_pattern', '{title} {area}');
            // Focus keyword will be set from the calling context
        }
        
        // Check if Yoast is active and save to proper Yoast meta keys
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Taxonomy_Meta')) {
            // Primary Yoast meta keys
            update_term_meta($term_id, '_yoast_wpseo_title', $meta_title);
            update_term_meta($term_id, '_yoast_wpseo_metadesc', $meta_description);
            update_term_meta($term_id, '_yoast_wpseo_focuskw', $focus_keyword);
            
            // Also update the taxonomy meta format Yoast uses internally
            $tax_meta = get_option('wpseo_taxonomy_meta', array());
            if (!isset($tax_meta[$taxonomy])) {
                $tax_meta[$taxonomy] = array();
            }
            $tax_meta[$taxonomy][$term_id] = array(
                'wpseo_title' => $meta_title,
                'wpseo_desc' => $meta_description,
                'wpseo_focuskw' => $focus_keyword
            );
            update_option('wpseo_taxonomy_meta', $tax_meta);
        }
        
        // Also set standard WP term meta as fallback
        update_term_meta($term_id, 'cg_meta_title', $meta_title);
        update_term_meta($term_id, 'cg_meta_description', $meta_description);
    }
    
    /**
     * Generate content from pattern with placeholders
     */
    private function generate_from_pattern($pattern, $title, $area, $category, $business_profile = array()) {
        if (empty($pattern)) {
            return '';
        }
        
        $placeholders = array(
            '{title}' => $title,
            '{area}' => $area,
            '{category}' => $category,
            '{name}' => $category,
            '{Title}' => ucwords($title),
            '{Area}' => ucwords($area),
            '{TITLE}' => strtoupper($title),
            '{AREA}' => strtoupper($area),
            '{title_lower}' => strtolower($title),
            '{area_lower}' => strtolower($area),
            
            // Business profile placeholders
            '{business_name}' => $business_profile['business_name'] ?? '',
            '{business_type}' => $business_profile['business_type'] ?? 'LocalBusiness',
            '{phone}' => $business_profile['phone'] ?? '',
            '{email}' => $business_profile['email'] ?? '',
            '{website}' => $business_profile['website'] ?? '',
            '{street_address}' => $business_profile['street_address'] ?? '',
            '{city}' => $business_profile['city'] ?? '',
            '{state}' => $business_profile['state'] ?? '',
            '{postal_code}' => $business_profile['postal_code'] ?? '',
            '{country}' => $business_profile['country'] ?? 'Australia',
            '{opening_hours}' => $business_profile['opening_hours'] ?? '',
            '{price_range}' => $business_profile['price_range'] ?? '',
            '{rating_value}' => $business_profile['rating_value'] ?? '5.0',
            '{rating_count}' => $business_profile['rating_count'] ?? '100',
            '{logo_url}' => $business_profile['logo_url'] ?? '',
            '{image_url}' => $business_profile['image_url'] ?? '',
        );
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $pattern);
    }
    
    /**
     * Generate description with all placeholders
     */
    private function generate_description($template, $title, $area, $category, $slug, $meta_title, $meta_desc, $business_profile) {
        if (empty($template)) {
            return sprintf(
                '<p>Explore our comprehensive %s services in %s. Find the best %s options tailored to your needs.</p>',
                esc_html($title),
                esc_html($area),
                esc_html($category)
            );
        }
        
        // Get site URL for generating category URL
        $category_url = home_url('/' . $slug . '/');
        $contact_url = $business_profile['website'] ?? home_url('/contact/');
        
        $placeholders = array(
            '{title}' => $title,
            '{area}' => $area,
            '{category}' => $category,
            '{name}' => $category,
            '{slug}' => $slug,
            '{url}' => $category_url,
            '{meta_title}' => $meta_title,
            '{meta_description}' => $meta_desc,
            '{meta_desc}' => $meta_desc,
            '{Title}' => ucwords($title),
            '{Area}' => ucwords($area),
            '{TITLE}' => strtoupper($title),
            '{AREA}' => strtoupper($area),
            
            // Business placeholders
            '{business_name}' => $business_profile['business_name'] ?? '',
            '{business_type}' => $business_profile['business_type'] ?? 'LocalBusiness',
            '{phone}' => $business_profile['phone'] ?? '',
            '{email}' => $business_profile['email'] ?? '',
            '{website}' => $business_profile['website'] ?? '',
            '{contact_url}' => $contact_url,
            '{street_address}' => $business_profile['street_address'] ?? '',
            '{city}' => $business_profile['city'] ?? '',
            '{state}' => $business_profile['state'] ?? '',
            '{postal_code}' => $business_profile['postal_code'] ?? '',
            '{country}' => $business_profile['country'] ?? 'Australia',
            '{rating_value}' => $business_profile['rating_value'] ?? '5.0',
            '{rating_count}' => $business_profile['rating_count'] ?? '100',
        );
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Generate Schema.org JSON-LD
     */
    private function generate_schema($template, $title, $area, $category, $slug, $meta_desc, $business_profile, $use_global = true) {
        $category_url = home_url('/' . $slug . '/');
        
        $placeholders = array(
            '{title}' => $title,
            '{area}' => $area,
            '{category}' => $category,
            '{slug}' => $slug,
            '{url}' => $category_url,
            '{meta_description}' => $meta_desc,
            
            // Business placeholders
            '{business_name}' => $business_profile['business_name'] ?? '',
            '{business_type}' => $business_profile['business_type'] ?? 'LocalBusiness',
            '{phone}' => $business_profile['phone'] ?? '',
            '{email}' => $business_profile['email'] ?? '',
            '{website}' => $business_profile['website'] ?? '',
            '{street_address}' => $business_profile['street_address'] ?? '',
            '{city}' => $business_profile['city'] ?? '',
            '{state}' => $business_profile['state'] ?? '',
            '{postal_code}' => $business_profile['postal_code'] ?? '',
            '{country}' => $business_profile['country'] ?? 'Australia',
            '{opening_hours}' => $business_profile['opening_hours'] ?? '"Mo-Fr 08:00-17:00"',
            '{price_range}' => $business_profile['price_range'] ?? '$$',
            '{rating_value}' => $business_profile['rating_value'] ?? '5.0',
            '{rating_count}' => $business_profile['rating_count'] ?? '100',
            '{logo_url}' => $business_profile['logo_url'] ?? '',
            '{image_url}' => $business_profile['image_url'] ?? '',
            '{latitude}' => '',
            '{longitude}' => '',
        );
        
        $schema = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        
        // Clean up empty values in JSON
        $schema = preg_replace('/"[^"]*":\s*""\s*,?\s*/', '', $schema);
        $schema = preg_replace('/,\s*}/', '}', $schema);
        $schema = preg_replace('/,\s*]/', ']', $schema);
        
        return $schema;
    }
    
    /**
     * Parse input textarea into array
     */
    private function parse_input($input) {
        $lines = explode("\n", $input);
        $items = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $items[] = $line;
            }
        }
        
        return $items;
    }
    
    /**
     * AJAX: Get category history
     */
    public function ajax_get_category_history() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page_raw = sanitize_text_field($_POST['per_page'] ?? '50');
        $include_yoast = isset($_POST['include_yoast']) && $_POST['include_yoast'] == '1';
        
        // Handle 'all' option
        if ($per_page_raw === 'all') {
            $total = $this->db->get_category_history_count($search);
            $per_page = $total > 0 ? $total : 1;
            $offset = 0;
            $page = 1;
        } else {
            $per_page = intval($per_page_raw);
            $offset = ($page - 1) * $per_page;
        }
        
        $history = $this->db->get_category_history(array(
            'search' => $search,
            'limit' => $per_page,
            'offset' => $offset
        ));
        
        // Add Yoast SEO scores if requested
        if ($include_yoast && defined('WPSEO_VERSION')) {
            foreach ($history as &$item) {
                $item['yoast_score'] = 0;
                if (!empty($item['term_id'])) {
                    // Get Yoast SEO score for taxonomy term
                    $score = get_term_meta($item['term_id'], '_yoast_wpseo_linkdex', true);
                    if ($score) {
                        $item['yoast_score'] = intval($score);
                    }
                }
            }
        }
        
        $total = $this->db->get_category_history_count($search);
        
        wp_send_json_success(array(
            'history' => $history,
            'total' => $total,
            'pages' => $per_page_raw === 'all' ? 1 : ceil($total / $per_page),
            'current_page' => $page
        ));
    }
    
    /**
     * AJAX: Bulk delete history logs only
     */
    public function ajax_bulk_delete_history() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No items selected.', 'category-generator')));
        }
        
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->db->delete_history_item($id)) {
                $deleted++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d log(s) deleted.', 'category-generator'), $deleted),
            'deleted' => $deleted
        ));
    }
    
    /**
     * AJAX: Bulk delete history logs AND WordPress categories
     */
    public function ajax_bulk_delete_history_and_categories() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        $terms = isset($_POST['terms']) ? $_POST['terms'] : array();
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No items selected.', 'category-generator')));
        }
        
        $logs_deleted = 0;
        $terms_deleted = 0;
        
        // Delete WordPress terms first
        foreach ($terms as $term_data) {
            $term_id = intval($term_data['term_id']);
            $taxonomy = sanitize_text_field($term_data['taxonomy']);
            
            if ($term_id > 0 && !empty($taxonomy)) {
                $result = wp_delete_term($term_id, $taxonomy);
                if ($result && !is_wp_error($result)) {
                    $terms_deleted++;
                }
            }
        }
        
        // Delete history logs
        foreach ($ids as $id) {
            if ($this->db->delete_history_item($id)) {
                $logs_deleted++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d log(s) and %d category(ies) deleted.', 'category-generator'), $logs_deleted, $terms_deleted),
            'logs_deleted' => $logs_deleted,
            'terms_deleted' => $terms_deleted
        ));
    }
    
    /**
     * AJAX: Save business profile
     */
    public function ajax_save_business_profile() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $data = array(
            'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
            'business_type' => sanitize_text_field($_POST['business_type'] ?? 'LocalBusiness'),
            'street_address' => sanitize_text_field($_POST['street_address'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? 'Australia'),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'opening_hours' => sanitize_textarea_field($_POST['opening_hours'] ?? ''),
            'price_range' => sanitize_text_field($_POST['price_range'] ?? ''),
            'service_areas' => sanitize_textarea_field($_POST['service_areas'] ?? ''),
            'services_offered' => sanitize_textarea_field($_POST['services_offered'] ?? ''),
            'rating_value' => floatval($_POST['rating_value'] ?? 5.0),
            'rating_count' => intval($_POST['rating_count'] ?? 100),
            'logo_url' => esc_url_raw($_POST['logo_url'] ?? ''),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'social_profiles' => sanitize_textarea_field($_POST['social_profiles'] ?? '')
        );
        
        $this->db->save_business_profile($data);
        
        wp_send_json_success(array('message' => __('Business profile saved!', 'category-generator')));
    }
    
    /**
     * AJAX: Get business profile
     */
    public function ajax_get_business_profile() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $profile = $this->db->get_business_profile();
        wp_send_json_success($profile);
    }
    
    /**
     * AJAX: Get templates
     */
    public function ajax_get_templates() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'html');
        
        switch ($type) {
            case 'meta':
                $templates = $this->db->get_meta_templates();
                break;
            case 'schema':
                $templates = $this->db->get_schema_templates();
                break;
            default:
                $templates = $this->db->get_html_templates();
        }
        
        wp_send_json_success($templates);
    }
    
    /**
     * AJAX: Get single template
     */
    public function ajax_get_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'html');
        $id = intval($_POST['id'] ?? 0);
        
        switch ($type) {
            case 'meta':
                $template = $this->db->get_meta_template($id);
                break;
            case 'schema':
                $template = $this->db->get_schema_template($id);
                break;
            default:
                $template = $this->db->get_html_template($id);
        }
        
        if ($template) {
            wp_send_json_success($template);
        } else {
            wp_send_json_error(array('message' => __('Template not found.', 'category-generator')));
        }
    }
    
    /**
     * AJAX: Save template
     */
    public function ajax_save_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'html');
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Name is required.', 'category-generator')));
        }
        
        switch ($type) {
            case 'meta':
                $meta_title = sanitize_text_field($_POST['meta_title_pattern'] ?? '');
                $meta_desc = sanitize_textarea_field($_POST['meta_description_pattern'] ?? '');
                $slug = sanitize_text_field($_POST['slug_pattern'] ?? '');
                
                if ($id > 0) {
                    $this->db->update_meta_template($id, $name, $meta_title, $meta_desc, $slug);
                } else {
                    $id = $this->db->insert_meta_template($name, $meta_title, $meta_desc, $slug);
                }
                break;
                
            case 'schema':
                $schema_type = sanitize_text_field($_POST['schema_type'] ?? 'LocalBusiness');
                $content = $_POST['content'] ?? '';
                
                if ($id > 0) {
                    $this->db->update_schema_template($id, $name, $schema_type, $content);
                } else {
                    $id = $this->db->insert_schema_template($name, $schema_type, $content);
                }
                break;
                
            default: // html
                $description = sanitize_textarea_field($_POST['description'] ?? '');
                $content = wp_kses_post($_POST['content'] ?? '');
                
                if ($id > 0) {
                    $this->db->update_html_template($id, $name, $description, $content);
                } else {
                    $id = $this->db->insert_html_template($name, $description, $content);
                }
        }
        
        wp_send_json_success(array('id' => $id, 'message' => __('Template saved!', 'category-generator')));
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'html');
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'category-generator')));
        }
        
        switch ($type) {
            case 'meta':
                $this->db->delete_meta_template($id);
                break;
            case 'schema':
                $this->db->delete_schema_template($id);
                break;
            default:
                $this->db->delete_html_template($id);
        }
        
        wp_send_json_success(array('message' => __('Template deleted!', 'category-generator')));
    }
    
    /**
     * AJAX: Get single history item for view modal
     */
    public function ajax_get_history_item() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $id = intval($_POST['id'] ?? 0);
        $item = $this->db->get_category_history_item($id);
        if ($item) {
            wp_send_json_success($item);
        } else {
            wp_send_json_error(array('message' => __('Item not found.', 'category-generator')));
        }
    }
    
    /**
     * AJAX: Get all business profiles
     */
    public function ajax_get_business_profiles() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $profiles = $this->db->get_all_business_profiles();
        wp_send_json_success($profiles);
    }
    
    /**
     * AJAX: Delete business profile
     */
    public function ajax_delete_business_profile() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $id = intval($_POST['id'] ?? 0);
        $this->db->delete_business_profile($id);
        wp_send_json_success(array('message' => __('Profile deleted!', 'category-generator')));
    }
    
    /**
     * AJAX: Duplicate template
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'html');
        $id = intval($_POST['id'] ?? 0);
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');
        
        $new_id = 0;
        switch ($type) {
            case 'meta':
                $template = $this->db->get_meta_template($id);
                if ($template) {
                    $new_id = $this->db->insert_meta_template(
                        $new_name ?: $template['name'] . ' (Copy)',
                        $template['meta_title_pattern'],
                        $template['meta_description_pattern'],
                        $template['slug_pattern']
                    );
                }
                break;
            case 'schema':
                $template = $this->db->get_schema_template($id);
                if ($template) {
                    $new_id = $this->db->insert_schema_template(
                        $new_name ?: $template['name'] . ' (Copy)',
                        $template['schema_type'],
                        $template['schema_content']
                    );
                }
                break;
            default:
                $template = $this->db->get_html_template($id);
                if ($template) {
                    $new_id = $this->db->insert_html_template(
                        $new_name ?: $template['name'] . ' (Copy)',
                        $template['description'] ?? '',
                        $template['content']
                    );
                }
        }
        
        if ($new_id) {
            wp_send_json_success(array('id' => $new_id, 'message' => __('Template duplicated!', 'category-generator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to duplicate.', 'category-generator')));
        }
    }
    
    /**
     * AJAX: Get inner templates
     */
    public function ajax_get_inner_templates() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $inner = CG_Inner_Templates::get_instance();
        $templates = $inner->get_all();
        wp_send_json_success($templates);
    }
    
    /**
     * AJAX: Get single inner template
     */
    public function ajax_get_inner_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $id = intval($_POST['id'] ?? 0);
        $inner = CG_Inner_Templates::get_instance();
        $template = $inner->get($id);
        if ($template) {
            wp_send_json_success($template);
        } else {
            wp_send_json_error(array('message' => __('Template not found.', 'category-generator')));
        }
    }
    
    /**
     * AJAX: Save inner template
     */
    public function ajax_save_inner_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $id = intval($_POST['id'] ?? 0);
        $data = array(
            'name_id' => sanitize_text_field($_POST['name_id'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? 'anchor'),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'variations' => intval($_POST['variations'] ?? 1)
        );
        
        $inner = CG_Inner_Templates::get_instance();
        if ($id > 0) {
            $inner->update($id, $data);
        } else {
            $id = $inner->create($data);
        }
        
        wp_send_json_success(array('id' => $id, 'message' => __('Saved!', 'category-generator')));
    }
    
    /**
     * AJAX: Delete inner template
     */
    public function ajax_delete_inner_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $id = intval($_POST['id'] ?? 0);
        $inner = CG_Inner_Templates::get_instance();
        $inner->delete($id);
        wp_send_json_success(array('message' => __('Deleted!', 'category-generator')));
    }
    
    /**
     * AJAX: Get variables
     */
    public function ajax_get_variables() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $vars = CG_Variables::get_instance();
        wp_send_json_success($vars->get_all_variables());
    }
    
    /**
     * AJAX: Save variable
     */
    public function ajax_save_variable() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $name = sanitize_text_field($_POST['name'] ?? '');
        $value = sanitize_textarea_field($_POST['value'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Name is required.', 'category-generator')));
        }
        
        $vars = CG_Variables::get_instance();
        $vars->save_variable($name, $value);
        wp_send_json_success(array('message' => __('Saved!', 'category-generator')));
    }
    
    /**
     * AJAX: Delete variable
     */
    public function ajax_delete_variable() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $name = sanitize_text_field($_POST['name'] ?? '');
        $vars = CG_Variables::get_instance();
        $vars->delete_variable($name);
        wp_send_json_success(array('message' => __('Deleted!', 'category-generator')));
    }
    
    /**
     * AJAX: Get settings
     */
    public function ajax_get_settings() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $settings = CG_Settings::get_instance();
        wp_send_json_success($settings->get_all());
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $settings_data = $_POST['settings'] ?? array();
        $settings = CG_Settings::get_instance();
        
        foreach ($settings_data as $key => $value) {
            $settings->set(sanitize_text_field($key), $value);
        }
        
        wp_send_json_success(array('message' => __('Settings saved!', 'category-generator')));
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_die(__('Permission denied.', 'category-generator'));
        }
        
        $type = sanitize_text_field($_GET['type'] ?? $_POST['type'] ?? 'all');
        $exporter = CG_Import_Export::get_instance();
        $exporter->export($type);
        exit;
    }
    
    /**
     * AJAX: Import data
     */
    public function ajax_import_data() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'category-generator')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
        
        $importer = CG_Import_Export::get_instance();
        $result = $importer->import($_FILES['file'], $type, $update_existing);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message'], 'details' => $result));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * AJAX: Get import history
     */
    public function ajax_get_import_history() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        $importer = CG_Import_Export::get_instance();
        wp_send_json_success($importer->get_history());
    }
    
    /**
     * AJAX: Run tests
     */
    public function ajax_run_tests() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'category-generator')));
        }
        
        $group = sanitize_text_field($_POST['group'] ?? 'all');
        $tester = CG_Tests::get_instance();
        
        if ($group === 'all') {
            $results = $tester->run_all_tests();
        } else {
            $results = $tester->run_test_group($group);
        }
        
        wp_send_json_success($results);
    }
    
    // ==================== Saved Titles/Areas AJAX ====================
    
    public function ajax_save_titles() {
        check_ajax_referer('cg_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        
        if ($id > 0) {
            $this->db->update_saved_titles($id, $name, $content);
        } else {
            $id = $this->db->save_titles($name, $content);
        }
        wp_send_json_success(array('id' => $id));
    }
    
    public function ajax_save_areas() {
        check_ajax_referer('cg_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        
        if ($id > 0) {
            $this->db->update_saved_areas($id, $name, $content);
        } else {
            $id = $this->db->save_areas($name, $content);
        }
        wp_send_json_success(array('id' => $id));
    }
    
    public function ajax_get_saved_titles() {
        check_ajax_referer('cg_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        $item = $this->db->get_saved_titles_item($id);
        wp_send_json_success($item ?: array());
    }
    
    public function ajax_get_saved_areas() {
        check_ajax_referer('cg_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        $item = $this->db->get_saved_areas_item($id);
        wp_send_json_success($item ?: array());
    }
    
    public function ajax_reset_database() {
        check_ajax_referer('cg_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        $this->db->reset_database();
        wp_send_json_success(array('message' => 'Database reset successfully'));
    }
    
    public function ajax_inject_inner_template() {
        check_ajax_referer('cg_nonce', 'nonce');
        $history_id = intval($_POST['history_id'] ?? 0);
        $inner_template_id = intval($_POST['inner_template_id'] ?? 0);
        $new_content = wp_kses_post($_POST['new_content'] ?? '');
        
        $history = $this->db->get_category_history_item($history_id);
        
        if (!$history) {
            wp_send_json_error(array('message' => 'History item not found'));
        }
        
        // Update in WordPress term
        $term_id = $history['term_id'];
        wp_update_term($term_id, $history['taxonomy'], array('description' => $new_content));
        
        // Update history record
        $this->db->update_category_history($history_id, array('meta_description' => $new_content));
        
        wp_send_json_success(array('message' => 'Injected successfully'));
    }
    
    public function ajax_get_term_description() {
        check_ajax_referer('cg_nonce', 'nonce');
        $term_id = intval($_POST['term_id'] ?? 0);
        $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'category');
        
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => 'Term not found'));
        }
        
        wp_send_json_success(array('description' => $term->description));
    }
    
    // ==================== Snapshot AJAX Handlers ====================
    
    public function ajax_create_snapshot() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        $notes = sanitize_text_field($_POST['notes'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'manual');
        
        if (empty($title)) {
            wp_send_json_error(array('message' => __('Snapshot title is required', 'category-generator')));
        }
        
        $snapshot = CG_Snapshot::get_instance();
        $result = $snapshot->create_snapshot($title, $notes, $type);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_restore_snapshot() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        $snapshot_id = intval($_POST['snapshot_id'] ?? 0);
        $create_backup = !empty($_POST['create_backup']);
        
        $snapshot = CG_Snapshot::get_instance();
        
        // Create backup before restore if requested
        if ($create_backup) {
            $snapshot->create_snapshot(__('Pre-restore backup', 'category-generator'), '', 'auto');
        }
        
        $result = $snapshot->restore_snapshot($snapshot_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_delete_snapshot() {
        check_ajax_referer('cg_nonce', 'nonce');
        $snapshot_id = intval($_POST['snapshot_id'] ?? 0);
        $snapshot = CG_Snapshot::get_instance();
        $snapshot->delete_snapshot($snapshot_id);
        wp_send_json_success();
    }
    
    public function ajax_download_snapshot() {
        check_ajax_referer('cg_nonce', 'nonce');
        $snapshot_id = intval($_GET['snapshot_id'] ?? 0);
        $snapshot = CG_Snapshot::get_instance();
        $snapshot->download_snapshot($snapshot_id);
    }
    
    public function ajax_get_recent_snapshots() {
        check_ajax_referer('cg_nonce', 'nonce');
        $limit = intval($_POST['limit'] ?? 10);
        $snapshots = $this->db->get_recent_snapshots($limit);
        wp_send_json_success(array('snapshots' => $snapshots));
    }
}

// Initialize the plugin
function category_generator_pro_init() {
    Category_Generator_Pro::get_instance();
}
add_action('plugins_loaded', 'category_generator_pro_init');

// Activation hook
register_activation_hook(__FILE__, 'category_generator_pro_activate');
function category_generator_pro_activate() {
    // Create necessary folders
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/category-generator';
    
    if (!file_exists($plugin_upload_dir)) {
        wp_mkdir_p($plugin_upload_dir);
    }
    
    // Initialize database (will create tables and default data)
    require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
    CG_Database::get_instance();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'category_generator_pro_deactivate');
function category_generator_pro_deactivate() {
    flush_rewrite_rules();
}
