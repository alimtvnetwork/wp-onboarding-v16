<?php
/**
 * Settings Handler for Category Generator
 * Manages all plugin settings including AI providers, remote templates, and class names
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Settings {
    
    private static $instance = null;
    private $db;
    private $settings_cache = null;
    
    // AI Providers
    const AI_OPENAI = 'openai';
    const AI_GEMINI = 'gemini';
    const AI_GROK = 'grok';
    const AI_DEEPSEEK = 'deepseek';
    const AI_CLAUDE = 'claude';
    const AI_CUSTOM = 'custom';
    
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
     * Get all AI providers configuration
     */
    public static function get_ai_providers() {
        return [
            self::AI_OPENAI => [
                'name' => 'OpenAI (ChatGPT)',
                'default_url' => 'https://api.openai.com/v1/chat/completions',
                'models' => [
                    'gpt-5' => 'GPT-5',
                    'gpt-4o' => 'GPT-4o',
                    'gpt-4o-mini' => 'GPT-4o Mini',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                ]
            ],
            self::AI_GEMINI => [
                'name' => 'Google Gemini',
                'default_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                'models' => [
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                ]
            ],
            self::AI_GROK => [
                'name' => 'xAI Grok',
                'default_url' => 'https://api.x.ai/v1/chat/completions',
                'models' => [
                    'grok-2' => 'Grok 2',
                    'grok-beta' => 'Grok Beta',
                ]
            ],
            self::AI_DEEPSEEK => [
                'name' => 'DeepSeek',
                'default_url' => 'https://api.deepseek.com/v1/chat/completions',
                'models' => [
                    'deepseek-chat' => 'DeepSeek Chat',
                    'deepseek-coder' => 'DeepSeek Coder',
                ]
            ],
            self::AI_CLAUDE => [
                'name' => 'Anthropic Claude',
                'default_url' => 'https://api.anthropic.com/v1/messages',
                'models' => [
                    'claude-sonnet-4-5' => 'Claude Sonnet 4.5',
                    'claude-3-5-sonnet' => 'Claude 3.5 Sonnet',
                ]
            ],
            self::AI_CUSTOM => [
                'name' => 'Custom Provider',
                'default_url' => '',
                'models' => []
            ],
        ];
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        if ($this->settings_cache === null) {
            $this->settings_cache = $this->db->get_settings();
        }
        return $this->settings_cache;
    }
    
    /**
     * Get a specific setting
     */
    public function get($key, $default = null) {
        $settings = $this->get_all();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Save a setting
     */
    public function set($key, $value) {
        $this->settings_cache = null;
        return $this->db->save_setting($key, $value);
    }
    
    /**
     * Save multiple settings
     */
    public function save_all($settings) {
        $this->settings_cache = null;
        foreach ($settings as $key => $value) {
            $this->db->save_setting($key, $value);
        }
        return true;
    }
    
    /**
     * Get default settings
     */
    public static function get_defaults() {
        return [
            // HTML Class Names
            'wrapper_class' => 'riseup-category-generator',
            'header_class' => 'category-header',
            'paragraph_class' => 'seo-container-para',
            'schema_wrapper_class' => 'category-schema-wrapper',
            
            // AI Settings
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => '',
            'ai_api_url' => '',
            'ai_html_model' => 'gpt-4o',
            'ai_meta_model' => 'gpt-4o-mini',
            
            // Custom AI
            'custom_ai_url' => '',
            'custom_ai_token' => '',
            'custom_ai_model' => '',
            
            // Remote Templates API
            'remote_template_apis' => '[]', // JSON array of API endpoints
            
            // Yoast Settings
            'yoast_use_default_title' => false,
            'yoast_focus_keyword_pattern' => '{title} {area}',
            
            // Business Profile
            'default_business_profile_id' => 1,
            'use_dynamic_location' => true,
            
            // General
            'auto_save_templates' => true,
            'confirm_before_generate' => true,
        ];
    }
    
    /**
     * Get CSS class settings
     */
    public function get_class_settings() {
        return [
            'wrapper_class' => $this->get('wrapper_class', 'riseup-category-generator'),
            'header_class' => $this->get('header_class', 'category-header'),
            'paragraph_class' => $this->get('paragraph_class', 'seo-container-para'),
            'schema_wrapper_class' => $this->get('schema_wrapper_class', 'category-schema-wrapper'),
        ];
    }
    
    /**
     * Get AI configuration for a specific provider
     */
    public function get_ai_config($provider = null) {
        if ($provider === null) {
            $provider = $this->get('ai_provider', 'openai');
        }
        
        $providers = self::get_ai_providers();
        $provider_config = $providers[$provider] ?? $providers['openai'];
        
        return [
            'provider' => $provider,
            'name' => $provider_config['name'],
            'url' => $this->get('ai_api_url') ?: $provider_config['default_url'],
            'api_key' => $this->get('ai_api_key', ''),
            'model' => $this->get('ai_model', 'gpt-4o-mini'),
            'html_model' => $this->get('ai_html_model', 'gpt-4o'),
            'meta_model' => $this->get('ai_meta_model', 'gpt-4o-mini'),
        ];
    }
    
    /**
     * Get remote template APIs
     */
    public function get_remote_apis() {
        $apis_json = $this->get('remote_template_apis', '[]');
        $apis = json_decode($apis_json, true);
        return gettype($apis) === 'array' ? $apis : [];
    }
    
    /**
     * Add remote template API
     */
    public function add_remote_api($api_config) {
        $apis = $this->get_remote_apis();
        $apis[] = [
            'id' => uniqid('api_'),
            'name' => $api_config['name'] ?? 'Unnamed API',
            'url' => $api_config['url'] ?? '',
            'api_key' => $api_config['api_key'] ?? '',
            'oauth_token' => $api_config['oauth_token'] ?? '',
            'enabled' => $api_config['enabled'] ?? true,
        ];
        return $this->set('remote_template_apis', json_encode($apis));
    }
    
    /**
     * Remove remote template API
     */
    public function remove_remote_api($api_id) {
        $apis = $this->get_remote_apis();
        $apis = array_filter($apis, function($api) use ($api_id) {
            return $api['id'] !== $api_id;
        });
        return $this->set('remote_template_apis', json_encode(array_values($apis)));
    }
    
    /**
     * Fetch templates from remote API
     */
    public function fetch_remote_templates($api_id) {
        $apis = $this->get_remote_apis();
        $api = null;
        
        foreach ($apis as $a) {
            if ($a['id'] === $api_id) {
                $api = $a;
                break;
            }
        }
        
        if (!$api || empty($api['url'])) {
            return ['success' => false, 'message' => 'API not found or URL is empty'];
        }
        
        $headers = [];
        if (!empty($api['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $api['api_key'];
        }
        
        $response = wp_remote_get($api['url'], [
            'headers' => $headers,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || gettype($data) !== 'array') {
            return ['success' => false, 'message' => 'Invalid response from API'];
        }
        
        return ['success' => true, 'templates' => $data];
    }
    
    /**
     * Get Yoast data from WordPress if available
     */
    public function get_yoast_data() {
        $data = [
            'is_active' => false,
            'company_name' => '',
            'company_logo' => '',
            'social_profiles' => [],
            'address' => [],
        ];
        
        // Check if Yoast is active
        if (!class_exists('WPSEO_Options') && !defined('WPSEO_VERSION')) {
            return $data;
        }
        
        $data['is_active'] = true;
        
        // Try to get Yoast settings
        if (function_exists('YoastSEO')) {
            $yoast = YoastSEO();
            
            // Company name
            if (method_exists($yoast->helpers->options, 'get')) {
                $data['company_name'] = $yoast->helpers->options->get('company_name', '');
                $data['company_logo'] = $yoast->helpers->options->get('company_logo', '');
            }
        }
        
        // Fallback to options
        $yoast_titles = get_option('wpseo_titles', []);
        if (!empty($yoast_titles['company_name'])) {
            $data['company_name'] = $yoast_titles['company_name'];
        }
        
        // Social profiles
        $yoast_social = get_option('wpseo_social', []);
        if (!empty($yoast_social)) {
            $data['social_profiles'] = array_filter([
                'facebook' => $yoast_social['facebook_site'] ?? '',
                'twitter' => $yoast_social['twitter_site'] ?? '',
                'instagram' => $yoast_social['instagram_url'] ?? '',
                'linkedin' => $yoast_social['linkedin_url'] ?? '',
                'youtube' => $yoast_social['youtube_url'] ?? '',
                'pinterest' => $yoast_social['pinterest_url'] ?? '',
            ]);
        }
        
        // Local SEO address if available
        if (class_exists('WPSEO_Local_Core')) {
            $data['address'] = [
                'street' => get_option('wpseo_local_address', ''),
                'city' => get_option('wpseo_local_city', ''),
                'state' => get_option('wpseo_local_state', ''),
                'postal_code' => get_option('wpseo_local_zipcode', ''),
                'country' => get_option('wpseo_local_country', ''),
                'phone' => get_option('wpseo_local_phone', ''),
                'email' => get_option('wpseo_local_email', ''),
            ];
        }
        
        return $data;
    }
    
    /**
     * Get WordPress site logo URL
     */
    public function get_site_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');
        
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                return $logo_url;
            }
        }
        
        // Fallback to site icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            return wp_get_attachment_image_url($site_icon_id, 'full');
        }
        
        return '';
    }
    
    /**
     * Get site URL
     */
    public function get_site_url() {
        return get_site_url();
    }
    
    /**
     * Get site title
     */
    public function get_site_title() {
        return get_bloginfo('name');
    }
}
