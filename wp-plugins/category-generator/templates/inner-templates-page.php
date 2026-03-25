<?php
/**
 * Inner Templates Management Page - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

$inner = CG_Inner_Templates::get_instance();
$templates = $inner->get_templates();
$types = CG_Inner_Templates::get_types();
?>

<div class="wrap cg-admin-wrap">
    <h1 class="cg-title">
        <span class="dashicons dashicons-welcome-widgets-menus"></span>
        <?php _e('Inner Templates', 'category-generator'); ?>
    </h1>
    
    <p class="cg-description">
        <?php _e('Create reusable template snippets that can be embedded in HTML templates using {inner:id} or {inner:name-id} syntax.', 'category-generator'); ?>
    </p>
    
    <div class="cg-inner-layout">
        <div class="cg-inner-list">
            <div class="cg-card">
                <div class="cg-card-header">
                    <h2><?php _e('All Inner Templates', 'category-generator'); ?></h2>
                    <button type="button" class="button button-primary" id="cg-add-inner-template">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Add New', 'category-generator'); ?>
                    </button>
                </div>
                
                <div class="cg-inner-filters">
                    <select id="cg-filter-type">
                        <option value=""><?php _e('All Types', 'category-generator'); ?></option>
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" id="cg-filter-search" placeholder="<?php esc_attr_e('Search templates...', 'category-generator'); ?>">
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('ID', 'category-generator'); ?></th>
                            <th><?php _e('Name', 'category-generator'); ?></th>
                            <th style="width: 120px;"><?php _e('Name ID', 'category-generator'); ?></th>
                            <th style="width: 120px;"><?php _e('Type', 'category-generator'); ?></th>
                            <th style="width: 100px;"><?php _e('Category', 'category-generator'); ?></th>
                            <th style="width: 200px;"><?php _e('Actions', 'category-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="inner-templates-body">
                        <?php if (empty($templates)): ?>
                        <tr class="cg-empty-row">
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <?php _e('No inner templates yet. Create your first one!', 'category-generator'); ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                        <tr data-id="<?php echo esc_attr($template['id']); ?>" data-type="<?php echo esc_attr($template['type']); ?>">
                            <td><?php echo esc_html($template['id']); ?></td>
                            <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                            <td><code><?php echo esc_html($template['name_id']); ?></code></td>
                            <td>
                                <span class="cg-type-badge cg-type-<?php echo esc_attr($template['type']); ?>">
                                    <?php echo esc_html($types[$template['type']] ?? $template['type']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($template['category'] ?? '-'); ?></td>
                            <td>
                                <button class="button cg-edit-inner" data-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Edit', 'category-generator'); ?></button>
                                <button class="button cg-clone-inner" data-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Clone', 'category-generator'); ?></button>
                                <button class="button cg-delete-inner" data-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Delete', 'category-generator'); ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="cg-inner-sidebar">
            <div class="cg-card">
                <h3><?php _e('Usage Guide', 'category-generator'); ?></h3>
                <div class="cg-usage-guide">
                    <p><strong><?php _e('By ID:', 'category-generator'); ?></strong></p>
                    <code>{inner:3}</code>
                    
                    <p><strong><?php _e('By Name ID:', 'category-generator'); ?></strong></p>
                    <code>{inner:company-founded}</code>
                    
                    <p><strong><?php _e('Available in:', 'category-generator'); ?></strong></p>
                    <ul>
                        <li><?php _e('HTML Description Templates', 'category-generator'); ?></li>
                        <li><?php _e('Meta Description Patterns', 'category-generator'); ?></li>
                        <li><?php _e('Schema Templates', 'category-generator'); ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="cg-card">
                <h3><?php _e('Template Types', 'category-generator'); ?></h3>
                <div class="cg-types-list">
                    <?php foreach ($types as $key => $label): ?>
                    <div class="cg-type-item">
                        <span class="cg-type-badge cg-type-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="cg-card">
                <h3><?php _e('Import / Export', 'category-generator'); ?></h3>
                <div class="cg-ie-buttons">
                    <button type="button" class="button" id="cg-export-inner">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export All', 'category-generator'); ?>
                    </button>
                    <button type="button" class="button" id="cg-import-inner">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Import', 'category-generator'); ?>
                    </button>
                </div>
                <input type="file" id="cg-import-file" accept=".zip,.csv,.db" style="display: none;">
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="cg-inner-modal" class="cg-modal" style="display: none;">
    <div class="cg-modal-content">
        <div class="cg-modal-header">
            <h2 id="cg-inner-modal-title"><?php _e('Add Inner Template', 'category-generator'); ?></h2>
            <button type="button" class="cg-modal-close">&times;</button>
        </div>
        <div class="cg-modal-body">
            <form id="cg-inner-form">
                <input type="hidden" id="inner-id" name="id" value="0">
                
                <div class="cg-form-row">
                    <div class="cg-form-group">
                        <label for="inner-name"><?php _e('Template Name', 'category-generator'); ?> *</label>
                        <input type="text" id="inner-name" name="name" required placeholder="Company Founded Statement">
                    </div>
                    
                    <div class="cg-form-group">
                        <label for="inner-name-id"><?php _e('Name ID (for {inner:xxx})', 'category-generator'); ?> *</label>
                        <input type="text" id="inner-name-id" name="name_id" required pattern="[a-zA-Z_][a-zA-Z0-9_-]*" placeholder="company-founded">
                        <span class="cg-hint"><?php _e('Letters, numbers, hyphens, underscores only', 'category-generator'); ?></span>
                    </div>
                </div>
                
                <div class="cg-form-row">
                    <div class="cg-form-group">
                        <label for="inner-type"><?php _e('Type', 'category-generator'); ?></label>
                        <select id="inner-type" name="type">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="cg-form-group">
                        <label for="inner-category"><?php _e('Category (for organization)', 'category-generator'); ?></label>
                        <input type="text" id="inner-category" name="category" placeholder="About, CTA, Marketing...">
                    </div>
                </div>
                
                <div class="cg-form-group">
                    <label for="inner-content"><?php _e('Template Content', 'category-generator'); ?> *</label>
                    <textarea id="inner-content" name="content" rows="8" required placeholder="Founded 2023 bringing 5 years combined expertise, {business_name} serves {area} with exceptional {title}."></textarea>
                    <div class="cg-template-helpers" style="margin-top: 10px;">
                        <strong><?php _e('Quick Insert:', 'category-generator'); ?></strong>
                        <button type="button" class="button cg-insert-inner-ph" data-placeholder="{title}">{title}</button>
                        <button type="button" class="button cg-insert-inner-ph" data-placeholder="{area}">{area}</button>
                        <button type="button" class="button cg-insert-inner-ph" data-placeholder="{business_name}">{business_name}</button>
                        <button type="button" class="button cg-insert-inner-ph" data-placeholder="{phone}">{phone}</button>
                        <button type="button" class="button cg-insert-inner-ph" data-placeholder="{website}">{website}</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="cg-modal-footer">
            <button type="button" class="button" id="cg-inner-cancel"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="button button-primary" id="cg-inner-save"><?php _e('Save Template', 'category-generator'); ?></button>
        </div>
    </div>
</div>

<style>
.cg-inner-layout { display: grid; grid-template-columns: 1fr 300px; gap: 24px; }
@media (max-width: 1200px) { .cg-inner-layout { grid-template-columns: 1fr; } }

.cg-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.cg-card-header h2 { margin: 0; }

.cg-inner-filters { display: flex; gap: 10px; margin-bottom: 15px; }
.cg-inner-filters select, .cg-inner-filters input { padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; }
.cg-inner-filters input { flex: 1; }

.cg-type-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.cg-type-anchor { background: #e3f2fd; color: #1976d2; }
.cg-type-header { background: #f3e5f5; color: #7b1fa2; }
.cg-type-marketing { background: #fff3e0; color: #f57c00; }
.cg-type-cta { background: #e8f5e9; color: #388e3c; }
.cg-type-snippet { background: #f5f5f5; color: #616161; }
.cg-type-link_list { background: #e1f5fe; color: #0277bd; }

.cg-usage-guide code { display: block; background: #f8f9fa; padding: 8px 12px; border-radius: 4px; margin: 8px 0 15px 0; font-size: 13px; }
.cg-usage-guide ul { margin: 8px 0 0 0; padding-left: 20px; font-size: 13px; }
.cg-usage-guide li { margin-bottom: 4px; }

.cg-types-list { display: flex; flex-wrap: wrap; gap: 8px; }
.cg-type-item { margin-bottom: 4px; }

.cg-ie-buttons { display: flex; gap: 10px; }
.cg-ie-buttons .button { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; }

.cg-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
@media (max-width: 600px) { .cg-form-row { grid-template-columns: 1fr; } }

/* Modal styles */
.cg-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.cg-modal-content { background: white; width: 90%; max-width: 700px; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; }
.cg-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #ddd; }
.cg-modal-header h2 { margin: 0; }
.cg-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.cg-modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.cg-modal-footer { padding: 16px 24px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; }
.cg-form-group { margin-bottom: 15px; }
.cg-form-group label { display: block; font-weight: 600; margin-bottom: 6px; }
.cg-form-group input, .cg-form-group textarea, .cg-form-group select { width: 100%; padding: 10px 12px; border: 1px solid #c3c4c7; border-radius: 4px; }
.cg-form-group textarea { font-family: monospace; font-size: 13px; }
.cg-hint { display: block; margin-top: 4px; font-size: 12px; color: #646970; }
</style>

<script>
jQuery(document).ready(function($) {
    // Filter by type
    $('#cg-filter-type').on('change', function() {
        const type = $(this).val();
        $('#inner-templates-body tr').each(function() {
            if (!type || $(this).data('type') === type) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Search filter
    $('#cg-filter-search').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('#inner-templates-body tr').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(search));
        });
    });
    
    // Open modal for new template
    $('#cg-add-inner-template').on('click', function() {
        $('#cg-inner-modal-title').text('Add Inner Template');
        $('#cg-inner-form')[0].reset();
        $('#inner-id').val(0);
        $('#cg-inner-modal').show();
    });
    
    // Close modal
    $('.cg-modal-close, #cg-inner-cancel').on('click', function() {
        $('#cg-inner-modal').hide();
    });
    
    // Edit template
    $(document).on('click', '.cg-edit-inner', function() {
        const id = $(this).data('id');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_inner_template', nonce: cgAdmin.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    const t = response.data;
                    $('#cg-inner-modal-title').text('Edit Inner Template');
                    $('#inner-id').val(t.id);
                    $('#inner-name').val(t.name);
                    $('#inner-name-id').val(t.name_id);
                    $('#inner-type').val(t.type);
                    $('#inner-category').val(t.category || '');
                    $('#inner-content').val(t.content);
                    $('#cg-inner-modal').show();
                }
            }
        });
    });
    
    // Clone template
    $(document).on('click', '.cg-clone-inner', function() {
        const id = $(this).data('id');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_clone_inner_template', nonce: cgAdmin.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Clone failed');
                }
            }
        });
    });
    
    // Delete template
    $(document).on('click', '.cg-delete-inner', function() {
        if (!confirm('Are you sure you want to delete this template?')) return;
        
        const id = $(this).data('id');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_delete_inner_template', nonce: cgAdmin.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Save template
    $('#cg-inner-save').on('click', function() {
        const data = {
            action: 'cg_save_inner_template',
            nonce: cgAdmin.nonce,
            id: $('#inner-id').val(),
            name: $('#inner-name').val(),
            name_id: $('#inner-name-id').val(),
            type: $('#inner-type').val(),
            category: $('#inner-category').val(),
            content: $('#inner-content').val()
        };
        
        if (!data.name || !data.name_id || !data.content) {
            alert('Please fill in all required fields');
            return;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Save failed');
                }
            }
        });
    });
    
    // Insert placeholder
    $(document).on('click', '.cg-insert-inner-ph', function() {
        const ph = $(this).data('placeholder');
        const $textarea = $('#inner-content');
        const start = $textarea[0].selectionStart;
        const val = $textarea.val();
        $textarea.val(val.substring(0, start) + ph + val.substring($textarea[0].selectionEnd));
        $textarea.focus();
    });
    
    // Export
    $('#cg-export-inner').on('click', function() {
        window.location.href = cgAdmin.ajaxUrl + '?action=cg_export&nonce=' + cgAdmin.nonce + '&types[]=inner_templates';
    });
    
    // Import
    $('#cg-import-inner').on('click', function() {
        $('#cg-import-file').click();
    });
    
    $('#cg-import-file').on('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('action', 'cg_import');
        formData.append('nonce', cgAdmin.nonce);
        formData.append('file', file);
        formData.append('types[]', 'inner_templates');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Import completed! Imported: ' + response.data.imported.length + ', Skipped: ' + response.data.skipped.length);
                    location.reload();
                } else {
                    alert(response.data.message || 'Import failed');
                }
            }
        });
    });
});
</script>
