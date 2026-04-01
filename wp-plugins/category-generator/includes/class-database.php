<?php
/**
 * SQLite Database Handler for Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim <contact@riseup-asia.com>
 * @copyright 2024 Riseup Asia LLC
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Database {
    
    private static $instance = null;
    private $db = null;
    private $db_path;
    private $db_init_error = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Store database in uploads directory (always writable)
        $upload_dir = wp_upload_dir();
        $basedir = $upload_dir['basedir'] ?? (WP_CONTENT_DIR . '/uploads');

        // Canonical location
        $new_path = $basedir . '/category-generator/db/category_generator.db';

        // Legacy location (from earlier builds)
        $legacy_path = $basedir . '/category-generator-db/category_generator.db';
        if (file_exists($legacy_path) && !file_exists($new_path)) {
            wp_mkdir_p(dirname($new_path));
            @copy($legacy_path, $new_path);
        }

        $this->db_path = $new_path;
        $this->init_database();
    }
    
    /**
     * Check if database is connected
     */
    public function is_connected() {
        return $this->db !== null;
    }
    
    /**
     * Get database file path
     */
    public function get_db_path() {
        return $this->db_path;
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->db) {
            $this->db->close();
            $this->db = null;
        }
    }
    
    /**
     * Initialize SQLite database
     */
    private function init_database() {
        // Prevent fatal errors on hosts where SQLite is not enabled
        if (!class_exists('SQLite3')) {
            $this->db_init_error = __('SQLite3 PHP extension is not enabled on this server. Category Generator requires SQLite to operate.', 'category-generator');
            if (is_admin()) {
                add_action('admin_notices', [$this, 'render_db_error_notice']);
            }
            return;
        }

        $path = $this->db_path;

        try {
            // Ensure directory exists and is writable
            $dir = dirname($path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            if (!is_writable($dir)) {
                throw new Exception('Database directory is not writable: ' . $dir);
            }

            $this->db = new SQLite3($path);
            $this->db->enableExceptions(true);

            // Create tables
            $this->create_tables();

            // Insert default templates if empty
            $this->insert_default_templates();

            // Success
            $this->db_init_error = null;
            return;
        } catch (Throwable $e) {
            $this->db = null;
            $this->db_init_error = $e->getMessage();
            error_log('Category Generator DB Error (' . $path . '): ' . $e->getMessage());
        }

        if (is_admin()) {
            add_action('admin_notices', [$this, 'render_db_error_notice']);
        }
    }

    /**
     * Admin notice when SQLite DB cannot be initialized.
     */
    public function render_db_error_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $msg = $this->db_init_error ? $this->db_init_error : __('Unknown error', 'category-generator');
        echo '<div class="notice notice-error"><p><strong>Category Generator:</strong> ' . esc_html($msg) . '</p></div>';
    }
    
    /**
     * Create all required tables
     */
    private function create_tables() {
        // Category history table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS category_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                term_id INTEGER NOT NULL,
                category_name TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT NOT NULL,
                area TEXT NOT NULL,
                parent_id INTEGER DEFAULT 0,
                taxonomy TEXT DEFAULT 'category',
                meta_title TEXT,
                meta_description TEXT,
                focus_keyword TEXT,
                has_schema INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER
            )
        ");
        
        // HTML templates table with multi-template selection support
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS html_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                content TEXT NOT NULL,
                category TEXT DEFAULT '',
                is_default INTEGER DEFAULT 0,
                is_faq INTEGER DEFAULT 0,
                faq_schema TEXT,
                template_group TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Migrate existing html_templates to add new columns if needed
        $this->migrate_html_templates_table();
        
        // Meta templates table (for Yoast)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS meta_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                meta_title_pattern TEXT,
                meta_description_pattern TEXT,
                meta_title_variations TEXT,
                meta_description_variations TEXT,
                slug_pattern TEXT,
                focus_keyword_pattern TEXT,
                category TEXT DEFAULT '',
                is_default INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Migrate existing meta_templates to add new columns if needed
        $this->migrate_meta_templates_table();
        
        // Schema templates table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS schema_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                schema_type TEXT DEFAULT 'LocalBusiness',
                schema_content TEXT NOT NULL,
                category TEXT DEFAULT '',
                is_default INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Inner templates table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS inner_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                name_id TEXT NOT NULL UNIQUE,
                type TEXT DEFAULT 'snippet',
                content TEXT NOT NULL,
                category TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Variables table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS variables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                value TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Settings table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Import/Export history table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS import_export_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation TEXT NOT NULL,
                types TEXT,
                format TEXT,
                imported_count INTEGER DEFAULT 0,
                updated_count INTEGER DEFAULT 0,
                skipped_count INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Area to postal code mapping
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS area_postal_mapping (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                area TEXT NOT NULL UNIQUE,
                postal_code TEXT NOT NULL,
                state TEXT,
                latitude REAL,
                longitude REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Business profile table (supports multiple profiles)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS business_profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                business_name TEXT,
                business_type TEXT DEFAULT 'LocalBusiness',
                street_address TEXT,
                city TEXT,
                state TEXT,
                postal_code TEXT,
                country TEXT DEFAULT 'Australia',
                phone TEXT,
                email TEXT,
                website TEXT,
                opening_hours TEXT,
                price_range TEXT,
                price_range_min REAL,
                price_range_max REAL,
                price_note TEXT DEFAULT 'subject to change',
                service_areas TEXT,
                services_offered TEXT,
                rating_value REAL,
                rating_count INTEGER,
                logo_url TEXT,
                image_url TEXT,
                social_profiles TEXT,
                is_default INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Saved titles table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS saved_titles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                content TEXT NOT NULL,
                category TEXT DEFAULT '',
                subcategory TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Saved areas table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS saved_areas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                content TEXT NOT NULL,
                category TEXT DEFAULT '',
                subcategory TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Template categories table (3-level hierarchy)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS template_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                parent_id INTEGER DEFAULT 0,
                level INTEGER DEFAULT 0,
                template_type TEXT DEFAULT 'html',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Category snapshots table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS category_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                notes TEXT,
                type TEXT DEFAULT 'manual',
                filename TEXT NOT NULL,
                filepath TEXT NOT NULL,
                terms_count INTEGER DEFAULT 0,
                taxonomy_count INTEGER DEFAULT 0,
                termmeta_count INTEGER DEFAULT 0,
                filesize INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER
            )
        ");
        
        // Create indexes
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_history_term ON category_history(term_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_history_name ON category_history(category_name)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_history_created ON category_history(created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_inner_name_id ON inner_templates(name_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(setting_key)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_saved_titles_name ON saved_titles(name)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_saved_areas_name ON saved_areas(name)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_template_categories_parent ON template_categories(parent_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_type ON category_snapshots(type)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_created ON category_snapshots(created_at)");
    }
    
    /**
     * Migrate html_templates table to add new columns
     */
    private function migrate_html_templates_table() {
        try {
            // Check if is_faq column exists
            $result = $this->db->query("PRAGMA table_info(html_templates)");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }
            
            if (!in_array('is_faq', $columns)) {
                $this->db->exec("ALTER TABLE html_templates ADD COLUMN is_faq INTEGER DEFAULT 0");
            }
            if (!in_array('faq_schema', $columns)) {
                $this->db->exec("ALTER TABLE html_templates ADD COLUMN faq_schema TEXT");
            }
            if (!in_array('template_group', $columns)) {
                $this->db->exec("ALTER TABLE html_templates ADD COLUMN template_group TEXT DEFAULT ''");
            }
        } catch (Exception $e) {
            error_log('CG HTML Template Migration: ' . $e->getMessage());
        }
    }
    
    /**
     * Migrate meta_templates table to add variation columns
     */
    private function migrate_meta_templates_table() {
        try {
            // Check if variation columns exist
            $result = $this->db->query("PRAGMA table_info(meta_templates)");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }
            
            if (!in_array('meta_title_variations', $columns)) {
                $this->db->exec("ALTER TABLE meta_templates ADD COLUMN meta_title_variations TEXT");
            }
            if (!in_array('meta_description_variations', $columns)) {
                $this->db->exec("ALTER TABLE meta_templates ADD COLUMN meta_description_variations TEXT");
            }
        } catch (Exception $e) {
            error_log('CG Meta Template Migration: ' . $e->getMessage());
        }
    }
    
    /**
     * Insert default templates
     */
    private function insert_default_templates() {
        // Check if templates already exist
        $count = $this->db->querySingle("SELECT COUNT(*) FROM html_templates");
        if ($count > 0) return;
        
        // Default HTML Templates
        $html_templates = $this->get_default_html_templates();
        foreach ($html_templates as $template) {
            $this->insert_html_template($template['name'], $template['description'], $template['content'], 1);
        }
        
        // Default Meta Templates
        $meta_templates = $this->get_default_meta_templates();
        foreach ($meta_templates as $template) {
            $this->insert_meta_template(
                $template['name'],
                $template['meta_title'],
                $template['meta_description'],
                $template['slug'],
                1
            );
        }
        
        // Default Schema Templates
        $schema_templates = $this->get_default_schema_templates();
        foreach ($schema_templates as $template) {
            $this->insert_schema_template($template['name'], $template['type'], $template['content'], 1);
        }
    }
    
    /**
     * Get default HTML templates
     */
    private function get_default_html_templates() {
        return [
            [
                'name' => 'Professional Services - Full SEO',
                'description' => 'Comprehensive 6-block SEO template with headers, CTAs, and schema-ready structure',
                'content' => '<!-- start block 1 -->
<h2 class="display-header" title="{title} services trusted by {area} businesses">
  <a title="Discover why {area} professionals choose {title}" href="{url}">
    <strong title="{title} {area} delivering exceptional standards">Why {area} Businesses Choose Professional {title}</strong>
  </a>
</h2>
<div class="seo-container-para contrast">
  Why does quality consistently matter when {area} business leaders evaluate {title} partners? Indeed, in this thriving area, excellence signals competence, integrity, and client respect. Moreover, every vibrant office and retail space across {area} depends on a trusted <strong title="Professional {title} service operating in {area}"><a title="Discover {title} service in {area}" href="{url}">{title} service</a></strong> that delivers both quality and professionalism simultaneously.
</div>
<!-- end block 1 -->

<!-- start block 2 -->
<h2 class="display-header" title="How scalable {title} supports {area} offices and retail venues">
  <a title="Can one approach serve both boutique offices and busy {area} spaces equally?" href="{url}">
    From Offices to Retail Spaces — Services That Scale
  </a>
</h2>
<div class="seo-container-para contrast">
  Every thriving business, whether a boutique firm or a bustling {area} retail outlet, deserves precision tailored to their operations. Consequently, the best <strong title="{area} {title} experts achieving sector-specific excellence"><a title="{area} {title} adapting systems for various environments" href="{url}">{area} {title} professionals</a></strong> structure their approach around your unique workflow, maintaining excellence across every sector and service level.
</div>
<!-- end block 2 -->

<!-- start block 3 -->
<h2 class="display-header" title="Why {area} clients praise reliable professional {title} execution">
  <a title="Wondering if flexible scheduling can truly keep {area} operations running smoothly?" href="{url}">
    Reliable. Professional. {area} Ready.
  </a>
</h2>
<div class="seo-container-para contrast">
  Dependable service means seamless business continuity without operational friction. Notably, flexible scheduling combined with professional methods and thoroughly trained teams maintains peak performance while productivity flows uninterrupted. This reliability builds client confidence month after month across {area}.
</div>
<!-- end block 3 -->

<!-- start block 4 -->
<h2 class="display-header" title="Why {area} workplaces prioritize compliance standards">
  <a title="Is your {area} {title} provider actually compliant with current legislation?" href="{url}">
    Safety, Compliance &amp; Confidence
  </a>
</h2>
<div class="seo-container-para contrast">
  Quality services mean nothing if they ignore relevant legislation and industry protocols. Indeed, industry leaders across {area} follow all applicable standards because workplace integrity demands it. Moreover, <strong title="Compliant {title} ensuring high audit pass rates"><a title="Review compliant {title} professionals working across {area}" href="{url}">leading professionals</a></strong> build every task around certified practices and strict adherence to requirements.
</div>
<!-- end block 4 -->

<!-- start block 5 -->
<h2 class="display-header" title="How forward-thinking {area} companies gain advantage through quality {title}">
  <a title="Can quality {title} actually deliver competitive advantage?" href="{url}">
    Future-Focused Solutions
  </a>
</h2>
<div class="seo-container-para contrast">
  Innovation continuously drives performance into tomorrow\'s standards. Therefore, from modern approaches to digital reporting systems, quality providers stay ahead of evolving business expectations and regulatory requirements. Companies choosing this approach report measurable improvements in client retention and brand preference.
</div>
<!-- end block 5 -->

<!-- start block 6 -->
<h2 class="display-header" title="Why {area} leaders view quality providers as trusted long-term partners">
  <a title="Ready to transform your {area} workspace into a brand advantage?" href="{url}">
    Final Thought: Your Trusted {title} Partner in {area}
  </a>
</h2>
<div class="seo-container-para contrast">
  In competitive precincts across {area}, partnership consistently outweighs transactional vendor relationships. Ready to join leading {area} businesses? Contact the best {title} provider in {area} and begin your partnership today.
</div>
<!-- end block 6 -->'
            ],
            [
                'name' => 'Simple Service Description',
                'description' => 'Clean, minimal template for service categories',
                'content' => '<div class="category-intro">
    <h2>Find the Best {title} in {area}</h2>
    <p>Looking for professional {title} services in {area}? We connect you with top-rated {title} professionals who serve the {area} area.</p>
    <ul>
        <li>Licensed and insured {title} experts</li>
        <li>Serving all of {area} and surrounding areas</li>
        <li>Free quotes and consultations</li>
    </ul>
</div>'
            ],
            [
                'name' => 'Local Business Focus',
                'description' => 'Template emphasizing local presence and community',
                'content' => '<div class="local-service-content">
    <h2>{title} Services in {area}</h2>
    <p>As a trusted local provider in {area}, we understand the unique needs of our community. Our {title} services are designed specifically for {area} residents and businesses.</p>
    
    <h3>Why Choose Local {title} in {area}?</h3>
    <ul>
        <li><strong>Local Knowledge:</strong> We know {area} inside and out</li>
        <li><strong>Quick Response:</strong> Being local means faster service times</li>
        <li><strong>Community Trust:</strong> Built relationships with {area} families and businesses</li>
        <li><strong>Competitive Pricing:</strong> Fair rates for quality {title} work</li>
    </ul>
    
    <p>Contact us today for your {title} needs in {area}. We\'re proud to serve this community!</p>
</div>'
            ],
            [
                'name' => 'Benefits-Focused',
                'description' => 'Template highlighting key benefits and features',
                'content' => '<div class="benefits-content">
    <h2>Premium {title} for {area}</h2>
    
    <div class="benefit-block">
        <h3>🏆 Quality Guaranteed</h3>
        <p>Our {title} services in {area} come with a satisfaction guarantee. We don\'t rest until you\'re completely happy with the results.</p>
    </div>
    
    <div class="benefit-block">
        <h3>⏰ Flexible Scheduling</h3>
        <p>We work around your schedule. Whether you need {title} services during business hours or after, we\'re here for {area} clients.</p>
    </div>
    
    <div class="benefit-block">
        <h3>💰 Transparent Pricing</h3>
        <p>No hidden fees or surprises. Get upfront pricing for all {title} work in the {area} area.</p>
    </div>
    
    <div class="benefit-block">
        <h3>🌟 Experienced Team</h3>
        <p>Our {title} professionals have years of experience serving {area} and surrounding suburbs.</p>
    </div>
</div>'
            ],
            [
                'name' => 'FAQ Style',
                'description' => 'Template structured as frequently asked questions',
                'content' => '<div class="faq-content">
    <h2>{title} in {area} - Frequently Asked Questions</h2>
    
    <div class="faq-item">
        <h3>What {title} services do you offer in {area}?</h3>
        <p>We provide comprehensive {title} solutions tailored to {area} residents and businesses. Our services cover everything from basic to advanced {title} needs.</p>
    </div>
    
    <div class="faq-item">
        <h3>How quickly can you provide {title} in {area}?</h3>
        <p>As local {area} specialists, we typically respond within 24 hours. Emergency {title} services are also available for urgent situations.</p>
    </div>
    
    <div class="faq-item">
        <h3>What are your {title} rates for {area}?</h3>
        <p>We offer competitive pricing for all {title} services in {area}. Contact us for a free, no-obligation quote tailored to your specific needs.</p>
    </div>
    
    <div class="faq-item">
        <h3>Are you licensed and insured for {title} in {area}?</h3>
        <p>Yes! All our {title} professionals are fully licensed, insured, and background-checked for your peace of mind.</p>
    </div>
</div>'
            ],
            [
                'name' => 'Commercial Cleaning Maribyrnong (Sample)',
                'description' => 'Full production template for commercial cleaning with all SEO elements',
                'content' => '<!-- start block 1 -->

<h2 class="display-header" title="73.42 {area} businesses report increasing trust by 2.15 quarterly choosing professional {title}">

  <a title="Discover why 96.51 of {area} professionals invest in {title} despite tight budgets" href="{url}">

    <strong title="{area} {title} delivering 97.34 presentation standards trusted by local leaders">Why {area} Businesses Choose Professional {title}</strong>

  </a>

</h2>

<div class="seo-container-para contrast">

  Why does presentation consistently outrank price when {area} business leaders evaluate {title} partners? Indeed, in this thriving Melbourne district, spotless workspaces signal competence, integrity, and client respect. Moreover, every vibrant office and retail space across {area} depends on a trusted <strong title="Professional {title} service operating in {area} with 94.23 consistency ratings"><a title="Discover {title} service in {area} delivering 95.67 satisfaction monthly" href="{url}">{title} service</a></strong> that safeguards both cleanliness and professionalism simultaneously. Furthermore, <strong title="{business_name} Melbourne\'s top rated cleaning authority with 5 years combined expertise"><a title="Learn why {business_name} leads Melbourne cleaning standards through proven 2.34 loyalty growth monthly" href="{website}">{business_name}</a></strong> demonstrates daily that success in {area} starts with a clean, polished foundation inspiring confidence. Ultimately, when clients and staff walk through those doors, a spotless environment speaks volumes about your commitment to excellence.

</div>

<!-- end block 1 -->

<!-- start block 2 -->

<h2 class="display-header" title="How scalable {title} supports 96.87 of {area} offices clinics and retail venues">

  <a title="Can one cleaning approach serve both boutique offices and busy {area} retail spaces equally?" href="{url}">

    From Offices to Retail Spaces — Cleaning That Scales

  </a>

</h2>

<div class="seo-container-para contrast">

  Every thriving business, whether a boutique accounting firm or a bustling {area} retail outlet, deserves precision tailored to their operations. Consequently, the best <strong title="{area} commercial cleaners achieving 98.12 sector specific excellence across mixed operations"><a title="{area} commercial cleaners adapting systems for offices clinics and retail environments" href="{url}">{area} commercial cleaners</a></strong> structure their protocols around your unique workflow, maintaining spotless clinics, shops, and hospitality venues simultaneously. Additionally, consistent quality remains the operational standard across every sector and service level. Significantly, this scalability transforms office cleaning into a strategic partner rather than just another vendor contract. Therefore, growing enterprises benefit from unified standards while expanding without service disruptions or quality compromises.

</div>

<!-- end block 2 -->

<!-- start block 3 -->

<h2 class="display-header" title="Why 95.78 of returning {area} clients praise {business_name} for reliable professional cleaning execution">

  <a title="Wondering if flexible scheduling can truly keep {area} operations running without disruption?" href="{url}">

    Reliable. Professional. {area} Ready.

  </a>

</h2>

<div class="seo-container-para contrast">

  Dependable cleaning means seamless business continuity without operational friction. Notably, flexible scheduling combined with green focused methods and thoroughly trained teams enables <strong title="{business_name} {title} maintaining 96.45 reliability scores across {area} operations"><a title="Discover office cleaning solutions in {area} tailored to production schedules and traffic patterns" href="{url}">{business_name}</a></strong> to maintain peak presentation while productivity flows uninterrupted. Furthermore, staff members execute every detail meticulously, ensuring operations continue smoothly while cleanliness speaks volumes about your standards. Significantly, this reliability builds client confidence month after month across {area}. Ultimately, when facility managers report zero cleaning related disruptions, that consistency becomes your competitive advantage in the marketplace.

</div>

<!-- end block 3 -->

<!-- start block 4 -->

<h2 class="display-header" title="Why 64.39 of {area} workplaces struggle with OH&S compliance according to fair work standards">

  <a title="Is your {area} {title} actually compliant with current OH&S legislation?" href="{url}">

    Safety, Compliance &amp; Confidence

  </a>

</h2>

<div class="seo-container-para contrast">

  Spotless surfaces mean nothing if {title} ignores OH&S legislation and infection control protocols. Indeed, industry leaders across {area} follow OH&S and COVID safe standards because workplace integrity demands it. Moreover, <strong title="OH&S compliant {title} ensuring 98.91 audit pass rates across Melbourne facilities"><a title="Review OH&S compliant cleaning professionals working across {area} with certified protocols" href="{url}">leading professionals</a></strong> build every task around certified products, safety auditing, and strict hygiene adherence. Consequently, when auditors inspect your {area} facility, that compliance framework becomes visible proof of your commitment to staff and visitor wellbeing.

</div>

<!-- end block 4 -->

<!-- start block 5 -->

<h2 class="display-header" title="How 91.67 of forward thinking {area} companies gain competitive advantage through sustainable cleaning">

  <a title="Can sustainable {title} actually deliver the same results as traditional methods?" href="{url}">

    Future Focused Cleaning Solutions

  </a>

</h2>

<div class="seo-container-para contrast">

  Innovation continuously drives hygiene performance into tomorrow\'s workplace standards. Therefore, from eco certified materials to digital check in reporting systems, quality providers stay ahead of evolving business expectations and regulatory requirements. Additionally, leading authorities recognize that organizations integrating sustainability with hygiene gain measurable long term loyalty. Ultimately, companies choosing this approach report 2.41 monthly increases in client retention and brand preference across their sectors.

</div>

<!-- end block 5 -->

<!-- start block 6 -->

<h2 class="display-header" title="Why 98.56 of {area} leaders view {business_name} as their trusted long term cleaning partner">

  <a title="Ready to transform your {area} workspace into a brand advantage rather than routine expense?" href="{url}">

    Final Thought: Your Trusted Cleaning Partner in {area}

  </a>

</h2>

<div class="seo-container-para contrast">

  In competitive precincts across {area}, partnership consistently outweighs transactional vendor relationships. Consequently, this team transforms workspaces into clean, inspiring environments fostering business growth and professionalism simultaneously. Significantly, stakeholders consistently report measurable returns on their cleaning investment through improved client first impressions and staff satisfaction metrics. Ultimately, ready to join leading {area} businesses? Simply contact <strong title="{business_name} {area} {title} experts delivering 96.72 rapid response times daily"><a title="Reach out to the best commercial cleaners in {area} for immediate consultation" href="{contact_url}">the best cleaning in {area}</a></strong> and begin your partnership today.

</div>

<!-- end block 6 -->'
            ]
        ];
    }
    
    /**
     * Get default meta templates
     */
    private function get_default_meta_templates() {
        return [
            [
                'name' => 'Professional Service Standard',
                'meta_title' => '{title} {area} | Professional Services | {business_name}',
                'meta_description' => 'Keep your {area} business spotless with professional {title}. Reliable, flexible, and compliant services for offices, retail, and commercial spaces.',
                'slug' => '{title}-{area}'
            ],
            [
                'name' => 'Local Service Focus',
                'meta_title' => 'Best {title} in {area} | Local Experts | Free Quote',
                'meta_description' => 'Looking for trusted {title} in {area}? Our experienced local team delivers quality results. Fully licensed, insured, and ready to serve. Call today!',
                'slug' => '{title}-in-{area}'
            ],
            [
                'name' => 'Commercial Cleaning Style',
                'meta_title' => '{title} {area} | Office & Commercial | {business_name}',
                'meta_description' => 'Keep your {area} business spotless with professional {title}. Reliable, flexible, and compliant services for offices, retail.',
                'slug' => '{title}-{area}'
            ],
            [
                'name' => 'Service + Location',
                'meta_title' => '{title} Services in {area} | Trusted Professionals',
                'meta_description' => 'Professional {title} services throughout {area} and surrounding suburbs. Quality workmanship, competitive prices, and exceptional customer service.',
                'slug' => '{title}-services-{area}'
            ],
            [
                'name' => 'Action-Oriented',
                'meta_title' => 'Get Expert {title} in {area} Today | Book Online',
                'meta_description' => 'Need {title} in {area}? Book online for fast, reliable service. Our certified professionals deliver outstanding results every time. Free estimates available.',
                'slug' => 'expert-{title}-{area}'
            ],
            [
                'name' => 'Trust & Quality',
                'meta_title' => 'Trusted {title} Experts | {area} | 5-Star Rated',
                'meta_description' => '{area}\'s most trusted {title} provider. 5-star rated, fully insured, and committed to excellence. Serving homes and businesses throughout {area}.',
                'slug' => 'trusted-{title}-{area}'
            ],
            [
                'name' => 'Price-Focused',
                'meta_title' => 'Affordable {title} {area} | Quality at Best Prices',
                'meta_description' => 'Quality {title} in {area} at prices you can afford. No hidden fees, transparent pricing, and satisfaction guaranteed. Request your free quote now.',
                'slug' => 'affordable-{title}-{area}'
            ],
            [
                'name' => 'Emergency Service',
                'meta_title' => '24/7 {title} {area} | Emergency Service Available',
                'meta_description' => 'Emergency {title} available 24/7 in {area}. Fast response times, professional service, and reliable results when you need them most. Call now!',
                'slug' => 'emergency-{title}-{area}'
            ],
            [
                'name' => 'Area-First SEO',
                'meta_title' => '{area} {title} | Local Professionals Near You',
                'meta_description' => 'Find the best {title} professionals in {area}. Local experts who understand your needs. Licensed, insured, and ready to help. Get started today!',
                'slug' => '{area}-{title}'
            ],
            [
                'name' => 'Residential Focus',
                'meta_title' => 'Home {title} {area} | Family-Owned Business',
                'meta_description' => 'Family-owned {title} serving {area} homes for over 10 years. We treat your home like our own. Trusted by thousands of {area} families.',
                'slug' => 'home-{title}-{area}'
            ]
        ];
    }
    
    /**
     * Get default schema templates
     */
    private function get_default_schema_templates() {
        return [
            [
                'name' => 'Local Business Standard',
                'type' => 'LocalBusiness',
                'content' => '{
  "@context": "https://schema.org",
  "@type": "{business_type}",
  "name": "{business_name}",
  "description": "{meta_description}",
  "url": "{url}",
  "telephone": "{phone}",
  "email": "{email}",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "{street_address}",
    "addressLocality": "{area}",
    "addressRegion": "{state}",
    "postalCode": "{postal_code}",
    "addressCountry": "{country}"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": "{latitude}",
    "longitude": "{longitude}"
  },
  "openingHours": {{{opening_hours}}},
  "priceRange": "{price_range}",
  "areaServed": {
    "@type": "City",
    "name": "{area}"
  },
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "{title} Services",
    "itemListElement": [
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "{title} in {area}"
        }
      }
    ]
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{rating_value}",
    "reviewCount": "{rating_count}"
  },
  "image": "{image_url}",
  "logo": "{logo_url}"
}'
            ],
            [
                'name' => 'Service Provider',
                'type' => 'ProfessionalService',
                'content' => '{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "name": "{business_name} - {title}",
  "description": "Professional {title} services in {area}. {meta_description}",
  "url": "{url}",
  "telephone": "{phone}",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "{area}",
    "addressRegion": "{state}",
    "addressCountry": "{country}"
  },
  "areaServed": ["{area}"],
  "serviceType": "{title}",
  "provider": {
    "@type": "Organization",
    "name": "{business_name}",
    "url": "{website}"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{rating_value}",
    "reviewCount": "{rating_count}",
    "bestRating": "5"
  }
}'
            ],
            [
                'name' => 'Service with FAQ',
                'type' => 'Service',
                'content' => '{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "{title}",
  "name": "{title} in {area}",
  "description": "{meta_description}",
  "provider": {
    "@type": "LocalBusiness",
    "name": "{business_name}",
    "telephone": "{phone}",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "{area}",
      "addressRegion": "{state}",
      "addressCountry": "{country}"
    }
  },
  "areaServed": {
    "@type": "City",
    "name": "{area}"
  },
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "{title} Services in {area}",
    "itemListElement": [
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "{category}"
        }
      }
    ]
  }
}'
            ]
        ];
    }
    
    // ==================== CRUD Operations ====================
    
    /**
     * Insert category history record
     */
    public function insert_category_history($data) {
        $stmt = $this->db->prepare("
            INSERT INTO category_history 
            (term_id, category_name, slug, title, area, parent_id, taxonomy, meta_title, meta_description, has_schema, created_by)
            VALUES (:term_id, :name, :slug, :title, :area, :parent_id, :taxonomy, :meta_title, :meta_desc, :has_schema, :created_by)
        ");
        
        $stmt->bindValue(':term_id', $data['term_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(':slug', $data['slug'], SQLITE3_TEXT);
        $stmt->bindValue(':title', $data['title'], SQLITE3_TEXT);
        $stmt->bindValue(':area', $data['area'], SQLITE3_TEXT);
        $stmt->bindValue(':parent_id', $data['parent_id'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':taxonomy', $data['taxonomy'] ?? 'category', SQLITE3_TEXT);
        $stmt->bindValue(':meta_title', $data['meta_title'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':meta_desc', $data['meta_description'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':has_schema', $data['has_schema'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':created_by', get_current_user_id(), SQLITE3_INTEGER);
        
        return $stmt->execute();
    }
    
    /**
     * Get category history with search/filter
     */
    public function get_category_history($args = []) {
        $defaults = [
            'search' => '',
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];
        $args = array_merge($defaults, $args);
        
        $where = "1=1";
        if (!empty($args['search'])) {
            $search = SQLite3::escapeString($args['search']);
            $where .= " AND (category_name LIKE '%{$search}%' OR title LIKE '%{$search}%' OR area LIKE '%{$search}%')";
        }
        
        $order = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';
        $allowed_cols = ['created_at', 'category_name', 'title', 'area'];
        $order_by = in_array($args['order_by'], $allowed_cols) ? $args['order_by'] : 'created_at';
        
        $sql = "SELECT * FROM category_history WHERE {$where} ORDER BY {$order_by} {$order} LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        $result = $this->db->query($sql);
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    /**
     * Get total category history count
     */
    public function get_category_history_count($search = '') {
        $where = "1=1";
        if (!empty($search)) {
            $search = SQLite3::escapeString($search);
            $where .= " AND (category_name LIKE '%{$search}%' OR title LIKE '%{$search}%' OR area LIKE '%{$search}%')";
        }
        return $this->db->querySingle("SELECT COUNT(*) FROM category_history WHERE {$where}");
    }
    
    /**
     * Get single category history item
     */
    public function get_category_history_item($id) {
        $stmt = $this->db->prepare("SELECT * FROM category_history WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    /**
     * Delete a category history item
     */
    public function delete_history_item($id) {
        $stmt = $this->db->prepare("DELETE FROM category_history WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }

    /**
     * Check if category was created by this tool
     */
    public function was_created_by_tool($term_id) {
        $stmt = $this->db->prepare("SELECT id FROM category_history WHERE term_id = :term_id LIMIT 1");
        $stmt->bindValue(':term_id', $term_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }
    
    /**
     * Delete business profile by ID
     */
    public function delete_business_profile($id) {
        $stmt = $this->db->prepare("DELETE FROM business_profile WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    // ==================== HTML Templates ====================
    
    public function insert_html_template($name, $description, $content, $is_default = 0, $is_faq = 0, $faq_schema = '', $template_group = '') {
        $stmt = $this->db->prepare("INSERT INTO html_templates (name, description, content, is_default, is_faq, faq_schema, template_group) VALUES (:name, :desc, :content, :default, :is_faq, :faq_schema, :template_group)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':default', $is_default, SQLITE3_INTEGER);
        $stmt->bindValue(':is_faq', $is_faq, SQLITE3_INTEGER);
        $stmt->bindValue(':faq_schema', $faq_schema, SQLITE3_TEXT);
        $stmt->bindValue(':template_group', $template_group, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_html_template($id, $name, $description, $content, $is_faq = 0, $faq_schema = '', $template_group = '') {
        $stmt = $this->db->prepare("UPDATE html_templates SET name = :name, description = :desc, content = :content, is_faq = :is_faq, faq_schema = :faq_schema, template_group = :template_group, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':is_faq', $is_faq, SQLITE3_INTEGER);
        $stmt->bindValue(':faq_schema', $faq_schema, SQLITE3_TEXT);
        $stmt->bindValue(':template_group', $template_group, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function get_faq_templates() {
        $result = $this->db->query("SELECT * FROM html_templates WHERE is_faq = 1 ORDER BY name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_html_templates_by_group($group) {
        $stmt = $this->db->prepare("SELECT * FROM html_templates WHERE template_group = :group ORDER BY name ASC");
        $stmt->bindValue(':group', $group, SQLITE3_TEXT);
        $result = $stmt->execute();
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function delete_html_template($id) {
        $stmt = $this->db->prepare("DELETE FROM html_templates WHERE id = :id AND is_default = 0");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_html_templates() {
        $result = $this->db->query("SELECT * FROM html_templates ORDER BY is_default DESC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_html_template($id) {
        $stmt = $this->db->prepare("SELECT * FROM html_templates WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // ==================== Meta Templates ====================
    
    public function insert_meta_template($name, $meta_title, $meta_desc, $slug, $is_default = 0, $title_variations = '', $desc_variations = '') {
        $stmt = $this->db->prepare("INSERT INTO meta_templates (name, meta_title_pattern, meta_description_pattern, slug_pattern, meta_title_variations, meta_description_variations, is_default) VALUES (:name, :title, :desc, :slug, :title_vars, :desc_vars, :default)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':title', $meta_title, SQLITE3_TEXT);
        $stmt->bindValue(':desc', $meta_desc, SQLITE3_TEXT);
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $stmt->bindValue(':title_vars', $title_variations, SQLITE3_TEXT);
        $stmt->bindValue(':desc_vars', $desc_variations, SQLITE3_TEXT);
        $stmt->bindValue(':default', $is_default, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_meta_template($id, $name, $meta_title, $meta_desc, $slug, $title_variations = '', $desc_variations = '') {
        $stmt = $this->db->prepare("UPDATE meta_templates SET name = :name, meta_title_pattern = :title, meta_description_pattern = :desc, slug_pattern = :slug, meta_title_variations = :title_vars, meta_description_variations = :desc_vars, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':title', $meta_title, SQLITE3_TEXT);
        $stmt->bindValue(':desc', $meta_desc, SQLITE3_TEXT);
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $stmt->bindValue(':title_vars', $title_variations, SQLITE3_TEXT);
        $stmt->bindValue(':desc_vars', $desc_variations, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_meta_template($id) {
        $stmt = $this->db->prepare("DELETE FROM meta_templates WHERE id = :id AND is_default = 0");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_meta_templates() {
        $result = $this->db->query("SELECT * FROM meta_templates ORDER BY is_default DESC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_meta_template($id) {
        $stmt = $this->db->prepare("SELECT * FROM meta_templates WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // ==================== Schema Templates ====================
    
    public function insert_schema_template($name, $type, $content, $is_default = 0) {
        $stmt = $this->db->prepare("INSERT INTO schema_templates (name, schema_type, schema_content, is_default) VALUES (:name, :type, :content, :default)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':default', $is_default, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_schema_template($id, $name, $type, $content) {
        $stmt = $this->db->prepare("UPDATE schema_templates SET name = :name, schema_type = :type, schema_content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_schema_template($id) {
        $stmt = $this->db->prepare("DELETE FROM schema_templates WHERE id = :id AND is_default = 0");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_schema_templates() {
        $result = $this->db->query("SELECT * FROM schema_templates ORDER BY is_default DESC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_schema_template($id) {
        $stmt = $this->db->prepare("SELECT * FROM schema_templates WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // ==================== Business Profile ====================
    
    public function save_business_profile($data) {
        // Check if profile exists
        $exists = $this->db->querySingle("SELECT id FROM business_profile LIMIT 1");
        
        if ($exists) {
            $stmt = $this->db->prepare("
                UPDATE business_profile SET
                    business_name = :name,
                    business_type = :type,
                    street_address = :street,
                    city = :city,
                    state = :state,
                    postal_code = :postal,
                    country = :country,
                    phone = :phone,
                    email = :email,
                    website = :website,
                    opening_hours = :hours,
                    price_range = :price,
                    service_areas = :areas,
                    services_offered = :services,
                    rating_value = :rating,
                    rating_count = :count,
                    logo_url = :logo,
                    image_url = :image,
                    social_profiles = :social,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->bindValue(':id', $exists, SQLITE3_INTEGER);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO business_profile 
                (business_name, business_type, street_address, city, state, postal_code, country, phone, email, website, opening_hours, price_range, service_areas, services_offered, rating_value, rating_count, logo_url, image_url, social_profiles)
                VALUES (:name, :type, :street, :city, :state, :postal, :country, :phone, :email, :website, :hours, :price, :areas, :services, :rating, :count, :logo, :image, :social)
            ");
        }
        
        $stmt->bindValue(':name', $data['business_name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':type', $data['business_type'] ?? 'LocalBusiness', SQLITE3_TEXT);
        $stmt->bindValue(':street', $data['street_address'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':city', $data['city'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':state', $data['state'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':postal', $data['postal_code'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':country', $data['country'] ?? 'Australia', SQLITE3_TEXT);
        $stmt->bindValue(':phone', $data['phone'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':email', $data['email'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':website', $data['website'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':hours', $data['opening_hours'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':price', $data['price_range'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':areas', $data['service_areas'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':services', $data['services_offered'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':rating', $data['rating_value'] ?? 0, SQLITE3_FLOAT);
        $stmt->bindValue(':count', $data['rating_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':logo', $data['logo_url'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':image', $data['image_url'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':social', $data['social_profiles'] ?? '', SQLITE3_TEXT);
        
        return $stmt->execute();
    }
    
    public function get_business_profile($id = null) {
        if ($id) {
            $stmt = $this->db->prepare("SELECT * FROM business_profile WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
        } else {
            $result = $this->db->query("SELECT * FROM business_profile LIMIT 1");
        }
        $profile = $result->fetchArray(SQLITE3_ASSOC);
        return $profile ?: [];
    }
    
    public function get_all_business_profiles() {
        $result = $this->db->query("SELECT * FROM business_profile ORDER BY business_name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items ?: [['id' => 1, 'business_name' => '']];
    }
    
    // ==================== Inner Templates ====================
    
    public function insert_inner_template($name, $name_id, $type, $content, $category = '') {
        $stmt = $this->db->prepare("INSERT INTO inner_templates (name, name_id, type, content, category) VALUES (:name, :name_id, :type, :content, :category)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':name_id', $name_id, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_inner_template($id, $name, $name_id, $type, $content, $category = '') {
        $stmt = $this->db->prepare("UPDATE inner_templates SET name = :name, name_id = :name_id, type = :type, content = :content, category = :category, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':name_id', $name_id, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_inner_template($id) {
        $stmt = $this->db->prepare("DELETE FROM inner_templates WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_inner_templates() {
        $result = $this->db->query("SELECT * FROM inner_templates ORDER BY category ASC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_inner_template($id) {
        $stmt = $this->db->prepare("SELECT * FROM inner_templates WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function get_inner_template_by_name($name_id) {
        $stmt = $this->db->prepare("SELECT * FROM inner_templates WHERE name_id = :name_id");
        $stmt->bindValue(':name_id', $name_id, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // ==================== Variables ====================
    
    public function save_variable($name, $value) {
        $existing = $this->db->querySingle("SELECT id FROM variables WHERE name = '" . SQLite3::escapeString($name) . "'");
        
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE variables SET value = :value, updated_at = CURRENT_TIMESTAMP WHERE name = :name");
        } else {
            $stmt = $this->db->prepare("INSERT INTO variables (name, value) VALUES (:name, :value)");
        }
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_variable($name) {
        $stmt = $this->db->prepare("DELETE FROM variables WHERE name = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function get_variables() {
        $result = $this->db->query("SELECT name, value FROM variables ORDER BY name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[$row['name']] = $row['value'];
        }
        return $items;
    }
    
    // ==================== Settings ====================
    
    public function save_setting($key, $value) {
        $existing = $this->db->querySingle("SELECT id FROM settings WHERE setting_key = '" . SQLite3::escapeString($key) . "'");
        
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key");
        } else {
            $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
        }
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', gettype($value) === 'array' ? json_encode($value) : $value, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function get_setting($key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }
    
    public function get_settings() {
        $result = $this->db->query("SELECT setting_key, setting_value FROM settings");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[$row['setting_key']] = $row['setting_value'];
        }
        return $items;
    }
    
    // ==================== Import/Export History ====================
    
    public function log_import_export($data) {
        $stmt = $this->db->prepare("INSERT INTO import_export_history (operation, types, format, imported_count, updated_count, skipped_count, error_count, user_id) VALUES (:op, :types, :format, :imported, :updated, :skipped, :errors, :user)");
        $stmt->bindValue(':op', $data['operation'], SQLITE3_TEXT);
        $stmt->bindValue(':types', $data['types'], SQLITE3_TEXT);
        $stmt->bindValue(':format', $data['format'], SQLITE3_TEXT);
        $stmt->bindValue(':imported', $data['imported_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':updated', $data['updated_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':skipped', $data['skipped_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':errors', $data['error_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':user', $data['user_id'] ?? 0, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_import_export_history($limit = 50) {
        $result = $this->db->query("SELECT * FROM import_export_history ORDER BY created_at DESC LIMIT {$limit}");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    // ==================== Area Postal Code Mapping ====================
    
    public function save_area_postal($area, $postal_code, $state = '', $lat = null, $lng = null) {
        $existing = $this->db->querySingle("SELECT id FROM area_postal_mapping WHERE area = '" . SQLite3::escapeString($area) . "'");
        
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE area_postal_mapping SET postal_code = :postal, state = :state, latitude = :lat, longitude = :lng WHERE area = :area");
        } else {
            $stmt = $this->db->prepare("INSERT INTO area_postal_mapping (area, postal_code, state, latitude, longitude) VALUES (:area, :postal, :state, :lat, :lng)");
        }
        $stmt->bindValue(':area', $area, SQLITE3_TEXT);
        $stmt->bindValue(':postal', $postal_code, SQLITE3_TEXT);
        $stmt->bindValue(':state', $state, SQLITE3_TEXT);
        $stmt->bindValue(':lat', $lat, SQLITE3_FLOAT);
        $stmt->bindValue(':lng', $lng, SQLITE3_FLOAT);
        return $stmt->execute();
    }
    
    public function get_area_postal($area) {
        $stmt = $this->db->prepare("SELECT * FROM area_postal_mapping WHERE area = :area");
        $stmt->bindValue(':area', $area, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function get_all_area_postals() {
        $result = $this->db->query("SELECT * FROM area_postal_mapping ORDER BY area ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    // ==================== Saved Titles/Areas ====================
    
    public function save_titles($name, $content, $category = '', $subcategory = '') {
        $stmt = $this->db->prepare("INSERT INTO saved_titles (name, content, category, subcategory) VALUES (:name, :content, :category, :subcategory)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':subcategory', $subcategory, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_saved_titles($id, $name, $content, $category = '', $subcategory = '') {
        $stmt = $this->db->prepare("UPDATE saved_titles SET name = :name, content = :content, category = :category, subcategory = :subcategory, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':subcategory', $subcategory, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_saved_titles($id) {
        $stmt = $this->db->prepare("DELETE FROM saved_titles WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_saved_titles() {
        $result = $this->db->query("SELECT * FROM saved_titles ORDER BY category ASC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_saved_titles_item($id) {
        $stmt = $this->db->prepare("SELECT * FROM saved_titles WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function save_areas($name, $content, $category = '', $subcategory = '') {
        $stmt = $this->db->prepare("INSERT INTO saved_areas (name, content, category, subcategory) VALUES (:name, :content, :category, :subcategory)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':subcategory', $subcategory, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function update_saved_areas($id, $name, $content, $category = '', $subcategory = '') {
        $stmt = $this->db->prepare("UPDATE saved_areas SET name = :name, content = :content, category = :category, subcategory = :subcategory, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':subcategory', $subcategory, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function delete_saved_areas($id) {
        $stmt = $this->db->prepare("DELETE FROM saved_areas WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_saved_areas() {
        $result = $this->db->query("SELECT * FROM saved_areas ORDER BY category ASC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_saved_areas_item($id) {
        $stmt = $this->db->prepare("SELECT * FROM saved_areas WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // ==================== Template Categories (3-level) ====================
    
    public function save_template_category($name, $parent_id = 0, $template_type = 'html') {
        $level = 0;
        if ($parent_id > 0) {
            $parent = $this->get_template_category($parent_id);
            $level = $parent ? ($parent['level'] + 1) : 0;
        }
        $stmt = $this->db->prepare("INSERT INTO template_categories (name, parent_id, level, template_type) VALUES (:name, :parent_id, :level, :type)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':parent_id', $parent_id, SQLITE3_INTEGER);
        $stmt->bindValue(':level', $level, SQLITE3_INTEGER);
        $stmt->bindValue(':type', $template_type, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function get_template_category($id) {
        $stmt = $this->db->prepare("SELECT * FROM template_categories WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function get_template_categories($template_type = null, $parent_id = null) {
        $where = "1=1";
        if ($template_type) {
            $where .= " AND template_type = '" . SQLite3::escapeString($template_type) . "'";
        }
        if ($parent_id !== null) {
            $where .= " AND parent_id = " . intval($parent_id);
        }
        $result = $this->db->query("SELECT * FROM template_categories WHERE {$where} ORDER BY level ASC, name ASC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function delete_template_category($id) {
        // First delete children
        $this->db->exec("DELETE FROM template_categories WHERE parent_id = " . intval($id));
        $stmt = $this->db->prepare("DELETE FROM template_categories WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    // ==================== Update History Item (for Inject) ====================
    
    public function update_category_history($id, $data) {
        $sets = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        
        if (empty($sets)) return false;
        
        $sql = "UPDATE category_history SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }
    
    // ==================== Reset Database ====================
    
    public function reset_database() {
        $tables = [
            'category_history',
            'html_templates', 
            'meta_templates',
            'schema_templates',
            'inner_templates',
            'variables',
            'settings',
            'import_export_history',
            'area_postal_mapping',
            'business_profile',
            'saved_titles',
            'saved_areas',
            'template_categories',
            'category_snapshots'
        ];
        
        foreach ($tables as $table) {
            $this->db->exec("DELETE FROM {$table}");
        }
        
        // Reinitialize with defaults
        $this->insert_default_templates();
        
        return true;
    }
    
    // ==================== Category Snapshots ====================
    
    public function save_snapshot($data) {
        $stmt = $this->db->prepare("INSERT INTO category_snapshots (title, notes, type, filename, filepath, terms_count, taxonomy_count, termmeta_count, filesize, created_by) VALUES (:title, :notes, :type, :filename, :filepath, :terms, :taxonomy, :termmeta, :filesize, :user)");
        $stmt->bindValue(':title', $data['title'], SQLITE3_TEXT);
        $stmt->bindValue(':notes', $data['notes'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':type', $data['type'] ?? 'manual', SQLITE3_TEXT);
        $stmt->bindValue(':filename', $data['filename'], SQLITE3_TEXT);
        $stmt->bindValue(':filepath', $data['filepath'], SQLITE3_TEXT);
        $stmt->bindValue(':terms', $data['terms_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':taxonomy', $data['taxonomy_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':termmeta', $data['termmeta_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':filesize', $data['filesize'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':user', get_current_user_id(), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function get_snapshot($id) {
        $stmt = $this->db->prepare("SELECT * FROM category_snapshots WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function get_snapshots($type = null) {
        $where = $type ? "WHERE type = '" . SQLite3::escapeString($type) . "'" : "";
        $result = $this->db->query("SELECT * FROM category_snapshots {$where} ORDER BY created_at DESC");
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_snapshots_by_type($type) {
        $stmt = $this->db->prepare("SELECT * FROM category_snapshots WHERE type = :type ORDER BY created_at DESC");
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $result = $stmt->execute();
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function get_recent_snapshots($limit = 10) {
        $result = $this->db->query("SELECT * FROM category_snapshots ORDER BY created_at DESC LIMIT " . intval($limit));
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }
    
    public function delete_snapshot($id) {
        $stmt = $this->db->prepare("DELETE FROM category_snapshots WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function get_snapshots_count($type = null) {
        $where = $type ? "WHERE type = '" . SQLite3::escapeString($type) . "'" : "";
        return $this->db->querySingle("SELECT COUNT(*) FROM category_snapshots {$where}");
    }
    
    // ==================== Utility ====================
    
    public function table_exists($table) {
        if (!$this->db) {
            return false;
        }
        $result = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
        return $result !== null;
    }
    
    /**
     * Close database connection
     */
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}
