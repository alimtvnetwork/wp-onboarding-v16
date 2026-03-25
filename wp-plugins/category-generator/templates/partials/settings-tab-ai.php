<?php
/**
 * Settings Page - AI Providers Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-ai">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2><?php _e('AI Provider Configuration', 'category-generator'); ?></h2>
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('Configure AI providers for generating content. API keys are stored securely.', 'category-generator'); ?></p>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="ai_provider"><?php _e('Primary AI Provider', 'category-generator'); ?></label>
            <select name="ai_provider" id="ai_provider">
                <?php foreach ($ai_providers as $key => $provider): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($settings->get('ai_provider', CG_Constants::DEFAULT_AI_PROVIDER), $key); ?>>
                        <?php echo esc_html($provider['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="cg-ai-config-grid">
            <?php foreach ($ai_providers as $key => $provider): 
                $is_current = ($settings->get('ai_provider', CG_Constants::DEFAULT_AI_PROVIDER) === $key);
            ?>
            <div class="cg-ai-provider-config" id="ai-config-<?php echo esc_attr($key); ?>" style="<?php echo $is_current ? '' : 'display:none;'; ?>">
                <h3><?php echo esc_html($provider['name']); ?></h3>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label><?php _e('API Key / Token', 'category-generator'); ?></label>
                    <input type="password" name="ai_key_<?php echo esc_attr($key); ?>" 
                           value="<?php echo esc_attr($settings->get("ai_key_{$key}", '')); ?>"
                           placeholder="sk-..." autocomplete="new-password">
                </div>
                
                <?php if ($key === 'custom'): ?>
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label><?php _e('Custom API URL', 'category-generator'); ?></label>
                    <input type="url" name="custom_ai_url" 
                           value="<?php echo esc_attr($settings->get('custom_ai_url', '')); ?>"
                           placeholder="https://api.example.com/v1/chat/completions">
                </div>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label><?php _e('Custom Model Name', 'category-generator'); ?></label>
                    <input type="text" name="custom_ai_model" 
                           value="<?php echo esc_attr($settings->get('custom_ai_model', '')); ?>"
                           placeholder="custom-model-v1">
                </div>
                <?php else: ?>
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label><?php _e('Model for HTML Content', 'category-generator'); ?></label>
                    <select name="ai_html_model_<?php echo esc_attr($key); ?>">
                        <?php foreach ($provider['models'] as $model_key => $model_name): ?>
                            <option value="<?php echo esc_attr($model_key); ?>" <?php selected($settings->get("ai_html_model_{$key}", ''), $model_key); ?>>
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label><?php _e('Model for Meta Descriptions', 'category-generator'); ?></label>
                    <select name="ai_meta_model_<?php echo esc_attr($key); ?>">
                        <?php foreach ($provider['models'] as $model_key => $model_name): ?>
                            <option value="<?php echo esc_attr($model_key); ?>" <?php selected($settings->get("ai_meta_model_{$key}", ''), $model_key); ?>>
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
