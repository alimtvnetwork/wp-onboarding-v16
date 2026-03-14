<?php
/**
 * UserAppPasswordTrait — App password create/revoke handlers.
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
use WP_Application_Passwords;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserAppPasswordTrait {

    /**
     * Handle POST /users/app-password — create an application password.
     */
    public function handleCreateAppPass(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'POST /users/app-password'));

        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();

            $userId = (int) ($body['UserId'] ?? 0);
            $name   = sanitize_text_field($body['Name'] ?? 'API Access');

            $isUserIdMissing = ($userId <= 0);

            if ($isUserIdMissing) {
                return EnvelopeBuilder::error('UserId is required', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $user = get_userdata($userId);
            $isUserFound = ($user !== false);

            if (!$isUserFound) {
                return EnvelopeBuilder::error('User not found', 404)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $result = WP_Application_Passwords::create_new_application_password(
                $userId,
                array('name' => $name),
            );

            $isError = is_wp_error($result);

            if ($isError) {
                $this->fileLogger->error('App password creation failed', array(
                    'userId' => $userId,
                    'error'  => $result->get_error_message(),
                ));

                return EnvelopeBuilder::error('App password creation failed: ' . $result->get_error_message(), 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $this->fileLogger->info('App password created', array(
                'userId' => $userId,
                'name'   => $name,
                'by'     => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('Application password created', 201)
                ->setSingleResult(array(
                    'UserId'   => $userId,
                    'Name'     => $name,
                    'Password' => $result[0],
                    'Uuid'     => $result[1]['uuid'],
                ))
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleCreateAppPass');
    }

    /**
     * Handle DELETE /users/app-password — revoke an application password.
     */
    public function handleRevokeAppPass(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'DELETE /users/app-password'));

        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();

            $userId = (int) ($body['UserId'] ?? 0);
            $uuid   = sanitize_text_field($body['Uuid'] ?? '');

            $isMissing = ($userId <= 0 || empty($uuid));

            if ($isMissing) {
                return EnvelopeBuilder::error('UserId and Uuid are required', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $deleted = WP_Application_Passwords::delete_application_password($userId, $uuid);
            $isError = is_wp_error($deleted);

            if ($isError) {
                $this->fileLogger->error('App password revocation failed', array(
                    'userId' => $userId,
                    'uuid'   => $uuid,
                    'error'  => $deleted->get_error_message(),
                ));

                return EnvelopeBuilder::error('Revocation failed: ' . $deleted->get_error_message(), 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $this->fileLogger->info('App password revoked', array(
                'userId' => $userId,
                'uuid'   => $uuid,
                'by'     => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('Application password revoked')
                ->setSingleResult(array('Revoked' => true))
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleRevokeAppPass');
    }
}
