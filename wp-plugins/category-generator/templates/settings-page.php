<?php
/**
 * Settings Page Template - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = CG_Settings::get_instance();
$db = CG_Database::get_instance();
$all_settings = $settings->get_all();
$defaults = CG_Settings::get_defaults();
$ai_providers = CG_Settings::get_ai_providers();
$yoast_data = $settings->get_yoast_data();
$remote_apis = $settings->get_remote_apis();
?>

<div class="wrap cg-admin-wrap">
    <h1 class="<?php echo esc_attr(CG_CSS::TEXT_TITLE); ?>">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Settings', 'category-generator'); ?>
    </h1>
    
    <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tabs.php'; ?>
    
    <form id="cg-settings-form">
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-general.php'; ?>
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-classes.php'; ?>
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-ai.php'; ?>
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-remote.php'; ?>
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-yoast.php'; ?>
        <?php include CG_PLUGIN_PATH . 'templates/partials/settings-tab-danger.php'; ?>
        
        <div class="cg-actions" style="margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px;">
            <button type="submit" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?> button-hero">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save All Settings', 'category-generator'); ?>
            </button>
        </div>
    </form>
</div>

<?php include CG_PLUGIN_PATH . 'templates/partials/settings-modals.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/settings-styles.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/settings-scripts.php'; ?>
