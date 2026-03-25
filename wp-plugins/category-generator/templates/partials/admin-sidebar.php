<?php
/**
 * Admin Sidebar Partial
 * 
 * Stats, Preview, and Results sidebar for Generate page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo CG_CSS::SIDEBAR; ?>">
    <!-- Stats Card -->
    <div class="<?php echo CG_CSS::CARD; ?> cg-stats-card">
        <h3>
            <span class="dashicons dashicons-chart-bar"></span>
            <?php _e('Summary', 'category-generator'); ?>
        </h3>
        
        <div class="<?php echo CG_CSS::STATS_GRID; ?>">
            <div class="<?php echo CG_CSS::STAT; ?>">
                <span class="<?php echo CG_CSS::STAT_VALUE; ?>" id="cg-total-parents">0</span>
                <span class="<?php echo CG_CSS::STAT_LABEL; ?>"><?php _e('Parent Categories', 'category-generator'); ?></span>
            </div>
            <div class="<?php echo CG_CSS::STAT; ?>">
                <span class="<?php echo CG_CSS::STAT_VALUE; ?>" id="cg-total-combinations">0</span>
                <span class="<?php echo CG_CSS::STAT_LABEL; ?>"><?php _e('Cross-Join Categories', 'category-generator'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Preview Card -->
    <div class="<?php echo CG_CSS::CARD; ?> cg-preview-card">
        <h3>
            <span class="dashicons dashicons-list-view"></span>
            <?php _e('Preview', 'category-generator'); ?>
        </h3>
        
        <div id="cg-preview-summary" class="<?php echo CG_CSS::PREVIEW_SUMMARY; ?>" style="display: none;">
            <div class="<?php echo CG_CSS::PREVIEW_STAT; ?> <?php echo CG_CSS::STAT_NEW; ?>">
                <span class="dashicons dashicons-plus"></span>
                <span id="cg-new-count">0</span> <?php _e('new', 'category-generator'); ?>
            </div>
            <div class="<?php echo CG_CSS::PREVIEW_STAT; ?> <?php echo CG_CSS::STAT_EXISTS; ?>">
                <span class="dashicons dashicons-yes"></span>
                <span id="cg-exists-count">0</span> <?php _e('exist', 'category-generator'); ?>
            </div>
        </div>
        
        <div id="cg-preview-list" class="<?php echo CG_CSS::PREVIEW_LIST; ?>">
            <p class="<?php echo CG_CSS::PREVIEW_EMPTY; ?>">
                <?php _e('Click "Preview What Will Happen" to see the plan.', 'category-generator'); ?>
            </p>
        </div>
    </div>
    
    <!-- Results -->
    <div class="<?php echo CG_CSS::CARD; ?> <?php echo CG_CSS::RESULTS_CARD; ?>" id="cg-results-card" style="display: none;">
        <h3>
            <span class="dashicons dashicons-yes-alt"></span>
            <?php _e('Results', 'category-generator'); ?>
        </h3>
        <div id="cg-results-content"></div>
    </div>
</div>
