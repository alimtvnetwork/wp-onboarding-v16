<?php
/**
 * Business Profile Page - Scripts
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<script>
jQuery(document).ready(function($) {
    const NOTICE_DURATION = <?php echo CG_Constants::NOTICE_DURATION; ?>;
    
    $('#cg-business-profile-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serializeArray();
        const data = {
            action: '<?php echo esc_js(CG_Constants::AJAX_SAVE_BUSINESS_PROFILE); ?>',
            nonce: cgAdmin.nonce
        };
        
        formData.forEach(function(item) {
            data[item.name] = item.value;
        });
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const $notice = $('<div class="cg-save-notice">✓ <?php echo esc_js(__('Business Profile saved successfully!', 'category-generator')); ?></div>');
                    $('body').append($notice);
                    setTimeout(function() {
                        $notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, NOTICE_DURATION);
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error saving profile', 'category-generator')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error saving profile. Please try again.', 'category-generator')); ?>');
            }
        });
    });
});
</script>
