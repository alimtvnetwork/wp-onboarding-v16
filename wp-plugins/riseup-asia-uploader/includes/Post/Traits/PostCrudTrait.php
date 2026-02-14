<?php
/**
 * Post CRUD Trait — post creation and update logic.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait PostCrudTrait {

    /**
     * Create a new post.
     *
     * @param array $data Post data: title, slug, content, status, categories.
     * @return array Result with success status and post data or error.
     */
    public function createPost($data) {
        $this->file_logger->info('Creating post', array('title' => $data['title'] ?? ''));

        if (empty($data['title'])) {
            $this->file_logger->warn('Post creation failed: title required');
            return array('success' => false, 'error' => 'Title is required');
        }

        if (empty($data['content'])) {
            $this->file_logger->warn('Post creation failed: content required');
            return array('success' => false, 'error' => 'Content is required');
        }

        try {
            $post_data = $this->buildPostData($data);
            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                return $this->handlePostError(ActionType::PostCreate->value, 0, $data['title'], $post_id->get_error_message());
            }

            $this->file_logger->info('Post created', array('post_id' => $post_id));
            $this->assignCategories($post_id, $data['categories'] ?? array());

            $this->logger->log_post_create($post_id, array(
                'title' => $data['title'], 'slug' => get_post_field('post_name', $post_id),
                'status' => $post_data['post_status'], 'categories' => $data['categories'] ?? array(),
            ));

            return array('success' => true, 'post' => $this->formatPost(get_post($post_id)));
        } catch (\Throwable $e) {
            $this->file_logger->log_exception($e, 'Post creation exception');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Update an existing post.
     *
     * @param int   $post_id Post ID.
     * @param array $data    Post data to update.
     * @return array Result with success status.
     */
    public function updatePost($post_id, $data) {
        $this->file_logger->info('Updating post', array('post_id' => $post_id));

        try {
            $post = get_post($post_id);
            if (!$post) {
                $this->file_logger->warn('Post not found', array('post_id' => $post_id));
                return array('success' => false, 'error' => 'Post not found');
            }

            $post_data = $this->buildUpdateData($post_id, $data);
            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                return $this->handlePostError(ActionType::PostUpdate->value, $post_id, '', $result->get_error_message(), $data);
            }

            $this->assignCategories($post_id, $data['categories'] ?? null);
            $this->logger->log_post_update($post_id, $data);
            $this->file_logger->info('Post updated', array('post_id' => $post_id));

            $updated_post = get_post($post_id);
            return array('success' => true, 'post' => $this->formatPost($updated_post, true));
        } catch (\Throwable $e) {
            $this->file_logger->log_exception($e, 'Post update exception');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /** Build post data array for insertion. */
    private function buildPostData(array $data): array {
        $post_data = array(
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_status'  => $this->validatePostStatus($data['status'] ?? POST_STATUS_DRAFT),
            'post_type'    => 'post',
        );

        if (!empty($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }

        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            $post_data['post_author'] = $current_user->ID;
        }

        return $post_data;
    }

    /** Build update data array. */
    private function buildUpdateData(int $post_id, array $data): array {
        $post_data = array('ID' => $post_id);
        if (isset($data['title']))   { $post_data['post_title']   = sanitize_text_field($data['title']); }
        if (isset($data['content'])) { $post_data['post_content'] = wp_kses_post($data['content']); }
        if (isset($data['slug']))    { $post_data['post_name']    = sanitize_title($data['slug']); }
        if (isset($data['status']))  { $post_data['post_status']  = $this->validatePostStatus($data['status']); }
        return $post_data;
    }

    /** Handle post operation error with audit logging. */
    private function handlePostError(string $action, int $post_id, string $title, string $error_msg, array $data = array()): array {
        $this->file_logger->error('Post operation failed', array('error' => $error_msg));
        $details = !empty($title) ? array('title' => $title) : $data;
        $this->logger->log_post_action($action, $post_id, STATUS_FAILED, $details, $error_msg);
        return array('success' => false, 'error' => $error_msg);
    }

    /** Assign categories to a post if provided. */
    private function assignCategories(int $post_id, $categories) {
        if (!empty($categories) && is_array($categories)) {
            wp_set_post_categories($post_id, array_map('intval', $categories));
        }
    }

    /** Format a post for API response. */
    private function formatPost($post, bool $isUpdate = false): array {
        $result = array(
            'id' => $post->ID, 'title' => $post->post_title,
            'slug' => $post->post_name, 'status' => $post->post_status,
            'permalink' => get_permalink($post->ID),
        );
        $result[$isUpdate ? 'updated_at' : 'created_at'] = ($isUpdate ? $post->post_modified_gmt : $post->post_date_gmt) . 'Z';
        return $result;
    }
}
