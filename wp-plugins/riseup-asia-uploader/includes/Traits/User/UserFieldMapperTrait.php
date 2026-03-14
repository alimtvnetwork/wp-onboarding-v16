<?php
/**
 * UserFieldMapperTrait — Maps WP_User to JSON response structure.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use WP_User;

trait UserFieldMapperTrait {

    /**
     * Build full user response array (for single GET).
     */
    private function mapUserToResponse(WP_User $user): array
    {
        $roles = $user->roles;
        $primaryRole = !empty($roles) ? reset($roles) : 'subscriber';

        $response = array(
            'Id'           => $user->ID,
            'Username'     => $user->user_login,
            'Email'        => $user->user_email,
            'FirstName'    => get_user_meta($user->ID, 'first_name', true) ?: '',
            'LastName'     => get_user_meta($user->ID, 'last_name', true) ?: '',
            'DisplayName'  => $user->display_name,
            'Nickname'     => get_user_meta($user->ID, 'nickname', true) ?: '',
            'Website'      => $user->user_url,
            'Bio'          => get_user_meta($user->ID, 'description', true) ?: '',
            'Role'         => $primaryRole,
            'RegisteredAt' => $user->user_registered,
            'Social'       => $this->readSocialMeta($user->ID),
        );

        $yoast = $this->readYoastMeta($user->ID);
        $hasYoast = ($yoast !== null);

        if ($hasYoast) {
            $response['Yoast'] = $yoast;
        }

        return $response;
    }

    /**
     * Build summary user response array (for list).
     */
    private function mapUserToSummary(WP_User $user): array
    {
        $roles = $user->roles;
        $primaryRole = !empty($roles) ? reset($roles) : 'subscriber';

        return array(
            'Id'           => $user->ID,
            'Username'     => $user->user_login,
            'Email'        => $user->user_email,
            'DisplayName'  => $user->display_name,
            'Role'         => $primaryRole,
            'RegisteredAt' => $user->user_registered,
        );
    }
}
