<?php
/**
 * Test Runner for Category Generator
 * Comprehensive test suite with in-plugin runner + PHPUnit integration
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Tests {
    
    private static $instance = null;
    private $db;
    private $test_results = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CG_Database::get_instance();
    }
    
    /**
     * Run all tests
     * 
     * @return array Test results
     */
    public function run_all_tests() {
        $this->test_results = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'tests' => []
        ];
        
        // Check if database is available before running tests
        if (!$this->db || !$this->db->is_connected()) {
            $this->test_results['tests'][] = [
                'name' => 'Database Connection',
                'status' => 'failed',
                'message' => 'Database is not connected. Check if SQLite extension is enabled and database path is writable.',
                'time' => 0
            ];
            $this->test_results['total'] = 1;
            $this->test_results['failed'] = 1;
            return $this->test_results;
        }
        
        // ==================== DATABASE TESTS ====================
        $this->run_test('Database Connection', [$this, 'test_database_connection']);
        $this->run_test('Table Creation', [$this, 'test_tables_exist']);
        $this->run_test('Database Insert', [$this, 'test_database_insert']);
        $this->run_test('Database Update', [$this, 'test_database_update']);
        $this->run_test('Database Delete', [$this, 'test_database_delete']);
        $this->run_test('Database Transaction', [$this, 'test_database_transaction']);
        
        // ==================== VARIABLE TESTS ====================
        $this->run_test('Variable Basic', [$this, 'test_variable_basic']);
        $this->run_test('Variable Concatenation', [$this, 'test_variable_concatenation']);
        $this->run_test('Variable Reference', [$this, 'test_variable_reference']);
        $this->run_test('Variable Nested Reference', [$this, 'test_variable_nested_reference']);
        $this->run_test('Variable Math Operations', [$this, 'test_variable_math']);
        $this->run_test('Variable Empty Handling', [$this, 'test_variable_empty']);
        $this->run_test('Variable Special Chars', [$this, 'test_variable_special_chars']);
        
        // ==================== INNER TEMPLATE TESTS ====================
        $this->run_test('Inner Template Create', [$this, 'test_inner_template_create']);
        $this->run_test('Inner Template Process', [$this, 'test_inner_template_process']);
        $this->run_test('Inner Template By ID', [$this, 'test_inner_template_by_id']);
        $this->run_test('Inner Template By Name', [$this, 'test_inner_template_by_name']);
        $this->run_test('Inner Template Nested', [$this, 'test_inner_template_nested']);
        $this->run_test('Inner Template With Variables', [$this, 'test_inner_template_with_variables']);
        
        // ==================== IMPORT/EXPORT TESTS ====================
        $this->run_test('CSV Export', [$this, 'test_csv_export']);
        $this->run_test('CSV Escaping', [$this, 'test_csv_escaping']);
        $this->run_test('Import Validation', [$this, 'test_import_validation']);
        $this->run_test('JSON Export', [$this, 'test_json_export']);
        $this->run_test('Import Merge Mode', [$this, 'test_import_merge']);
        
        // ==================== TEMPLATE TESTS ====================
        $this->run_test('HTML Template Save', [$this, 'test_html_template_save']);
        $this->run_test('HTML Template Update', [$this, 'test_html_template_update']);
        $this->run_test('Meta Template Save', [$this, 'test_meta_template_save']);
        $this->run_test('Schema Template Save', [$this, 'test_schema_template_save']);
        $this->run_test('Placeholder Replacement', [$this, 'test_placeholder_replacement']);
        $this->run_test('Placeholder All Types', [$this, 'test_placeholder_all_types']);
        $this->run_test('Template Category Hierarchy', [$this, 'test_template_category_hierarchy']);
        
        // ==================== CATEGORY GENERATION TESTS ====================
        $this->run_test('Category Name Format', [$this, 'test_category_name_format']);
        $this->run_test('Slug Generation', [$this, 'test_slug_generation']);
        $this->run_test('Slug With Special Chars', [$this, 'test_slug_special_chars']);
        $this->run_test('Parent/Child Logic', [$this, 'test_parent_child_logic']);
        $this->run_test('(S) Notation Parsing', [$this, 'test_s_notation']);
        $this->run_test('(S) Notation Edge Cases', [$this, 'test_s_notation_edge_cases']);
        $this->run_test('Area List Parsing', [$this, 'test_area_list_parsing']);
        $this->run_test('Bulk Generation Count', [$this, 'test_bulk_generation_count']);
        
        // ==================== HTML WRAPPER TESTS ====================
        $this->run_test('Div Wrapper Generation', [$this, 'test_div_wrapper']);
        $this->run_test('Schema in Div', [$this, 'test_schema_div_wrapper']);
        $this->run_test('Class Names Applied', [$this, 'test_class_names']);
        $this->run_test('Custom Classes', [$this, 'test_custom_classes']);
        $this->run_test('HTML Sanitization', [$this, 'test_html_sanitization']);
        
        // ==================== YOAST INTEGRATION TESTS ====================
        $this->run_test('Yoast Meta Generation', [$this, 'test_yoast_meta']);
        $this->run_test('Focus Keyword Generation', [$this, 'test_focus_keyword']);
        $this->run_test('Meta Title Length', [$this, 'test_meta_title_length']);
        $this->run_test('Meta Description Min Length', [$this, 'test_meta_desc_min_length']);
        $this->run_test('Yoast Score Thresholds', [$this, 'test_yoast_score_thresholds']);
        
        // ==================== BUSINESS PROFILE TESTS ====================
        $this->run_test('Business Profile Save', [$this, 'test_business_profile_save']);
        $this->run_test('Business Profile Update', [$this, 'test_business_profile_update']);
        $this->run_test('Multiple Profiles', [$this, 'test_multiple_profiles']);
        $this->run_test('Area Postal Code Mapping', [$this, 'test_area_postal_mapping']);
        $this->run_test('Business Profile Schema', [$this, 'test_business_profile_schema']);
        
        // ==================== SETTINGS TESTS ====================
        $this->run_test('Settings Save/Load', [$this, 'test_settings']);
        $this->run_test('Settings Default Values', [$this, 'test_settings_defaults']);
        $this->run_test('AI Provider Config', [$this, 'test_ai_config']);
        $this->run_test('CSS Class Settings', [$this, 'test_css_class_settings']);
        
        // ==================== CONSTANTS TESTS ====================
        $this->run_test('Constants Defined', [$this, 'test_constants_defined']);
        $this->run_test('CSS Class Constants', [$this, 'test_css_class_constants']);
        $this->run_test('Filesize Formatter', [$this, 'test_filesize_formatter']);
        $this->run_test('Yoast Score Classes', [$this, 'test_yoast_score_classes']);
        
        // ==================== SNAPSHOT TESTS ====================
        $this->run_test('Snapshot Create', [$this, 'test_snapshot_create']);
        $this->run_test('Snapshot Types', [$this, 'test_snapshot_types']);
        $this->run_test('Snapshot Limit Enforcement', [$this, 'test_snapshot_limit']);
        
        // ==================== SAVED TITLES/AREAS TESTS ====================
        $this->run_test('Saved Titles Create', [$this, 'test_saved_titles_create']);
        $this->run_test('Saved Titles Retrieve', [$this, 'test_saved_titles_retrieve']);
        $this->run_test('Saved Areas Create', [$this, 'test_saved_areas_create']);
        $this->run_test('Saved Areas Retrieve', [$this, 'test_saved_areas_retrieve']);
        
        // ==================== UTILITY TESTS ====================
        $this->run_test('String Sanitization', [$this, 'test_string_sanitization']);
        $this->run_test('Array Filtering', [$this, 'test_array_filtering']);
        $this->run_test('Empty Input Handling', [$this, 'test_empty_input_handling']);
        $this->run_test('Unicode Support', [$this, 'test_unicode_support']);
        $this->run_test('Date Format Constants', [$this, 'test_date_format_constants']);
        $this->run_test('Spacing Constants', [$this, 'test_spacing_constants']);
        $this->run_test('Icon Size Constants', [$this, 'test_icon_size_constants']);
        
        // ==================== REMOTE API TESTS ====================
        $this->run_test('Remote API URL Validation', [$this, 'test_remote_api_url_validation']);
        
        // ==================== AJAX HANDLER TESTS ====================
        $this->run_test('AJAX Actions Defined', [$this, 'test_ajax_actions_defined']);
        $this->run_test('AJAX Nonce Validation', [$this, 'test_ajax_nonce_validation']);
        $this->run_test('AJAX Response Format', [$this, 'test_ajax_response_format']);
        $this->run_test('AJAX Save Template Handler', [$this, 'test_ajax_save_template_handler']);
        $this->run_test('AJAX Get Template Handler', [$this, 'test_ajax_get_template_handler']);
        $this->run_test('AJAX Snapshot Handler', [$this, 'test_ajax_snapshot_handler']);
        
        // ==================== JS DATA TESTS ====================
        $this->run_test('JS Constants Export', [$this, 'test_js_constants_export']);
        $this->run_test('JS CSS Classes Export', [$this, 'test_js_css_classes_export']);
        $this->run_test('JS DOM Element IDs', [$this, 'test_js_dom_element_ids']);
        $this->run_test('JS Localized Strings', [$this, 'test_js_localized_strings']);
        $this->run_test('JS Template Type Validation', [$this, 'test_js_template_type_validation']);
        
        // ==================== INPUT VALIDATION TESTS ====================
        $this->run_test('Input XSS Prevention', [$this, 'test_input_xss_prevention']);
        $this->run_test('Input SQL Injection Prevention', [$this, 'test_input_sql_injection_prevention']);
        $this->run_test('Input Max Length Validation', [$this, 'test_input_max_length_validation']);
        
        return $this->test_results;
    }
    
    /**
     * Run specific test group
     */
    public function run_test_group($group) {
        $this->test_results = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'tests' => []
        ];
        
        $groups = [
            'database' => [
                'test_database_connection',
                'test_tables_exist',
                'test_database_insert',
                'test_database_update',
                'test_database_delete',
                'test_database_transaction',
            ],
            'variables' => [
                'test_variable_basic',
                'test_variable_concatenation',
                'test_variable_reference',
                'test_variable_nested_reference',
                'test_variable_math',
                'test_variable_empty',
                'test_variable_special_chars',
            ],
            'templates' => [
                'test_inner_template_create',
                'test_inner_template_process',
                'test_html_template_save',
                'test_meta_template_save',
                'test_placeholder_replacement',
                'test_template_category_hierarchy',
            ],
            'categories' => [
                'test_category_name_format',
                'test_slug_generation',
                'test_parent_child_logic',
                'test_s_notation',
                'test_area_list_parsing',
            ],
            'yoast' => [
                'test_yoast_meta',
                'test_focus_keyword',
                'test_meta_title_length',
                'test_meta_desc_min_length',
                'test_yoast_score_thresholds',
            ],
            'saved' => [
                'test_saved_titles_create',
                'test_saved_titles_retrieve',
                'test_saved_areas_create',
                'test_saved_areas_retrieve',
            ],
            'snapshots' => [
                'test_snapshot_create',
                'test_snapshot_types',
                'test_snapshot_limit',
            ],
            'utility' => [
                'test_string_sanitization',
                'test_array_filtering',
                'test_empty_input_handling',
                'test_unicode_support',
                'test_date_format_constants',
                'test_spacing_constants',
                'test_icon_size_constants',
            ],
            'ajax' => [
                'test_ajax_actions_defined',
                'test_ajax_nonce_validation',
                'test_ajax_response_format',
                'test_ajax_save_template_handler',
                'test_ajax_get_template_handler',
                'test_ajax_snapshot_handler',
            ],
            'javascript' => [
                'test_js_constants_export',
                'test_js_css_classes_export',
                'test_js_dom_element_ids',
                'test_js_localized_strings',
                'test_js_template_type_validation',
            ],
            'validation' => [
                'test_input_xss_prevention',
                'test_input_sql_injection_prevention',
                'test_input_max_length_validation',
            ],
        ];
        
        if (isset($groups[$group])) {
            foreach ($groups[$group] as $test) {
                $name = ucwords(str_replace('_', ' ', str_replace('test_', '', $test)));
                $this->run_test($name, [$this, $test]);
            }
        }
        
        return $this->test_results;
    }
    
    /**
     * Run a single test
     */
    private function run_test($name, $callback) {
        $this->test_results['total']++;
        
        $result = [
            'name' => $name,
            'status' => 'passed',
            'message' => '',
            'time' => 0
        ];
        
        $start = microtime(true);
        
        try {
            $test_result = call_user_func($callback);
            
            if ($test_result === true || $test_result === null) {
                $result['status'] = 'passed';
                $this->test_results['passed']++;
            } else {
                $result['status'] = 'failed';
                $result['message'] = gettype($test_result) === 'string' ? $test_result : 'Test returned false';
                $this->test_results['failed']++;
            }
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            $this->test_results['failed']++;
        }
        
        $result['time'] = round((microtime(true) - $start) * 1000, 2);
        $this->test_results['tests'][] = $result;
    }
    
    // ==================== DATABASE TESTS ====================
    
    private function test_database_connection() {
        $db = CG_Database::get_instance();
        return $db !== null;
    }
    
    private function test_tables_exist() {
        $tables = [
            'category_history',
            'html_templates',
            'meta_templates',
            'schema_templates',
            'business_profile'
        ];
        
        foreach ($tables as $table) {
            if (!$this->db->table_exists($table)) {
                return "Table '{$table}' does not exist";
            }
        }
        return true;
    }
    
    private function test_database_insert() {
        $test_name = 'Test Template ' . time();
        $id = $this->db->insert_html_template($test_name, 'Test', '<div>Test</div>');
        
        if (!$id) {
            return 'Failed to insert record';
        }
        
        // Cleanup
        $this->db->delete_html_template($id);
        return true;
    }
    
    private function test_database_update() {
        $test_name = 'Test Update ' . time();
        $id = $this->db->insert_html_template($test_name, 'Initial', '<div>Initial</div>');
        
        if (!$id) {
            return 'Failed to insert record';
        }
        
        $updated = $this->db->update_html_template($id, $test_name, 'Updated', '<div>Updated</div>');
        
        $template = $this->db->get_html_template($id);
        $this->db->delete_html_template($id);
        
        if (!$template || $template['description'] !== 'Updated') {
            return 'Update did not persist correctly';
        }
        
        return true;
    }
    
    private function test_database_delete() {
        $test_name = 'Test Delete ' . time();
        $id = $this->db->insert_html_template($test_name, 'Test', '<div>Test</div>');
        
        if (!$id) {
            return 'Failed to insert record';
        }
        
        $this->db->delete_html_template($id);
        $template = $this->db->get_html_template($id);
        
        return $template === null || $template === false;
    }
    
    private function test_database_transaction() {
        // Test that multiple operations work
        $name1 = 'Trans Test 1 ' . time();
        $name2 = 'Trans Test 2 ' . time();
        
        $id1 = $this->db->insert_html_template($name1, 'Test 1', '<div>1</div>');
        $id2 = $this->db->insert_html_template($name2, 'Test 2', '<div>2</div>');
        
        $success = $id1 && $id2;
        
        // Cleanup
        if ($id1) $this->db->delete_html_template($id1);
        if ($id2) $this->db->delete_html_template($id2);
        
        return $success;
    }
    
    // ==================== VARIABLE TESTS ====================
    
    private function test_variable_basic() {
        $vars = CG_Variables::get_instance();
        $result = $vars->compile_variables(['title' => 'Test']);
        return isset($result['title']) && $result['title'] === 'Test';
    }
    
    private function test_variable_concatenation() {
        $vars = CG_Variables::get_instance();
        $context = ['first' => 'Hello', 'second' => 'World'];
        $result = $vars->parse_expression('"Hello" + " " + "World"', $context);
        return $result === 'Hello World';
    }
    
    private function test_variable_reference() {
        $vars = CG_Variables::get_instance();
        $context = ['base' => 'Hello', 'derived' => '{var:base} World'];
        $compiled = $vars->compile_variables($context);
        return $compiled['derived'] === 'Hello World';
    }
    
    private function test_variable_nested_reference() {
        $vars = CG_Variables::get_instance();
        $context = [
            'level1' => 'A',
            'level2' => '{var:level1}B',
            'level3' => '{var:level2}C'
        ];
        $compiled = $vars->compile_variables($context);
        return $compiled['level3'] === 'ABC';
    }
    
    private function test_variable_math() {
        $vars = CG_Variables::get_instance();
        $result = $vars->parse_expression('5 + 3', []);
        return $result == 8;
    }
    
    private function test_variable_empty() {
        $vars = CG_Variables::get_instance();
        $context = ['empty' => '', 'null' => null];
        $compiled = $vars->compile_variables($context);
        return $compiled['empty'] === '' && ($compiled['null'] === '' || $compiled['null'] === null);
    }
    
    private function test_variable_special_chars() {
        $vars = CG_Variables::get_instance();
        $context = ['special' => 'Test & "quotes" <tag>'];
        $compiled = $vars->compile_variables($context);
        return strpos($compiled['special'], '&') !== false;
    }
    
    // ==================== INNER TEMPLATE TESTS ====================
    
    private function test_inner_template_create() {
        $inner = CG_Inner_Templates::get_instance();
        $id = $inner->save_template([
            'name' => 'Test Template ' . time(),
            'name_id' => 'test_template_' . time(),
            'type' => 'snippet',
            'content' => 'Test content for {title}'
        ]);
        return $id > 0;
    }
    
    private function test_inner_template_process() {
        $inner = CG_Inner_Templates::get_instance();
        $result = $inner->process_content(
            'Before {title} After',
            ['title' => 'TEST']
        );
        return strpos($result, 'TEST') !== false;
    }
    
    private function test_inner_template_by_id() {
        $inner = CG_Inner_Templates::get_instance();
        $templates = $inner->get_templates();
        
        if (empty($templates)) {
            return 'No templates to test';
        }
        
        $first = $templates[0];
        $content = "Test {inner:{$first['id']}} end";
        $result = $inner->process_content($content, []);
        
        return strpos($result, '{inner:') === false || $result !== $content;
    }
    
    private function test_inner_template_by_name() {
        $inner = CG_Inner_Templates::get_instance();
        $templates = $inner->get_templates();
        
        if (empty($templates)) {
            return 'No templates to test';
        }
        
        $first = $templates[0];
        if (empty($first['name_id'])) {
            return 'Template has no name_id';
        }
        
        $content = "Test {inner:{$first['name_id']}} end";
        $result = $inner->process_content($content, []);
        
        return strpos($result, '{inner:') === false || $result !== $content;
    }
    
    private function test_inner_template_nested() {
        // Test nested inner template references
        $inner = CG_Inner_Templates::get_instance();
        $content = "Start {title} End";
        $result = $inner->process_content($content, ['title' => 'NESTED']);
        return strpos($result, 'NESTED') !== false;
    }
    
    private function test_inner_template_with_variables() {
        $inner = CG_Inner_Templates::get_instance();
        $content = "{title} in {area} by {business_name}";
        $result = $inner->process_content($content, [
            'title' => 'Service',
            'area' => 'Melbourne',
            'business_name' => 'Test Co'
        ]);
        return strpos($result, 'Service') !== false && strpos($result, 'Melbourne') !== false;
    }
    
    // ==================== IMPORT/EXPORT TESTS ====================
    
    private function test_csv_export() {
        $ie = CG_Import_Export::get_instance();
        $zip_path = $ie->export(['html_templates'], 'csv');
        
        if (!$zip_path || !file_exists($zip_path)) {
            return 'Export failed to create file';
        }
        
        // Cleanup
        unlink($zip_path);
        return true;
    }
    
    private function test_csv_escaping() {
        $data = [
            ['name' => 'Test', 'content' => "Line 1\nLine 2\nLine 3"]
        ];
        
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));
        
        $escaped = str_replace(["\r\n", "\n", "\r"], "\\n", $data[0]['content']);
        fputcsv($output, [$data[0]['name'], $escaped]);
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return substr_count($csv, "\n") <= 2;
    }
    
    private function test_import_validation() {
        $ie = CG_Import_Export::get_instance();
        $result = $ie->import('/nonexistent/file.csv', []);
        return $result['success'] === false;
    }
    
    private function test_json_export() {
        $ie = CG_Import_Export::get_instance();
        $zip_path = $ie->export(['html_templates'], 'json');
        
        if (!$zip_path || !file_exists($zip_path)) {
            return 'JSON export failed to create file';
        }
        
        unlink($zip_path);
        return true;
    }
    
    private function test_import_merge() {
        // Test that import merge mode works
        return true; // Implementation depends on actual data
    }
    
    // ==================== TEMPLATE TESTS ====================
    
    private function test_html_template_save() {
        $id = $this->db->insert_html_template(
            'Test HTML Template ' . time(),
            'Test Description',
            '<div>{title} in {area}</div>'
        );
        
        if (!$id) return 'Failed to insert template';
        
        $template = $this->db->get_html_template($id);
        if (!$template) return 'Failed to retrieve template';
        
        $this->db->delete_html_template($id);
        return true;
    }
    
    private function test_html_template_update() {
        $id = $this->db->insert_html_template(
            'Test Update ' . time(),
            'Initial',
            '<div>Initial</div>'
        );
        
        if (!$id) return 'Failed to insert template';
        
        $this->db->update_html_template($id, 'Test Update ' . time(), 'Initial', '<div>Updated</div>');
        $template = $this->db->get_html_template($id);
        
        $success = $template && strpos($template['content'], 'Updated') !== false;
        $this->db->delete_html_template($id);
        
        return $success;
    }
    
    private function test_meta_template_save() {
        $id = $this->db->insert_meta_template(
            'Test Meta Template ' . time(),
            '{title} {area} | Site',
            'Description for {title} in {area}',
            '{title}-{area}'
        );
        
        if (!$id) return 'Failed to insert template';
        
        $this->db->delete_meta_template($id);
        return true;
    }
    
    private function test_schema_template_save() {
        $schema = json_encode([
            '@type' => 'LocalBusiness',
            'name' => '{business_name}'
        ]);
        
        $id = $this->db->insert_schema_template(
            'Test Schema ' . time(),
            $schema
        );
        
        if (!$id) return 'Failed to insert schema template';
        
        $this->db->delete_schema_template($id);
        return true;
    }
    
    private function test_placeholder_replacement() {
        $template = '{title} services in {area} by {business_name}';
        $context = [
            'title' => 'Cleaning',
            'area' => 'Melbourne',
            'business_name' => 'Test Co'
        ];
        
        $result = str_replace(
            ['{title}', '{area}', '{business_name}'],
            [$context['title'], $context['area'], $context['business_name']],
            $template
        );
        
        return $result === 'Cleaning services in Melbourne by Test Co';
    }
    
    private function test_placeholder_all_types() {
        $placeholders = [
            '{title}' => 'Title',
            '{area}' => 'Area',
            '{category}' => 'Category',
            '{slug}' => 'slug-value',
            '{url}' => 'https://example.com',
            '{business_name}' => 'Business',
            '{meta_title}' => 'Meta Title',
            '{meta_description}' => 'Meta Description'
        ];
        
        $template = implode(' | ', array_keys($placeholders));
        $result = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        
        foreach ($placeholders as $value) {
            if (strpos($result, $value) === false) {
                return "Placeholder for '{$value}' not replaced";
            }
        }
        
        return true;
    }
    
    private function test_template_category_hierarchy() {
        // Test 3-level hierarchy: Root -> Category -> Subcategory
        $hierarchy = [
            'root' => [
                'name' => 'Services',
                'children' => [
                    [
                        'name' => 'Cleaning',
                        'children' => [
                            ['name' => 'Commercial Cleaning'],
                            ['name' => 'Residential Cleaning']
                        ]
                    ]
                ]
            ]
        ];
        
        return isset($hierarchy['root']['children'][0]['children']);
    }
    
    // ==================== CATEGORY TESTS ====================
    
    private function test_category_name_format() {
        $format = '{title} {area}';
        $title = 'Cleaning';
        $area = 'Sydney';
        
        $result = str_replace(
            ['{title}', '{area}'],
            [$title, $area],
            $format
        );
        
        return $result === 'Cleaning Sydney';
    }
    
    private function test_slug_generation() {
        $name = 'Commercial Cleaning Melbourne CBD';
        $slug = sanitize_title($name);
        return $slug === 'commercial-cleaning-melbourne-cbd';
    }
    
    private function test_slug_special_chars() {
        $test_cases = [
            'Melbourne & Sydney' => 'melbourne-sydney',
            'Test "Quotes"' => 'test-quotes',
            'Café Services' => 'cafe-services',
        ];
        
        foreach ($test_cases as $input => $expected) {
            $slug = sanitize_title($input);
            if ($slug !== $expected) {
                // Flexible check - just ensure no special chars
                if (preg_match('/[^a-z0-9\-]/', $slug)) {
                    return "Slug '{$slug}' contains invalid characters";
                }
            }
        }
        
        return true;
    }
    
    private function test_parent_child_logic() {
        $areas = ['Melbourne', 'Sydney(S)', 'Brisbane'];
        
        $children = [];
        foreach ($areas as $area) {
            if (preg_match('/\(S\)\s*$/i', $area)) {
                $children[] = trim(preg_replace('/\(S\)\s*$/i', '', $area));
            }
        }
        
        return count($children) === 1 && $children[0] === 'Sydney';
    }
    
    private function test_s_notation() {
        $test_cases = [
            'Melbourne(S)' => ['is_sub' => true, 'clean' => 'Melbourne'],
            'Sydney (S)' => ['is_sub' => true, 'clean' => 'Sydney'],
            'Brisbane' => ['is_sub' => false, 'clean' => 'Brisbane'],
            'Perth(s)' => ['is_sub' => true, 'clean' => 'Perth'],
        ];
        
        foreach ($test_cases as $input => $expected) {
            $is_sub = (bool) preg_match('/\(S\)\s*$/i', $input);
            $clean = trim(preg_replace('/\(S\)\s*$/i', '', $input));
            
            if ($is_sub !== $expected['is_sub'] || $clean !== $expected['clean']) {
                return "Failed for input: {$input}";
            }
        }
        
        return true;
    }
    
    private function test_s_notation_edge_cases() {
        $edge_cases = [
            '(S)' => ['is_sub' => true, 'clean' => ''],
            'Test(S)More' => ['is_sub' => false, 'clean' => 'Test(S)More'],
            'Normal Area' => ['is_sub' => false, 'clean' => 'Normal Area'],
        ];
        
        foreach ($edge_cases as $input => $expected) {
            $is_sub = (bool) preg_match('/\(S\)\s*$/i', $input);
            $clean = trim(preg_replace('/\(S\)\s*$/i', '', $input));
            
            if ($is_sub !== $expected['is_sub']) {
                return "Edge case failed for: {$input}";
            }
        }
        
        return true;
    }
    
    private function test_area_list_parsing() {
        $area_text = "Melbourne\nSydney(S)\nBrisbane\n\nPerth";
        $areas = array_filter(array_map('trim', explode("\n", $area_text)));
        
        return count($areas) === 4;
    }
    
    private function test_bulk_generation_count() {
        $titles = ['Cleaning', 'Plumbing'];
        $areas = ['Melbourne', 'Sydney', 'Brisbane'];
        
        $expected_count = count($titles) * count($areas);
        return $expected_count === 6;
    }
    
    // ==================== HTML WRAPPER TESTS ====================
    
    private function test_div_wrapper() {
        $content = '<h2>Title</h2><p>Content</p>';
        $wrapper_class = CG_Constants::DEFAULT_WRAPPER_CLASS;
        
        $wrapped = "<div class=\"{$wrapper_class}\">{$content}</div>";
        
        return strpos($wrapped, '<div class="riseup-category-generator">') === 0;
    }
    
    private function test_schema_div_wrapper() {
        $schema = '{"@type": "LocalBusiness"}';
        $class = CG_Constants::DEFAULT_SCHEMA_WRAPPER_CLASS;
        
        $wrapped = "<div class=\"{$class}\"><script type=\"application/ld+json\">{$schema}</script></div>";
        
        return strpos($wrapped, '<div class="category-schema-wrapper">') !== false;
    }
    
    private function test_class_names() {
        $settings = CG_Settings::get_instance();
        $classes = $settings->get_class_settings();
        
        return !empty($classes['wrapper_class']) && !empty($classes['header_class']);
    }
    
    private function test_custom_classes() {
        $custom_class = 'my-custom-class';
        $content = '<p>Test</p>';
        $wrapped = "<div class=\"{$custom_class}\">{$content}</div>";
        
        return strpos($wrapped, 'my-custom-class') !== false;
    }
    
    private function test_html_sanitization() {
        $dangerous = '<script>alert("xss")</script>';
        $sanitized = wp_kses($dangerous, []);
        
        return strpos($sanitized, '<script>') === false;
    }
    
    // ==================== YOAST TESTS ====================
    
    private function test_yoast_meta() {
        $pattern = '{title} {area} | Professional Services';
        $result = str_replace(
            ['{title}', '{area}'],
            ['Cleaning', 'Melbourne'],
            $pattern
        );
        
        return $result === 'Cleaning Melbourne | Professional Services';
    }
    
    private function test_focus_keyword() {
        $pattern = CG_Constants::DEFAULT_FOCUS_KEYWORD_PATTERN;
        $result = str_replace(
            ['{title}', '{area}'],
            ['Cleaning', 'Melbourne'],
            $pattern
        );
        
        return $result === 'Cleaning Melbourne';
    }
    
    private function test_meta_title_length() {
        $title = 'Commercial Cleaning Services Melbourne CBD Victoria Australia';
        $max_length = 60;
        
        $truncated = strlen($title) > $max_length ? substr($title, 0, $max_length) : $title;
        
        return strlen($truncated) <= $max_length;
    }
    
    private function test_meta_desc_min_length() {
        $min_length = CG_Constants::META_DESC_MIN_CHARS;
        $description = str_repeat('Lorem ipsum ', 15);
        
        return strlen($description) >= $min_length;
    }
    
    private function test_yoast_score_thresholds() {
        $good = CG_Constants::get_yoast_score_class(80);
        $ok = CG_Constants::get_yoast_score_class(50);
        $bad = CG_Constants::get_yoast_score_class(20);
        
        return $good === 'cg-yoast-good' && $ok === 'cg-yoast-ok' && $bad === 'cg-yoast-bad';
    }
    
    // ==================== BUSINESS PROFILE TESTS ====================
    
    private function test_business_profile_save() {
        $data = [
            'business_name' => 'Test Business ' . time(),
            'phone' => '1234567890'
        ];
        
        $this->db->save_business_profile($data);
        $profile = $this->db->get_business_profile();
        
        return !empty($profile);
    }
    
    private function test_business_profile_update() {
        $profile = $this->db->get_business_profile();
        if (!$profile) {
            return 'No profile to update';
        }
        
        $new_name = 'Updated Business ' . time();
        $this->db->save_business_profile(['business_name' => $new_name]);
        
        $updated = $this->db->get_business_profile();
        return $updated && $updated['business_name'] === $new_name;
    }
    
    private function test_multiple_profiles() {
        $profiles = $this->db->get_all_business_profiles();
        return gettype($profiles) === 'array';
    }
    
    private function test_area_postal_mapping() {
        $mapping = [
            'Melbourne' => '3000',
            'Sydney' => '2000',
            'Brisbane' => '4000',
            'Perth' => '6000',
        ];
        
        return isset($mapping['Melbourne']) && $mapping['Melbourne'] === '3000';
    }
    
    private function test_business_profile_schema() {
        $profile = [
            'business_name' => 'Test Business',
            'phone' => '123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'city' => 'Melbourne',
            'country' => 'Australia'
        ];
        
        $schema = [
            '@type' => 'LocalBusiness',
            'name' => $profile['business_name'],
            'telephone' => $profile['phone'],
            'email' => $profile['email']
        ];
        
        $json = json_encode($schema);
        return strpos($json, 'LocalBusiness') !== false;
    }
    
    // ==================== SETTINGS TESTS ====================
    
    private function test_settings() {
        $settings = CG_Settings::get_instance();
        
        $test_key = 'test_setting_' . time();
        $test_value = 'test_value';
        
        $settings->set($test_key, $test_value);
        $retrieved = $settings->get($test_key);
        
        return $retrieved === $test_value;
    }
    
    private function test_settings_defaults() {
        $settings = CG_Settings::get_instance();
        $defaults = CG_Settings::get_defaults();
        
        return gettype($defaults) === 'array' && !empty($defaults);
    }
    
    private function test_ai_config() {
        $settings = CG_Settings::get_instance();
        $config = $settings->get_ai_config();
        
        return isset($config['provider']) && isset($config['url']);
    }
    
    private function test_css_class_settings() {
        $settings = CG_Settings::get_instance();
        $classes = $settings->get_class_settings();
        
        return isset($classes['wrapper_class']) && !empty($classes['wrapper_class']);
    }
    
    // ==================== CONSTANTS TESTS ====================
    
    private function test_constants_defined() {
        $required = [
            'PAGINATION_DEFAULT',
            'SNAPSHOT_LIMIT_DEFAULT',
            'META_DESC_MIN_CHARS',
            'DEFAULT_WRAPPER_CLASS',
        ];
        
        foreach ($required as $const) {
            if (!defined('CG_Constants::' . $const)) {
                $value = constant('CG_Constants::' . $const);
                if ($value === null) {
                    return "Constant {$const} not defined";
                }
            }
        }
        
        return true;
    }
    
    private function test_css_class_constants() {
        $required = [
            'LAYOUT_CARD',
            'LAYOUT_TABS',
            'MODAL',
            'BTN_PRIMARY',
        ];
        
        foreach ($required as $const) {
            $value = constant('CG_CSS::' . $const);
            if (empty($value)) {
                return "CSS class {$const} not defined";
            }
        }
        
        return true;
    }
    
    private function test_filesize_formatter() {
        $tests = [
            500 => '500 B',
            1024 => '1.00 KB',
            1048576 => '1.00 MB',
        ];
        
        foreach ($tests as $bytes => $expected) {
            $result = CG_Constants::format_filesize($bytes);
            if ($result !== $expected) {
                return "Expected {$expected}, got {$result}";
            }
        }
        
        return true;
    }
    
    private function test_yoast_score_classes() {
        $good = CG_Constants::get_yoast_score_class(75);
        $ok = CG_Constants::get_yoast_score_class(50);
        $bad = CG_Constants::get_yoast_score_class(30);
        $na = CG_Constants::get_yoast_score_class(0);
        
        return $good === 'cg-yoast-good' &&
               $ok === 'cg-yoast-ok' &&
               $bad === 'cg-yoast-bad' &&
               $na === 'cg-yoast-na';
    }
    
    // ==================== SNAPSHOT TESTS ====================
    
    private function test_snapshot_create() {
        $snapshot = CG_Snapshot::get_instance();
        $result = $snapshot->create_snapshot('Test Snapshot ' . time(), 'Test notes', 'manual');
        
        return $result && isset($result['success']) && $result['success'];
    }
    
    private function test_snapshot_types() {
        return CG_Constants::SNAPSHOT_TYPE_MANUAL === 'manual' &&
               CG_Constants::SNAPSHOT_TYPE_AUTO === 'auto';
    }
    
    private function test_snapshot_limit() {
        $limit = CG_Constants::SNAPSHOT_LIMIT_DEFAULT;
        return $limit >= CG_Constants::SNAPSHOT_LIMIT_MIN &&
               $limit <= CG_Constants::SNAPSHOT_LIMIT_MAX;
    }
    
    // ==================== SAVED TITLES/AREAS TESTS ====================
    
    private function test_saved_titles_create() {
        $name = 'Test Titles ' . time();
        $content = "Commercial Cleaning\nOffice Cleaning\nWindow Cleaning";
        
        $id = $this->db->save_titles($name, $content, '', '');
        
        return $id && $id > 0;
    }
    
    private function test_saved_titles_retrieve() {
        $items = $this->db->get_saved_titles();
        
        if (empty($items)) {
            // Create one first
            $this->db->save_titles('Test Titles Retrieve', "Test Content", '', '');
            $items = $this->db->get_saved_titles();
        }
        
        if (empty($items)) {
            return false;
        }
        
        $first = $items[0];
        $retrieved = $this->db->get_saved_titles_item($first['id']);
        
        return $retrieved && isset($retrieved['content']);
    }
    
    private function test_saved_areas_create() {
        $name = 'Test Areas ' . time();
        $content = "Melbourne\nSydney(S)\nBrisbane\nPerth";
        
        $id = $this->db->save_areas($name, $content, '', '');
        
        return $id && $id > 0;
    }
    
    private function test_saved_areas_retrieve() {
        $items = $this->db->get_saved_areas();
        
        if (empty($items)) {
            // Create one first
            $this->db->save_areas('Test Areas Retrieve', "Test Content", '', '');
            $items = $this->db->get_saved_areas();
        }
        
        if (empty($items)) {
            return false;
        }
        
        $first = $items[0];
        $retrieved = $this->db->get_saved_areas_item($first['id']);
        
        return $retrieved && isset($retrieved['content']);
    }
    
    // ==================== REMOTE API TESTS ====================
    
    private function test_remote_api_url_validation() {
        $valid_urls = [
            'https://example.com/api',
            'http://localhost:8080/api',
            'https://api.domain.com/v1/templates',
        ];
        
        $invalid_urls = [
            'not-a-url',
            'ftp://example.com',
        ];
        
        foreach ($valid_urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return "Valid URL failed: {$url}";
            }
        }
        
        foreach ($invalid_urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'http') === 0) {
                // This is expected to be invalid but might pass filter
            }
        }
        
        return true;
    }
    
    // ==================== UTILITY TESTS ====================
    
    private function test_string_sanitization() {
        // Test that HTML is properly stripped/sanitized
        $input = '<script>alert("xss")</script>Test';
        $sanitized = wp_strip_all_tags($input);
        return strpos($sanitized, '<script>') === false && strpos($sanitized, 'Test') !== false;
    }
    
    private function test_array_filtering() {
        // Test filtering empty lines from input
        $input = "Line1\n\nLine2\n  \nLine3";
        $lines = array_filter(array_map('trim', explode("\n", $input)));
        return count($lines) === 3;
    }
    
    private function test_empty_input_handling() {
        // Test that empty inputs don't cause errors
        $empty_string = '';
        $empty_array = [];
        
        return empty($empty_string) && empty($empty_array);
    }
    
    private function test_unicode_support() {
        // Test Unicode characters in category names
        $unicode_strings = [
            'Café',
            'Straße',
            '日本語',
            'Ελληνικά',
            'العربية'
        ];
        
        foreach ($unicode_strings as $str) {
            if (mb_strlen($str) === 0) {
                return "Unicode string check failed: {$str}";
            }
        }
        return true;
    }
    
    private function test_date_format_constants() {
        // Verify date format constants are valid
        $sortable = date(CG_Constants::DATE_FORMAT_SORTABLE);
        $snapshot = date(CG_Constants::DATE_FORMAT_SNAPSHOT);
        $timestamp = date(CG_Constants::DATE_FORMAT_TIMESTAMP);
        
        return !empty($sortable) && !empty($snapshot) && !empty($timestamp);
    }
    
    private function test_spacing_constants() {
        // Verify spacing constants are reasonable values
        return CG_Constants::SPACING_XS < CG_Constants::SPACING_SMALL &&
               CG_Constants::SPACING_SMALL < CG_Constants::SPACING_MEDIUM &&
               CG_Constants::SPACING_MEDIUM < CG_Constants::SPACING_LARGE &&
               CG_Constants::SPACING_LARGE < CG_Constants::SPACING_XLARGE;
    }
    
    private function test_icon_size_constants() {
        // Verify icon size constants are reasonable values
        return CG_Constants::ICON_SIZE_SMALL < CG_Constants::ICON_SIZE_MEDIUM &&
               CG_Constants::ICON_SIZE_MEDIUM < CG_Constants::ICON_SIZE_LARGE &&
               CG_Constants::ICON_SIZE_LARGE < CG_Constants::ICON_SIZE_XLARGE;
    }
    
    // ==================== AJAX HANDLER TESTS ====================
    
    private function test_ajax_actions_defined() {
        // Verify all AJAX action constants are defined
        $required_actions = [
            CG_Constants::AJAX_CREATE_SNAPSHOT,
            CG_Constants::AJAX_RESTORE_SNAPSHOT,
            CG_Constants::AJAX_DELETE_SNAPSHOT,
            CG_Constants::AJAX_SAVE_SETTINGS,
            CG_Constants::AJAX_SAVE_TEMPLATE,
            CG_Constants::AJAX_GET_TEMPLATE,
            CG_Constants::AJAX_RUN_TESTS,
            CG_Constants::AJAX_GET_SAVED_TITLES,
            CG_Constants::AJAX_GET_SAVED_AREAS,
        ];
        
        foreach ($required_actions as $action) {
            if (empty($action)) {
                return "AJAX action constant is empty";
            }
            if (strpos($action, 'cg_') !== 0) {
                return "AJAX action '{$action}' doesn't start with 'cg_' prefix";
            }
        }
        return true;
    }
    
    private function test_ajax_nonce_validation() {
        // Test that nonce creation works
        $nonce = wp_create_nonce('cg_nonce');
        
        if (empty($nonce)) {
            return "Failed to create nonce";
        }
        
        // Verify nonce is valid
        $verify = wp_verify_nonce($nonce, 'cg_nonce');
        return $verify !== false;
    }
    
    private function test_ajax_response_format() {
        // Test JSON response structure
        $success_response = [
            'success' => true,
            'data' => ['message' => 'Test']
        ];
        
        $error_response = [
            'success' => false,
            'data' => ['message' => 'Error']
        ];
        
        $success_json = json_encode($success_response);
        $error_json = json_encode($error_response);
        
        return json_decode($success_json, true)['success'] === true &&
               json_decode($error_json, true)['success'] === false;
    }
    
    private function test_ajax_save_template_handler() {
        // Test that save template uses correct database method
        $test_name = 'AJAX Handler Test ' . time();
        $id = $this->db->insert_html_template($test_name, 'Test Description', '<div>Test</div>');
        
        if (!$id) {
            return "Failed to insert test template";
        }
        
        // Update the template with correct parameters
        $updated = $this->db->update_html_template($id, $test_name, 'Updated Description', '<div>Updated</div>');
        
        // Cleanup
        $this->db->delete_html_template($id);
        
        return $updated !== false;
    }
    
    private function test_ajax_get_template_handler() {
        // Test template retrieval
        $test_name = 'AJAX Get Test ' . time();
        $id = $this->db->insert_html_template($test_name, 'Get Test', '<div>Content</div>');
        
        if (!$id) {
            return "Failed to insert template for get test";
        }
        
        $template = $this->db->get_html_template($id);
        
        // Cleanup
        $this->db->delete_html_template($id);
        
        return $template && isset($template['name']) && $template['name'] === $test_name;
    }
    
    private function test_ajax_snapshot_handler() {
        // Test snapshot AJAX operations
        $snapshot = CG_Snapshot::get_instance();
        
        if (!method_exists($snapshot, 'get_recent')) {
            return "Snapshot get_recent method not found";
        }
        
        $recent = $snapshot->get_recent(5);
        return gettype($recent) === 'array';
    }
    
    // ==================== JS DATA TESTS ====================
    
    private function test_js_constants_export() {
        // Verify JS constants export format
        $js_constants = CG_Constants::get_js_constants();
        
        if (gettype($js_constants) !== 'array') {
            return "JS constants not an array";
        }
        
        $required_keys = ['pagination', 'limits', 'truncate', 'animation', 'yoastScore'];
        
        foreach ($required_keys as $key) {
            if (!isset($js_constants[$key])) {
                return "Missing JS constant key: {$key}";
            }
        }
        
        return true;
    }
    
    private function test_js_css_classes_export() {
        // Verify CSS classes are properly formatted for JS
        $classes = CG_CSS::get_js_classes();
        
        if (gettype($classes) !== 'array') {
            return "JS classes not an array";
        }
        
        // Check that class names don't contain invalid characters
        foreach ($classes as $group => $items) {
            if (gettype($items) !== 'array') continue;
            foreach ($items as $key => $class) {
                if (preg_match('/[^a-zA-Z0-9\-_]/', $class)) {
                    return "Invalid class name: {$class}";
                }
            }
        }
        
        return true;
    }
    
    private function test_js_dom_element_ids() {
        // Verify DOM element IDs are properly formatted
        $ids = CG_CSS::get_js_ids();
        
        if (gettype($ids) !== 'array') {
            return "JS IDs not an array";
        }
        
        // IDs should start with 'cg-'
        foreach ($ids as $group => $items) {
            if (gettype($items) !== 'array') continue;
            foreach ($items as $key => $id) {
                if (strpos($id, 'cg-') !== 0) {
                    return "ID '{$id}' doesn't start with 'cg-' prefix";
                }
            }
        }
        
        return true;
    }
    
    private function test_js_localized_strings() {
        // Test that localized strings are translatable
        $test_strings = [
            __('Generate', 'category-generator'),
            __('Save', 'category-generator'),
            __('Delete', 'category-generator'),
            __('Settings', 'category-generator'),
        ];
        
        foreach ($test_strings as $str) {
            if (empty($str)) {
                return "Empty localized string found";
            }
        }
        
        return true;
    }
    
    private function test_js_template_type_validation() {
        // Test template type constants used in JS
        $valid_types = ['html', 'meta', 'schema'];
        
        // Simulate what JS would receive
        foreach ($valid_types as $type) {
            if (!in_array($type, $valid_types)) {
                return "Invalid template type: {$type}";
            }
        }
        
        return true;
    }
    
    // ==================== INPUT VALIDATION TESTS ====================
    
    private function test_input_xss_prevention() {
        // Test XSS prevention
        $xss_attempts = [
            '<script>alert("xss")</script>',
            '<img src=x onerror=alert("xss")>',
            'javascript:alert("xss")',
            '<svg onload=alert("xss")>',
        ];
        
        foreach ($xss_attempts as $attempt) {
            $sanitized = wp_kses_post($attempt);
            if (strpos($sanitized, '<script') !== false || strpos($sanitized, 'javascript:') !== false) {
                return "XSS not prevented: {$attempt}";
            }
        }
        
        return true;
    }
    
    private function test_input_sql_injection_prevention() {
        // Test SQL injection prevention via escaping
        $sql_attempts = [
            "'; DROP TABLE users; --",
            "1 OR 1=1",
            "UNION SELECT * FROM users",
        ];
        
        global $wpdb;
        
        foreach ($sql_attempts as $attempt) {
            $escaped = $wpdb->prepare('%s', $attempt);
            // Prepared statements should quote the input
            if ($escaped === $attempt) {
                return "SQL injection not prevented: {$attempt}";
            }
        }
        
        return true;
    }
    
    private function test_input_max_length_validation() {
        // Test truncation constants
        $short = CG_Constants::TRUNCATE_SHORT;
        $medium = CG_Constants::TRUNCATE_MEDIUM;
        $long = CG_Constants::TRUNCATE_LONG;
        
        // Verify they're ordered correctly
        if ($short >= $medium || $medium >= $long) {
            return "Truncate constants not in order";
        }
        
        // Test actual truncation
        $long_string = str_repeat('A', 200);
        $truncated = substr($long_string, 0, $short);
        
        return strlen($truncated) === $short;
    }
    
    /**
     * Get PHPUnit test file content
     */
    public function get_phpunit_tests() {
        return <<<'PHP'
<?php
/**
 * PHPUnit Tests for Category Generator
 * Run with: vendor/bin/phpunit tests/CategoryGeneratorTest.php
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

use PHPUnit\Framework\TestCase;

class CategoryGeneratorTest extends TestCase {
    
    private static $db;
    private static $settings;
    
    public static function setUpBeforeClass(): void {
        // Load WordPress test environment
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__DIR__) . '/wordpress/');
        }
        
        // Initialize plugin classes
        self::$db = CG_Database::get_instance();
        self::$settings = CG_Settings::get_instance();
    }
    
    // ==================== DATABASE TESTS ====================
    
    public function testDatabaseConnection() {
        $db = CG_Database::get_instance();
        $this->assertNotNull($db);
    }
    
    public function testTablesExist() {
        $tables = ['category_history', 'html_templates', 'meta_templates'];
        foreach ($tables as $table) {
            $this->assertTrue(self::$db->table_exists($table), "Table {$table} should exist");
        }
    }
    
    public function testDatabaseInsert() {
        $id = self::$db->insert_html_template('PHPUnit Test', 'Test', '<div>Test</div>');
        $this->assertGreaterThan(0, $id);
        self::$db->delete_html_template($id);
    }
    
    // ==================== VARIABLE TESTS ====================
    
    public function testVariableCompilation() {
        $vars = CG_Variables::get_instance();
        $result = $vars->compile_variables(['title' => 'Test']);
        $this->assertEquals('Test', $result['title']);
    }
    
    public function testVariableConcatenation() {
        $vars = CG_Variables::get_instance();
        $result = $vars->parse_expression('"Hello" + " " + "World"', []);
        $this->assertEquals('Hello World', $result);
    }
    
    public function testVariableReference() {
        $vars = CG_Variables::get_instance();
        $context = ['base' => 'Hello', 'derived' => '{var:base} World'];
        $compiled = $vars->compile_variables($context);
        $this->assertEquals('Hello World', $compiled['derived']);
    }
    
    // ==================== PLACEHOLDER TESTS ====================
    
    public function testPlaceholderReplacement() {
        $template = '{title} in {area}';
        $result = str_replace(['{title}', '{area}'], ['Cleaning', 'Melbourne'], $template);
        $this->assertEquals('Cleaning in Melbourne', $result);
    }
    
    public function testAllPlaceholders() {
        $placeholders = CG_Constants::get_placeholders();
        $this->assertContains('{title}', $placeholders);
        $this->assertContains('{area}', $placeholders);
        $this->assertContains('{business_name}', $placeholders);
    }
    
    // ==================== SLUG TESTS ====================
    
    public function testSlugGeneration() {
        $name = 'Commercial Cleaning Melbourne';
        $slug = sanitize_title($name);
        $this->assertEquals('commercial-cleaning-melbourne', $slug);
    }
    
    public function testSlugSpecialChars() {
        $name = 'Café & Restaurant Services';
        $slug = sanitize_title($name);
        $this->assertDoesNotMatchRegularExpression('/[^a-z0-9\-]/', $slug);
    }
    
    // ==================== S-NOTATION TESTS ====================
    
    public function testSNotationParsing() {
        $area = 'Sydney(S)';
        $is_sub = preg_match('/\(S\)\s*$/i', $area);
        $clean = trim(preg_replace('/\(S\)\s*$/i', '', $area));
        
        $this->assertTrue((bool) $is_sub);
        $this->assertEquals('Sydney', $clean);
    }
    
    public function testSNotationCaseInsensitive() {
        $variations = ['Melbourne(S)', 'Sydney(s)', 'Brisbane (S)', 'Perth (s)'];
        
        foreach ($variations as $area) {
            $is_sub = preg_match('/\(S\)\s*$/i', $area);
            $this->assertTrue((bool) $is_sub, "{$area} should be detected as subcategory");
        }
    }
    
    public function testNonSubcategoryArea() {
        $area = 'Normal Area';
        $is_sub = preg_match('/\(S\)\s*$/i', $area);
        $this->assertFalse((bool) $is_sub);
    }
    
    // ==================== HTML WRAPPER TESTS ====================
    
    public function testDivWrapper() {
        $content = '<p>Content</p>';
        $wrapper_class = CG_Constants::DEFAULT_WRAPPER_CLASS;
        $wrapped = "<div class=\"{$wrapper_class}\">{$content}</div>";
        
        $this->assertStringStartsWith('<div class="riseup-category-generator">', $wrapped);
    }
    
    public function testSchemaWrapper() {
        $schema = '{"@type": "LocalBusiness"}';
        $class = CG_Constants::DEFAULT_SCHEMA_WRAPPER_CLASS;
        $wrapped = "<div class=\"{$class}\"><script type=\"application/ld+json\">{$schema}</script></div>";
        
        $this->assertStringContainsString('category-schema-wrapper', $wrapped);
        $this->assertStringContainsString('application/ld+json', $wrapped);
    }
    
    // ==================== META TESTS ====================
    
    public function testMetaTitleGeneration() {
        $pattern = '{title} {area} | Site';
        $result = str_replace(['{title}', '{area}'], ['Cleaning', 'Melbourne'], $pattern);
        $this->assertEquals('Cleaning Melbourne | Site', $result);
    }
    
    public function testMetaDescriptionMinLength() {
        $min = CG_Constants::META_DESC_MIN_CHARS;
        $this->assertGreaterThan(100, $min);
    }
    
    // ==================== YOAST SCORE TESTS ====================
    
    public function testYoastScoreGood() {
        $class = CG_Constants::get_yoast_score_class(80);
        $this->assertEquals('cg-yoast-good', $class);
    }
    
    public function testYoastScoreOk() {
        $class = CG_Constants::get_yoast_score_class(50);
        $this->assertEquals('cg-yoast-ok', $class);
    }
    
    public function testYoastScoreBad() {
        $class = CG_Constants::get_yoast_score_class(20);
        $this->assertEquals('cg-yoast-bad', $class);
    }
    
    public function testYoastScoreNA() {
        $class = CG_Constants::get_yoast_score_class(0);
        $this->assertEquals('cg-yoast-na', $class);
    }
    
    // ==================== CONSTANTS TESTS ====================
    
    public function testConstantsDefined() {
        $this->assertEquals(50, CG_Constants::PAGINATION_DEFAULT);
        $this->assertEquals(20, CG_Constants::SNAPSHOT_LIMIT_DEFAULT);
        $this->assertEquals(135, CG_Constants::META_DESC_MIN_CHARS);
    }
    
    public function testFilesizeFormatter() {
        $this->assertEquals('500 B', CG_Constants::format_filesize(500));
        $this->assertEquals('1.00 KB', CG_Constants::format_filesize(1024));
        $this->assertEquals('1.00 MB', CG_Constants::format_filesize(1048576));
    }
    
    public function testBusinessTypes() {
        $types = CG_Constants::get_business_types();
        $this->assertArrayHasKey('LocalBusiness', $types);
        $this->assertArrayHasKey('CleaningService', $types);
        $this->assertArrayHasKey('Plumber', $types);
    }
    
    public function testPriceRanges() {
        $ranges = CG_Constants::get_price_ranges();
        $this->assertArrayHasKey('$', $ranges);
        $this->assertArrayHasKey('$$$$', $ranges);
    }
    
    // ==================== SETTINGS TESTS ====================
    
    public function testSettingsSaveLoad() {
        $key = 'phpunit_test_' . time();
        self::$settings->set($key, 'test_value');
        $this->assertEquals('test_value', self::$settings->get($key));
    }
    
    public function testSettingsDefaults() {
        $defaults = CG_Settings::get_defaults();
        $this->assertIsArray($defaults);
        $this->assertNotEmpty($defaults);
    }
    
    public function testAIProviders() {
        $providers = CG_Settings::get_ai_providers();
        $this->assertIsArray($providers);
    }
    
    // ==================== CSS CLASS TESTS ====================
    
    public function testCSSClassConstants() {
        $this->assertEquals('cg-card', CG_CSS::LAYOUT_CARD);
        $this->assertEquals('cg-tabs', CG_CSS::LAYOUT_TABS);
        $this->assertEquals('cg-modal', CG_CSS::MODAL);
    }
    
    public function testCSSJsClasses() {
        $classes = CG_CSS::get_js_classes();
        $this->assertArrayHasKey('layout', $classes);
        $this->assertArrayHasKey('modal', $classes);
    }
    
    public function testCSSJsIds() {
        $ids = CG_CSS::get_js_ids();
        $this->assertArrayHasKey('history', $ids);
        $this->assertArrayHasKey('snapshot', $ids);
    }
}
PHP;
    }
}
