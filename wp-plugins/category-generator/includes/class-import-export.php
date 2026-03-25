<?php
/**
 * Import/Export Handler for Category Generator
 * Supports SQLite DB, CSV, XML, and ZIP formats
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Import_Export {
    
    private static $instance = null;
    private $db;
    private $upload_dir;
    private $plugin_dir;
    
    // Exportable data types
    const TYPE_HTML_TEMPLATES = 'html_templates';
    const TYPE_META_TEMPLATES = 'meta_templates';
    const TYPE_SCHEMA_TEMPLATES = 'schema_templates';
    const TYPE_INNER_TEMPLATES = 'inner_templates';
    const TYPE_BUSINESS_PROFILES = 'business_profiles';
    const TYPE_VARIABLES = 'variables';
    const TYPE_HISTORY = 'category_history';
    const TYPE_SETTINGS = 'settings';
    const TYPE_ALL = 'all';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CG_Database::get_instance();
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/category-generator/';
        $this->plugin_dir = $this->upload_dir . 'exports/';
        
        // Ensure directories exist
        if (!file_exists($this->plugin_dir)) {
            wp_mkdir_p($this->plugin_dir);
        }
    }
    
    /**
     * Get available export types
     */
    public static function get_export_types() {
        return array(
            self::TYPE_HTML_TEMPLATES => __('HTML Templates', 'category-generator'),
            self::TYPE_META_TEMPLATES => __('Meta Templates', 'category-generator'),
            self::TYPE_SCHEMA_TEMPLATES => __('Schema Templates', 'category-generator'),
            self::TYPE_INNER_TEMPLATES => __('Inner Templates', 'category-generator'),
            self::TYPE_BUSINESS_PROFILES => __('Business Profiles', 'category-generator'),
            self::TYPE_VARIABLES => __('Variables', 'category-generator'),
            self::TYPE_HISTORY => __('Category History', 'category-generator'),
            self::TYPE_SETTINGS => __('Settings', 'category-generator'),
            self::TYPE_ALL => __('Everything', 'category-generator'),
        );
    }
    
    /**
     * Export data to ZIP file
     * 
     * @param array $types Types to export
     * @param string $format 'sqlite' or 'csv'
     * @return string|false Path to ZIP file or false on failure
     */
    public function export($types = array(), $format = 'sqlite') {
        if (empty($types) || in_array(self::TYPE_ALL, $types)) {
            $types = array_keys(self::get_export_types());
            unset($types[array_search(self::TYPE_ALL, $types)]);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $zip_filename = "cg_export_{$timestamp}.zip";
        $zip_path = $this->plugin_dir . $zip_filename;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        
        // Add manifest
        $manifest = array(
            'version' => CG_PLUGIN_VERSION,
            'exported_at' => current_time('mysql'),
            'format' => $format,
            'types' => $types,
            'site_url' => get_site_url()
        );
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        if ($format === 'sqlite') {
            // Export SQLite database directly
            $db_path = $this->upload_dir . 'category_generator.db';
            if (file_exists($db_path)) {
                $zip->addFile($db_path, 'category_generator.db');
            }
        } else {
            // Export as CSV files
            foreach ($types as $type) {
                $data = $this->get_export_data($type);
                if (!empty($data)) {
                    $csv_content = $this->array_to_csv($data);
                    $zip->addFromString("{$type}.csv", $csv_content);
                }
            }
        }
        
        $zip->close();
        
        // Log export to history
        $this->log_import_export('export', $types, $format);
        
        return $zip_path;
    }
    
    /**
     * Get data for export by type
     */
    private function get_export_data($type) {
        switch ($type) {
            case self::TYPE_HTML_TEMPLATES:
                return $this->db->get_html_templates();
            case self::TYPE_META_TEMPLATES:
                return $this->db->get_meta_templates();
            case self::TYPE_SCHEMA_TEMPLATES:
                return $this->db->get_schema_templates();
            case self::TYPE_INNER_TEMPLATES:
                return $this->db->get_inner_templates();
            case self::TYPE_BUSINESS_PROFILES:
                return $this->db->get_all_business_profiles();
            case self::TYPE_VARIABLES:
                return $this->db->get_variables();
            case self::TYPE_HISTORY:
                return $this->db->get_category_history(array('limit' => 99999));
            case self::TYPE_SETTINGS:
                return $this->db->get_settings();
            default:
                return array();
        }
    }
    
    /**
     * Convert array to CSV string with proper encapsulation
     */
    private function array_to_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows with proper escaping
        foreach ($data as $row) {
            // Escape any newlines and special characters in values
            $escaped_row = array_map(function($value) {
                if (is_string($value)) {
                    // Handle multi-line content and special chars
                    return str_replace(
                        array("\r\n", "\n", "\r"),
                        array("\\n", "\\n", "\\n"),
                        $value
                    );
                }
                return $value;
            }, $row);
            
            fputcsv($output, $escaped_row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Import from uploaded file
     * 
     * @param string $file_path Path to uploaded file (ZIP, CSV, or SQLite DB)
     * @param array $options Import options (update_existing, types, etc.)
     * @return array Import results
     */
    public function import($file_path, $options = array()) {
        $defaults = array(
            'update_existing' => false,
            'types' => array(), // Empty means all
        );
        $options = array_merge($defaults, $options);
        
        $results = array(
            'success' => true,
            'imported' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array()
        );
        
        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        try {
            switch ($file_ext) {
                case 'zip':
                    $results = $this->import_from_zip($file_path, $options);
                    break;
                case 'csv':
                    $results = $this->import_from_csv($file_path, $options);
                    break;
                case 'db':
                case 'sqlite':
                    $results = $this->import_from_sqlite($file_path, $options);
                    break;
                case 'xml':
                    $results = $this->import_from_xml($file_path, $options);
                    break;
                default:
                    $results['success'] = false;
                    $results['errors'][] = __('Unsupported file format.', 'category-generator');
            }
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }
        
        // Log import
        $this->log_import_export('import', $options['types'] ?: array('all'), $file_ext, $results);
        
        return $results;
    }
    
    /**
     * Import from ZIP file
     */
    private function import_from_zip($zip_path, $options) {
        $results = array(
            'success' => true,
            'imported' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array()
        );
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            $results['success'] = false;
            $results['errors'][] = __('Failed to open ZIP file.', 'category-generator');
            return $results;
        }
        
        // Create temp directory
        $temp_dir = $this->plugin_dir . 'temp_import_' . time() . '/';
        wp_mkdir_p($temp_dir);
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Read manifest if exists
        $manifest = array();
        if (file_exists($temp_dir . 'manifest.json')) {
            $manifest = json_decode(file_get_contents($temp_dir . 'manifest.json'), true);
        }
        
        // Check for SQLite database
        if (file_exists($temp_dir . 'category_generator.db')) {
            $sub_results = $this->import_from_sqlite($temp_dir . 'category_generator.db', $options);
            $results = $this->merge_results($results, $sub_results);
        }
        
        // Check for CSV files
        $csv_files = glob($temp_dir . '*.csv');
        foreach ($csv_files as $csv_file) {
            $type = pathinfo($csv_file, PATHINFO_FILENAME);
            
            // Skip if not in requested types
            if (!empty($options['types']) && !in_array($type, $options['types'])) {
                continue;
            }
            
            $sub_results = $this->import_from_csv($csv_file, array_merge($options, array('type' => $type)));
            $results = $this->merge_results($results, $sub_results);
        }
        
        // Cleanup temp directory
        $this->delete_directory($temp_dir);
        
        return $results;
    }
    
    /**
     * Import from CSV file
     */
    private function import_from_csv($csv_path, $options) {
        $results = array(
            'success' => true,
            'imported' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array()
        );
        
        $type = $options['type'] ?? pathinfo($csv_path, PATHINFO_FILENAME);
        
        if (($handle = fopen($csv_path, 'r')) === false) {
            $results['success'] = false;
            $results['errors'][] = __('Failed to open CSV file.', 'category-generator');
            return $results;
        }
        
        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $results['success'] = false;
            $results['errors'][] = __('Invalid CSV format - no headers found.', 'category-generator');
            return $results;
        }
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            
            // Unescape newlines
            foreach ($data as $key => $value) {
                $data[$key] = str_replace("\\n", "\n", $value);
            }
            
            $import_result = $this->import_single_record($type, $data, $options['update_existing']);
            
            switch ($import_result['status']) {
                case 'imported':
                    $results['imported'][] = $data['name'] ?? $data['id'] ?? 'Unknown';
                    break;
                case 'updated':
                    $results['updated'][] = $data['name'] ?? $data['id'] ?? 'Unknown';
                    break;
                case 'skipped':
                    $results['skipped'][] = $data['name'] ?? $data['id'] ?? 'Unknown';
                    break;
                case 'error':
                    $results['errors'][] = $import_result['message'];
                    break;
            }
        }
        
        fclose($handle);
        return $results;
    }
    
    /**
     * Import from SQLite database
     */
    private function import_from_sqlite($db_path, $options) {
        $results = array(
            'success' => true,
            'imported' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array()
        );
        
        try {
            $import_db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
            
            $tables = array(
                'html_templates' => self::TYPE_HTML_TEMPLATES,
                'meta_templates' => self::TYPE_META_TEMPLATES,
                'schema_templates' => self::TYPE_SCHEMA_TEMPLATES,
                'inner_templates' => self::TYPE_INNER_TEMPLATES,
                'business_profile' => self::TYPE_BUSINESS_PROFILES,
                'variables' => self::TYPE_VARIABLES,
            );
            
            foreach ($tables as $table => $type) {
                // Skip if not in requested types
                if (!empty($options['types']) && !in_array($type, $options['types'])) {
                    continue;
                }
                
                // Check if table exists
                $table_exists = $import_db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                if (!$table_exists) continue;
                
                $query = $import_db->query("SELECT * FROM {$table}");
                while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                    $import_result = $this->import_single_record($type, $row, $options['update_existing']);
                    
                    switch ($import_result['status']) {
                        case 'imported':
                            $results['imported'][] = $row['name'] ?? $row['id'] ?? 'Unknown';
                            break;
                        case 'updated':
                            $results['updated'][] = $row['name'] ?? $row['id'] ?? 'Unknown';
                            break;
                        case 'skipped':
                            $results['skipped'][] = $row['name'] ?? $row['id'] ?? 'Unknown';
                            break;
                    }
                }
            }
            
            $import_db->close();
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Import from XML file
     */
    private function import_from_xml($xml_path, $options) {
        $results = array(
            'success' => true,
            'imported' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array()
        );
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xml_path);
        
        if ($xml === false) {
            $results['success'] = false;
            foreach (libxml_get_errors() as $error) {
                $results['errors'][] = $error->message;
            }
            return $results;
        }
        
        // Process each type
        foreach ($xml->children() as $type_node) {
            $type = $type_node->getName();
            
            if (!empty($options['types']) && !in_array($type, $options['types'])) {
                continue;
            }
            
            foreach ($type_node->children() as $item) {
                $data = array();
                foreach ($item->children() as $field) {
                    $data[$field->getName()] = (string) $field;
                }
                
                $import_result = $this->import_single_record($type, $data, $options['update_existing']);
                
                switch ($import_result['status']) {
                    case 'imported':
                        $results['imported'][] = $data['name'] ?? 'Unknown';
                        break;
                    case 'updated':
                        $results['updated'][] = $data['name'] ?? 'Unknown';
                        break;
                    case 'skipped':
                        $results['skipped'][] = $data['name'] ?? 'Unknown';
                        break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Import a single record
     */
    private function import_single_record($type, $data, $update_existing) {
        $result = array('status' => 'error', 'message' => '');
        
        // Remove ID to force new insert (unless updating)
        $original_id = $data['id'] ?? null;
        unset($data['id']);
        
        // Check for existing record by name
        $existing = $this->find_existing_record($type, $data);
        
        if ($existing) {
            if ($update_existing) {
                // Update existing record
                $this->update_record($type, $existing['id'], $data);
                $result['status'] = 'updated';
            } else {
                $result['status'] = 'skipped';
            }
        } else {
            // Insert new record
            $this->insert_record($type, $data);
            $result['status'] = 'imported';
        }
        
        return $result;
    }
    
    /**
     * Find existing record by name/identifier
     */
    private function find_existing_record($type, $data) {
        $name = $data['name'] ?? '';
        if (empty($name)) return null;
        
        switch ($type) {
            case self::TYPE_HTML_TEMPLATES:
                $templates = $this->db->get_html_templates();
                foreach ($templates as $t) {
                    if ($t['name'] === $name) return $t;
                }
                break;
            case self::TYPE_META_TEMPLATES:
                $templates = $this->db->get_meta_templates();
                foreach ($templates as $t) {
                    if ($t['name'] === $name) return $t;
                }
                break;
            case self::TYPE_SCHEMA_TEMPLATES:
                $templates = $this->db->get_schema_templates();
                foreach ($templates as $t) {
                    if ($t['name'] === $name) return $t;
                }
                break;
            case self::TYPE_INNER_TEMPLATES:
                return $this->db->get_inner_template_by_name($data['name_id'] ?? '');
        }
        
        return null;
    }
    
    /**
     * Insert a new record
     */
    private function insert_record($type, $data) {
        switch ($type) {
            case self::TYPE_HTML_TEMPLATES:
                return $this->db->insert_html_template(
                    $data['name'],
                    $data['description'] ?? '',
                    $data['content'],
                    0
                );
            case self::TYPE_META_TEMPLATES:
                return $this->db->insert_meta_template(
                    $data['name'],
                    $data['meta_title_pattern'] ?? '',
                    $data['meta_description_pattern'] ?? '',
                    $data['slug_pattern'] ?? '',
                    0
                );
            case self::TYPE_SCHEMA_TEMPLATES:
                return $this->db->insert_schema_template(
                    $data['name'],
                    $data['schema_type'] ?? 'LocalBusiness',
                    $data['content'] ?? $data['schema_content'],
                    0
                );
            case self::TYPE_INNER_TEMPLATES:
                return $this->db->insert_inner_template(
                    $data['name'],
                    $data['name_id'],
                    $data['type'] ?? 'snippet',
                    $data['content'],
                    $data['category'] ?? ''
                );
        }
        return false;
    }
    
    /**
     * Update an existing record
     */
    private function update_record($type, $id, $data) {
        switch ($type) {
            case self::TYPE_HTML_TEMPLATES:
                return $this->db->update_html_template(
                    $id,
                    $data['name'],
                    $data['description'] ?? '',
                    $data['content']
                );
            case self::TYPE_META_TEMPLATES:
                return $this->db->update_meta_template(
                    $id,
                    $data['name'],
                    $data['meta_title_pattern'] ?? '',
                    $data['meta_description_pattern'] ?? '',
                    $data['slug_pattern'] ?? ''
                );
            case self::TYPE_SCHEMA_TEMPLATES:
                return $this->db->update_schema_template(
                    $id,
                    $data['name'],
                    $data['schema_type'] ?? 'LocalBusiness',
                    $data['content'] ?? $data['schema_content']
                );
            case self::TYPE_INNER_TEMPLATES:
                return $this->db->update_inner_template(
                    $id,
                    $data['name'],
                    $data['name_id'],
                    $data['type'] ?? 'snippet',
                    $data['content'],
                    $data['category'] ?? ''
                );
        }
        return false;
    }
    
    /**
     * Merge two result arrays
     */
    private function merge_results($results1, $results2) {
        return array(
            'success' => $results1['success'] && $results2['success'],
            'imported' => array_merge($results1['imported'], $results2['imported']),
            'skipped' => array_merge($results1['skipped'], $results2['skipped']),
            'updated' => array_merge($results1['updated'], $results2['updated']),
            'errors' => array_merge($results1['errors'], $results2['errors'])
        );
    }
    
    /**
     * Log import/export operation
     */
    private function log_import_export($operation, $types, $format, $results = null) {
        $this->db->log_import_export(array(
            'operation' => $operation,
            'types' => json_encode($types),
            'format' => $format,
            'imported_count' => $results ? count($results['imported'] ?? array()) : 0,
            'updated_count' => $results ? count($results['updated'] ?? array()) : 0,
            'skipped_count' => $results ? count($results['skipped'] ?? array()) : 0,
            'error_count' => $results ? count($results['errors'] ?? array()) : 0,
            'user_id' => get_current_user_id(),
        ));
    }
    
    /**
     * Get import/export history
     */
    public function get_history($limit = 50) {
        return $this->db->get_import_export_history($limit);
    }
    
    /**
     * Delete a directory recursively
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Get download URL for export
     */
    public function get_download_url($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/category-generator/exports/' . $filename;
    }
}
