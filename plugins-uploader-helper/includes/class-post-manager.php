<?php
/**
 * Rise Up Asia - Post Manager
 *
 * Handles blog post and category creation.
 *
 * @package RiseUpAsia
 * @since   1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseUp_Post_Manager
 *
 * Provides methods for creating and updating posts and categories.
 */
class RiseUp_Post_Manager {

    /**
     * Logger instance.
     *
     * @var RiseUp_Logger
     */
    private $logger;

    /**
     * Singleton instance.
     *
     * @var RiseUp_Post_Manager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseUp_Post_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->logger = RiseUp_Logger::get_instance();
    }

    /**
     * Create a new post.
     *
     * @param array $data Post data: title, slug, content, status, categories.
     *
     * @return array Result with success status and post data or error.
     */
    public function create_post($data) {
        // Validate required fields.
        if (empty($data['title'])) {
            return array(
                'success' => false,
                'error'   => 'Title is required',
            );
        }

        if (empty($data['content'])) {
            return array(
                'success' => false,
                'error'   => 'Content is required',
            );
        }

        // Prepare post data.
        $post_data = array(
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_status'  => $this->validate_post_status($data['status'] ?? RISEUP_POST_STATUS_DRAFT),
            'post_type'    => 'post',
        );

        // Set slug if provided.
        if (!empty($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }

        // Set author to current user.
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            $post_data['post_author'] = $current_user->ID;
        }

        // Insert the post.
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            $this->logger->log_post_action(
                RISEUP_ACTION_POST_CREATE,
                0,
                RISEUP_STATUS_FAILED,
                array('title' => $data['title']),
                $post_id->get_error_message()
            );
            return array(
                'success' => false,
                'error'   => $post_id->get_error_message(),
            );
        }

        // Assign categories if provided.
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $category_ids = array_map('intval', $data['categories']);
            wp_set_post_categories($post_id, $category_ids);
        }

        // Log success.
        $this->logger->log_post_create($post_id, array(
            'title'      => $data['title'],
            'slug'       => get_post_field('post_name', $post_id),
            'status'     => $post_data['post_status'],
            'categories' => $data['categories'] ?? array(),
        ));

        // Return post data.
        $post = get_post($post_id);
        return array(
            'success' => true,
            'post'    => array(
                'id'         => $post_id,
                'title'      => $post->post_title,
                'slug'       => $post->post_name,
                'status'     => $post->post_status,
                'permalink'  => get_permalink($post_id),
                'created_at' => $post->post_date_gmt . 'Z',
            ),
        );
    }

    /**
     * Update an existing post.
     *
     * @param int   $post_id Post ID.
     * @param array $data    Post data to update.
     *
     * @return array Result with success status.
     */
    public function update_post($post_id, $data) {
        $post = get_post($post_id);

        if (!$post) {
            return array(
                'success' => false,
                'error'   => 'Post not found',
            );
        }

        $post_data = array('ID' => $post_id);

        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['content'])) {
            $post_data['post_content'] = wp_kses_post($data['content']);
        }

        if (isset($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }

        if (isset($data['status'])) {
            $post_data['post_status'] = $this->validate_post_status($data['status']);
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            $this->logger->log_post_action(
                RISEUP_ACTION_POST_UPDATE,
                $post_id,
                RISEUP_STATUS_FAILED,
                $data,
                $result->get_error_message()
            );
            return array(
                'success' => false,
                'error'   => $result->get_error_message(),
            );
        }

        // Update categories if provided.
        if (isset($data['categories']) && is_array($data['categories'])) {
            $category_ids = array_map('intval', $data['categories']);
            wp_set_post_categories($post_id, $category_ids);
        }

        // Log success.
        $this->logger->log_post_update($post_id, $data);

        // Return updated post data.
        $updated_post = get_post($post_id);
        return array(
            'success' => true,
            'post'    => array(
                'id'         => $post_id,
                'title'      => $updated_post->post_title,
                'slug'       => $updated_post->post_name,
                'status'     => $updated_post->post_status,
                'permalink'  => get_permalink($post_id),
                'updated_at' => $updated_post->post_modified_gmt . 'Z',
            ),
        );
    }

    /**
     * List posts with pagination.
     *
     * @param array $params Query parameters: status, limit, offset, search.
     *
     * @return array Posts list.
     */
    public function list_posts($params = array()) {
        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => min((int) ($params['limit'] ?? RISEUP_DEFAULT_LIMIT), RISEUP_MAX_LIMIT),
            'offset'         => max(0, (int) ($params['offset'] ?? 0)),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if (!empty($params['status'])) {
            $args['post_status'] = $this->validate_post_status($params['status']);
        } else {
            $args['post_status'] = array('publish', 'draft', 'pending');
        }

        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        $query = new WP_Query($args);
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
            'success' => true,
            'total'   => $query->found_posts,
            'limit'   => $args['posts_per_page'],
            'offset'  => $args['offset'],
            'posts'   => $posts,
        );
    }

    /**
     * Create a new category.
     *
     * @param array $data Category data: name, slug, description, parent.
     *
     * @return array Result with success status.
     */
    public function create_category($data) {
        if (empty($data['name'])) {
            return array(
                'success' => false,
                'error'   => 'Category name is required',
            );
        }

        $args = array(
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'parent'      => (int) ($data['parent'] ?? 0),
        );

        if (!empty($data['slug'])) {
            $args['slug'] = sanitize_title($data['slug']);
        }

        $result = wp_insert_term(sanitize_text_field($data['name']), 'category', $args);

        if (is_wp_error($result)) {
            $this->logger->log_post_action(
                RISEUP_ACTION_CATEGORY_CREATE,
                0,
                RISEUP_STATUS_FAILED,
                $data,
                $result->get_error_message()
            );
            return array(
                'success' => false,
                'error'   => $result->get_error_message(),
            );
        }

        // Log success.
        $this->logger->log_category_create($result['term_id'], array(
            'name' => $data['name'],
            'slug' => $args['slug'] ?? '',
        ));

        $term = get_term($result['term_id'], 'category');
        return array(
            'success'  => true,
            'category' => array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'parent'      => $term->parent,
                'count'       => $term->count,
            ),
        );
    }

    /**
     * List categories.
     *
     * @param array $params Query parameters: limit, offset, search.
     *
     * @return array Categories list.
     */
    public function list_categories($params = array()) {
        $args = array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'number'     => min((int) ($params['limit'] ?? RISEUP_DEFAULT_LIMIT), RISEUP_MAX_LIMIT),
            'offset'     => max(0, (int) ($params['offset'] ?? 0)),
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if (!empty($params['search'])) {
            $args['search'] = sanitize_text_field($params['search']);
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return array(
                'success' => false,
                'error'   => $terms->get_error_message(),
            );
        }

        $categories = array();
        foreach ($terms as $term) {
            $categories[] = array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'parent'      => $term->parent,
                'count'       => $term->count,
            );
        }

        // Get total count.
        $total = wp_count_terms(array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ));

        return array(
            'success'    => true,
            'total'      => (int) $total,
            'limit'      => $args['number'],
            'offset'     => $args['offset'],
            'categories' => $categories,
        );
    }

    /**
     * Validate post status.
     *
     * @param string $status Input status.
     *
     * @return string Valid status.
     */
    private function validate_post_status($status) {
        $valid_statuses = array(
            RISEUP_POST_STATUS_PUBLISH,
            RISEUP_POST_STATUS_DRAFT,
            RISEUP_POST_STATUS_PENDING,
        );

        return in_array($status, $valid_statuses, true) ? $status : RISEUP_POST_STATUS_DRAFT;
    }
}
