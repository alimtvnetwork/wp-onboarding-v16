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

use RiseupAsia\ErrorHandling\ErrorResponse;

trait PostQueryTrait {

    public function listPosts(array $params = array()): array {
        $this->fileLogger->debug('Listing posts', $params);

        try {
            $args = array(
                'post_type'      => 'post',
                'posts_per_page' => min((int) ($params['limit'] ?? DEFAULT_LIMIT), MAX_LIMIT),
                'offset'         => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            if (!empty($params['status'])) {
                $args['post_status'] = $this->validatePostStatus($params['status']);
            } else {
                $args['post_status'] = array('publish', 'draft', 'pending');
            }

            if (!empty($params['search'])) {
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

            return array(
                'success' => true, 'total' => $query->found_posts,
                'limit' => $args['posts_per_page'], 'offset' => $args['offset'],
                'posts' => $posts,
            );
        } catch (\Throwable $e) {
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'List posts exception');
        }
    }
}
