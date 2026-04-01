<?php
/**
 * Admin Page Template - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

$saved_titles = $this->db->get_saved_titles();
$saved_areas = $this->db->get_saved_areas();
$recent_snapshots = $this->db->get_recent_snapshots(10);
$settings = CG_Settings::get_instance();
$auto_snapshot_enabled = $settings->get('auto_snapshot_before_generate', false);
?>

<div class="wrap cg-admin-wrap">
    <h1 class="cg-title">
        <span class="dashicons dashicons-category"></span>
        <?php _e('Category Generator Pro', 'category-generator'); ?>
        <span class="cg-version">v<?php echo CG_PLUGIN_VERSION; ?></span>
    </h1>
    
    <!-- Snapshot Toolbar -->
    <?php include CG_PLUGIN_PATH . 'templates/partials/admin-snapshot-toolbar.php'; ?>
    
    <div class="cg-container">
        <div class="cg-main">
            <!-- Step 1: Input Section -->
            <div class="cg-card">
                <h2>
                    <span class="cg-step-number">1</span>
                    <?php _e('Enter Titles & Areas', 'category-generator'); ?>
                </h2>
                <p class="cg-description">
                    <?php _e('Enter one item per line. The plugin will create all combinations (cross-join) of titles and areas. Use (S) suffix on areas to mark them as sub-categories.', 'category-generator'); ?>
                </p>
                
                <div class="cg-input-grid">
                    <!-- Titles Section -->
                    <div class="cg-input-group">
                        <label for="cg-titles">
                            <?php _e('Titles (Services/Products)', 'category-generator'); ?>
                            <span class="cg-hint"><?php _e('One per line - these become parent categories if enabled', 'category-generator'); ?></span>
                        </label>
                        <div class="cg-template-selector cg-inline-selector">
                            <select id="cg-titles-template-select">
                                <option value=""><?php _e('— Load saved titles —', 'category-generator'); ?></option>
                                <?php foreach ($saved_titles as $st): ?>
                                    <option value="<?php echo esc_attr($st['id']); ?>"><?php echo esc_html($st['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button cg-save-btn cg-hidden" id="cg-save-titles-btn"><?php _e('Save', 'category-generator'); ?></button>
                            <button type="button" class="button" id="cg-save-titles-as-new" title="<?php esc_attr_e('Save As New', 'category-generator'); ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <textarea id="cg-titles" rows="8" placeholder="<?php esc_attr_e("Commercial Cleaning\nOffice Cleaning\nCarpet Cleaning\nWindow Cleaning", 'category-generator'); ?>"></textarea>
                        <span class="cg-count"><span id="cg-titles-count">0</span> <?php _e('items', 'category-generator'); ?></span>
                    </div>
                    
                    <!-- Areas Section -->
                    <div class="cg-input-group">
                        <label for="cg-areas">
                            <?php _e('Areas (Locations)', 'category-generator'); ?>
                            <span class="cg-hint"><?php _e('One per line - add (S) suffix to make sub-category', 'category-generator'); ?></span>
                        </label>
                        <div class="cg-template-selector cg-inline-selector">
                            <select id="cg-areas-template-select">
                                <option value=""><?php _e('— Load saved areas —', 'category-generator'); ?></option>
                                <?php foreach ($saved_areas as $sa): ?>
                                    <option value="<?php echo esc_attr($sa['id']); ?>"><?php echo esc_html($sa['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button cg-save-btn cg-hidden" id="cg-save-areas-btn"><?php _e('Save', 'category-generator'); ?></button>
                            <button type="button" class="button" id="cg-save-areas-as-new" title="<?php esc_attr_e('Save As New', 'category-generator'); ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <textarea id="cg-areas" rows="8" placeholder="<?php esc_attr_e("Melbourne\nSydney(S)\nBrisbane(S)\nPerth", 'category-generator'); ?>"></textarea>
                        <span class="cg-count"><span id="cg-areas-count">0</span> <?php _e('items', 'category-generator'); ?></span>
                    </div>
                </div>
                
                <div class="cg-format-section">
                    <label for="cg-format">
                        <?php _e('Category Name Format', 'category-generator'); ?>
                    </label>
                    <input type="text" id="cg-format" value="{title} {area}" placeholder="{title} {area}">
                    <p class="cg-hint">
                        <?php _e('Available: {title}, {area}, {Title}, {Area}, {TITLE}, {AREA}', 'category-generator'); ?>
                    </p>
                </div>
                
                <!-- Parent/Child Options -->
                <div class="cg-options-grid">
                    <label class="cg-checkbox-label">
                        <input type="checkbox" id="cg-create-parents" checked>
                        <span><?php _e('Create title as parent category first', 'category-generator'); ?></span>
                        <span class="cg-hint-inline"><?php _e('(e.g., "Commercial Cleaning" becomes a parent)', 'category-generator'); ?></span>
                    </label>
                    
                    <label class="cg-checkbox-label">
                        <input type="checkbox" id="cg-make-children">
                        <span><?php _e('Make ALL cross-joined categories as children of their title', 'category-generator'); ?></span>
                        <span class="cg-hint-inline"><?php _e('(or use (S) notation per area)', 'category-generator'); ?></span>
                    </label>
                </div>
            </div>
            
            <!-- Step 2: HTML Template -->
            <div class="cg-card">
                <h2>
                    <span class="cg-step-number">2</span>
                    <?php _e('HTML Description Template', 'category-generator'); ?>
                </h2>
                
                <!-- Multi-Template Selection (for random variation) -->
                <div class="cg-template-multi-select">
                    <div class="cg-template-multi-select-header">
                        <label><?php _e('Select Templates for Random Variation (up to 5):', 'category-generator'); ?></label>
                        <span class="cg-hint"><?php _e('During generation, one will be randomly selected per category', 'category-generator'); ?></span>
                    </div>
                    <div class="cg-template-checkboxes" id="cg-html-template-checkboxes">
                        <?php foreach ($html_templates as $index => $template): ?>
                            <?php if (empty($template['is_faq'])): ?>
                            <label class="cg-template-checkbox-item">
                                <input type="checkbox" name="cg_html_templates[]" value="<?php echo esc_attr($template['id']); ?>" <?php echo $index < 5 && $template['is_default'] ? 'checked' : ''; ?>>
                                <span><?php echo esc_html($template['name']); ?></span>
                            </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="cg-template-selector">
                    <label for="cg-html-template-select"><?php _e('Edit Template:', 'category-generator'); ?></label>
                    <select id="cg-html-template-select">
                        <option value=""><?php _e('— Select a template —', 'category-generator'); ?></option>
                        <?php foreach ($html_templates as $template): ?>
                            <option value="<?php echo esc_attr($template['id']); ?>" data-category="<?php echo esc_attr($template['category'] ?? ''); ?>">
                                <?php echo esc_html($template['name']); ?>
                                <?php if ($template['is_default']): ?>(Default)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button cg-save-btn cg-hidden" id="cg-save-html-template">
                        <i class="fas fa-save"></i> <?php _e('Save', 'category-generator'); ?>
                    </button>
                    <button type="button" class="button" id="cg-save-html-as-new" title="<?php esc_attr_e('Save As New', 'category-generator'); ?>">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="button" id="cg-clone-html-template" title="<?php esc_attr_e('Clone', 'category-generator'); ?>">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <div class="cg-sample-section">
                    <textarea id="cg-sample-html" rows="12" placeholder="<?php esc_attr_e('<div class="category-intro">
    <h2>Find the Best {title} in {area}</h2>
    <p>Looking for professional {title} services in {area}?</p>
</div>', 'category-generator'); ?>"></textarea>
                </div>
                
                <div class="cg-template-helpers">
                    <strong><?php _e('Quick Insert:', 'category-generator'); ?></strong>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{title}">{title}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{area}">{area}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{category}">{category}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{slug}">{slug}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{url}">{url}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{business_name}">{business_name}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{meta_title}">{meta_title}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{meta_description}">{meta_desc}</button>
                    <button type="button" class="button cg-insert-placeholder" data-placeholder="{inner:}">{inner:}</button>
                </div>
                
                <!-- FAQ Section with 4 Variations -->
                <div class="cg-faq-section">
                    <h4>
                        <i class="fas fa-question-circle"></i>
                        <?php _e('FAQ Variations (Random Selection + Schema)', 'category-generator'); ?>
                    </h4>
                    <p class="cg-hint" style="margin-bottom: 12px;">
                        <?php _e('Each FAQ variation generates both visible HTML and FAQPage Schema.org JSON-LD. One variation is randomly selected during generation.', 'category-generator'); ?>
                    </p>
                    
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="cg-faq-variation">
                        <div class="cg-faq-variation-header">
                            <span class="cg-faq-variation-num"><?php echo $i; ?></span>
                            <span><?php printf(__('FAQ Variation %d', 'category-generator'), $i); ?></span>
                        </div>
                        <textarea id="cg-faq-variation-<?php echo $i; ?>" class="cg-faq-field" placeholder="<?php echo esc_attr($i === 1 ? '<!-- FAQ Block -->
<div class="faq-section">
    <h3>Frequently Asked Questions about {title} in {area}</h3>
    <div class="faq-item">
        <h4>Q: What {title} services do you offer in {area}?</h4>
        <p>A: We provide comprehensive {title} solutions tailored for {area} businesses.</p>
    </div>
    <div class="faq-item">
        <h4>Q: How do I get a quote for {title}?</h4>
        <p>A: Contact us for a free consultation and custom quote.</p>
    </div>
</div>' : 'FAQ Variation ' . $i . ' (optional)'); ?>"></textarea>
                    </div>
                    <?php endfor; ?>
                    
                    <label class="cg-checkbox-label" style="margin-top: 12px;">
                        <input type="checkbox" id="cg-include-faq-schema" checked>
                        <span><?php _e('Generate FAQPage Schema.org JSON-LD for FAQ content', 'category-generator'); ?></span>
                    </label>
                </div>
            </div>
            
            <!-- Step 3: Yoast SEO Settings -->
            <div class="cg-card">
                <h2>
                    <span class="cg-step-number">3</span>
                    <?php _e('Yoast SEO Settings', 'category-generator'); ?>
                </h2>
                
                <div class="cg-template-selector">
                    <label for="cg-meta-template-select"><?php _e('Template:', 'category-generator'); ?></label>
                    <select id="cg-meta-template-select">
                        <option value=""><?php _e('— Select a template —', 'category-generator'); ?></option>
                        <?php foreach ($meta_templates as $template): ?>
                            <option value="<?php echo esc_attr($template['id']); ?>">
                                <?php echo esc_html($template['name']); ?>
                                <?php if ($template['is_default']): ?>(Default)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button cg-save-btn cg-hidden" id="cg-save-meta-template"><?php _e('Save', 'category-generator'); ?></button>
                    <button type="button" class="button" id="cg-save-meta-as-new" title="<?php esc_attr_e('Save As New', 'category-generator'); ?>">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </button>
                </div>
                
                <div class="cg-meta-fields">
                    <!-- Meta Title Variations (5-6) -->
                    <div class="cg-field-group">
                        <label><?php _e('Meta Title Patterns', 'category-generator'); ?> <span class="cg-hint"><?php _e('(5-6 variations, first is default)', 'category-generator'); ?></span></label>
                        <div class="cg-meta-variations-scroll">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <div class="cg-meta-variation">
                                <span class="cg-variation-num"><?php echo $i; ?></span>
                                <input type="text" id="cg-meta-title-<?php echo $i; ?>" class="cg-meta-title-field" placeholder="<?php echo $i === 1 ? '{title} {area} | Professional Services | {business_name}' : 'Variation ' . $i . ' (optional)'; ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Meta Description Variations (10-12) -->
                    <div class="cg-field-group">
                        <label><?php _e('Meta Description Patterns', 'category-generator'); ?> <span class="cg-hint"><?php _e('(10-12 variations, must be 135+ chars)', 'category-generator'); ?></span></label>
                        <div class="cg-meta-variations-scroll cg-meta-desc-scroll">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <div class="cg-meta-variation cg-meta-desc-variation">
                                <span class="cg-variation-num"><?php echo $i; ?></span>
                                <textarea id="cg-meta-description-<?php echo $i; ?>" class="cg-meta-desc-field" rows="2" placeholder="<?php echo $i === 1 ? 'Keep your {area} business spotless with professional {title}. Reliable, flexible, and compliant services for offices, retail.' : 'Variation ' . $i . ' (optional)'; ?>"></textarea>
                                <span class="cg-char-count"><span class="cg-meta-desc-count-<?php echo $i; ?>">0</span> chars</span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="cg-field-group">
                        <label for="cg-slug-pattern"><?php _e('Slug Pattern', 'category-generator'); ?></label>
                        <input type="text" id="cg-slug-pattern" placeholder="{title}-{area}">
                        <span class="cg-hint"><?php _e('Leave empty to auto-generate from category name', 'category-generator'); ?></span>
                    </div>
                </div>
                
                <div class="cg-options-grid">
                    <label class="cg-checkbox-label">
                        <input type="checkbox" id="cg-update-existing-meta">
                        <span><?php _e('Update Yoast meta for existing categories too', 'category-generator'); ?></span>
                    </label>
                </div>
            </div>
            
            <!-- Step 4: Schema Settings -->
            <div class="cg-card">
                <h2>
                    <span class="cg-step-number">4</span>
                    <?php _e('Local Business Schema (JSON-LD)', 'category-generator'); ?>
                </h2>
                
                <div class="cg-options-grid" style="margin-bottom: 15px;">
                    <label class="cg-checkbox-label">
                        <input type="checkbox" id="cg-include-schema">
                        <span><?php _e('Include Schema.org JSON-LD in category description (wrapped in div)', 'category-generator'); ?></span>
                    </label>
                    
                    <label class="cg-checkbox-label">
                        <input type="checkbox" id="cg-use-global-schema" checked>
                        <span><?php _e('Auto-inject Business Profile data into schema', 'category-generator'); ?></span>
                    </label>
                </div>
                
                <div id="cg-schema-section" style="display: none;">
                    <div class="cg-template-selector">
                        <label for="cg-schema-template-select"><?php _e('Template:', 'category-generator'); ?></label>
                        <select id="cg-schema-template-select">
                            <option value=""><?php _e('— Select a template —', 'category-generator'); ?></option>
                            <?php foreach ($schema_templates as $template): ?>
                                <option value="<?php echo esc_attr($template['id']); ?>">
                                    <?php echo esc_html($template['name']); ?> (<?php echo esc_html($template['schema_type']); ?>)
                                    <?php if ($template['is_default']): ?>(Default)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button cg-save-btn cg-hidden" id="cg-save-schema-template"><?php _e('Save', 'category-generator'); ?></button>
                        <button type="button" class="button" id="cg-save-schema-as-new" title="<?php esc_attr_e('Save As New', 'category-generator'); ?>">
                            <span class="dashicons dashicons-plus-alt2"></span>
                        </button>
                    </div>
                    
                    <div class="cg-sample-section">
                        <textarea id="cg-schema-content" rows="10" placeholder='<?php echo esc_attr('{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "{business_name}",
  "url": "{url}"
}'); ?>'></textarea>
                    </div>
                    
                    <div class="cg-template-helpers">
                        <strong><?php _e('Schema Placeholders:', 'category-generator'); ?></strong>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{business_name}">{business_name}</button>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{phone}">{phone}</button>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{email}">{email}</button>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{website}">{website}</button>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{rating_value}">{rating_value}</button>
                        <button type="button" class="button cg-insert-schema" data-placeholder="{rating_count}">{rating_count}</button>
                    </div>
                    
                    <?php if (empty($business_profile['business_name'])): ?>
                        <div class="cg-notice cg-notice-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Business Profile not configured. ', 'category-generator'); ?>
                            <a href="<?php echo admin_url('admin.php?page=cg-business-profile'); ?>"><?php _e('Set up your Business Profile', 'category-generator'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Step 5: Settings -->
            <div class="cg-card">
                <h2>
                    <span class="cg-step-number">5</span>
                    <?php _e('WordPress Settings', 'category-generator'); ?>
                </h2>
                
                <div class="cg-settings-grid">
                    <div class="cg-setting-group">
                        <label for="cg-taxonomy">
                            <?php _e('Taxonomy', 'category-generator'); ?>
                        </label>
                        <select id="cg-taxonomy">
                            <option value="category"><?php _e('Categories (Posts)', 'category-generator'); ?></option>
                            <?php
                            $taxonomies = get_taxonomies(['public' => true, 'show_ui' => true], 'objects');
                            foreach ($taxonomies as $tax) {
                                if ($tax->name !== 'category' && $tax->name !== 'post_tag') {
                                    echo '<option value="' . esc_attr($tax->name) . '">' . esc_html($tax->label) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="cg-setting-group">
                        <label for="cg-parent">
                            <?php _e('Static Parent Category (Optional)', 'category-generator'); ?>
                        </label>
                        <select id="cg-parent">
                            <option value="0"><?php _e('— No Parent —', 'category-generator'); ?></option>
                            <?php
                            foreach ($categories as $cat) {
                                echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                            }
                            ?>
                        </select>
                        <span class="cg-hint"><?php _e('This is the top-level parent for all created categories', 'category-generator'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="cg-actions">
                <button type="button" id="cg-preview-btn" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Preview What Will Happen', 'category-generator'); ?>
                </button>
                
                <button type="button" id="cg-generate-btn" class="button button-primary button-hero" disabled>
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Generate Categories', 'category-generator'); ?>
                </button>
            </div>
        </div>
        
        <!-- Sidebar -->
        <?php include CG_PLUGIN_PATH . 'templates/partials/admin-sidebar.php'; ?>
    </div>
</div>

<!-- Loading Overlay -->
<?php include CG_PLUGIN_PATH . 'templates/partials/admin-loading.php'; ?>

<!-- Admin Styles -->
<?php include CG_PLUGIN_PATH . 'templates/partials/admin-styles.php'; ?>

<!-- Snapshot Scripts -->
<?php include CG_PLUGIN_PATH . 'templates/partials/admin-snapshot-scripts.php'; ?>
