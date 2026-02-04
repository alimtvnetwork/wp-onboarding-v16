<?php
/**
 * Tests admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$run_tests = isset($_GET['run']) && $_GET['run'] === '1';
$results = null;
$summary = null;

if ($run_tests) {
    $results = $test_runner->run_all_tests();
    $summary = $test_runner->get_summary();
}
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Test Runner', 'plugins-onboard'); ?>
    </h1>

    <p class="description">
        <?php esc_html_e('Run unit and integration tests to verify the plugin is working correctly.', 'plugins-onboard'); ?>
    </p>

    <!-- Run Tests Button -->
    <div class="onboard-section">
        <a href="<?php echo admin_url('admin.php?page=plugins-onboard-tests&run=1'); ?>" class="button button-primary button-large">
            <span class="dashicons dashicons-controls-play"></span>
            <?php esc_html_e('Run All Tests', 'plugins-onboard'); ?>
        </a>
    </div>

    <?php if ($results) : ?>
    <!-- Test Summary -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Test Summary', 'plugins-onboard'); ?></h2>
        <div class="onboard-cards">
            <div class="onboard-card">
                <div class="card-content">
                    <h3><?php echo $summary['total']; ?></h3>
                    <p><?php esc_html_e('Total Tests', 'plugins-onboard'); ?></p>
                </div>
            </div>
            <div class="onboard-card card-success">
                <div class="card-content">
                    <h3><?php echo $summary['passed']; ?></h3>
                    <p><?php esc_html_e('Passed', 'plugins-onboard'); ?></p>
                </div>
            </div>
            <div class="onboard-card <?php echo $summary['failed'] > 0 ? 'card-error' : ''; ?>">
                <div class="card-content">
                    <h3><?php echo $summary['failed']; ?></h3>
                    <p><?php esc_html_e('Failed', 'plugins-onboard'); ?></p>
                </div>
            </div>
            <div class="onboard-card">
                <div class="card-content">
                    <h3><?php echo $summary['success_rate']; ?>%</h3>
                    <p><?php esc_html_e('Success Rate', 'plugins-onboard'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Results -->
    <?php foreach ($results as $category_key => $category) : ?>
    <div class="onboard-section">
        <h2><?php echo esc_html($category['name']); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('Test Name', 'plugins-onboard'); ?></th>
                    <th style="width: 15%;"><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                    <th style="width: 10%;"><?php esc_html_e('Assertions', 'plugins-onboard'); ?></th>
                    <th style="width: 45%;"><?php esc_html_e('Message', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($category['tests'] as $test) : ?>
                <tr class="test-<?php echo esc_attr($test['status']); ?>">
                    <td>
                        <strong><?php echo esc_html($test['name']); ?></strong>
                    </td>
                    <td>
                        <?php if ($test['status'] === 'passed') : ?>
                            <span class="status-badge status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Passed', 'plugins-onboard'); ?>
                            </span>
                        <?php elseif ($test['status'] === 'failed') : ?>
                            <span class="status-badge status-failed">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Failed', 'plugins-onboard'); ?>
                            </span>
                        <?php else : ?>
                            <span class="status-badge status-inactive">
                                <?php esc_html_e('Skipped', 'plugins-onboard'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo isset($test['assertions']) ? $test['assertions'] : '-'; ?></td>
                    <td>
                        <?php if ($test['status'] === 'failed') : ?>
                            <span class="error-message"><?php echo esc_html($test['message']); ?></span>
                        <?php else : ?>
                            <?php echo esc_html($test['message']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Test Information -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Available Test Categories', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Category', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Unit Tests', 'plugins-onboard'); ?></strong></td>
                    <td><?php esc_html_e('Tests individual components: encryption, JWT, rate limiting, UUID generation, security utilities.', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Integration Tests', 'plugins-onboard'); ?></strong></td>
                    <td><?php esc_html_e('Tests complete workflows: database operations, OAuth flow, mutation tokens, snapshots, audit logging.', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Security Tests Checklist -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Security Checklist', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <tbody>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('All tokens encrypted at rest in SQLite', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('All endpoints require valid Bearer token or mutation token', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('IP whitelist enforced before mutation token generation', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('Rate limiting prevents brute-force attacks', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('ZIP files validated before extraction', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('Path traversal prevention during ZIP extraction', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('Token reuse prevented (delete after first use)', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    <td><?php esc_html_e('CSRF protection on admin UI forms', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
