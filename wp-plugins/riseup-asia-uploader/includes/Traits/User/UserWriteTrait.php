<?php
/**
 * UserWriteTrait — POST /users and PUT /users/{id} handlers.
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
use RiseupAsia\Enums\UserRoleType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserWriteTrait {

    /**
     * Handle POST /users — create a new user.
     */
    public function handleCreateUser(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'POST /users'));

        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();

            $username = sanitize_user($body['Username'] ?? '');
            $email    = sanitize_email($body['Email'] ?? '');
            $password = $body['Password'] ?? '';

            $isMissingRequired = empty($username) || empty($email) || empty($password);

            if ($isMissingRequired) {
                return EnvelopeBuilder::error('Username, Email, and Password are required', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $role = sanitize_text_field($body['Role'] ?? 'subscriber');
            $isRoleValid = UserRoleType::isValidSlug($role);

            if (!$isRoleValid) {
                return EnvelopeBuilder::error('Invalid role: ' . $role, 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $userdata = array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'display_name' => sanitize_text_field($body['DisplayName'] ?? $username),
                'first_name'   => sanitize_text_field($body['FirstName'] ?? ''),
                'last_name'    => sanitize_text_field($body['LastName'] ?? ''),
                'nickname'     => sanitize_text_field($body['Nickname'] ?? ''),
                'user_url'     => esc_url_raw($body['Website'] ?? ''),
                'description'  => sanitize_textarea_field($body['Bio'] ?? ''),
                'role'         => $role,
            );

            $newUserId = wp_insert_user($userdata);
            $isError = is_wp_error($newUserId);

            if ($isError) {
                $errorMessage = $newUserId->get_error_message();
                $this->fileLogger->error('User creation failed', array(
                    'username' => $username,
                    'error'    => $errorMessage,
                ));

                return EnvelopeBuilder::error('User creation failed: ' . $errorMessage, 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            // Write social meta
            $hasSocial = isset($body['Social']) && is_array($body['Social']);

            if ($hasSocial) {
                $this->writeSocialMeta($newUserId, $body['Social']);
            }

            // Write Yoast meta
            $hasYoast = isset($body['Yoast']) && is_array($body['Yoast']);

            if ($hasYoast) {
                $this->writeYoastMeta($newUserId, $body['Yoast']);
            }

            $result = array(
                'Id'       => $newUserId,
                'Username' => $username,
                'Email'    => $email,
                'Role'     => $role,
            );

            // Create app password if requested
            $shouldCreateAppPass = !empty($body['CreateAppPassword']);

            if ($shouldCreateAppPass) {
                $appPassName = sanitize_text_field($body['AppPasswordName'] ?? 'API Access');
                $appPassResult = WP_Application_Passwords::create_new_application_password(
                    $newUserId,
                    array('name' => $appPassName),
                );

                $isAppPassCreated = !is_wp_error($appPassResult);

                if ($isAppPassCreated) {
                    $result['AppPassword'] = $appPassResult[0];
                }
            }

            $this->fileLogger->info('User created', array(
                'userId'   => $newUserId,
                'username' => $username,
                'role'     => $role,
                'by'       => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('User created', 201)
                ->setSingleResult($result)
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleCreateUser');
    }

    /**
     * Handle PUT /users/{id} — update user fields (partial update).
     */
    public function handleUpdateUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'PUT /users/{id}', 'userId' => $userId));

        return $this->safeExecute(function () use ($request, $userId) {
            $user = get_userdata($userId);
            $isUserFound = ($user !== false);

            if (!$isUserFound) {
                return EnvelopeBuilder::error('User not found', 404)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $body = $request->get_json_params();
            $modified = array();
            $userdata = array('ID' => $userId);

            // Core fields mapping
            $coreFieldMap = array(
                'Email'       => 'user_email',
                'DisplayName' => 'display_name',
            );

            foreach ($coreFieldMap as $jsonKey => $wpKey) {
                $isProvided = isset($body[$jsonKey]);

                if ($isProvided) {
                    $userdata[$wpKey] = sanitize_text_field($body[$jsonKey]);
                    $modified[] = $jsonKey;
                }
            }

            // Website needs esc_url_raw, not sanitize_text_field
            $hasWebsite = isset($body['Website']);

            if ($hasWebsite) {
                $userdata['user_url'] = esc_url_raw($body['Website']);
                $modified[] = 'Website';
            }

            // Meta fields mapping
            $metaFieldMap = array(
                'FirstName' => 'first_name',
                'LastName'  => 'last_name',
                'Nickname'  => 'nickname',
            );

            foreach ($metaFieldMap as $jsonKey => $metaKey) {
                $isProvided = isset($body[$jsonKey]);

                if ($isProvided) {
                    update_user_meta($userId, $metaKey, sanitize_text_field($body[$jsonKey]));
                    $modified[] = $jsonKey;
                }
            }

            // Bio needs sanitize_textarea_field for multi-line content
            $hasBio = isset($body['Bio']);

            if ($hasBio) {
                update_user_meta($userId, 'description', sanitize_textarea_field($body['Bio']));
                $modified[] = 'Bio';
            }

            // Password
            $hasPassword = isset($body['Password']) && !empty($body['Password']);

            if ($hasPassword) {
                $userdata['user_pass'] = $body['Password'];
                $modified[] = 'Password';
            }

            // Role
            $hasRole = isset($body['Role']);

            if ($hasRole) {
                $role = sanitize_text_field($body['Role']);
                $isRoleValid = UserRoleType::isValidSlug($role);

                if ($isRoleValid) {
                    $user->set_role($role);
                    $modified[] = 'Role';
                }
            }

            // Update core user data if any core fields changed
            $hasCoreChanges = count($userdata) > 1;

            if ($hasCoreChanges) {
                $updateResult = wp_update_user($userdata);
                $isUpdateError = is_wp_error($updateResult);

                if ($isUpdateError) {
                    $this->fileLogger->error('User update failed', array(
                        'userId' => $userId,
                        'error'  => $updateResult->get_error_message(),
                    ));

                    return EnvelopeBuilder::error('Update failed: ' . $updateResult->get_error_message(), 400)
                        ->autoDetectRequestedAt()
                        ->setDelegatedAt(home_url())
                        ->toResponse();
                }
            }

            // Social meta
            $hasSocial = isset($body['Social']) && is_array($body['Social']);

            if ($hasSocial) {
                $socialModified = $this->writeSocialMeta($userId, $body['Social']);
                $modified = array_merge($modified, $socialModified);
            }

            // Yoast meta
            $hasYoast = isset($body['Yoast']) && is_array($body['Yoast']);

            if ($hasYoast) {
                $yoastModified = $this->writeYoastMeta($userId, $body['Yoast']);
                $modified = array_merge($modified, $yoastModified);
            }

            $this->fileLogger->info('User updated', array(
                'userId'         => $userId,
                'fieldsModified' => $modified,
                'by'             => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('User updated')
                ->setSingleResult(array(
                    'Id'             => $userId,
                    'Updated'        => true,
                    'FieldsModified' => $modified,
                ))
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleUpdateUser');
    }
}
