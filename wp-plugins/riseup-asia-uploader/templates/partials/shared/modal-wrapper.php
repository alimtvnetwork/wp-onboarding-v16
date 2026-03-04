<?php
/**
 * Shared Partial: Modal Wrapper
 *
 * Renders a standard modal shell with overlay, content container, header, and body.
 * The caller provides body content after including this partial via the $modalBody variable,
 * OR uses the open/close pattern with output buffering.
 *
 * Usage (simple — body as variable):
 *   $modalId    = 'my-modal';
 *   $modalTitle = __('Confirm Action', $pluginSlug);
 *   $modalIcon  = 'dashicons-warning';
 *   $modalIconColor = '#d63638';
 *   $modalBody  = '<p>Are you sure?</p><button>Yes</button>';
 *   include __DIR__ . '/shared/modal-wrapper.php';
 *
 * Optional variables:
 *   $modalMaxWidth    — CSS max-width (default: '600px')
 *   $modalCloseButton — Whether to show close × button (default: true)
 *   $modalHeaderExtra — Raw HTML after title (e.g. badge)
 *   $modalFooter      — Raw HTML for footer/actions row
 *
 * @package RiseupAsiaUploader
 * @since   2.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$_modalId          = $modalId ?? 'riseup-modal';
$_modalTitle       = $modalTitle ?? '';
$_modalIcon        = $modalIcon ?? '';
$_modalIconColor   = $modalIconColor ?? '';
$_modalMaxWidth    = $modalMaxWidth ?? '600px';
$_modalCloseButton = $modalCloseButton ?? true;
$_modalHeaderExtra = $modalHeaderExtra ?? '';
$_modalBody        = $modalBody ?? '';
$_modalFooter      = $modalFooter ?? '';
?>
<div id="<?php echo esc_attr($_modalId); ?>" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content" style="max-width: <?php echo esc_attr($_modalMaxWidth); ?>;">
        <?php if ($_modalTitle || $_modalCloseButton): ?>
        <div class="riseup-modal-header">
            <div class="modal-header-left">
                <?php if ($_modalIcon): ?>
                <span class="dashicons <?php echo esc_attr($_modalIcon); ?>"<?php if ($_modalIconColor): ?> style="color: <?php echo esc_attr($_modalIconColor); ?>;"<?php endif; ?>></span>
                <?php endif; ?>
                <?php if ($_modalTitle): ?>
                <h3><?php echo esc_html($_modalTitle); ?></h3>
                <?php endif; ?>
                <?php echo $_modalHeaderExtra; ?>
            </div>
            <?php if ($_modalCloseButton): ?>
            <button type="button" class="riseup-modal-close">&times;</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="riseup-modal-body">
            <?php echo $_modalBody; ?>
        </div>
        <?php if ($_modalFooter): ?>
        <div class="riseup-modal-footer">
            <?php echo $_modalFooter; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
// Reset variables to prevent bleed into subsequent includes
unset($modalId, $modalTitle, $modalIcon, $modalIconColor, $modalMaxWidth, $modalCloseButton, $modalHeaderExtra, $modalBody, $modalFooter);
?>
