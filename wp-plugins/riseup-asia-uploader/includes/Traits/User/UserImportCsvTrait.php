<?php
/**
 * UserImportCsvTrait — POST /users/import handler.
 *
 * Imports users from CSV. Passwords can be plaintext (hashed on insert)
 * or pre-hashed (stored directly).
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
use RiseupAsia\Enums\UserMetaKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserImportCsvTrait {

    /**
     * Handle POST /users/import — import users from CSV.
     */
    public function handleImportUsers(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'POST /users/import'));

        return $this->safeExecute(function () use ($request) {
            $files = $request->get_file_params();
            $hasFile = isset($files['file']['tmp_name']) && !empty($files['file']['tmp_name']);

            if (!$hasFile) {
                return EnvelopeBuilder::error('CSV file is required', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $handle = fopen($files['file']['tmp_name'], 'r');
            $isFileOpened = ($handle !== false);

            if (!$isFileOpened) {
                return EnvelopeBuilder::error('Failed to read CSV file', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $headers = fgetcsv($handle);
            $headerMap = array_flip($headers);

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = array();
            $rowNum = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $result = $this->processImportRow($row, $headerMap, $rowNum);

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } elseif (is_string($result) && str_starts_with($result, 'error:')) {
                    $username = $row[$headerMap['Username'] ?? 0] ?? '';
                    $errors[] = array(
                        'Row'      => $rowNum,
                        'Username' => $username,
                        'Error'    => substr($result, 6),
                    );
                } else {
                    $skipped++;
                }
            }

            fclose($handle);

            $this->fileLogger->info('Users imported from CSV', array(
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors'  => count($errors),
                'by'      => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('Import complete')
                ->setSingleResult(array(
                    'Created' => $created,
                    'Updated' => $updated,
                    'Skipped' => $skipped,
                    'Errors'  => $errors,
                ))
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleImportUsers');
    }

    /**
     * Process a single CSV import row.
     *
     * @return string 'created', 'updated', 'skipped', or 'error:message'
     */
    private function processImportRow(array $row, array $headerMap, int $rowNum): string
    {
        $username = $row[$headerMap['Username'] ?? -1] ?? '';
        $email    = $row[$headerMap['Email'] ?? -1] ?? '';

        $isMissing = empty($username) || empty($email);

        if ($isMissing) {
            return 'error:Missing username or email';
        }

        $existingUser = get_user_by('login', $username);
        $isExisting = ($existingUser !== false);

        if (!$isExisting) {
            $existingUser = get_user_by('email', $email);
            $isExisting = ($existingUser !== false);
        }

        $passwordHash = $row[$headerMap['PasswordHash'] ?? -1] ?? '';

        if ($isExisting) {
            return $this->updateExistingFromCsv($existingUser->ID, $row, $headerMap, $passwordHash);
        }

        return $this->createFromCsv($row, $headerMap, $passwordHash);
    }

    private function createFromCsv(array $row, array $headerMap, string $passwordHash): string
    {
        $username = $row[$headerMap['Username'] ?? -1] ?? '';
        $email    = $row[$headerMap['Email'] ?? -1] ?? '';

        $password = $this->resolveImportPassword($passwordHash);

        $userdata = array(
            'user_login'   => sanitize_user($username),
            'user_email'   => sanitize_email($email),
            'user_pass'    => $password,
            'display_name' => $row[$headerMap['DisplayName'] ?? -1] ?? $username,
            'first_name'   => $row[$headerMap['FirstName'] ?? -1] ?? '',
            'last_name'    => $row[$headerMap['LastName'] ?? -1] ?? '',
            'nickname'     => $row[$headerMap['Nickname'] ?? -1] ?? '',
            'user_url'     => $row[$headerMap['Website'] ?? -1] ?? '',
            'description'  => $row[$headerMap['Bio'] ?? -1] ?? '',
            'role'         => $row[$headerMap['Role'] ?? -1] ?? 'subscriber',
        );

        $newUserId = wp_insert_user($userdata);
        $isError = is_wp_error($newUserId);

        if ($isError) {
            return 'error:' . $newUserId->get_error_message();
        }

        // If password was pre-hashed, set it directly
        $isPreHashed = $this->isPreHashedPassword($passwordHash);

        if ($isPreHashed) {
            global $wpdb;
            $wpdb->update($wpdb->users, array('user_pass' => $passwordHash), array('ID' => $newUserId));
            wp_cache_delete($newUserId, 'users');
        }

        $this->importMetaFromCsv($newUserId, $row, $headerMap);

        return 'created';
    }

    private function updateExistingFromCsv(int $userId, array $row, array $headerMap, string $passwordHash): string
    {
        $userdata = array('ID' => $userId);

        $fieldMap = array(
            'Email'       => 'user_email',
            'DisplayName' => 'display_name',
            'Website'     => 'user_url',
        );

        foreach ($fieldMap as $csvKey => $wpKey) {
            $idx = $headerMap[$csvKey] ?? -1;
            $hasValue = ($idx >= 0 && isset($row[$idx]) && $row[$idx] !== '');

            if ($hasValue) {
                $userdata[$wpKey] = $row[$idx];
            }
        }

        $hasPasswordChange = !empty($passwordHash);

        if ($hasPasswordChange) {
            $isPreHashed = $this->isPreHashedPassword($passwordHash);

            if ($isPreHashed) {
                global $wpdb;
                $wpdb->update($wpdb->users, array('user_pass' => $passwordHash), array('ID' => $userId));
                wp_cache_delete($userId, 'users');
            } else {
                $userdata['user_pass'] = $passwordHash;
            }
        }

        $hasCoreChanges = count($userdata) > 1;

        if ($hasCoreChanges) {
            $result = wp_update_user($userdata);
            $isError = is_wp_error($result);

            if ($isError) {
                return 'error:' . $result->get_error_message();
            }
        }

        $this->importMetaFromCsv($userId, $row, $headerMap);

        return 'updated';
    }

    private function importMetaFromCsv(int $userId, array $row, array $headerMap): void
    {
        $metaFieldMap = array(
            'FirstName' => 'first_name',
            'LastName'  => 'last_name',
            'Nickname'  => 'nickname',
            'Bio'       => 'description',
        );

        foreach ($metaFieldMap as $csvKey => $metaKey) {
            $idx = $headerMap[$csvKey] ?? -1;
            $hasValue = ($idx >= 0 && isset($row[$idx]));

            if ($hasValue) {
                update_user_meta($userId, $metaKey, sanitize_text_field($row[$idx]));
            }
        }

        // Social fields
        foreach (UserMetaKeyType::socialCases() as $meta) {
            $csvKey = 'Social.' . $meta->jsonKey();
            $idx = $headerMap[$csvKey] ?? -1;
            $hasValue = ($idx >= 0 && isset($row[$idx]));

            if ($hasValue) {
                update_user_meta($userId, $meta->value, sanitize_text_field($row[$idx]));
            }
        }

        // Yoast fields
        foreach (UserMetaKeyType::yoastCases() as $meta) {
            $csvKey = 'Yoast.' . $meta->jsonKey();
            $idx = $headerMap[$csvKey] ?? -1;
            $hasValue = ($idx >= 0 && isset($row[$idx]));

            if ($hasValue) {
                update_user_meta($userId, $meta->value, sanitize_text_field($row[$idx]));
            }
        }

        // Role
        $roleIdx = $headerMap['Role'] ?? -1;
        $hasRole = ($roleIdx >= 0 && isset($row[$roleIdx]) && !empty($row[$roleIdx]));

        if ($hasRole) {
            $user = get_userdata($userId);

            if ($user) {
                $user->set_role(sanitize_text_field($row[$roleIdx]));
            }
        }
    }

    private function resolveImportPassword(string $passwordHash): string
    {
        $isEmpty = empty($passwordHash);

        if ($isEmpty) {
            return wp_generate_password(24, true, true);
        }

        $isPreHashed = $this->isPreHashedPassword($passwordHash);

        if ($isPreHashed) {
            return wp_generate_password(24, true, true);
        }

        return $passwordHash;
    }

    private function isPreHashedPassword(string $value): bool
    {
        $isPhpass = str_starts_with($value, '$P$');
        $isBcrypt = str_starts_with($value, '$2y$');

        return $isPhpass || $isBcrypt;
    }
}
