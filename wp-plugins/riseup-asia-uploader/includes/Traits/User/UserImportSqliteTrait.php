<?php
/**
 * UserImportSqliteTrait — POST /users/import-sqlite handler.
 *
 * Imports users from a SQLite ZIP backup.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use WP_REST_Request;
use WP_REST_Response;
use ZipArchive;
use RiseupAsia\Enums\UserMetaKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserImportSqliteTrait {

    /**
     * Handle POST /users/import-sqlite — import users from SQLite ZIP.
     */
    public function handleImportSqlite(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'POST /users/import-sqlite'));

        return $this->safeExecute(function () use ($request) {
            $files = $request->get_file_params();
            $hasFile = isset($files['file']['tmp_name']) && !empty($files['file']['tmp_name']);

            if (!$hasFile) {
                return EnvelopeBuilder::error('ZIP file is required', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $uploadDir = wp_upload_dir();
            $tempDir = $uploadDir['basedir'] . '/riseup-asia-uploader/temp';
            wp_mkdir_p($tempDir);

            // Extract ZIP
            $zip = new ZipArchive();
            $isOpened = $zip->open($files['file']['tmp_name']);

            if ($isOpened !== true) {
                return EnvelopeBuilder::error('Invalid ZIP file', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $dbPath = $tempDir . '/users-export.sqlite';
            $isDbFound = file_exists($dbPath);

            if (!$isDbFound) {
                return EnvelopeBuilder::error('SQLite database not found in ZIP', 400)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $result = $this->importFromSqliteDb($pdo);

            $pdo = null;
            unlink($dbPath);

            $this->fileLogger->info('Users imported from SQLite', array(
                'created' => $result['Created'],
                'updated' => $result['Updated'],
                'errors'  => count($result['Errors']),
                'by'      => wp_get_current_user()->user_login,
            ));

            return EnvelopeBuilder::success('Import complete')
                ->setSingleResult($result)
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleImportSqlite');
    }

    private function importFromSqliteDb(PDO $pdo): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();

        $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $sqliteUser) {
            $username = $sqliteUser['username'];
            $email    = $sqliteUser['email'];

            $existingUser = get_user_by('login', $username);
            $isExisting = ($existingUser !== false);

            if (!$isExisting) {
                $existingUser = get_user_by('email', $email);
                $isExisting = ($existingUser !== false);
            }

            if ($isExisting) {
                $updateResult = $this->updateUserFromSqlite($existingUser->ID, $sqliteUser, $pdo);
                $isError = str_starts_with($updateResult, 'error:');

                if ($isError) {
                    $errors[] = array('Username' => $username, 'Error' => substr($updateResult, 6));
                } else {
                    $updated++;
                }
            } else {
                $createResult = $this->createUserFromSqlite($sqliteUser, $pdo);
                $isError = str_starts_with($createResult, 'error:');

                if ($isError) {
                    $errors[] = array('Username' => $username, 'Error' => substr($createResult, 6));
                } else {
                    $created++;
                }
            }
        }

        return array(
            'Created' => $created,
            'Updated' => $updated,
            'Skipped' => $skipped,
            'Errors'  => $errors,
        );
    }

    private function createUserFromSqlite(array $sqliteUser, PDO $pdo): string
    {
        $tempPassword = wp_generate_password(24, true, true);

        $userdata = array(
            'user_login'   => sanitize_user($sqliteUser['username']),
            'user_email'   => sanitize_email($sqliteUser['email']),
            'user_pass'    => $tempPassword,
            'display_name' => $sqliteUser['display_name'] ?? '',
            'first_name'   => $sqliteUser['first_name'] ?? '',
            'last_name'    => $sqliteUser['last_name'] ?? '',
            'nickname'     => $sqliteUser['nickname'] ?? '',
            'user_url'     => $sqliteUser['website'] ?? '',
            'description'  => $sqliteUser['bio'] ?? '',
            'role'         => $sqliteUser['role'] ?? 'subscriber',
        );

        $newUserId = wp_insert_user($userdata);
        $isError = is_wp_error($newUserId);

        if ($isError) {
            return 'error:' . $newUserId->get_error_message();
        }

        // Restore password hash directly
        $hasPasswordHash = !empty($sqliteUser['password_hash']);

        if ($hasPasswordHash) {
            global $wpdb;
            $wpdb->update($wpdb->users, array('user_pass' => $sqliteUser['password_hash']), array('ID' => $newUserId));
            wp_cache_delete($newUserId, 'users');
        }

        $this->importSqliteMeta($newUserId, $sqliteUser, $pdo);

        return 'created';
    }

    private function updateUserFromSqlite(int $userId, array $sqliteUser, PDO $pdo): string
    {
        $userdata = array('ID' => $userId);

        $hasEmail = !empty($sqliteUser['email']);
        if ($hasEmail) { $userdata['user_email'] = sanitize_email($sqliteUser['email']); }

        $hasDisplayName = !empty($sqliteUser['display_name']);
        if ($hasDisplayName) { $userdata['display_name'] = $sqliteUser['display_name']; }

        $hasWebsite = !empty($sqliteUser['website']);
        if ($hasWebsite) { $userdata['user_url'] = $sqliteUser['website']; }

        $hasCoreChanges = count($userdata) > 1;

        if ($hasCoreChanges) {
            $result = wp_update_user($userdata);
            $isError = is_wp_error($result);

            if ($isError) {
                return 'error:' . $result->get_error_message();
            }
        }

        // Restore password hash
        $hasPasswordHash = !empty($sqliteUser['password_hash']);

        if ($hasPasswordHash) {
            global $wpdb;
            $wpdb->update($wpdb->users, array('user_pass' => $sqliteUser['password_hash']), array('ID' => $userId));
            wp_cache_delete($userId, 'users');
        }

        $this->importSqliteMeta($userId, $sqliteUser, $pdo);

        return 'updated';
    }

    private function importSqliteMeta(int $wpUserId, array $sqliteUser, PDO $pdo): void
    {
        $sqliteId = (int) $sqliteUser['id'];

        // Core meta
        $metaMap = array(
            'first_name'  => $sqliteUser['first_name'] ?? '',
            'last_name'   => $sqliteUser['last_name'] ?? '',
            'nickname'    => $sqliteUser['nickname'] ?? '',
            'description' => $sqliteUser['bio'] ?? '',
        );

        foreach ($metaMap as $key => $value) {
            $hasValue = !empty($value);

            if ($hasValue) {
                update_user_meta($wpUserId, $key, sanitize_text_field($value));
            }
        }

        // Role
        $hasRole = !empty($sqliteUser['role']);

        if ($hasRole) {
            $user = get_userdata($wpUserId);

            if ($user) {
                $user->set_role(sanitize_text_field($sqliteUser['role']));
            }
        }

        // Social meta from SQLite
        $socialStmt = $pdo->prepare("SELECT platform, url FROM user_social WHERE user_id = ?");
        $socialStmt->execute(array($sqliteId));
        $socialRows = $socialStmt->fetchAll(PDO::FETCH_ASSOC);

        $socialJsonKeyToMeta = array();

        foreach (UserMetaKeyType::socialCases() as $meta) {
            $socialJsonKeyToMeta[$meta->jsonKey()] = $meta->value;
        }

        foreach ($socialRows as $socialRow) {
            $metaKey = $socialJsonKeyToMeta[$socialRow['platform']] ?? null;
            $hasMetaKey = ($metaKey !== null);

            if ($hasMetaKey) {
                update_user_meta($wpUserId, $metaKey, sanitize_text_field($socialRow['url']));
            }
        }

        // Yoast meta from SQLite
        $yoastStmt = $pdo->prepare("SELECT meta_key, value FROM user_yoast WHERE user_id = ?");
        $yoastStmt->execute(array($sqliteId));
        $yoastRows = $yoastStmt->fetchAll(PDO::FETCH_ASSOC);

        $yoastJsonKeyToMeta = array();

        foreach (UserMetaKeyType::yoastCases() as $meta) {
            $yoastJsonKeyToMeta[$meta->jsonKey()] = $meta->value;
        }

        foreach ($yoastRows as $yoastRow) {
            $metaKey = $yoastJsonKeyToMeta[$yoastRow['meta_key']] ?? null;
            $hasMetaKey = ($metaKey !== null);

            if ($hasMetaKey) {
                update_user_meta($wpUserId, $metaKey, sanitize_text_field($yoastRow['value']));
            }
        }
    }
}
