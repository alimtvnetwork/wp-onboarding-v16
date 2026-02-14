<?php
/**
 * CategoryTrait — Category creation and listing.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait CategoryTrait {

    public function createCategory(array $data): array {
        $this->fileLogger->info('Creating category', array('name' => $data['name'] ?? ''));

        if (empty($data['name'])) {
            $this->fileLogger->warn('Category creation failed: name required');
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
                $errorMsg = $result->get_error_message();
                $this->fileLogger->error('Category creation failed', array('error' => $errorMsg));
                $this->logger->log_post_action(ActionType::CategoryCreate->value, 0, STATUS_FAILED, $data, $errorMsg);
                return array('success' => false, 'error' => $errorMsg);
            }

            $this->logger->log_category_create($result['term_id'], array(
                'name' => $data['name'], 'slug' => $args['slug'] ?? '',
            ));

            $this->fileLogger->info('Category created', array('term_id' => $result['term_id']));
            return array('success' => true, 'category' => $this->formatCategory(get_term($result['term_id'], 'category')));
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'Category creation exception');
        }
    }

    public function listCategories(array $params = array()): array {
        $this->fileLogger->debug('Listing categories', $params);

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
                $this->fileLogger->error('List categories failed', array('error' => $terms->get_error_message()));
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
            return ErrorResponse::logAndReturn($this->fileLogger, $e, 'List categories exception');
        }
    }

    private function formatCategory(object $term): array {
        return array(
            'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug,
            'description' => $term->description, 'parent' => $term->parent, 'count' => $term->count,
        );
    }
}
