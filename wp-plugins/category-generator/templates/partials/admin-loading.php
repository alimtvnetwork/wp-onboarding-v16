<?php
/**
 * Admin Loading Overlay Partial
 * 
 * Loading overlay for Generate page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="cg-loading" class="<?php echo CG_CSS::LOADING; ?>" style="display: none;">
    <div class="<?php echo CG_CSS::LOADING_INNER; ?>">
        <span class="spinner is-active"></span>
        <p id="cg-loading-text"><?php _e('Processing...', 'category-generator'); ?></p>
    </div>
</div>
