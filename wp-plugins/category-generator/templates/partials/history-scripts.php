<?php
/**
 * History Page Scripts Partial
 * 
 * JavaScript for the history page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}

$js_constants = CG_Constants::get_js_constants();
$js_css = CG_CSS::get_js_classes();
$js_ids = CG_CSS::get_js_ids();
$has_yoast = defined('WPSEO_VERSION');
?>
<script>
jQuery(document).ready(function($) {
    // Constants from PHP
    var CG_CONST = <?php echo json_encode($js_constants); ?>;
    var CG_CSS = <?php echo json_encode($js_css); ?>;
    var CG_IDS = <?php echo json_encode($js_ids); ?>;
    
    let currentPage = 1;
    let perPage = CG_CONST.pagination.default;
    const hasYoast = <?php echo $has_yoast ? 'true' : 'false'; ?>;
    const colSpan = hasYoast ? CG_CONST.columns.historyWithYoast : CG_CONST.columns.historyDefault;
    
    function getPerPage() {
        const val = $('#' + CG_IDS.history.perPage).val();
        return val === 'all' ? 'all' : parseInt(val);
    }
    
    function loadHistory(page, search) {
        page = page || 1;
        search = search || '';
        currentPage = page;
        perPage = getPerPage();
        $('#' + CG_IDS.history.body).html('<tr class="' + CG_CSS.table.loadingRow + '"><td colspan="' + colSpan + '" style="text-align:center;padding:40px;"><span class="spinner is-active" style="float:none;"></span> Loading...</td></tr>');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_category_history', nonce: cgAdmin.nonce, page: page, per_page: perPage, search: search, include_yoast: hasYoast ? 1 : 0 },
            success: function(response) {
                if (response.success) renderHistory(response.data);
                else $('#' + CG_IDS.history.body).html('<tr class="' + CG_CSS.table.emptyRow + '"><td colspan="' + colSpan + '">Error loading history</td></tr>');
            },
            error: function() {
                $('#' + CG_IDS.history.body).html('<tr class="' + CG_CSS.table.emptyRow + '"><td colspan="' + colSpan + '">Error loading history</td></tr>');
            }
        });
    }
    
    function renderHistory(data) {
        const { history, total, pages, current_page } = data;
        $('#' + CG_IDS.history.total).text(total);
        $('#' + CG_IDS.history.selectAll).prop('checked', false);
        updateBulkActionsBar();
        
        if (history.length === 0) {
            $('#' + CG_IDS.history.body).html('<tr class="' + CG_CSS.table.emptyRow + '"><td colspan="' + colSpan + '">No categories found.</td></tr>');
            $('#' + CG_IDS.history.pagination).empty();
            return;
        }
        
        let html = '';
        history.forEach(function(item) {
            const hasSchema = item.has_schema == 1 ? 'Yes' : 'No';
            const editUrl = '<?php echo admin_url('term.php?taxonomy='); ?>' + item.taxonomy + '&tag_ID=' + item.term_id;
            const metaTitle = item.meta_title ? escapeHtml(item.meta_title.substring(0, CG_CONST.truncate.short)) + (item.meta_title.length > CG_CONST.truncate.short ? '...' : '') : '-';
            const metaDesc = item.meta_description ? escapeHtml(item.meta_description.substring(0, CG_CONST.truncate.medium)) + (item.meta_description.length > CG_CONST.truncate.medium ? '...' : '') : '-';
            
            let yoastCol = '';
            if (hasYoast) {
                const score = item.yoast_score || 0;
                let scoreClass = CG_CSS.yoast.na;
                let scoreTitle = 'Not analyzed';
                if (score >= CG_CONST.yoastScore.good) { scoreClass = CG_CSS.yoast.good; scoreTitle = 'Good (' + score + ')'; }
                else if (score >= CG_CONST.yoastScore.ok) { scoreClass = CG_CSS.yoast.ok; scoreTitle = 'Needs improvement (' + score + ')'; }
                else if (score > 0) { scoreClass = CG_CSS.yoast.bad; scoreTitle = 'Poor (' + score + ')'; }
                yoastCol = '<td><span class="' + CG_CSS.yoast.score + ' ' + scoreClass + '" title="' + scoreTitle + '"></span></td>';
            }
            
            html += '<tr data-id="' + item.id + '" data-term-id="' + item.term_id + '" data-taxonomy="' + item.taxonomy + '">' +
                '<td class="column-cb"><input type="checkbox" class="' + CG_CSS.table.rowCheckbox + '" value="' + item.id + '"></td>' +
                '<td>' + item.term_id + '</td>' +
                '<td class="column-name" title="' + escapeHtml(item.category_name) + '">' + escapeHtml(item.category_name) + '</td>' +
                '<td class="column-slug" title="' + escapeHtml(item.slug || '') + '">' + escapeHtml(item.slug || '-') + '</td>' +
                '<td>' + escapeHtml(item.title) + '</td>' +
                '<td>' + escapeHtml(item.area || '-') + '</td>' +
                '<td>' + item.taxonomy + '</td>' +
                '<td class="column-meta-title" title="' + escapeHtml(item.meta_title || '') + '">' + metaTitle + '</td>' +
                '<td class="column-meta-desc" title="' + escapeHtml(item.meta_description || '') + '">' + metaDesc + '</td>' +
                '<td><span class="' + CG_CSS.badge.base + ' ' + CG_CSS.badge[hasSchema.toLowerCase()] + '">' + hasSchema + '</span></td>' +
                yoastCol +
                '<td>' + item.created_at + '</td>' +
                '<td>' +
                    '<a href="#" class="' + CG_CSS.action.link + ' ' + CG_CSS.action.viewHistory + '" data-id="' + item.id + '">View</a>' +
                    '<a href="#" class="' + CG_CSS.action.link + ' ' + CG_CSS.action.injectLink + ' cg-inject-history" data-id="' + item.id + '">Inject</a>' +
                    '<a href="' + editUrl + '" class="' + CG_CSS.action.link + '" target="_blank">Edit</a>' +
                '</td>' +
            '</tr>';
        });
        
        $('#' + CG_IDS.history.body).html(html);
        renderPagination(pages, current_page);
    }
    
    function renderPagination(totalPages, currentPage) {
        if (totalPages <= 1 || perPage === 'all') { $('#' + CG_IDS.history.pagination).empty(); return; }
        
        let html = '<button class="button ' + (currentPage === 1 ? 'disabled' : '') + '" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + '>&laquo;</button>';
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += '<button class="button ' + (i === currentPage ? CG_CSS.pagination.current : '') + '" data-page="' + i + '">' + i + '</button>';
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span>...</span>';
            }
        }
        html += '<button class="button ' + (currentPage === totalPages ? 'disabled' : '') + '" data-page="' + (currentPage + 1) + '" ' + (currentPage === totalPages ? 'disabled' : '') + '>&raquo;</button>';
        $('#' + CG_IDS.history.pagination).html(html);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Selection & Bulk Actions
    function getSelectedIds() {
        return $('.' + CG_CSS.table.rowCheckbox + ':checked').map(function() { return $(this).val(); }).get();
    }
    
    function getSelectedTermIds() {
        return $('.' + CG_CSS.table.rowCheckbox + ':checked').closest('tr').map(function() { 
            return { term_id: $(this).data('term-id'), taxonomy: $(this).data('taxonomy') }; 
        }).get();
    }
    
    function updateBulkActionsBar() {
        const count = getSelectedIds().length;
        $('#' + CG_IDS.history.selectedCount).text(count);
        if (count > 0) {
            $('#' + CG_IDS.history.bulkActionsBar).slideDown(CG_CONST.animation.fadeDuration);
        } else {
            $('#' + CG_IDS.history.bulkActionsBar).slideUp(CG_CONST.animation.fadeDuration);
        }
    }
    
    // Select all checkbox
    $('#' + CG_IDS.history.selectAll).on('change', function() {
        $('.' + CG_CSS.table.rowCheckbox).prop('checked', $(this).is(':checked'));
        updateBulkActionsBar();
    });
    
    // Individual row checkbox
    $(document).on('change', '.' + CG_CSS.table.rowCheckbox, function() {
        updateBulkActionsBar();
        if (!$(this).is(':checked')) {
            $('#' + CG_IDS.history.selectAll).prop('checked', false);
        }
    });
    
    // Cancel bulk selection
    $('#' + CG_IDS.history.bulkCancel).on('click', function() {
        $('#' + CG_IDS.history.selectAll).prop('checked', false);
        $('.' + CG_CSS.table.rowCheckbox).prop('checked', false);
        updateBulkActionsBar();
    });
    
    // Bulk actions
    $('.' + CG_CSS.bulk.action).on('click', function() {
        const action = $(this).data('action');
        const ids = getSelectedIds();
        const termData = getSelectedTermIds();
        
        if (ids.length === 0) {
            alert('<?php _e('Please select at least one item.', 'category-generator'); ?>');
            return;
        }
        
        if (action === 'snapshot') {
            const name = prompt('<?php _e('Enter snapshot name:', 'category-generator'); ?>', 'Bulk Snapshot - ' + ids.length + ' items');
            if (!name) return;
            
            $.ajax({
                url: cgAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'cg_create_snapshot', nonce: cgAdmin.nonce, name: name, notes: 'Created from history bulk action with ' + ids.length + ' selected items' },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Snapshot created successfully!', 'category-generator'); ?>');
                    } else {
                        alert('<?php _e('Error:', 'category-generator'); ?> ' + (response.data.message || 'Unknown error'));
                    }
                }
            });
        } else if (action === 'delete-logs') {
            if (!confirm('<?php _e('Are you sure you want to remove these history logs? This will NOT delete the actual categories.', 'category-generator'); ?>')) return;
            
            $.ajax({
                url: cgAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'cg_bulk_delete_history', nonce: cgAdmin.nonce, ids: ids },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Deleted', 'category-generator'); ?> ' + response.data.deleted + ' <?php _e('log(s).', 'category-generator'); ?>');
                        loadHistory(currentPage, $('#' + CG_IDS.history.search).val());
                    } else {
                        alert('<?php _e('Error:', 'category-generator'); ?> ' + (response.data.message || 'Unknown error'));
                    }
                }
            });
        } else if (action === 'delete-all') {
            if (!confirm('<?php _e('⚠️ WARNING: This will delete history logs AND their corresponding WordPress categories. This action cannot be undone!', 'category-generator'); ?>')) return;
            if (!confirm('<?php _e('Please confirm again that you want to permanently delete these categories from WordPress.', 'category-generator'); ?>')) return;
            
            $.ajax({
                url: cgAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'cg_bulk_delete_history_and_categories', nonce: cgAdmin.nonce, ids: ids, terms: termData },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Deleted', 'category-generator'); ?> ' + response.data.logs_deleted + ' <?php _e('log(s) and', 'category-generator'); ?> ' + response.data.terms_deleted + ' <?php _e('category(ies).', 'category-generator'); ?>');
                        loadHistory(currentPage, $('#' + CG_IDS.history.search).val());
                    } else {
                        alert('<?php _e('Error:', 'category-generator'); ?> ' + (response.data.message || 'Unknown error'));
                    }
                }
            });
        }
    });
    
    function viewHistoryItem(id) {
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_history_item', nonce: cgAdmin.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    const item = response.data;
                    let html = 
                        '<div class="cg-view-section"><h4><?php _e('Category Name', 'category-generator'); ?></h4><code>' + escapeHtml(item.category_name) + '</code></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Slug', 'category-generator'); ?></h4><code>' + escapeHtml(item.slug || '-') + '</code></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Title / Area', 'category-generator'); ?></h4><code>' + escapeHtml(item.title) + '</code> / <code>' + escapeHtml(item.area || '-') + '</code></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Meta Title', 'category-generator'); ?></h4><pre>' + escapeHtml(item.meta_title || '-') + '</pre></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Meta Description', 'category-generator'); ?></h4><pre>' + escapeHtml(item.meta_description || '-') + '</pre></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Has Schema', 'category-generator'); ?></h4><span class="' + CG_CSS.badge.base + ' ' + CG_CSS.badge[item.has_schema == 1 ? 'yes' : 'no'] + '">' + (item.has_schema == 1 ? 'Yes' : 'No') + '</span></div>' +
                        '<div class="cg-view-section"><h4><?php _e('Created', 'category-generator'); ?></h4>' + item.created_at + '</div>';
                    $('#cg-history-view-content').html(html);
                    $('#cg-history-view-modal').show();
                }
            }
        });
    }
    
    // Open inject modal for a history item
    function openInjectModal(historyId) {
        $('#cg-inject-history-id').val(historyId);
        $('#cg-inject-template-select').val('');
        $('#cg-inject-template-preview').html('<em><?php _e('Select a template to see preview', 'category-generator'); ?></em>');
        $('#cg-inject-content').val('<?php _e('Loading...', 'category-generator'); ?>');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'cg_get_history_item', nonce: cgAdmin.nonce, id: historyId },
            success: function(response) {
                if (response.success) {
                    const termId = response.data.term_id;
                    const taxonomy = response.data.taxonomy;
                    
                    $.ajax({
                        url: cgAdmin.ajaxUrl,
                        type: 'POST',
                        data: { action: 'cg_get_term_description', nonce: cgAdmin.nonce, term_id: termId, taxonomy: taxonomy },
                        success: function(descResponse) {
                            if (descResponse.success) {
                                $('#cg-inject-content').val(descResponse.data.description || '');
                            } else {
                                $('#cg-inject-content').val(response.data.meta_description || '');
                            }
                        },
                        error: function() {
                            $('#cg-inject-content').val(response.data.meta_description || '');
                        }
                    });
                }
            }
        });
        
        $('#cg-inject-modal').show();
    }
    
    // Perform the injection
    function performInject(position) {
        const historyId = $('#cg-inject-history-id').val();
        const templateId = $('#cg-inject-template-select').val();
        const content = $('#cg-inject-content').val();
        
        if (!templateId) {
            alert('<?php _e('Please select an inner template.', 'category-generator'); ?>');
            return;
        }
        
        const templateContent = $('#cg-inject-template-select option:selected').data('content') || '';
        let newContent;
        
        if (position === 'start') {
            newContent = templateContent + '\n' + content;
        } else if (position === 'end') {
            newContent = content + '\n' + templateContent;
        } else {
            const textarea = $('#cg-inject-content')[0];
            const cursorPos = textarea.selectionStart || 0;
            newContent = content.substring(0, cursorPos) + templateContent + content.substring(cursorPos);
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cg_inject_inner_template',
                nonce: cgAdmin.nonce,
                history_id: historyId,
                inner_template_id: templateId,
                new_content: newContent
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Template injected successfully!', 'category-generator'); ?>');
                    $('#cg-inject-modal').hide();
                    loadHistory(currentPage, $('#' + CG_IDS.history.search).val());
                } else {
                    alert(response.data.message || '<?php _e('Error injecting template', 'category-generator'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error injecting template', 'category-generator'); ?>');
            }
        });
    }
    
    // Event handlers
    $('#cg-history-search-btn').on('click', function() { loadHistory(1, $('#' + CG_IDS.history.search).val()); });
    $('#' + CG_IDS.history.search).on('keypress', function(e) { if (e.which === 13) loadHistory(1, $(this).val()); });
    $('#' + CG_IDS.history.perPage).on('change', function() { loadHistory(1, $('#' + CG_IDS.history.search).val()); });
    $(document).on('click', '#' + CG_IDS.history.pagination + ' button:not(.disabled)', function() { loadHistory($(this).data('page'), $('#' + CG_IDS.history.search).val()); });
    $(document).on('click', '.' + CG_CSS.action.viewHistory, function(e) { e.preventDefault(); viewHistoryItem($(this).data('id')); });
    $(document).on('click', '.cg-inject-history', function(e) { e.preventDefault(); openInjectModal($(this).data('id')); });
    $(document).on('click', '.' + CG_CSS.modal.close, function() { $(this).closest('.' + CG_CSS.modal.base).hide(); });
    $(document).on('click', '.' + CG_CSS.modal.base, function(e) { if (e.target === this) $(this).hide(); });
    
    // Template preview on select change
    $('#cg-inject-template-select').on('change', function() {
        const content = $(this).find('option:selected').data('content');
        if (content) {
            $('#cg-inject-template-preview').text(content);
        } else {
            $('#cg-inject-template-preview').html('<em><?php _e('Select a template to see preview', 'category-generator'); ?></em>');
        }
    });
    
    // Inject buttons
    $('#cg-inject-cancel').on('click', function() { $('#cg-inject-modal').hide(); });
    $('#cg-inject-at-start').on('click', function() { performInject('start'); });
    $('#cg-inject-at-end').on('click', function() { performInject('end'); });
    $('#cg-inject-at-cursor').on('click', function() { performInject('cursor'); });
    
    // Export
    $('#cg-history-export-btn').on('click', function() {
        window.location.href = cgAdmin.ajaxUrl + '?action=cg_export_data&nonce=' + cgAdmin.nonce + '&type=history';
    });
    
    // Import modal
    $('#cg-history-import-btn').on('click', function() { $('#cg-history-import-modal').show(); });
    $('#cg-history-import-submit').on('click', function() {
        const file = $('#cg-history-import-file')[0].files[0];
        if (!file) { alert('<?php _e('Please select a file', 'category-generator'); ?>'); return; }
        
        const formData = new FormData();
        formData.append('action', 'cg_import_data');
        formData.append('nonce', cgAdmin.nonce);
        formData.append('type', 'history');
        formData.append('file', file);
        formData.append('update_existing', $('#cg-history-import-update').is(':checked') ? '1' : '0');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Import completed:', 'category-generator'); ?> ' + response.data.message);
                    $('#cg-history-import-modal').hide();
                    loadHistory();
                } else {
                    alert('<?php _e('Error:', 'category-generator'); ?> ' + response.data.message);
                }
            }
        });
    });
    
    // ============= Columns Visibility Dropdown (v2.5.0) =============
    const CG_COLUMNS_STORAGE_KEY = 'cg_history_hidden_columns_v1';
    // Columns hidden by default (preserves prior responsive behavior on first load)
    const CG_DEFAULT_HIDDEN = ['column-taxonomy', 'column-schema', 'column-meta-title'];
    
    function getHiddenColumns() {
        try {
            const raw = localStorage.getItem(CG_COLUMNS_STORAGE_KEY);
            if (raw === null) return CG_DEFAULT_HIDDEN.slice();
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) { return CG_DEFAULT_HIDDEN.slice(); }
    }
    
    function saveHiddenColumns(hidden) {
        try { localStorage.setItem(CG_COLUMNS_STORAGE_KEY, JSON.stringify(hidden)); } catch (e) {}
    }
    
    function applyColumnVisibility() {
        const hidden = getHiddenColumns();
        const $table = $('#' + CG_IDS.history.table);
        $table.find('th, td').removeClass('cg-col-hidden');
        hidden.forEach(function(colClass) {
            $table.find('.' + colClass).addClass('cg-col-hidden');
        });
        $('.cg-col-toggle').each(function() {
            const col = $(this).data('col');
            $(this).prop('checked', hidden.indexOf(col) === -1);
        });
    }
    
    $('#cg-columns-toggle-btn').on('click', function(e) {
        e.stopPropagation();
        const $dd = $('#cg-columns-dropdown');
        const isOpen = $dd.is(':visible');
        $dd.toggle();
        $(this).attr('aria-expanded', !isOpen);
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.cg-columns-dropdown-wrapper').length) {
            $('#cg-columns-dropdown').hide();
            $('#cg-columns-toggle-btn').attr('aria-expanded', 'false');
        }
    });
    
    $(document).on('change', '.cg-col-toggle', function() {
        const col = $(this).data('col');
        const visible = $(this).is(':checked');
        let hidden = getHiddenColumns();
        if (visible) {
            hidden = hidden.filter(function(c) { return c !== col; });
        } else if (hidden.indexOf(col) === -1) {
            hidden.push(col);
        }
        saveHiddenColumns(hidden);
        applyColumnVisibility();
    });
    
    $('#cg-columns-show-all').on('click', function() {
        saveHiddenColumns([]);
        applyColumnVisibility();
    });
    
    $('#cg-columns-reset').on('click', function() {
        saveHiddenColumns(CG_DEFAULT_HIDDEN.slice());
        applyColumnVisibility();
    });
    
    // Re-apply after every history render (tbody is replaced on AJAX reload)
    const _origLoadHistory = loadHistory;
    loadHistory = function(page, search) {
        const result = _origLoadHistory(page, search);
        applyColumnVisibility();
        setTimeout(applyColumnVisibility, 100);
        setTimeout(applyColumnVisibility, 600);
        return result;
    };
    
    applyColumnVisibility();
    
    loadHistory();
});
</script>
