<?php
/**
 * Business Profile Page - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap cg-admin-wrap">
    <h1 class="<?php echo esc_attr(CG_CSS::TEXT_TITLE); ?>">
        <span class="dashicons dashicons-building"></span>
        <?php _e('Business Profile', 'category-generator'); ?>
    </h1>
    
    <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>" style="margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px;">
        <?php _e('Configure your business details here. These will be used to populate placeholders in your templates and Schema.org markup.', 'category-generator'); ?>
    </p>
    
    <?php include CG_PLUGIN_PATH . 'templates/partials/business-profile-form.php'; ?>
</div>

<?php include CG_PLUGIN_PATH . 'templates/partials/business-profile-styles.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/business-profile-scripts.php'; ?>
