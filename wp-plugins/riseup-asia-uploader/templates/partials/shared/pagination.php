<?php
/**
 * Shared Partial: Pagination
 *
 * Renders WordPress-native pagination using paginate_links().
 *
 * Required variables (from parent scope):
 *   $totalPages     — Total number of pages (int)
 *   $page           — Current page number (int)
 *
 * Optional variables:
 *   $paginationBase — Custom base URL pattern (default: add_query_arg('paged', '%#%'))
 *   $paginationArgs — Full override array for paginate_links() (ignores $paginationBase if set)
 *
 * @package RiseupAsiaUploader
 * @since   2.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($totalPages) || $totalPages <= 1) {
    return;
}
?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php
        $defaultArgs = array(
            'base'      => isset($paginationBase) ? $paginationBase : add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $totalPages,
            'current'   => $page,
        );

        $args = isset($paginationArgs) ? $paginationArgs : $defaultArgs;
        echo paginate_links($args);
        ?>
    </div>
</div>
