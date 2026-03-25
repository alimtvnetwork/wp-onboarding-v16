<?php
/**
 * Inner Templates Handler for Category Generator
 * Reusable template snippets that can be embedded via {inner:id} or {inner:name}
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Inner_Templates {
    
    private static $instance = null;
    private $db;
    private $templates_cache = null;
    
    // Template types
    const TYPE_ANCHOR = 'anchor';
    const TYPE_HEADER = 'header';
    const TYPE_MARKETING = 'marketing';
    const TYPE_CTA = 'cta';
    const TYPE_SNIPPET = 'snippet';
    const TYPE_LINK_LIST = 'link_list';
    
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
     * Get all inner template types
     */
    public static function get_types() {
        return array(
            self::TYPE_ANCHOR => __('Anchor Link', 'category-generator'),
            self::TYPE_HEADER => __('Header Block', 'category-generator'),
            self::TYPE_MARKETING => __('Marketing Copy', 'category-generator'),
            self::TYPE_CTA => __('Call to Action', 'category-generator'),
            self::TYPE_SNIPPET => __('Text Snippet', 'category-generator'),
            self::TYPE_LINK_LIST => __('Category Link List', 'category-generator'),
        );
    }
    
    /**
     * Get all inner templates
     */
    public function get_templates() {
        if ($this->templates_cache === null) {
            $this->templates_cache = $this->db->get_inner_templates();
        }
        return $this->templates_cache;
    }
    
    /**
     * Get template by ID
     */
    public function get_template($id) {
        return $this->db->get_inner_template($id);
    }
    
    /**
     * Get template by name identifier
     */
    public function get_template_by_name($name) {
        return $this->db->get_inner_template_by_name($name);
    }
    
    /**
     * Save/update an inner template
     */
    public function save_template($data) {
        $this->templates_cache = null; // Clear cache
        
        if (isset($data['id']) && $data['id'] > 0) {
            return $this->db->update_inner_template(
                $data['id'],
                $data['name'],
                $data['name_id'],
                $data['type'],
                $data['content'],
                $data['category'] ?? ''
            );
        } else {
            return $this->db->insert_inner_template(
                $data['name'],
                $data['name_id'],
                $data['type'],
                $data['content'],
                $data['category'] ?? ''
            );
        }
    }
    
    /**
     * Delete an inner template
     */
    public function delete_template($id) {
        $this->templates_cache = null;
        return $this->db->delete_inner_template($id);
    }
    
    /**
     * Duplicate an inner template
     */
    public function duplicate_template($id) {
        $template = $this->get_template($id);
        if (!$template) {
            return false;
        }
        
        $new_data = array(
            'name' => $template['name'] . ' (Copy)',
            'name_id' => $template['name_id'] . '_copy_' . time(),
            'type' => $template['type'],
            'content' => $template['content'],
            'category' => $template['category'] ?? ''
        );
        
        return $this->save_template($new_data);
    }
    
    /**
     * Process content and replace inner template references
     * Syntax: {inner:id} or {inner:name-id}
     * 
     * @param string $content Content with inner template references
     * @param array $context Variables context for placeholder replacement
     * @return string Processed content
     */
    public function process_content($content, $context = array()) {
        // Match {inner:N} for numeric IDs
        $content = preg_replace_callback(
            '/\{inner:(\d+)\}/',
            function($matches) use ($context) {
                $template = $this->get_template(intval($matches[1]));
                if ($template) {
                    return $this->process_template_content($template['content'], $context);
                }
                return $matches[0];
            },
            $content
        );
        
        // Match {inner:name-id} for named templates
        $content = preg_replace_callback(
            '/\{inner:([a-zA-Z_][a-zA-Z0-9_-]*)\}/',
            function($matches) use ($context) {
                $template = $this->get_template_by_name($matches[1]);
                if ($template) {
                    return $this->process_template_content($template['content'], $context);
                }
                return $matches[0];
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Process template content with context variables
     */
    private function process_template_content($template_content, $context) {
        $placeholders = array(
            '{title}' => $context['title'] ?? '',
            '{area}' => $context['area'] ?? '',
            '{category}' => $context['category'] ?? '',
            '{slug}' => $context['slug'] ?? '',
            '{url}' => $context['url'] ?? '',
            '{business_name}' => $context['business_name'] ?? '',
            '{phone}' => $context['phone'] ?? '',
            '{email}' => $context['email'] ?? '',
            '{website}' => $context['website'] ?? '',
        );
        
        // Add business profile fields if available
        if (isset($context['business_profile']) && is_array($context['business_profile'])) {
            foreach ($context['business_profile'] as $key => $value) {
                $placeholders['{' . $key . '}'] = $value;
            }
        }
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template_content);
    }
    
    /**
     * Generate category link list HTML
     * Used for internal linking within templates
     * 
     * @param array $categories Array of category slugs or IDs
     * @param string $template Link template
     * @param int $limit Maximum links to include
     * @return string Generated HTML
     */
    public function generate_category_links($categories = array(), $template = '<a href="{url}" title="{name}">{name}</a>', $limit = 5) {
        if (empty($categories)) {
            // Get random existing categories
            $args = array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'number' => $limit,
                'orderby' => 'rand'
            );
            $categories = get_categories($args);
        }
        
        $links = array();
        $count = 0;
        
        foreach ($categories as $cat) {
            if ($count >= $limit) break;
            
            if (is_object($cat)) {
                $cat_obj = $cat;
            } elseif (is_numeric($cat)) {
                $cat_obj = get_category($cat);
            } else {
                $cat_obj = get_category_by_slug($cat);
            }
            
            if ($cat_obj) {
                $link = str_replace(
                    array('{url}', '{name}', '{slug}', '{id}'),
                    array(
                        get_category_link($cat_obj->term_id),
                        esc_html($cat_obj->name),
                        $cat_obj->slug,
                        $cat_obj->term_id
                    ),
                    $template
                );
                $links[] = $link;
                $count++;
            }
        }
        
        return implode(', ', $links);
    }
    
    /**
     * Get default inner templates
     */
    public function get_default_templates() {
        return array(
            array(
                'name' => 'Company Founded Statement',
                'name_id' => 'company-founded',
                'type' => self::TYPE_SNIPPET,
                'content' => 'Founded 2023 bringing 5 years combined expertise, {business_name} serves {area} with exceptional {title}. Furthermore, completing more than 210 projects demonstrates verified capability. Additionally, client loyalty grows 2% monthly.',
                'category' => 'About'
            ),
            array(
                'name' => 'Service Area Statement',
                'name_id' => 'service-area',
                'type' => self::TYPE_SNIPPET,
                'content' => 'Proudly serving {area} and surrounding suburbs with professional {title} services.',
                'category' => 'About'
            ),
            array(
                'name' => 'Contact CTA',
                'name_id' => 'contact-cta',
                'type' => self::TYPE_CTA,
                'content' => '<a href="{website}/contact" class="cg-cta-button" title="Contact {business_name} for {title}">Get Your Free Quote Today</a>',
                'category' => 'CTA'
            ),
            array(
                'name' => 'Phone CTA',
                'name_id' => 'phone-cta',
                'type' => self::TYPE_CTA,
                'content' => '<a href="tel:{phone}" class="cg-cta-phone" title="Call {business_name}">Call Now: {phone}</a>',
                'category' => 'CTA'
            ),
            array(
                'name' => 'Trust Statement',
                'name_id' => 'trust-statement',
                'type' => self::TYPE_MARKETING,
                'content' => 'Trusted by over 500 {area} businesses and families. Licensed, insured, and committed to excellence.',
                'category' => 'Marketing'
            ),
            array(
                'name' => 'Internal Category Links',
                'name_id' => 'related-services',
                'type' => self::TYPE_LINK_LIST,
                'content' => 'Explore our related services: {category_links}',
                'category' => 'Links'
            ),
        );
    }
}
