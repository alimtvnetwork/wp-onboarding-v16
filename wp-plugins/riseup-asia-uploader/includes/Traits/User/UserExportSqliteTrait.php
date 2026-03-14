<?php
/**
 * UserExportSqliteTrait — GET /users/export-sqlite handler.
 *
 * Exports all users into a SQLite database bundled as ZIP.
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
use WP_User_Query;
use ZipArchive;
use RiseupAsia\Enums\UserMetaKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserExportSqliteTrait {

    /**
     * Handle GET /users/export-sqlite — export as SQLite ZIP.
     */
    public function handleExportSqlite(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'GET /users/export-sqlite'));

        return $this->safeExecute(function () use ($request) {
            $uploadDir = wp_upload_dir();
            $tempDir = $uploadDir['basedir'] . '/riseup-asia-uploader/temp';
            wp_mkdir_p($tempDir);

            $dbPath  = $tempDir . '/users-export.sqlite';
            $zipPath = $tempDir . '/users-export.zip';

            // Clean up previous exports
            if (file_exists($dbPath)) { unlink($dbPath); }
            if (file_exists($zipPath)) { unlink($zipPath); }

            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->createSqliteUserSchema($pdo);

            $userQuery = new WP_User_Query(array('number' => -1, 'orderby' => 'ID', 'order' => 'ASC'));
            $users = $userQuery->get_results();

            $this->populateSqliteUsers($pdo, $users);

            $pdo = null;

            // Create ZIP
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE);
            $zip->addFile($dbPath, 'users-export.sqlite');
            $zip->close();

            $zipContent = file_get_contents($zipPath);

            // Cleanup temp files
            unlink($dbPath);
            unlink($zipPath);

            $this->fileLogger->info('Users exported as SQLite ZIP', array(
                'count' => count($users),
                'by'    => wp_get_current_user()->user_login,
            ));

            $response = new WP_REST_Response($zipContent, 200);
            $response->header('Content-Type', 'application/zip');
            $response->header('Content-Disposition', 'attachment; filename="users-export.zip"');

            return $response;
        }, 'handleExportSqlite');
    }

    private function createSqliteUserSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE users (
            id              INTEGER PRIMARY KEY,
            username        TEXT NOT NULL UNIQUE,
            email           TEXT NOT NULL,
            password_hash   TEXT NOT NULL,
            first_name      TEXT DEFAULT '',
            last_name       TEXT DEFAULT '',
            display_name    TEXT DEFAULT '',
            nickname        TEXT DEFAULT '',
            website         TEXT DEFAULT '',
            bio             TEXT DEFAULT '',
            role            TEXT DEFAULT 'subscriber',
            registered_at   TEXT DEFAULT ''
        )");

        $pdo->exec("CREATE TABLE user_social (
            user_id   INTEGER NOT NULL REFERENCES users(id),
            platform  TEXT NOT NULL,
            url       TEXT DEFAULT '',
            PRIMARY KEY (user_id, platform)
        )");

        $pdo->exec("CREATE TABLE user_yoast (
            user_id   INTEGER NOT NULL REFERENCES users(id),
            meta_key  TEXT NOT NULL,
            value     TEXT DEFAULT '',
            PRIMARY KEY (user_id, meta_key)
        )");
    }

    /**
     * @param \WP_User[] $users
     */
    private function populateSqliteUsers(PDO $pdo, array $users): void
    {
        $userStmt = $pdo->prepare("INSERT INTO users
            (id, username, email, password_hash, first_name, last_name, display_name, nickname, website, bio, role, registered_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $socialStmt = $pdo->prepare("INSERT INTO user_social (user_id, platform, url) VALUES (?, ?, ?)");
        $yoastStmt  = $pdo->prepare("INSERT INTO user_yoast (user_id, meta_key, value) VALUES (?, ?, ?)");

        $pdo->beginTransaction();

        foreach ($users as $user) {
            $roles = $user->roles;
            $primaryRole = !empty($roles) ? reset($roles) : 'subscriber';

            $userStmt->execute(array(
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
            ));

            // Social meta
            foreach (UserMetaKeyType::socialCases() as $meta) {
                $value = get_user_meta($user->ID, $meta->value, true);
                $hasValue = !empty($value);

                if ($hasValue) {
                    $socialStmt->execute(array($user->ID, $meta->jsonKey(), $value));
                }
            }

            // Yoast meta
            foreach (UserMetaKeyType::yoastCases() as $meta) {
                $value = get_user_meta($user->ID, $meta->value, true);
                $hasValue = !empty($value);

                if ($hasValue) {
                    $yoastStmt->execute(array($user->ID, $meta->jsonKey(), $value));
                }
            }
        }

        $pdo->commit();
    }
}
