<?php
/**
 * WordPress Category Snapshot Handler
 * 
 * Creates snapshots of WordPress category tables (wp_terms, wp_term_taxonomy, wp_termmeta)
 * and stores them in SQLite format for quick backup/restore operations.
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim <contact@riseup-asia.com>
 * @copyright 2024 Riseup Asia LLC
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Snapshot {
    
    private static $instance = null;
    private $db;
    private $snapshot_dir;
    
    /**
     * WordPress tables to snapshot
     */
    private $wp_tables = array(
        'terms',
        'term_taxonomy', 
        'termmeta'
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CG_Database::get_instance();
        $this->init_snapshot_directory();
    }
    
    /**
     * Initialize snapshot directory in wp-content
     */
    private function init_snapshot_directory() {
        $this->snapshot_dir = WP_CONTENT_DIR . '/category-generator-snapshots';
        
        if (!file_exists($this->snapshot_dir)) {
            wp_mkdir_p($this->snapshot_dir);
            
            // Add .htaccess to protect snapshots
            $htaccess = $this->snapshot_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Order deny,allow\nDeny from all");
            }
            
            // Add index.php for extra security
            $index = $this->snapshot_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php // Silence is golden");
            }
        }
    }
    
    /**
     * Get snapshot directory path
     */
    public function get_snapshot_dir() {
        return $this->snapshot_dir;
    }
    
    /**
     * Create a snapshot of WordPress category tables
     * 
     * @param string $title Snapshot title/name
     * @param string $notes Optional notes about the snapshot
     * @param string $type 'manual' or 'auto'
     * @return array Result with success status and message
     */
    public function create_snapshot($title, $notes = '', $type = 'manual') {
        global $wpdb;
        
        try {
            // Generate filename: date + slug from title
            $date = date('Y-m-d_His');
            $slug = sanitize_title(substr($title, 0, 50));
            $filename = "{$date}_{$slug}.db";
            $filepath = $this->snapshot_dir . '/' . $filename;
            
            // Create new SQLite database for snapshot
            $snapshot_db = new SQLite3($filepath);
            $snapshot_db->enableExceptions(true);
            
            // Create tables structure in snapshot
            $this->create_snapshot_tables($snapshot_db);
            
            // Copy data from WordPress tables
            $counts = $this->copy_wp_tables_to_snapshot($snapshot_db, $wpdb);
            
            // Add metadata table
            $snapshot_db->exec("
                CREATE TABLE IF NOT EXISTS snapshot_meta (
                    key TEXT PRIMARY KEY,
                    value TEXT
                )
            ");
            
            $stmt = $snapshot_db->prepare("INSERT INTO snapshot_meta (key, value) VALUES (:key, :value)");
            
            $meta = array(
                'title' => $title,
                'notes' => $notes,
                'type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
                'wp_version' => get_bloginfo('version'),
                'plugin_version' => CG_PLUGIN_VERSION,
                'terms_count' => $counts['terms'],
                'taxonomy_count' => $counts['term_taxonomy'],
                'termmeta_count' => $counts['termmeta']
            );
            
            foreach ($meta as $key => $value) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':value', $value, SQLITE3_TEXT);
                $stmt->execute();
                $stmt->reset();
            }
            
            $snapshot_db->close();
            
            // Record in plugin database
            $snapshot_id = $this->db->save_snapshot(array(
                'title' => $title,
                'notes' => $notes,
                'type' => $type,
                'filename' => $filename,
                'filepath' => $filepath,
                'terms_count' => $counts['terms'],
                'taxonomy_count' => $counts['term_taxonomy'],
                'termmeta_count' => $counts['termmeta'],
                'filesize' => filesize($filepath)
            ));
            
            // Enforce snapshot limit
            $this->enforce_snapshot_limit($type);
            
            return array(
                'success' => true,
                'message' => sprintf(__('Snapshot created: %s', 'category-generator'), $title),
                'snapshot_id' => $snapshot_id,
                'filename' => $filename,
                'counts' => $counts
            );
            
        } catch (Exception $e) {
            error_log('CG Snapshot Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to create snapshot: ', 'category-generator') . $e->getMessage()
            );
        }
    }
    
    /**
     * Create tables in snapshot database
     */
    private function create_snapshot_tables($snapshot_db) {
        // wp_terms structure
        $snapshot_db->exec("
            CREATE TABLE IF NOT EXISTS wp_terms (
                term_id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                term_group INTEGER DEFAULT 0
            )
        ");
        
        // wp_term_taxonomy structure
        $snapshot_db->exec("
            CREATE TABLE IF NOT EXISTS wp_term_taxonomy (
                term_taxonomy_id INTEGER PRIMARY KEY,
                term_id INTEGER NOT NULL,
                taxonomy TEXT NOT NULL,
                description TEXT,
                parent INTEGER DEFAULT 0,
                count INTEGER DEFAULT 0
            )
        ");
        
        // wp_termmeta structure  
        $snapshot_db->exec("
            CREATE TABLE IF NOT EXISTS wp_termmeta (
                meta_id INTEGER PRIMARY KEY,
                term_id INTEGER NOT NULL,
                meta_key TEXT,
                meta_value TEXT
            )
        ");
        
        // Create indexes
        $snapshot_db->exec("CREATE INDEX IF NOT EXISTS idx_terms_slug ON wp_terms(slug)");
        $snapshot_db->exec("CREATE INDEX IF NOT EXISTS idx_taxonomy_term ON wp_term_taxonomy(term_id)");
        $snapshot_db->exec("CREATE INDEX IF NOT EXISTS idx_taxonomy_type ON wp_term_taxonomy(taxonomy)");
        $snapshot_db->exec("CREATE INDEX IF NOT EXISTS idx_termmeta_term ON wp_termmeta(term_id)");
    }
    
    /**
     * Copy WordPress tables to snapshot
     */
    private function copy_wp_tables_to_snapshot($snapshot_db, $wpdb) {
        $counts = array(
            'terms' => 0,
            'term_taxonomy' => 0,
            'termmeta' => 0
        );
        
        // Get category taxonomy term IDs for filtering
        $category_term_ids = $wpdb->get_col("
            SELECT term_id FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy IN ('category', 'post_tag', 'product_cat', 'product_tag')
        ");
        
        if (empty($category_term_ids)) {
            return $counts;
        }
        
        $term_ids_str = implode(',', array_map('intval', $category_term_ids));
        
        // Copy wp_terms
        $terms = $wpdb->get_results("SELECT * FROM {$wpdb->terms} WHERE term_id IN ({$term_ids_str})", ARRAY_A);
        if ($terms) {
            $stmt = $snapshot_db->prepare("INSERT INTO wp_terms (term_id, name, slug, term_group) VALUES (:term_id, :name, :slug, :term_group)");
            foreach ($terms as $term) {
                $stmt->bindValue(':term_id', $term['term_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':name', $term['name'], SQLITE3_TEXT);
                $stmt->bindValue(':slug', $term['slug'], SQLITE3_TEXT);
                $stmt->bindValue(':term_group', $term['term_group'], SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->reset();
                $counts['terms']++;
            }
        }
        
        // Copy wp_term_taxonomy
        $taxonomies = $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$term_ids_str})", ARRAY_A);
        if ($taxonomies) {
            $stmt = $snapshot_db->prepare("INSERT INTO wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent, count) VALUES (:tt_id, :term_id, :taxonomy, :description, :parent, :count)");
            foreach ($taxonomies as $tax) {
                $stmt->bindValue(':tt_id', $tax['term_taxonomy_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':term_id', $tax['term_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':taxonomy', $tax['taxonomy'], SQLITE3_TEXT);
                $stmt->bindValue(':description', $tax['description'], SQLITE3_TEXT);
                $stmt->bindValue(':parent', $tax['parent'], SQLITE3_INTEGER);
                $stmt->bindValue(':count', $tax['count'], SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->reset();
                $counts['term_taxonomy']++;
            }
        }
        
        // Copy wp_termmeta
        $termmeta = $wpdb->get_results("SELECT * FROM {$wpdb->termmeta} WHERE term_id IN ({$term_ids_str})", ARRAY_A);
        if ($termmeta) {
            $stmt = $snapshot_db->prepare("INSERT INTO wp_termmeta (meta_id, term_id, meta_key, meta_value) VALUES (:meta_id, :term_id, :meta_key, :meta_value)");
            foreach ($termmeta as $meta) {
                $stmt->bindValue(':meta_id', $meta['meta_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':term_id', $meta['term_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':meta_key', $meta['meta_key'], SQLITE3_TEXT);
                $stmt->bindValue(':meta_value', $meta['meta_value'], SQLITE3_TEXT);
                $stmt->execute();
                $stmt->reset();
                $counts['termmeta']++;
            }
        }
        
        return $counts;
    }
    
    /**
     * Restore categories from a snapshot (merge mode)
     * 
     * @param int $snapshot_id Snapshot ID from database
     * @return array Result with success status and message
     */
    public function restore_snapshot($snapshot_id) {
        global $wpdb;
        
        try {
            $snapshot = $this->db->get_snapshot($snapshot_id);
            
            if (!$snapshot) {
                return array(
                    'success' => false,
                    'message' => __('Snapshot not found', 'category-generator')
                );
            }
            
            $filepath = $snapshot['filepath'];
            
            if (!file_exists($filepath)) {
                return array(
                    'success' => false,
                    'message' => __('Snapshot file not found', 'category-generator')
                );
            }
            
            // Open snapshot database
            $snapshot_db = new SQLite3($filepath);
            $snapshot_db->enableExceptions(true);
            
            $restored = array(
                'terms' => 0,
                'updated' => 0,
                'skipped' => 0
            );
            
            // Get all terms from snapshot
            $result = $snapshot_db->query("SELECT * FROM wp_terms");
            $snapshot_terms = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $snapshot_terms[$row['term_id']] = $row;
            }
            
            // Get taxonomy data
            $result = $snapshot_db->query("SELECT * FROM wp_term_taxonomy");
            $snapshot_taxonomies = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $snapshot_taxonomies[$row['term_id']] = $row;
            }
            
            // Get termmeta
            $result = $snapshot_db->query("SELECT * FROM wp_termmeta");
            $snapshot_meta = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!isset($snapshot_meta[$row['term_id']])) {
                    $snapshot_meta[$row['term_id']] = array();
                }
                $snapshot_meta[$row['term_id']][] = $row;
            }
            
            $snapshot_db->close();
            
            // Restore each term (merge mode - update existing or insert new)
            foreach ($snapshot_terms as $term_id => $term) {
                $taxonomy = $snapshot_taxonomies[$term_id]['taxonomy'] ?? 'category';
                
                // Check if term with same slug exists
                $existing_term = get_term_by('slug', $term['slug'], $taxonomy);
                
                if ($existing_term) {
                    // Update existing term
                    wp_update_term($existing_term->term_id, $taxonomy, array(
                        'name' => $term['name'],
                        'description' => $snapshot_taxonomies[$term_id]['description'] ?? ''
                    ));
                    $restored['updated']++;
                    $actual_term_id = $existing_term->term_id;
                } else {
                    // Create new term
                    $parent = 0;
                    if (!empty($snapshot_taxonomies[$term_id]['parent'])) {
                        // Try to find parent by looking up in our already restored terms
                        $parent_snapshot = $snapshot_terms[$snapshot_taxonomies[$term_id]['parent']] ?? null;
                        if ($parent_snapshot) {
                            $parent_term = get_term_by('slug', $parent_snapshot['slug'], $taxonomy);
                            if ($parent_term) {
                                $parent = $parent_term->term_id;
                            }
                        }
                    }
                    
                    $result = wp_insert_term($term['name'], $taxonomy, array(
                        'slug' => $term['slug'],
                        'description' => $snapshot_taxonomies[$term_id]['description'] ?? '',
                        'parent' => $parent
                    ));
                    
                    if (!is_wp_error($result)) {
                        $restored['terms']++;
                        $actual_term_id = $result['term_id'];
                    } else {
                        $restored['skipped']++;
                        continue;
                    }
                }
                
                // Restore termmeta
                if (isset($snapshot_meta[$term_id]) && isset($actual_term_id)) {
                    foreach ($snapshot_meta[$term_id] as $meta) {
                        update_term_meta($actual_term_id, $meta['meta_key'], maybe_unserialize($meta['meta_value']));
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Restore complete: %d new, %d updated, %d skipped', 'category-generator'),
                    $restored['terms'],
                    $restored['updated'],
                    $restored['skipped']
                ),
                'stats' => $restored
            );
            
        } catch (Exception $e) {
            error_log('CG Snapshot Restore Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to restore snapshot: ', 'category-generator') . $e->getMessage()
            );
        }
    }
    
    /**
     * Delete a snapshot
     */
    public function delete_snapshot($snapshot_id) {
        $snapshot = $this->db->get_snapshot($snapshot_id);
        
        if ($snapshot && file_exists($snapshot['filepath'])) {
            unlink($snapshot['filepath']);
        }
        
        return $this->db->delete_snapshot($snapshot_id);
    }
    
    /**
     * Enforce snapshot limit based on settings
     */
    private function enforce_snapshot_limit($type) {
        $settings = CG_Settings::get_instance();
        $limit = intval($settings->get('snapshot_limit', 20));
        
        if ($limit <= 0) {
            return; // No limit
        }
        
        // Get snapshots of this type ordered by date
        $snapshots = $this->db->get_snapshots_by_type($type);
        
        if (count($snapshots) > $limit) {
            // Delete oldest snapshots beyond limit
            $to_delete = array_slice($snapshots, $limit);
            foreach ($to_delete as $snapshot) {
                $this->delete_snapshot($snapshot['id']);
            }
        }
    }
    
    /**
     * Get recent snapshots for quick access
     * 
     * @param int $limit Number of snapshots to return
     * @return array List of recent snapshots
     */
    public function get_recent_snapshots($limit = 10) {
        return $this->db->get_recent_snapshots($limit);
    }
    
    /**
     * Get snapshot info from file
     */
    public function get_snapshot_info($snapshot_id) {
        $snapshot = $this->db->get_snapshot($snapshot_id);
        
        if (!$snapshot || !file_exists($snapshot['filepath'])) {
            return null;
        }
        
        try {
            $snapshot_db = new SQLite3($snapshot['filepath']);
            $meta = array();
            $result = $snapshot_db->query("SELECT * FROM snapshot_meta");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $meta[$row['key']] = $row['value'];
            }
            $snapshot_db->close();
            
            return array_merge($snapshot, array('meta' => $meta));
        } catch (Exception $e) {
            return $snapshot;
        }
    }
    
    /**
     * Download snapshot file
     */
    public function download_snapshot($snapshot_id) {
        $snapshot = $this->db->get_snapshot($snapshot_id);
        
        if (!$snapshot || !file_exists($snapshot['filepath'])) {
            return false;
        }
        
        $filename = 'category-snapshot-' . date('Y-m-d-His') . '-' . sanitize_title($snapshot['title']) . '.db';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($snapshot['filepath']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($snapshot['filepath']);
        exit;
    }
}
