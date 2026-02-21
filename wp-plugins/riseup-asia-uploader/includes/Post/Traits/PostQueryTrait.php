<?php
/**
 * PostQueryTrait — Post listing with pagination.
 *
 * @package RiseupAsia\Post\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Post\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PostStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;
use Throwable;
use RiseupAsia\ErrorHandling\ErrorResponse;

trait PostQueryTrait {

    public function listPosts(array $params = array()): array {
        $this->fileLogger->debug('Listing posts', $params);

        try {
            $args = array(
                'post_type'      => 'post',
                'posts_per_page' => min((int) ($params['limit'] ?? PaginationConfigType::DefaultLimit->value), PaginationConfigType::MaxLimit->value),
                'offset'         => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            $hasStatus = BooleanHelpers::hasValue($params['status'] ?? null);
            if ($hasStatus) {
                $args['post_status'] = $this->validatePostStatus($params['status']);
            } else {
                $args['post_status'] = PostStatusType::validValues();
            }

            $hasSearch = BooleanHelpers::hasValue($params['search'] ?? null);
            if ($hasSearch) {
                $args['s'] = sanitize_text_field($params['search']);
            }

            $query = new \WP_Query($args);
            $posts = array();

            foreach ($query->posts as $post) {
                $posts[] = array(
                    'id'         => $post->ID,
                    'title'      => $post->post_title,
                    'slug'       => $post->post_name,
                    'status'     => $post->post_status,
                    'permalink'  => get_permalink($post->ID),
                    'created_at' => $post->post_date_gmt . 'Z',
                    'updated_at' => $post->post_modified_gmt . 'Z',
                );
            }

            return ResultHelper::ok(array(
                ResponseKeyType::Total->value  => $query->found_posts,
                ResponseKeyType::Limit->value  => $args['posts_per_page'],
                ResponseKeyType::Offset->value => $args['offset'],
                ResponseKeyType::Posts->value   => $posts,
            ));
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'List posts exception');
        }
    }
}
