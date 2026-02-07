<?php
/**
 * Riseup Asia Uploader - Post Manager
 *
 * Handles blog post and category creation.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Riseup_Post_Manager
 *
 * Provides methods for creating and updating posts and categories.
 */
class Riseup_Post_Manager {

    /**
     * Logger instance.
     *
     * @var Riseup_Logger
     */
    private $logger;

    /**
     * File logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $file_logger;

    /**
     * Singleton instance.
     *
     * @var Riseup_Post_Manager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_Post_Manager
     */
    public static function get_instance() {
        if (RiseupBooleanHelpers::is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = Riseup_File_Logger::get_instance();
        $this->logger = Riseup_Logger::get_instance();
        $this->file_logger->info('Post manager initialized');
    }

    /**
     * Create a new post.
     *
     * @param array $data Post data: title, slug, content, status, categories.
     *
     * @return array Result with success status and post data or error.
     */
    public function create_post($data) {
        $this->file_logger->info('Creating post', array('title' => $data['title'] ?? ''));
        
        // Validate required fields
        if (RiseupBooleanHelpers::is_empty($data['title'])) {
            $this->file_logger->warn('Post creation failed: title required');
            return array(
                'success' => false,
                'error'   => 'Title is required',
            );
        }

        if (RiseupBooleanHelpers::is_empty($data['content'])) {
            $this->file_logger->warn('Post creation failed: content required');
            return array(
                'success' => false,
                'error'   => 'Content is required',
            );
        }

        try {
            // Prepare post data
            $post_data = array(
                'post_title'   => sanitize_text_field($data['title']),
                'post_content' => wp_kses_post($data['content']),
                'post_status'  => $this->validate_post_status($data['status'] ?? RISEUP_POST_STATUS_DRAFT),
                'post_type'    => 'post',
            );

            // Set slug if provided
            if (RiseupBooleanHelpers::has_content($data['slug'])) {
                $post_data['post_name'] = sanitize_title($data['slug']);
            }

            // Set author to current user
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID > 0) {
                $post_data['post_author'] = $current_user->ID;
            }

            $this->file_logger->debug('Inserting post', $post_data);
            
            // Insert the post
            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                $error_msg = $post_id->get_error_message();
                $this->file_logger->error('Post insertion failed', array('error' => $error_msg));
                $this->logger->log_post_action(
                    RISEUP_ACTION_POST_CREATE,
                    0,
                    RISEUP_STATUS_FAILED,
                    array('title' => $data['title']),
                    $error_msg
                );
                return array(
                    'success' => false,
                    'error'   => $error_msg,
                );
            }

            $this->file_logger->info('Post created', array('post_id' => $post_id));

            // Assign categories if provided
            if (RiseupBooleanHelpers::has_content($data['categories']) && is_array($data['categories'])) {
                $category_ids = array_map('intval', $data['categories']);
                wp_set_post_categories($post_id, $category_ids);
                $this->file_logger->debug('Categories assigned', array('categories' => $category_ids));
            }

            // Log success
            $this->logger->log_post_create($post_id, array(
                'title'      => $data['title'],
                'slug'       => get_post_field('post_name', $post_id),
                'status'     => $post_data['post_status'],
                'categories' => $data['categories'] ?? array(),
            ));

            // Return post data
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
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Post creation exception');
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
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
        $this->file_logger->info('Updating post', array('post_id' => $post_id));
        
        try {
            $post = get_post($post_id);

            if (RiseupBooleanHelpers::is_falsy($post)) {
                $this->file_logger->warn('Post not found', array('post_id' => $post_id));
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

            $this->file_logger->debug('Updating post data', $post_data);
            
            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $this->file_logger->error('Post update failed', array('error' => $error_msg));
                $this->logger->log_post_action(
                    RISEUP_ACTION_POST_UPDATE,
                    $post_id,
                    RISEUP_STATUS_FAILED,
                    $data,
                    $error_msg
                );
                return array(
                    'success' => false,
                    'error'   => $error_msg,
                );
            }

            // Update categories if provided
            if (isset($data['categories']) && is_array($data['categories'])) {
                $category_ids = array_map('intval', $data['categories']);
                wp_set_post_categories($post_id, $category_ids);
            }

            // Log success
            $this->logger->log_post_update($post_id, $data);
            $this->file_logger->info('Post updated', array('post_id' => $post_id));

            // Return updated post data
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
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Post update exception');
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * List posts with pagination.
     *
     * @param array $params Query parameters: status, limit, offset, search.
     *
     * @return array Posts list.
     */
    public function list_posts($params = array()) {
        $this->file_logger->debug('Listing posts', $params);
        
        try {
            $args = array(
                'post_type'      => 'post',
                'posts_per_page' => min((int) ($params['limit'] ?? RISEUP_DEFAULT_LIMIT), RISEUP_MAX_LIMIT),
                'offset'         => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            if (RiseupBooleanHelpers::has_content($params['status'])) {
                $args['post_status'] = $this->validate_post_status($params['status']);
            } else {
                $args['post_status'] = array('publish', 'draft', 'pending');
            }

            if (RiseupBooleanHelpers::has_content($params['search'])) {
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

            $this->file_logger->debug('Posts listed', array('total' => $query->found_posts));
            
            return array(
                'success' => true,
                'total'   => $query->found_posts,
                'limit'   => $args['posts_per_page'],
                'offset'  => $args['offset'],
                'posts'   => $posts,
            );
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'List posts exception');
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * Create a new category.
     *
     * @param array $data Category data: name, slug, description, parent.
     *
     * @return array Result with success status.
     */
    public function create_category($data) {
        $this->file_logger->info('Creating category', array('name' => $data['name'] ?? ''));
        
        if (RiseupBooleanHelpers::is_empty($data['name'])) {
            $this->file_logger->warn('Category creation failed: name required');
            return array(
                'success' => false,
                'error'   => 'Category name is required',
            );
        }

        try {
            $args = array(
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'parent'      => (int) ($data['parent'] ?? 0),
            );

            if (RiseupBooleanHelpers::has_content($data['slug'])) {
                $args['slug'] = sanitize_title($data['slug']);
            }

            $result = wp_insert_term(sanitize_text_field($data['name']), 'category', $args);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $this->file_logger->error('Category creation failed', array('error' => $error_msg));
                $this->logger->log_post_action(
                    RISEUP_ACTION_CATEGORY_CREATE,
                    0,
                    RISEUP_STATUS_FAILED,
                    $data,
                    $error_msg
                );
                return array(
                    'success' => false,
                    'error'   => $error_msg,
                );
            }

            // Log success
            $this->logger->log_category_create($result['term_id'], array(
                'name' => $data['name'],
                'slug' => $args['slug'] ?? '',
            ));

            $this->file_logger->info('Category created', array('term_id' => $result['term_id']));
            
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
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Category creation exception');
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * List categories.
     *
     * @param array $params Query parameters: limit, offset, search.
     *
     * @return array Categories list.
     */
    public function list_categories($params = array()) {
        $this->file_logger->debug('Listing categories', $params);
        
        try {
            $args = array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
                'number'     => min((int) ($params['limit'] ?? RISEUP_DEFAULT_LIMIT), RISEUP_MAX_LIMIT),
                'offset'     => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'    => 'name',
                'order'      => 'ASC',
            );

            if (RiseupBooleanHelpers::has_content($params['search'])) {
                $args['search'] = sanitize_text_field($params['search']);
            }

            $terms = get_terms($args);

            if (is_wp_error($terms)) {
                $error_msg = $terms->get_error_message();
                $this->file_logger->error('List categories failed', array('error' => $error_msg));
                return array(
                    'success' => false,
                    'error'   => $error_msg,
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

            // Get total count
            $total = wp_count_terms(array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
            ));

            $this->file_logger->debug('Categories listed', array('total' => $total));
            
            return array(
                'success'    => true,
                'total'      => (int) $total,
                'limit'      => $args['number'],
                'offset'     => $args['offset'],
                'categories' => $categories,
            );
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'List categories exception');
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
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
