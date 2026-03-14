<?php
/**
 * UserExportCsvTrait — GET /users/export handler.
 *
 * Exports users as CSV with hashed passwords (never plaintext).
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;
use RiseupAsia\Enums\UserMetaKeyType;

trait UserExportCsvTrait {

    /**
     * Handle GET /users/export — export all users as CSV.
     */
    public function handleExportUsers(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'GET /users/export'));

        return $this->safeExecute(function () use ($request) {
            $role = $request->get_param('role') ?: '';

            $queryArgs = array(
                'number'  => -1,
                'orderby' => 'ID',
                'order'   => 'ASC',
            );

            $hasRole = !empty($role);

            if ($hasRole) {
                $queryArgs['role'] = sanitize_text_field($role);
            }

            $userQuery = new WP_User_Query($queryArgs);
            $users = $userQuery->get_results();

            $isYoastActive = $this->isYoastActive();
            $headers = $this->buildCsvHeaders($isYoastActive);

            $output = fopen('php://temp', 'r+');
            fputcsv($output, $headers);

            foreach ($users as $user) {
                $row = $this->buildCsvRow($user, $isYoastActive);
                fputcsv($output, $row);
            }

            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            $this->fileLogger->info('Users exported as CSV', array(
                'count' => count($users),
                'by'    => wp_get_current_user()->user_login,
            ));

            $response = new WP_REST_Response($csvContent, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('Content-Disposition', 'attachment; filename="users-export.csv"');

            return $response;
        }, 'handleExportUsers');
    }

    /**
     * Build CSV header row.
     *
     * @return string[]
     */
    private function buildCsvHeaders(bool $includeYoast): array
    {
        $headers = array(
            'Id', 'Username', 'Email', 'PasswordHash',
            'FirstName', 'LastName', 'DisplayName', 'Nickname',
            'Website', 'Bio', 'Role', 'RegisteredAt',
        );

        foreach (UserMetaKeyType::socialCases() as $meta) {
            $headers[] = 'Social.' . $meta->jsonKey();
        }

        if ($includeYoast) {
            foreach (UserMetaKeyType::yoastCases() as $meta) {
                $headers[] = 'Yoast.' . $meta->jsonKey();
            }
        }

        return $headers;
    }

    /**
     * Build CSV data row for a user.
     *
     * @return string[]
     */
    private function buildCsvRow(\WP_User $user, bool $includeYoast): array
    {
        $roles = $user->roles;
        $primaryRole = !empty($roles) ? reset($roles) : 'subscriber';

        $row = array(
            $user->ID,
            $user->user_login,
            $user->user_email,
            $user->user_pass,
            get_user_meta($user->ID, 'first_name', true) ?: '',
            get_user_meta($user->ID, 'last_name', true) ?: '',
            $user->display_name,
            get_user_meta($user->ID, 'nickname', true) ?: '',
            $user->user_url,
            get_user_meta($user->ID, 'description', true) ?: '',
            $primaryRole,
            $user->user_registered,
        );

        foreach (UserMetaKeyType::socialCases() as $meta) {
            $value = get_user_meta($user->ID, $meta->value, true);
            $row[] = is_string($value) ? $value : '';
        }

        if ($includeYoast) {
            foreach (UserMetaKeyType::yoastCases() as $meta) {
                $value = get_user_meta($user->ID, $meta->value, true);
                $row[] = is_string($value) ? $value : '';
            }
        }

        return $row;
    }
}
