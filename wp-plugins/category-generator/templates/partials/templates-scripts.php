<?php
/**
 * Templates Page - Scripts
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB); ?>').on('click', function() {
        const tab = $(this).data('tab');
        $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB); ?>').removeClass('active');
        $(this).addClass('active');
        $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB_CONTENT); ?>').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // Category filtering
    $('.cg-category-filter').on('change', function() {
        const category = $(this).val();
        const $tbody = $(this).closest('.<?php echo esc_js(CG_CSS::LAYOUT_CARD); ?>').find('tbody');
        
        $tbody.find('tr').each(function() {
            const rowCategory = $(this).data('category') || '';
            if (!category || rowCategory === category) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Modal functions
    function openModal(type, id = 0) {
        const isNew = id === 0;
        $('#cg-modal-title').text(isNew ? '<?php echo esc_js(__('Add New Template', 'category-generator')); ?>' : '<?php echo esc_js(__('Edit Template', 'category-generator')); ?>');
        $('#tpl-id').val(id);
        $('#tpl-type').val(type);
        
        // Show/hide appropriate fields
        $('.cg-form-fields').hide();
        $('.cg-fields-' + type).show();
        
        // Clear form
        if (isNew) {
            $('#cg-template-form')[0].reset();
            $('#<?php echo esc_js(CG_CSS::ID_TEMPLATE_MODAL); ?>').fadeIn(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
        } else {
            // Load template data
            $.ajax({
                url: cgAdmin.ajaxUrl,
                type: 'POST',
                data: { action: '<?php echo esc_js(CG_Constants::AJAX_GET_TEMPLATE); ?>', nonce: cgAdmin.nonce, type: type, id: id },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#tpl-name').val(data.name);
                        $('#tpl-category').val(data.category || '');
                        
                        if (type === 'html') {
                            $('#tpl-description').val(data.description || '');
                            $('#tpl-content').val(data.content || '');
                        } else if (type === 'meta') {
                            $('#tpl-meta-title').val(data.meta_title_pattern || '');
                            $('#tpl-meta-desc').val(data.meta_description_pattern || '');
                            $('#tpl-slug').val(data.slug_pattern || '');
                        } else if (type === 'schema') {
                            $('#tpl-schema-type').val(data.schema_type || '<?php echo esc_js(CG_Constants::DEFAULT_SCHEMA_TYPE); ?>');
                            $('#tpl-schema-content').val(data.schema_content || '');
                        }
                        
                        $('#<?php echo esc_js(CG_CSS::ID_TEMPLATE_MODAL); ?>').fadeIn(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
                    }
                }
            });
        }
    }
    
    function closeModal() {
        $('#<?php echo esc_js(CG_CSS::ID_TEMPLATE_MODAL); ?>').fadeOut(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
        $('#<?php echo esc_js(CG_CSS::ID_CATEGORY_MODAL); ?>').fadeOut(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
    }
    
    // Event handlers
    $('#cg-add-html-template').on('click', function() { openModal('html', 0); });
    $('#cg-add-meta-template').on('click', function() { openModal('meta', 0); });
    $('#cg-add-schema-template').on('click', function() { openModal('schema', 0); });
    
    $(document).on('click', '.cg-edit-template', function() {
        openModal($(this).data('type'), $(this).data('id'));
    });
    
    $(document).on('click', '.cg-delete-template', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this template?', 'category-generator')); ?>')) return;
        
        const $btn = $(this);
        const type = $btn.data('type');
        const id = $btn.data('id');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: '<?php echo esc_js(CG_Constants::AJAX_DELETE_TEMPLATE); ?>', nonce: cgAdmin.nonce, type: type, id: id },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>, function() { $(this).remove(); });
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error deleting template', 'category-generator')); ?>');
                }
            }
        });
    });
    
    $('.<?php echo esc_js(CG_CSS::MODAL_CLOSE); ?>, #cg-modal-cancel').on('click', closeModal);
    
    $('#cg-modal-save').on('click', function() {
        const type = $('#tpl-type').val();
        const data = {
            action: '<?php echo esc_js(CG_Constants::AJAX_SAVE_TEMPLATE); ?>',
            nonce: cgAdmin.nonce,
            type: type,
            id: $('#tpl-id').val(),
            name: $('#tpl-name').val(),
            category: $('#tpl-category').val()
        };
        
        if (type === 'html') {
            data.description = $('#tpl-description').val();
            data.content = $('#tpl-content').val();
        } else if (type === 'meta') {
            data.meta_title_pattern = $('#tpl-meta-title').val();
            data.meta_description_pattern = $('#tpl-meta-desc').val();
            data.slug_pattern = $('#tpl-slug').val();
        } else if (type === 'schema') {
            data.schema_type = $('#tpl-schema-type').val();
            data.content = $('#tpl-schema-content').val();
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error saving template', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Category management
    $('#cg-add-category-btn').on('click', function() {
        $('#cat-parent-id').val(0);
        $('#cat-level').val(0);
        $('#cat-name').val('');
        $('#cat-parent-display').hide();
        $('#cg-category-modal-title').text('<?php echo esc_js(__('Add Root Category', 'category-generator')); ?>');
        $('#<?php echo esc_js(CG_CSS::ID_CATEGORY_MODAL); ?>').fadeIn(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
    });
    
    $(document).on('click', '.cg-add-child-category', function() {
        const parentId = $(this).data('parent');
        const level = $(this).data('level');
        const parentName = $(this).closest('.cg-tree-item-content').find('.cg-tree-name').text();
        
        $('#cat-parent-id').val(parentId);
        $('#cat-level').val(level);
        $('#cat-name').val('');
        $('#cat-parent-name').text(parentName);
        $('#cat-parent-display').show();
        $('#cg-category-modal-title').text(level === 1 ? '<?php echo esc_js(__('Add Category', 'category-generator')); ?>' : '<?php echo esc_js(__('Add Subcategory', 'category-generator')); ?>');
        $('#<?php echo esc_js(CG_CSS::ID_CATEGORY_MODAL); ?>').fadeIn(<?php echo CG_Constants::ANIMATION_FADE_DURATION; ?>);
    });
    
    $('#cg-save-category-btn').on('click', function() {
        const name = $('#cat-name').val().trim();
        if (!name) {
            alert('<?php echo esc_js(__('Please enter a category name', 'category-generator')); ?>');
            return;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_SAVE_TEMPLATE_CATEGORY); ?>',
                nonce: cgAdmin.nonce,
                name: name,
                parent_id: $('#cat-parent-id').val(),
                template_type: $('#cat-template-type').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error saving category', 'category-generator')); ?>');
                }
            }
        });
    });
    
    $(document).on('click', '.cg-delete-category', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure? This will also delete all child categories.', 'category-generator')); ?>')) return;
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_DELETE_TEMPLATE_CATEGORY); ?>',
                nonce: cgAdmin.nonce,
                id: $(this).data('id')
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Close modal on backdrop click
    $('.<?php echo esc_js(CG_CSS::MODAL); ?>').on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
});
</script>
