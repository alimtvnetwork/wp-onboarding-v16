<?php
/**
 * License Settings admin page template.
 *
 * Variables available from AdminLicensePageTrait::renderLicensePage():
 *   $licenseKey, $licenseStatus, $checkedAt, $status, $validation
 *
 * @package RiseupAsia\Admin
 * @since   2.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$hasKey        = !empty($licenseKey);
$isActive      = ($licenseStatus === 'active');
$maskedKey     = $hasKey ? substr($licenseKey, 0, 10) . str_repeat('•', max(0, strlen($licenseKey) - 14)) . substr($licenseKey, -4) : '';
$activations   = $status['activations'] ?? [];
$maxAct        = $validation['maxActivations'] ?? '—';
$currentAct    = $validation['activations'] ?? count($activations);
$product       = $validation['product'] ?? '—';
$licenseType   = $validation['type'] ?? '—';
$domain        = wp_parse_url(get_site_url(), PHP_URL_HOST) ?? 'unknown';
$domainActive  = false;

foreach ($activations as $act) {
    if (($act['domain'] ?? '') === $domain) {
        $domainActive = true;
        break;
    }
}

$statusColor = match ($licenseStatus) {
    'active'    => '#00a32a',
    'expired'   => '#d63638',
    'suspended' => '#dba617',
    'revoked'   => '#d63638',
    default     => '#787c82',
};

$nonce = wp_create_nonce('riseup_license_nonce');
?>

<div class="wrap">
    <h1><?php esc_html_e('License Settings', 'riseup-asia-uploader'); ?></h1>

    <div id="riseup-license-notice" style="display:none;" class="notice is-dismissible"><p></p></div>

    <!-- License Key Input -->
    <div class="card" style="max-width:720px; margin-top:20px; padding:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('License Key', 'riseup-asia-uploader'); ?></h2>

        <?php if ($hasKey): ?>
            <p>
                <strong><?php esc_html_e('Current Key:', 'riseup-asia-uploader'); ?></strong>
                <code style="font-size:14px;"><?php echo esc_html($maskedKey); ?></code>
            </p>
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="riseup-license-key"><?php echo $hasKey ? esc_html__('Replace Key', 'riseup-asia-uploader') : esc_html__('Enter Key', 'riseup-asia-uploader'); ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="riseup-license-key"
                        class="regular-text"
                        placeholder="RISEUP-XXXX-XXXX-XXXX-XXXX"
                        autocomplete="off"
                    />
                    <button type="button" id="riseup-license-save" class="button button-primary">
                        <?php esc_html_e('Save & Validate', 'riseup-asia-uploader'); ?>
                    </button>
                    <?php if ($hasKey): ?>
                        <button type="button" id="riseup-license-remove" class="button" style="color:#d63638; border-color:#d63638;">
                            <?php esc_html_e('Remove Key', 'riseup-asia-uploader'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($hasKey): ?>
    <!-- Status Display -->
    <div class="card" style="max-width:720px; margin-top:20px; padding:20px;">
        <h2 style="margin-top:0;">
            <?php esc_html_e('License Status', 'riseup-asia-uploader'); ?>
            <span style="display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; color:#fff; background:<?php echo esc_attr($statusColor); ?>; vertical-align:middle; margin-left:8px;">
                <?php echo esc_html(strtoupper($licenseStatus ?: 'unknown')); ?>
            </span>
        </h2>

        <table class="widefat striped" style="max-width:500px;">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Product', 'riseup-asia-uploader'); ?></strong></td>
                    <td><?php echo esc_html($product); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('License Type', 'riseup-asia-uploader'); ?></strong></td>
                    <td><?php echo esc_html($licenseType); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Activations', 'riseup-asia-uploader'); ?></strong></td>
                    <td><?php echo esc_html($currentAct . ' / ' . $maxAct); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('This Domain', 'riseup-asia-uploader'); ?></strong></td>
                    <td>
                        <code><?php echo esc_html($domain); ?></code>
                        <?php if ($domainActive): ?>
                            <span style="color:#00a32a; margin-left:6px;">✓ <?php esc_html_e('Activated', 'riseup-asia-uploader'); ?></span>
                        <?php else: ?>
                            <span style="color:#787c82; margin-left:6px;">— <?php esc_html_e('Not activated', 'riseup-asia-uploader'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($checkedAt)): ?>
                <tr>
                    <td><strong><?php esc_html_e('Last Checked', 'riseup-asia-uploader'); ?></strong></td>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', (int) $checkedAt)); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top:16px;">
            <?php if ($domainActive): ?>
                <button type="button" id="riseup-license-deactivate" class="button">
                    <?php esc_html_e('Deactivate on This Site', 'riseup-asia-uploader'); ?>
                </button>
            <?php else: ?>
                <button type="button" id="riseup-license-activate" class="button button-primary">
                    <?php esc_html_e('Activate on This Site', 'riseup-asia-uploader'); ?>
                </button>
            <?php endif; ?>
            <button type="button" id="riseup-license-refresh" class="button" style="margin-left:8px;">
                <?php esc_html_e('Refresh Status', 'riseup-asia-uploader'); ?>
            </button>
        </p>
    </div>

    <!-- Activations List -->
    <?php if (!empty($activations)): ?>
    <div class="card" style="max-width:720px; margin-top:20px; padding:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Active Domains', 'riseup-asia-uploader'); ?></h2>
        <table class="widefat striped" style="max-width:500px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Domain', 'riseup-asia-uploader'); ?></th>
                    <th><?php esc_html_e('Activated', 'riseup-asia-uploader'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activations as $activation): ?>
                <tr>
                    <td>
                        <code><?php echo esc_html($activation['domain'] ?? '—'); ?></code>
                        <?php if (($activation['domain'] ?? '') === $domain): ?>
                            <strong style="color:#00a32a; margin-left:4px;">(this site)</strong>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($activation['activatedAt'] ?? $activation['created_at'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function($) {
    var nonce = <?php echo wp_json_encode($nonce); ?>;

    function showNotice(msg, type) {
        var $n = $('#riseup-license-notice');
        $n.removeClass('notice-success notice-error notice-warning')
          .addClass('notice-' + type)
          .find('p').text(msg);
        $n.show();
        $('html, body').animate({scrollTop: 0}, 200);
    }

    function licenseAjax(action, data, btn) {
        var $btn = $(btn);
        var origText = $btn.text();
        $btn.prop('disabled', true).text('Working…');

        $.post(ajaxurl, $.extend({action: action, _nonce: nonce}, data))
            .done(function(resp) {
                if (resp.success) {
                    showNotice(resp.data.message, 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    showNotice(resp.data.message || 'An error occurred.', 'error');
                    $btn.prop('disabled', false).text(origText);
                }
            })
            .fail(function() {
                showNotice('Request failed. Please try again.', 'error');
                $btn.prop('disabled', false).text(origText);
            });
    }

    $('#riseup-license-save').on('click', function() {
        var key = $('#riseup-license-key').val().trim();
        if (!key) { showNotice('Please enter a license key.', 'warning'); return; }
        licenseAjax('riseup_license_save', {license_key: key}, this);
    });

    $('#riseup-license-activate').on('click', function() {
        licenseAjax('riseup_license_activate', {}, this);
    });

    $('#riseup-license-deactivate').on('click', function() {
        if (!confirm('Deactivate license on this site?')) return;
        licenseAjax('riseup_license_deactivate', {}, this);
    });

    $('#riseup-license-remove').on('click', function() {
        if (!confirm('Remove the license key? This will also deactivate this site.')) return;
        licenseAjax('riseup_license_remove', {}, this);
    });

    $('#riseup-license-refresh').on('click', function() {
        licenseAjax('riseup_license_refresh', {}, this);
    });
})(jQuery);
</script>
