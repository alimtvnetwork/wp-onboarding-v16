<?php
/**
 * Shared Partial: Page Header
 *
 * Renders the standard admin page header with dashicon, title, version badge,
 * and optional description paragraph.
 *
 * Required variables (from parent scope):
 *   $pageIcon        — Dashicons class (e.g. 'dashicons-database')
 *   $pageTitle       — Translated page title string
 *   $pluginSlug      — Plugin text domain variable
 *
 * Optional variables:
 *   $pageDescription — Translated description string (omit to skip)
 *   $headerExtra     — Raw HTML inserted after the version badge (e.g. badge count)
 *
 * @package RiseupAsiaUploader
 * @since   2.10.0
 */

use RiseupAsia\Enums\PluginConfigType;

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1>
    <span class="dashicons <?php echo esc_attr($pageIcon); ?>"></span>
    <?php echo esc_html($pageTitle); ?>
    <span class="riseup-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    <?php if (!empty($headerExtra)) { echo $headerExtra; } ?>
</h1>

<?php if (!empty($pageDescription)): ?>
<p class="description">
    <?php echo esc_html($pageDescription); ?>
</p>
<?php endif; ?>
