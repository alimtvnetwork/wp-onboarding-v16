<?php
/**
 * CategoryTrait — Category creation and listing.
 *
 * @package RiseupAsia\Post\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Post\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\StatusType;
use Throwable;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\BooleanHelpers;

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
            $hasSlug = BooleanHelpers::hasValue($data['slug'] ?? null);
            if ($hasSlug) {
                $args['slug'] = sanitize_title($data['slug']);
            }

            $result = wp_insert_term(sanitize_text_field($data['name']), 'category', $args);

            if (is_wp_error($result)) {
                $errorMsg = $result->get_error_message();
                $this->fileLogger->error('Category creation failed', array('error' => $errorMsg));
                $this->logger->logPostAction(ActionType::CategoryCreate->value, 0, StatusType::Failed->value, $data, $errorMsg);
                return array('success' => false, 'error' => $errorMsg);
            }

            $this->logger->logCategoryCreate($result['term_id'], array(
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
                'number'     => min((int) ($params['limit'] ?? PaginationConfigType::DefaultLimit->value), PaginationConfigType::MaxLimit->value),
                'offset'     => max(0, (int) ($params['offset'] ?? 0)),
                'orderby'    => 'name',
                'order'      => 'ASC',
            );
            $hasSearch = BooleanHelpers::hasValue($params['search'] ?? null);
            if ($hasSearch) {
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
