<?php
/**
 * History Modals Partial
 * 
 * View, Import, and Inject modals for history page.
 * 
 * @package Category_Generator_Area
 * @var array $inner_templates Array of inner templates for inject modal
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- View Modal -->
<div id="cg-history-view-modal" class="<?php echo CG_CSS::MODAL; ?>" style="display:none;">
    <div class="<?php echo CG_CSS::MODAL_CONTENT; ?> <?php echo CG_CSS::MODAL_LARGE; ?>">
        <div class="<?php echo CG_CSS::MODAL_HEADER; ?>">
            <h2><?php _e('Category Details', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo CG_CSS::MODAL_CLOSE; ?>">&times;</button>
        </div>
        <div class="<?php echo CG_CSS::MODAL_BODY; ?>" id="cg-history-view-content">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="cg-history-import-modal" class="<?php echo CG_CSS::MODAL; ?>" style="display:none;">
    <div class="<?php echo CG_CSS::MODAL_CONTENT; ?>">
        <div class="<?php echo CG_CSS::MODAL_HEADER; ?>">
            <h2><?php _e('Import History', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo CG_CSS::MODAL_CLOSE; ?>">&times;</button>
        </div>
        <div class="<?php echo CG_CSS::MODAL_BODY; ?>">
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <label><?php _e('Select File', 'category-generator'); ?></label>
                <input type="file" id="cg-history-import-file" accept=".zip,.csv,.sqlite,.db">
                <p class="description"><?php _e('Accepts ZIP, CSV, or SQLite database files.', 'category-generator'); ?></p>
            </div>
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <label>
                    <input type="checkbox" id="cg-history-import-update" value="1">
                    <?php _e('Update existing records if they match', 'category-generator'); ?>
                </label>
            </div>
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <button type="button" class="button button-primary" id="cg-history-import-submit">
                    <?php _e('Import', 'category-generator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inject Inner Template Modal -->
<div id="cg-inject-modal" class="<?php echo CG_CSS::MODAL; ?>" style="display:none;">
    <div class="<?php echo CG_CSS::MODAL_CONTENT; ?> <?php echo CG_CSS::MODAL_LARGE; ?>">
        <div class="<?php echo CG_CSS::MODAL_HEADER; ?>">
            <h2><?php _e('Inject Inner Template', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo CG_CSS::MODAL_CLOSE; ?>">&times;</button>
        </div>
        <div class="<?php echo CG_CSS::MODAL_BODY; ?>">
            <input type="hidden" id="cg-inject-history-id">
            
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <label for="cg-inject-template-select"><?php _e('Select Inner Template', 'category-generator'); ?></label>
                <select id="cg-inject-template-select">
                    <option value=""><?php _e('— Select a template —', 'category-generator'); ?></option>
                    <?php foreach ($inner_templates as $tpl): ?>
                        <option value="<?php echo esc_attr($tpl['id']); ?>" data-content="<?php echo esc_attr($tpl['content']); ?>">
                            <?php echo esc_html($tpl['name']); ?> (<?php echo esc_html($tpl['type']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <label><?php _e('Template Preview', 'category-generator'); ?></label>
                <div id="cg-inject-template-preview" class="<?php echo CG_CSS::INJECT_PREVIEW; ?>">
                    <em><?php _e('Select a template to see preview', 'category-generator'); ?></em>
                </div>
            </div>
            
            <div class="<?php echo CG_CSS::FORM_ROW; ?>">
                <label for="cg-inject-content"><?php _e('Current Description (click to set insertion point)', 'category-generator'); ?></label>
                <textarea id="cg-inject-content" rows="10" placeholder="<?php esc_attr_e('Loading...', 'category-generator'); ?>"></textarea>
                <p class="description"><?php _e('Click inside the text where you want to insert the template, then click "Inject at Cursor".', 'category-generator'); ?></p>
            </div>
            
            <div class="<?php echo CG_CSS::FORM_ROW; ?> <?php echo CG_CSS::INJECT_ACTIONS; ?>">
                <button type="button" class="button" id="cg-inject-cancel"><?php _e('Cancel', 'category-generator'); ?></button>
                <button type="button" class="button button-secondary" id="cg-inject-at-start"><?php _e('Insert at Start', 'category-generator'); ?></button>
                <button type="button" class="button button-secondary" id="cg-inject-at-end"><?php _e('Insert at End', 'category-generator'); ?></button>
                <button type="button" class="button button-primary" id="cg-inject-at-cursor"><?php _e('Inject at Cursor', 'category-generator'); ?></button>
            </div>
        </div>
    </div>
</div>
