<?php
/**
 * Business Profile Page - Form
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}

$business_types = CG_Constants::get_business_types();
$price_ranges = CG_Constants::get_price_ranges();
?>

<form id="cg-business-profile-form">
    <div class="cg-profile-grid">
        <!-- Basic Info -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Basic Information', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-business-name"><?php _e('Business Name', 'category-generator'); ?></label>
                <input type="text" id="bp-business-name" name="business_name" value="<?php echo esc_attr($business_profile['business_name'] ?? ''); ?>" placeholder="Atto Property">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-business-type"><?php _e('Business Type (Schema.org)', 'category-generator'); ?></label>
                <select id="bp-business-type" name="business_type">
                    <?php
                    $current = $business_profile['business_type'] ?? CG_Constants::DEFAULT_BUSINESS_TYPE;
                    foreach ($business_types as $value => $label):
                    ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-website"><?php _e('Website URL', 'category-generator'); ?></label>
                <input type="url" id="bp-website" name="website" value="<?php echo esc_attr($business_profile['website'] ?? ''); ?>" placeholder="https://example.com">
            </div>
        </div>
        
        <!-- Contact Info -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Contact Information', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-phone"><?php _e('Phone Number', 'category-generator'); ?></label>
                <input type="tel" id="bp-phone" name="phone" value="<?php echo esc_attr($business_profile['phone'] ?? ''); ?>" placeholder="+61 3 1234 5678">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-email"><?php _e('Email Address', 'category-generator'); ?></label>
                <input type="email" id="bp-email" name="email" value="<?php echo esc_attr($business_profile['email'] ?? ''); ?>" placeholder="contact@example.com">
            </div>
        </div>
        
        <!-- Address -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Address', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-street"><?php _e('Street Address', 'category-generator'); ?></label>
                <input type="text" id="bp-street" name="street_address" value="<?php echo esc_attr($business_profile['street_address'] ?? ''); ?>" placeholder="123 Main Street">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_ROW); ?>">
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-city"><?php _e('City', 'category-generator'); ?></label>
                    <input type="text" id="bp-city" name="city" value="<?php echo esc_attr($business_profile['city'] ?? ''); ?>" placeholder="Melbourne">
                </div>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-state"><?php _e('State/Province', 'category-generator'); ?></label>
                    <input type="text" id="bp-state" name="state" value="<?php echo esc_attr($business_profile['state'] ?? ''); ?>" placeholder="VIC">
                </div>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_ROW); ?>">
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-postal"><?php _e('Postal Code', 'category-generator'); ?></label>
                    <input type="text" id="bp-postal" name="postal_code" value="<?php echo esc_attr($business_profile['postal_code'] ?? ''); ?>" placeholder="3000">
                </div>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-country"><?php _e('Country', 'category-generator'); ?></label>
                    <input type="text" id="bp-country" name="country" value="<?php echo esc_attr($business_profile['country'] ?? CG_Constants::DEFAULT_COUNTRY); ?>" placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_COUNTRY); ?>">
                </div>
            </div>
        </div>
        
        <!-- Business Details -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Business Details', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-hours"><?php _e('Opening Hours', 'category-generator'); ?></label>
                <textarea id="bp-hours" name="opening_hours" rows="3" placeholder='"Mo-Fr 08:00-17:00", "Sa 09:00-13:00"'><?php echo esc_textarea($business_profile['opening_hours'] ?? ''); ?></textarea>
                <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Use Schema.org format, one per line in quotes', 'category-generator'); ?></span>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-price-range"><?php _e('Price Range', 'category-generator'); ?></label>
                <select id="bp-price-range" name="price_range">
                    <?php foreach ($price_ranges as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($business_profile['price_range'] ?? CG_Constants::DEFAULT_PRICE_RANGE, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-service-areas"><?php _e('Service Areas', 'category-generator'); ?></label>
                <textarea id="bp-service-areas" name="service_areas" rows="3" placeholder="Melbourne, Sydney, Brisbane"><?php echo esc_textarea($business_profile['service_areas'] ?? ''); ?></textarea>
                <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Comma-separated list of areas you serve', 'category-generator'); ?></span>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-services"><?php _e('Services Offered', 'category-generator'); ?></label>
                <textarea id="bp-services" name="services_offered" rows="3" placeholder="Commercial Cleaning, Office Cleaning, Carpet Cleaning"><?php echo esc_textarea($business_profile['services_offered'] ?? ''); ?></textarea>
                <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Comma-separated list of your services', 'category-generator'); ?></span>
            </div>
        </div>
        
        <!-- Ratings & Reviews -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Ratings & Reviews', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_ROW); ?>">
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-rating"><?php _e('Average Rating', 'category-generator'); ?></label>
                    <input type="number" id="bp-rating" name="rating_value" 
                           step="<?php echo CG_Constants::RATING_STEP; ?>" 
                           min="<?php echo CG_Constants::RATING_MIN; ?>" 
                           max="<?php echo CG_Constants::RATING_MAX; ?>" 
                           value="<?php echo esc_attr($business_profile['rating_value'] ?? CG_Constants::DEFAULT_RATING_VALUE); ?>" 
                           placeholder="<?php echo CG_Constants::DEFAULT_RATING_VALUE; ?>">
                </div>
                
                <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                    <label for="bp-reviews"><?php _e('Number of Reviews', 'category-generator'); ?></label>
                    <input type="number" id="bp-reviews" name="rating_count" min="0" value="<?php echo esc_attr($business_profile['rating_count'] ?? CG_Constants::DEFAULT_RATING_COUNT); ?>" placeholder="<?php echo CG_Constants::DEFAULT_RATING_COUNT; ?>">
                </div>
            </div>
        </div>
        
        <!-- Media -->
        <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
            <h2><?php _e('Media', 'category-generator'); ?></h2>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-logo"><?php _e('Logo URL', 'category-generator'); ?></label>
                <input type="url" id="bp-logo" name="logo_url" value="<?php echo esc_attr($business_profile['logo_url'] ?? ''); ?>" placeholder="https://example.com/logo.png">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-image"><?php _e('Business Image URL', 'category-generator'); ?></label>
                <input type="url" id="bp-image" name="image_url" value="<?php echo esc_attr($business_profile['image_url'] ?? ''); ?>" placeholder="https://example.com/business-image.jpg">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="bp-social"><?php _e('Social Profiles', 'category-generator'); ?></label>
                <textarea id="bp-social" name="social_profiles" rows="3" placeholder="https://facebook.com/yourbusiness
https://linkedin.com/company/yourbusiness"><?php echo esc_textarea($business_profile['social_profiles'] ?? ''); ?></textarea>
                <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('One URL per line', 'category-generator'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="cg-actions" style="margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px;">
        <button type="submit" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?> button-hero">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Save Business Profile', 'category-generator'); ?>
        </button>
    </div>
</form>
