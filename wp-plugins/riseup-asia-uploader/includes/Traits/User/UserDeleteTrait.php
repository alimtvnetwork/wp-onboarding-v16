<?php
/**
 * UserDeleteTrait — DELETE /users/{id} handler.
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
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserDeleteTrait {

    /**
     * Handle DELETE /users/{id} — delete user with optional content reassignment.
     */
    public function handleDeleteUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'DELETE /users/{id}', 'userId' => $userId));

        return $this->safeExecute(function () use ($request, $userId) {
            $user = get_userdata($userId);
            $isUserFound = ($user !== false);

            if (!$isUserFound) {
                return EnvelopeBuilder::error('User not found', 404)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $isSelfDelete = ($userId === get_current_user_id());

            if ($isSelfDelete) {
                return EnvelopeBuilder::error('Cannot delete your own account', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            require_once ABSPATH . 'wp-admin/includes/user.php';

            $reassign = $request->get_param('reassign');
            $hasReassign = !empty($reassign);
            $reassignId = $hasReassign ? (int) $reassign : null;

            $deleted = wp_delete_user($userId, $reassignId);

            if (!$deleted) {
                $this->fileLogger->error('User deletion failed', array('userId' => $userId));

                return EnvelopeBuilder::error('User deletion failed', 500)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $this->fileLogger->info('User deleted', array(
                'userId'       => $userId,
                'username'     => $user->user_login,
                'reassignedTo' => $reassignId,
                'by'           => wp_get_current_user()->user_login,
            ));

            $result = array(
                'Deleted' => true,
            );

            if ($hasReassign) {
                $result['ReassignedTo'] = $reassignId;
            }

            return EnvelopeBuilder::success('User deleted')
                ->setSingleResult($result)
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleDeleteUser');
    }
}
