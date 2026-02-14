<?php
/**
 * Category Trait — category creation and listing.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait CategoryTrait {

    /**
     * Create a new category.
     *
     * @param array $data Category data: name, slug, description, parent.
     * @return array Result with success status.
     */
    public function createCategory($data) {
        $this->file_logger->info('Creating category', array('name' => $data['name'] ?? ''));

        if (empty($data['name'])) {
            $this->file_logger->warn('Category creation failed: name required');
            return array('success' => false, 'error' => 'Category name is required');
        }

        try {
            $args = array(
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'parent'      => (int) ($data['parent'] ?? 0),
            );
            if (!empty($data['slug'])) {
                $args['slug'] = sanitize_title($data['slug']);
            }

            $result = wp_insert_term(sanitize_text_field($data['name']), 'category', $args);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $this->file_logger->error('Category creation failed', array('error' => $error_msg));
                $this->logger->log_post_action(ActionType::CategoryCreate->value, 0, STATUS_FAILED, $data, $error_msg);
                return array('success' => false, 'error' => $error_msg);
            }

            $this->logger->log_category_create($result['term_id'], array(
                'name' => $data['name'], 'slug' => $args['slug'] ?? '',
            ));

            $this->file_logger->info('Category created', array('term_id' => $result['term_id']));
            return array('success' => true, 'category' => $this->formatCategory(get_term($result['term_id'], 'category')));
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->file_logger, $e, 'Category creation exception');
        }
    }

    /**
     * List categories.
     *
     * @param array $params Query parameters: limit, offset, search.
     * @return array Categories list.
     */
    public function listCategories($params = array()) {
        $this->file_logger->debug('Listing categories', $params);

        try {
            $args = array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
                'number'     => min((int) ($params['limit'] ?? DEFAULT_LIMIT), MAX_LIMIT),
                'offset'     => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'    => 'name',
                'order'      => 'ASC',
            );
            if (!empty($params['search'])) {
                $args['search'] = sanitize_text_field($params['search']);
            }

            $terms = get_terms($args);
            if (is_wp_error($terms)) {
                $this->file_logger->error('List categories failed', array('error' => $terms->get_error_message()));
                return array('success' => false, 'error' => $terms->get_error_message());
            }

            $categories = array_map(array($this, 'formatCategory'), $terms);
            $total = wp_count_terms(array('taxonomy' => 'category', 'hide_empty' => false));

            return array(
                'success' => true, 'total' => (int) $total,
                'limit' => $args['number'], 'offset' => $args['offset'],
                'categories' => $categories,
            );
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->file_logger, $e, 'List categories exception');
        }
    }

    /** Format a category term for API response. */
    private function formatCategory($term): array {
        return array(
            'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug,
            'description' => $term->description, 'parent' => $term->parent, 'count' => $term->count,
        );
    }
}
