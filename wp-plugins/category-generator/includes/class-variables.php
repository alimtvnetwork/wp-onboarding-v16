<?php
/**
 * Variable Processor for Category Generator
 * Handles variable compilation where one variable can reference another
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Variables {
    
    private static $instance = null;
    private $db;
    private $variables = array();
    
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
     * Load variables from database
     */
    public function load_variables() {
        $this->variables = $this->db->get_variables();
        return $this->variables;
    }
    
    /**
     * Compile variables with string concatenation
     * Variables can reference other variables using {var:name} syntax
     * 
     * @param array $context Additional context variables (title, area, etc.)
     * @return array Compiled variables
     */
    public function compile_variables($context = array()) {
        $this->load_variables();
        
        // Merge context with stored variables
        $all_vars = array_merge($context, $this->variables);
        $compiled = array();
        $max_iterations = 10; // Prevent infinite loops
        
        foreach ($all_vars as $name => $value) {
            $compiled[$name] = $this->resolve_value($value, $all_vars, $max_iterations);
        }
        
        return $compiled;
    }
    
    /**
     * Resolve a single value, replacing variable references
     * 
     * @param string $value The value to resolve
     * @param array $variables Available variables
     * @param int $depth Maximum recursion depth
     * @return string Resolved value
     */
    private function resolve_value($value, $variables, $depth = 10) {
        if ($depth <= 0 || !is_string($value)) {
            return $value;
        }
        
        // Find {var:name} patterns for variable references
        $pattern = '/\{var:([a-zA-Z_][a-zA-Z0-9_]*)\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($variables, $depth) {
            $var_name = $matches[1];
            
            if (isset($variables[$var_name])) {
                // Recursively resolve the referenced variable
                return $this->resolve_value($variables[$var_name], $variables, $depth - 1);
            }
            
            return $matches[0]; // Return unchanged if not found
        }, $value);
    }
    
    /**
     * Parse a value with string concatenation support
     * Syntax: value1 + value2 + {var:name}
     * 
     * @param string $expression The expression to parse
     * @param array $context Context variables
     * @return string Concatenated result
     */
    public function parse_expression($expression, $context = array()) {
        // Split by + for concatenation
        $parts = preg_split('/\s*\+\s*/', $expression);
        $result = '';
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Remove surrounding quotes if present
            if (preg_match('/^["\'](.*)["\']\s*$/', $part, $matches)) {
                $result .= $matches[1];
            } else {
                // Resolve variable reference
                $result .= $this->resolve_value($part, $context);
            }
        }
        
        return $result;
    }
    
    /**
     * Save a variable
     */
    public function save_variable($name, $value) {
        return $this->db->save_variable($name, $value);
    }
    
    /**
     * Delete a variable
     */
    public function delete_variable($name) {
        return $this->db->delete_variable($name);
    }
    
    /**
     * Get all stored variables
     */
    public function get_all_variables() {
        return $this->db->get_variables();
    }
    
    /**
     * Process template with variables
     * Replaces {var:name} patterns in template
     * 
     * @param string $template Template content
     * @param array $context Context variables (title, area, business_profile, etc.)
     * @return string Processed template
     */
    public function process_template($template, $context = array()) {
        $compiled = $this->compile_variables($context);
        
        // Replace {var:name} patterns
        $pattern = '/\{var:([a-zA-Z_][a-zA-Z0-9_]*)\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($compiled) {
            $var_name = $matches[1];
            return isset($compiled[$var_name]) ? $compiled[$var_name] : $matches[0];
        }, $template);
    }
}
