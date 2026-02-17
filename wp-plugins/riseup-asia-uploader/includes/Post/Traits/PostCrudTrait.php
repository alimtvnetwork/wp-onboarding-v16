<?php
/**
 * PostCrudTrait — Post creation and update logic.
 *
 * @package RiseupAsia\Post\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Post\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\PostStatusType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\BooleanHelpers;

trait PostCrudTrait {

    public function createPost(array $data): array {
        $this->fileLogger->info('Creating post', array('title' => $data['title'] ?? ''));

        if (empty($data['title'])) {
            $this->fileLogger->warn('Post creation failed: title required');

            return array('success' => false, 'error' => 'Title is required');
        }

        if (empty($data['content'])) {
            $this->fileLogger->warn('Post creation failed: content required');

            return array('success' => false, 'error' => 'Content is required');
        }

        try {
            $postData = $this->buildPostData($data);
            $postId = wp_insert_post($postData, true);

            if (is_wp_error($postId)) {
                return $this->handlePostError(ActionType::PostCreate->value, 0, $data['title'], $postId->get_error_message());
            }

            $this->fileLogger->info('Post created', array('post_id' => $postId));
            $this->assignCategories($postId, $data['categories'] ?? array());

            $this->logger->logPostCreate($postId, array(
                'title' => $data['title'], 'slug' => get_post_field('post_name', $postId),
                'status' => $postData['post_status'], 'categories' => $data['categories'] ?? array(),
            ));

            return array('success' => true, 'post' => $this->formatPost(get_post($postId)));
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'Post creation exception');
        }
    }

    public function updatePost(int $postId, array $data): array {
        $this->fileLogger->info('Updating post', array('post_id' => $postId));

        try {
            $post = get_post($postId);
            $isPostMissing = ($post === null);
            if ($isPostMissing) {
                $this->fileLogger->warn('Post not found', array('post_id' => $postId));

                return array('success' => false, 'error' => 'Post not found');
            }

            $postData = $this->buildUpdateData($postId, $data);
            $result = wp_update_post($postData, true);

            if (is_wp_error($result)) {
                return $this->handlePostError(ActionType::PostUpdate->value, $postId, '', $result->get_error_message(), $data);
            }

            $this->assignCategories($postId, $data['categories'] ?? null);
            $this->logger->logPostUpdate($postId, $data);
            $this->fileLogger->info('Post updated', array('post_id' => $postId));

            $updatedPost = get_post($postId);

            return array('success' => true, 'post' => $this->formatPost($updatedPost, true));
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'Post update exception');
        }
    }

    private function buildPostData(array $data): array {
        $postData = array(
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_status'  => $this->validatePostStatus($data['status'] ?? PostStatusType::Draft->value),
            'post_type'    => 'post',
        );

        $hasSlug = BooleanHelpers::hasValue($data['slug'] ?? null);
        if ($hasSlug) {
            $postData['post_name'] = sanitize_title($data['slug']);
        }

        $currentUser = wp_get_current_user();
        if ($currentUser && $currentUser->ID > 0) {
            $postData['post_author'] = $currentUser->ID;
        }

        return $postData;
    }

    private function buildUpdateData(int $postId, array $data): array {
        $postData = array('ID' => $postId);
        if (isset($data['title']))   { $postData['post_title']   = sanitize_text_field($data['title']); }
        if (isset($data['content'])) { $postData['post_content'] = wp_kses_post($data['content']); }
        if (isset($data['slug']))    { $postData['post_name']    = sanitize_title($data['slug']); }
        if (isset($data['status']))  { $postData['post_status']  = $this->validatePostStatus($data['status']); }

        return $postData;
    }

    private function handlePostError(
        string $action,
        int $postId,
        string $title,
        string $errorMsg,
        array $data = array(),
    ): array {
        $this->fileLogger->error('Post operation failed', array('error' => $errorMsg));
        $hasTitle = BooleanHelpers::hasValue($title);
        $details = $hasTitle ? array('title' => $title) : $data;
        $this->logger->logPostAction($action, $postId, StatusType::Failed->value, $details, $errorMsg);

        return array('success' => false, 'error' => $errorMsg);
    }

    private function assignCategories(int $postId, ?array $categories): void {
        $hasCategories = BooleanHelpers::hasValue($categories) && is_array($categories);
        if ($hasCategories) {
            wp_set_post_categories($postId, array_map('intval', $categories));
        }
    }

    private function formatPost(object $post, bool $isUpdate = false): array {
        $result = array(
            'id' => $post->ID, 'title' => $post->post_title,
            'slug' => $post->post_name, 'status' => $post->post_status,
            'permalink' => get_permalink($post->ID),
        );
        $result[$isUpdate ? 'updated_at' : 'created_at'] = ($isUpdate ? $post->post_modified_gmt : $post->post_date_gmt) . 'Z';

        return $result;
    }
}
