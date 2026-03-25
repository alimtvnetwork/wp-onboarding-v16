/**
 * Category Generator Pro - Admin JavaScript v2.3
 * Enhanced with auto-load, save on change, titles/areas saving, inject functionality, toast notifications
 */

(function($) {
    'use strict';

    // ==================== Toast Notification System ====================
    const Toast = {
        container: null,
        
        init() {
            if (!this.container) {
                this.container = $('<div class="cg-toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show(type, title, message, duration = 4000) {
            this.init();
            
            const icons = {
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-times-circle"></i>',
                warning: '<i class="fas fa-exclamation-triangle"></i>',
                info: '<i class="fas fa-info-circle"></i>'
            };
            
            const $toast = $(`
                <div class="cg-toast cg-toast-${type}">
                    <div class="cg-toast-icon">${icons[type] || icons.info}</div>
                    <div class="cg-toast-content">
                        <div class="cg-toast-title">${title}</div>
                        ${message ? `<div class="cg-toast-message">${message}</div>` : ''}
                    </div>
                    <button class="cg-toast-close" type="button">&times;</button>
                </div>
            `);
            
            this.container.append($toast);
            
            $toast.find('.cg-toast-close').on('click', () => this.hide($toast));
            
            if (duration > 0) {
                setTimeout(() => this.hide($toast), duration);
            }
            
            return $toast;
        },
        
        hide($toast) {
            $toast.addClass('cg-toast-hiding');
            setTimeout(() => $toast.remove(), 300);
        },
        
        success(title, message, duration) {
            return this.show('success', title, message, duration);
        },
        
        error(title, message, duration) {
            return this.show('error', title, message, duration);
        },
        
        warning(title, message, duration) {
            return this.show('warning', title, message, duration);
        },
        
        info(title, message, duration) {
            return this.show('info', title, message, duration);
        }
    };
    
    // Make Toast globally accessible
    window.cgToast = Toast;

    // Track modified state for templates
    let templateModified = {
        html: false,
        meta: false,
        schema: false,
        titles: false,
        areas: false
    };
    
    // Track currently loaded template IDs
    let currentTemplateId = {
        html: null,
        meta: null,
        schema: null,
        titles: null,
        areas: null
    };

    // Cache DOM elements
    const $titles = $('#cg-titles');
    const $areas = $('#cg-areas');
    const $format = $('#cg-format');
    const $sampleHtml = $('#cg-sample-html');
    const $taxonomy = $('#cg-taxonomy');
    const $parent = $('#cg-parent');
    const $previewBtn = $('#cg-preview-btn');
    const $generateBtn = $('#cg-generate-btn');
    const $previewList = $('#cg-preview-list');
    const $resultsCard = $('#cg-results-card');
    const $resultsContent = $('#cg-results-content');
    const $loading = $('#cg-loading');
    const $loadingText = $('#cg-loading-text');

    function countLines($textarea) {
        const text = $textarea.val().trim();
        if (!text) return 0;
        return text.split('\n').filter(line => line.trim()).length;
    }

    function updateCounts() {
        const titlesCount = countLines($titles);
        const areasCount = countLines($areas);
        $('#cg-titles-count').text(titlesCount);
        $('#cg-areas-count').text(areasCount);
        $('#cg-total-parents').text(titlesCount);
        $('#cg-total-combinations').text(titlesCount * areasCount);
    }

    function updateMetaDescCounts() {
        for (let i = 1; i <= 12; i++) {
            const count = $(`#cg-meta-description-${i}`).val()?.length || 0;
            $(`.cg-meta-desc-count-${i}`).text(count).css('color', count >= 135 ? '#00a32a' : (count > 0 ? '#d63638' : '#666'));
        }
    }

    function showLoading(text) {
        $loadingText.text(text || cgAdmin.strings.generating);
        $loading.fadeIn(200);
    }

    function hideLoading() {
        $loading.fadeOut(200);
    }

    function previewCombinations() {
        const titles = $titles.val().trim();
        const areas = $areas.val().trim();

        if (!titles || !areas) {
            Toast.warning('Missing Input', 'Please enter both titles and areas.');
            return;
        }

        showLoading('Analyzing categories...');

        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cg_preview_combinations',
                nonce: cgAdmin.nonce,
                titles: titles,
                areas: areas,
                format: $format.val().trim() || '{title} {area}',
                taxonomy: $taxonomy.val(),
                create_parents: $('#cg-create-parents').is(':checked'),
                make_children: $('#cg-make-children').is(':checked')
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    renderPreview(response.data);
                    $generateBtn.prop('disabled', false);
                    Toast.success('Preview Ready', 'Categories analyzed successfully.');
                } else {
                    Toast.error('Error', response.data.message || cgAdmin.strings.error);
                }
            },
            error: function() {
                hideLoading();
                Toast.error('Error', cgAdmin.strings.error);
            }
        });
    }

    function renderPreview(data) {
        const { parent_categories, child_categories, summary } = data;
        
        $('#cg-preview-summary').show();
        $('#cg-new-count').text(summary.new_parents + summary.new_children);
        $('#cg-exists-count').text(summary.existing_parents + summary.existing_children);

        let html = '';
        
        if (parent_categories && parent_categories.length > 0 && $('#cg-create-parents').is(':checked')) {
            html += '<div class="cg-preview-section"><strong>Parent Categories:</strong></div>';
            parent_categories.forEach(function(cat) {
                const statusClass = cat.status === 'new' ? 'cg-status-new' : 'cg-status-exists';
                const statusIcon = cat.status === 'new' ? '➕' : '✓';
                html += `<div class="cg-preview-item ${statusClass}">
                    <span class="cg-preview-status">${statusIcon}</span>
                    <span class="cg-preview-name">${escapeHtml(cat.name)}</span>
                    <span class="cg-preview-badge">${cat.status}</span>
                </div>`;
            });
        }

        if (child_categories && child_categories.length > 0) {
            html += '<div class="cg-preview-section"><strong>Categories:</strong></div>';
            child_categories.forEach(function(cat) {
                const statusClass = cat.status === 'new' ? 'cg-status-new' : 'cg-status-exists';
                const statusIcon = cat.status === 'new' ? '➕' : '✓';
                const childIcon = cat.will_be_child ? '↳ ' : '';
                html += `<div class="cg-preview-item ${statusClass}">
                    <span class="cg-preview-status">${statusIcon}</span>
                    <span class="cg-preview-name">${childIcon}${escapeHtml(cat.name)}</span>
                    <span class="cg-preview-badge">${cat.status}</span>
                </div>`;
            });
        }

        $previewList.html(html);
    }

    function generateCategories() {
        if (!confirm(cgAdmin.strings.confirm)) return;

        showLoading(cgAdmin.strings.generating);
        
        // Collect all meta title/description variations
        const metaTitles = [];
        const metaDescs = [];
        for (let i = 1; i <= 6; i++) {
            const val = $(`#cg-meta-title-${i}`).val();
            if (val) metaTitles.push(val);
        }
        for (let i = 1; i <= 12; i++) {
            const val = $(`#cg-meta-description-${i}`).val();
            if (val) metaDescs.push(val);
        }
        
        // Collect selected HTML templates for random variation
        const selectedTemplates = [];
        $('input[name="cg_html_templates[]"]:checked').each(function() {
            selectedTemplates.push($(this).val());
        });
        
        // Collect FAQ variations
        const faqVariations = [];
        for (let i = 1; i <= 4; i++) {
            const val = $(`#cg-faq-variation-${i}`).val();
            if (val && val.trim()) faqVariations.push(val);
        }

        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cg_generate_categories',
                nonce: cgAdmin.nonce,
                titles: $titles.val().trim(),
                areas: $areas.val().trim(),
                format: $format.val().trim() || '{title} {area}',
                taxonomy: $taxonomy.val(),
                parent_id: $parent.val(),
                create_parents: $('#cg-create-parents').is(':checked'),
                make_children: $('#cg-make-children').is(':checked'),
                update_existing_meta: $('#cg-update-existing-meta').is(':checked'),
                include_schema: $('#cg-include-schema').is(':checked'),
                use_global_schema: $('#cg-use-global-schema').is(':checked'),
                html_template: $sampleHtml.val(),
                html_template_ids: JSON.stringify(selectedTemplates),
                faq_variations: JSON.stringify(faqVariations),
                include_faq_schema: $('#cg-include-faq-schema').is(':checked'),
                meta_title_pattern: metaTitles[0] || $('#cg-meta-title-1').val(),
                meta_title_variations: JSON.stringify(metaTitles),
                meta_description_pattern: metaDescs[0] || $('#cg-meta-description-1').val(),
                meta_description_variations: JSON.stringify(metaDescs),
                slug_pattern: $('#cg-slug-pattern').val(),
                schema_template: $('#cg-schema-content').val()
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showResults(response.data);
                    Toast.success('Generation Complete', `Created ${(response.data.categories_created?.length || 0) + (response.data.parents_created?.length || 0)} categories`);
                } else {
                    Toast.error('Error', response.data.message || cgAdmin.strings.error);
                }
            },
            error: function() {
                hideLoading();
                Toast.error('Error', cgAdmin.strings.error);
            }
        });
    }

    function showResults(data) {
        let html = '<div class="cg-result-success">✓ Generation complete!</div>';
        
        if (data.parents_created && data.parents_created.length > 0) {
            html += `<p><strong>${data.parents_created.length} parent categories created</strong></p>`;
        }
        
        if (data.categories_created && data.categories_created.length > 0) {
            html += `<p><strong>${data.categories_created.length} categories created</strong></p>`;
        }
        
        if (data.categories_existed && data.categories_existed.length > 0) {
            html += `<p>${data.categories_existed.length} already existed</p>`;
        }
        
        if (data.meta_updated && data.meta_updated.length > 0) {
            html += `<p>${data.meta_updated.length} had meta updated</p>`;
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div class="cg-result-errors"><h4>⚠ Issues:</h4><ul>';
            data.errors.forEach(e => html += `<li>${escapeHtml(e)}</li>`);
            html += '</ul></div>';
        }

        $resultsContent.html(html);
        $resultsCard.slideDown(300);
    }

    // Auto-load template when select changes
    function loadTemplate(type, id, callback) {
        if (!id) {
            currentTemplateId[type] = null;
            return;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_template', nonce: cgAdmin.nonce, type: type, id: id },
            success: function(response) {
                if (response.success && callback) {
                    callback(response.data);
                    currentTemplateId[type] = id;
                    templateModified[type] = false;
                    updateSaveButton(type);
                }
            }
        });
    }
    
    // Load saved titles/areas
    function loadSavedContent(type, id, callback) {
        if (!id) {
            currentTemplateId[type] = null;
            return;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_saved_' + type, nonce: cgAdmin.nonce, id: id },
            success: function(response) {
                if (response.success && callback) {
                    callback(response.data);
                    currentTemplateId[type] = id;
                    templateModified[type] = false;
                    updateSaveButton(type);
                }
            }
        });
    }

    // Update save button state based on modification
    function updateSaveButton(type) {
        const $saveBtn = $(`#cg-save-${type}-btn, #cg-save-${type}-template`);
        if (templateModified[type] && currentTemplateId[type]) {
            $saveBtn.removeClass('cg-hidden').addClass('cg-modified');
        } else {
            $saveBtn.addClass('cg-hidden').removeClass('cg-modified');
        }
    }

    // Mark template as modified
    function markModified(type) {
        templateModified[type] = true;
        updateSaveButton(type);
    }

    // Save template
    function saveTemplate(type, asNew = false) {
        let name, id, data;
        
        if (asNew) {
            name = prompt('Enter name for new template:');
            if (!name) return;
            id = 0;
        } else {
            id = currentTemplateId[type];
            if (!id) {
                Toast.warning('No Template Selected', 'Please select a template to save.');
                return;
            }
            const $select = $(`#cg-${type}-template-select`);
            name = $select.find('option:selected').text().replace(/\(Default\)/, '').trim();
        }
        
        data = {
            action: 'cg_save_template',
            nonce: cgAdmin.nonce,
            type: type,
            id: id,
            name: name
        };
        
        switch (type) {
            case 'html':
                data.content = $sampleHtml.val();
                data.description = '';
                break;
            case 'meta':
                data.meta_title_pattern = $('#cg-meta-title-1').val();
                data.meta_description_pattern = $('#cg-meta-description-1').val();
                data.slug_pattern = $('#cg-slug-pattern').val();
                // Collect all variations
                const metaTitles = [], metaDescs = [];
                for (let i = 1; i <= 6; i++) {
                    const val = $(`#cg-meta-title-${i}`).val();
                    if (val) metaTitles.push(val);
                }
                for (let i = 1; i <= 12; i++) {
                    const val = $(`#cg-meta-description-${i}`).val();
                    if (val) metaDescs.push(val);
                }
                data.meta_title_variations = JSON.stringify(metaTitles);
                data.meta_description_variations = JSON.stringify(metaDescs);
                break;
            case 'schema':
                data.content = $('#cg-schema-content').val();
                data.schema_type = 'LocalBusiness';
                break;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    Toast.success('Saved', cgAdmin.strings.saved);
                    templateModified[type] = false;
                    updateSaveButton(type);
                    if (asNew) location.reload();
                } else {
                    Toast.error('Error', response.data.message || cgAdmin.strings.error);
                }
            }
        });
    }
    
    // Save titles/areas
    function saveTitlesAreas(type, asNew = false) {
        let name, id, content;
        const $textarea = type === 'titles' ? $titles : $areas;
        content = $textarea.val();
        
        if (asNew) {
            name = prompt(`Enter name for saved ${type}:`);
            if (!name) return;
            id = 0;
        } else {
            id = currentTemplateId[type];
            if (!id) {
                Toast.warning('No Selection', `No saved ${type} selected to update.`);
                return;
            }
            const $select = $(`#cg-${type}-template-select`);
            name = $select.find('option:selected').text().trim();
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cg_save_' + type,
                nonce: cgAdmin.nonce,
                id: id,
                name: name,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    Toast.success('Saved', cgAdmin.strings.saved);
                    templateModified[type] = false;
                    updateSaveButton(type);
                    if (asNew) location.reload();
                } else {
                    Toast.error('Error', response.data.message || cgAdmin.strings.error);
                }
            }
        });
    }

    // Clone/Duplicate template
    function duplicateTemplate(type) {
        const id = currentTemplateId[type];
        
        if (!id) {
            Toast.warning('No Template', 'Please select a template first.');
            return;
        }
        
        const newName = prompt('Enter name for duplicated template:');
        if (!newName) return;
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cg_duplicate_template',
                nonce: cgAdmin.nonce,
                type: type,
                id: id,
                new_name: newName
            },
            success: function(response) {
                if (response.success) {
                    Toast.success('Duplicated', 'Template duplicated successfully!');
                    location.reload();
                } else {
                    Toast.error('Error', response.data.message || cgAdmin.strings.error);
                }
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Check for unsaved changes before navigation
    function checkUnsavedChanges() {
        for (const type in templateModified) {
            if (templateModified[type]) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        }
    }

    function init() {
        if (!$titles.length) return; // Not on main page
        
        $titles.on('input', function() {
            updateCounts();
            markModified('titles');
        });
        $areas.on('input', function() {
            updateCounts();
            markModified('areas');
        });
        
        // Meta description char count
        for (let i = 1; i <= 12; i++) {
            $(`#cg-meta-description-${i}`).on('input', function() {
                updateMetaDescCounts();
                markModified('meta');
            });
        }
        
        $previewBtn.on('click', previewCombinations);
        $generateBtn.on('click', generateCategories);
        
        // Placeholder insertion
        $('.cg-insert-placeholder').on('click', function() {
            const placeholder = $(this).data('placeholder');
            const textarea = $sampleHtml[0];
            const start = textarea.selectionStart;
            textarea.value = textarea.value.substring(0, start) + placeholder + textarea.value.substring(textarea.selectionEnd);
            textarea.focus();
            markModified('html');
        });
        
        $('.cg-insert-schema').on('click', function() {
            const placeholder = $(this).data('placeholder');
            const textarea = $('#cg-schema-content')[0];
            const start = textarea.selectionStart;
            textarea.value = textarea.value.substring(0, start) + placeholder + textarea.value.substring(textarea.selectionEnd);
            textarea.focus();
            markModified('schema');
        });
        
        $('#cg-include-schema').on('change', function() {
            $('#cg-schema-section').toggle(this.checked);
        });
        
        // AUTO-LOAD on select change for Titles
        $('#cg-titles-template-select').on('change', function() {
            const id = $(this).val();
            if (templateModified.titles && !confirm('Discard unsaved changes?')) {
                $(this).val(currentTemplateId.titles || '');
                return;
            }
            if (id) loadSavedContent('titles', id, data => {
                $titles.val(data.content);
                updateCounts();
            });
        });
        
        // AUTO-LOAD on select change for Areas
        $('#cg-areas-template-select').on('change', function() {
            const id = $(this).val();
            if (templateModified.areas && !confirm('Discard unsaved changes?')) {
                $(this).val(currentTemplateId.areas || '');
                return;
            }
            if (id) loadSavedContent('areas', id, data => {
                $areas.val(data.content);
                updateCounts();
            });
        });
        
        // AUTO-LOAD on select change for HTML template
        $('#cg-html-template-select').on('change', function() {
            const id = $(this).val();
            if (templateModified.html && !confirm('Discard unsaved changes?')) {
                $(this).val(currentTemplateId.html || '');
                return;
            }
            if (id) loadTemplate('html', id, data => {
                $sampleHtml.val(data.content);
            });
        });
        
        // AUTO-LOAD on select change for Meta template
        $('#cg-meta-template-select').on('change', function() {
            const id = $(this).val();
            if (templateModified.meta && !confirm('Discard unsaved changes?')) {
                $(this).val(currentTemplateId.meta || '');
                return;
            }
            if (id) loadTemplate('meta', id, data => {
                $('#cg-meta-title-1').val(data.meta_title_pattern);
                $('#cg-meta-description-1').val(data.meta_description_pattern);
                $('#cg-slug-pattern').val(data.slug_pattern);
                // Load variations if available
                if (data.meta_title_variations) {
                    try {
                        const variations = JSON.parse(data.meta_title_variations);
                        variations.forEach((v, i) => $(`#cg-meta-title-${i+1}`).val(v));
                    } catch(e) {}
                }
                if (data.meta_description_variations) {
                    try {
                        const variations = JSON.parse(data.meta_description_variations);
                        variations.forEach((v, i) => $(`#cg-meta-description-${i+1}`).val(v));
                    } catch(e) {}
                }
                updateMetaDescCounts();
            });
        });
        
        // AUTO-LOAD on select change for Schema template
        $('#cg-schema-template-select').on('change', function() {
            const id = $(this).val();
            if (templateModified.schema && !confirm('Discard unsaved changes?')) {
                $(this).val(currentTemplateId.schema || '');
                return;
            }
            if (id) loadTemplate('schema', id, data => {
                $('#cg-schema-content').val(data.schema_content);
            });
        });
        
        // Track modifications
        $sampleHtml.on('input', () => markModified('html'));
        for (let i = 1; i <= 6; i++) {
            $(`#cg-meta-title-${i}`).on('input', () => markModified('meta'));
        }
        $('#cg-slug-pattern').on('input', () => markModified('meta'));
        $('#cg-schema-content').on('input', () => markModified('schema'));
        
        // Save buttons (update existing)
        $('#cg-save-titles-btn').on('click', () => saveTitlesAreas('titles'));
        $('#cg-save-areas-btn').on('click', () => saveTitlesAreas('areas'));
        $('#cg-save-html-template').on('click', () => saveTemplate('html'));
        $('#cg-save-meta-template').on('click', () => saveTemplate('meta'));
        $('#cg-save-schema-template').on('click', () => saveTemplate('schema'));
        
        // Save As New buttons
        $('#cg-save-titles-as-new').on('click', () => saveTitlesAreas('titles', true));
        $('#cg-save-areas-as-new').on('click', () => saveTitlesAreas('areas', true));
        $('#cg-save-html-as-new').on('click', () => saveTemplate('html', true));
        $('#cg-save-meta-as-new').on('click', () => saveTemplate('meta', true));
        $('#cg-save-schema-as-new').on('click', () => saveTemplate('schema', true));
        
        // Clone buttons
        $('#cg-clone-html-template').on('click', () => duplicateTemplate('html'));
        
        // Warn before leaving with unsaved changes
        $(window).on('beforeunload', checkUnsavedChanges);
        
        // Template checkbox toggle styling
        $('.cg-template-checkbox-item input[type="checkbox"]').on('change', function() {
            const $item = $(this).closest('.cg-template-checkbox-item');
            if (this.checked) {
                $item.addClass('selected');
            } else {
                $item.removeClass('selected');
            }
            
            // Limit to 5 selections
            const $checkboxes = $('.cg-template-checkbox-item input[type="checkbox"]');
            const checkedCount = $checkboxes.filter(':checked').length;
            if (checkedCount >= 5) {
                $checkboxes.not(':checked').prop('disabled', true);
            } else {
                $checkboxes.prop('disabled', false);
            }
        });
        
        // Initialize checkbox states
        $('.cg-template-checkbox-item input[type="checkbox"]:checked').each(function() {
            $(this).closest('.cg-template-checkbox-item').addClass('selected');
        });
        
        updateCounts();
        updateMetaDescCounts();
    }

    $(document).ready(init);

})(jQuery);
