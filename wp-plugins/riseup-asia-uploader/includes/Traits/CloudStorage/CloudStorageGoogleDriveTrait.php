<?php
/**
 * CloudStorageGoogleDriveTrait — Google Drive API operations for cloud storage.
 *
 * Supports OAuth2 Bearer authentication, resumable uploads for large files,
 * folder creation, file listing, and deletion via the Drive v3 API.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.15.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;
use Throwable;

use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\HttpHeaderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait CloudStorageGoogleDriveTrait {

    private const GDRIVE_API      = 'https://www.googleapis.com/drive/v3';
    private const GDRIVE_UPLOAD   = 'https://www.googleapis.com/upload/drive/v3';
    private const GDRIVE_TOKEN    = 'https://oauth2.googleapis.com/token';
    private const GDRIVE_CHUNK_SZ = 262144; // 256 KB (must be multiple of 256 KB)
    private const GDRIVE_SIMPLE_MAX = 5242880; // 5 MB threshold for simple upload

    /** Test connection by verifying the authenticated user. */
    private function googleDriveTestConnection(array $account, string $token): array
    {
        $validToken = $this->googleDriveEnsureValidToken($account);

        $options  = $this->googleDriveBuildOptions('GET', $validToken);
        $response = wp_remote_get(self::GDRIVE_API . '/about?fields=user', $options);
        $body     = $this->googleDriveParseResponse($response);

        $email = $body['user']['emailAddress'] ?? '';

        return array(
            ResponseKeyType::Success->value          => true,
            ResponseKeyType::ConnectionStatus->value => 'Connected',
            ResponseKeyType::Email->value            => $email,
            ResponseKeyType::Message->value          => sprintf('Connected as %s', $email),
        );
    }

    /** Ensure a backup folder exists; create if missing. Returns folder ID. */
    private function googleDriveEnsureFolder(array $account, string $token): string
    {
        $folderId = $account['FolderId'] ?? '';
        $hasFolderId = !empty($folderId);

        if ($hasFolderId) {
            $checkUrl = self::GDRIVE_API . '/files/' . urlencode($folderId) . '?fields=id,trashed';
            $options  = $this->googleDriveBuildOptions('GET', $token);

            $response   = wp_remote_get($checkUrl, $options);
            $statusCode = (int) wp_remote_retrieve_response_code($response);
            $isFound    = ($statusCode === HttpStatusType::Ok->value);

            if ($isFound) {
                $data      = json_decode(wp_remote_retrieve_body($response), true) ?? array();
                $isTrashed = ($data['trashed'] ?? false);

                if (!$isTrashed) {
                    return $folderId;
                }
            }
        }

        $folderName = $account['FolderName'] ?? 'WordPress Backups';
        $newId      = $this->googleDriveCreateFolder($token, $folderName);

        $table = TableType::CloudStorageAccounts->value;

        $this->db->execute(
            "UPDATE {$table} SET FolderId = ?, FolderName = ?, UpdatedAt = datetime('now') WHERE Id = ?",
            array($newId, $folderName, $account['Id']),
        );

        return $newId;
    }

    /** Upload a file to Google Drive (simple or resumable). */
    private function googleDriveUploadFile(array $account, string $token, string $localPath, string $remotePath): array
    {
        $validToken = $this->googleDriveEnsureValidToken($account);
        $folderId   = $this->googleDriveEnsureFolder($account, $validToken);
        $fileName   = basename($remotePath);
        $fileSize   = filesize($localPath);
        $isLarge    = ($fileSize > self::GDRIVE_SIMPLE_MAX);

        if ($isLarge) {
            $fileData = $this->googleDriveUploadResumable($validToken, $folderId, $localPath, $fileName);
        } else {
            $fileData = $this->googleDriveUploadSimple($validToken, $folderId, $localPath, $fileName);
        }

        return array(
            ResponseKeyType::RemotePath->value => $fileName,
            ResponseKeyType::RemoteUrl->value  => $fileData['webViewLink'] ?? '',
            ResponseKeyType::Bytes->value      => $fileSize,
        );
    }

    /** List files in the backup folder. */
    private function googleDriveListFiles(array $account, string $token, string $dir): array
    {
        $validToken = $this->googleDriveEnsureValidToken($account);
        $folderId   = $account['FolderId'] ?? '';
        $isEmpty    = empty($folderId);

        if ($isEmpty) {
            return array();
        }

        $query = sprintf("'%s' in parents and trashed=false", $folderId);

        $url = self::GDRIVE_API . '/files?' . http_build_query(array(
            'q'       => $query,
            'fields'  => 'files(id,name,size,createdTime,webViewLink)',
            'orderBy' => 'name',
        ));

        $options  = $this->googleDriveBuildOptions('GET', $validToken);
        $response = wp_remote_get($url, $options);
        $body     = $this->googleDriveParseResponse($response);

        $files    = array();
        $items    = $body['files'] ?? array();

        foreach ($items as $item) {
            $files[] = array(
                'Name'      => $item['name'] ?? '',
                'Path'      => $item['id'] ?? '',
                'Size'      => (int) ($item['size'] ?? 0),
                'CreatedAt' => $item['createdTime'] ?? '',
                'RemoteUrl' => $item['webViewLink'] ?? '',
            );
        }

        return $files;
    }

    /** Delete a file from Google Drive by file ID. */
    private function googleDriveDeleteFile(array $account, string $token, string $fileId): bool
    {
        $validToken = $this->googleDriveEnsureValidToken($account);

        $url     = self::GDRIVE_API . '/files/' . urlencode($fileId);
        $options = $this->googleDriveBuildOptions('DELETE', $validToken);

        $response   = wp_remote_request($url, $options);
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $isDeleted  = ($statusCode === HttpStatusType::NoContent->value);

        return $isDeleted;
    }

    // ── Google Drive Private Helpers ───────────────────────────────

    /** Ensure the access token is valid; refresh if expired. Returns a valid token. */
    private function googleDriveEnsureValidToken(array &$account): string
    {
        $expiresAt    = $account['TokenExpiresAt'] ?? '';
        $hasExpiry    = !empty($expiresAt);
        $bufferTime   = time() + 60;
        $isTokenValid = $hasExpiry && (strtotime($expiresAt) > $bufferTime);

        if ($isTokenValid) {
            return $this->decryptToken($account['AccessToken']);
        }

        $refreshToken = $this->decryptToken($account['RefreshToken'] ?? '');
        $isRefreshEmpty = empty($refreshToken);

        if ($isRefreshEmpty) {
            throw new RuntimeException('Google Drive refresh token is missing. Please re-authorize the account.');
        }

        $clientId     = $this->getEncryptedOption('riseup_google_oauth_client_id');
        $clientSecret = $this->getEncryptedOption('riseup_google_oauth_client_secret');

        $refreshOptions           = HttpConfigType::defaultGetOptions();
        $refreshOptions['method'] = 'POST';
        $refreshOptions['body']   = array(
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        );

        $response = wp_remote_post(self::GDRIVE_TOKEN, $refreshOptions);
        $body     = json_decode(wp_remote_retrieve_body($response), true) ?? array();

        $isRefreshError = isset($body['error']);

        if ($isRefreshError) {
            $this->fileLogger->error('Google OAuth refresh failed', $body);

            throw new RuntimeException(
                'Failed to refresh Google Drive token: ' . ($body['error_description'] ?? $body['error']),
            );
        }

        $newAccessToken = $body['access_token'] ?? '';
        $newExpiresAt   = gmdate('Y-m-d\TH:i:s\Z', time() + (int) ($body['expires_in'] ?? 3600));

        $table = TableType::CloudStorageAccounts->value;

        $this->db->execute(
            "UPDATE {$table} SET AccessToken = ?, TokenExpiresAt = ?, UpdatedAt = datetime('now') WHERE Id = ?",
            array($this->encryptToken($newAccessToken), $newExpiresAt, $account['Id']),
        );

        $account['AccessToken']   = $this->encryptToken($newAccessToken);
        $account['TokenExpiresAt'] = $newExpiresAt;

        return $newAccessToken;
    }

    /** Create a folder in Google Drive. Returns the folder ID. */
    private function googleDriveCreateFolder(string $token, string $folderName): string
    {
        $metadata = wp_json_encode(array(
            'name'     => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ));

        $options = $this->googleDriveBuildOptions('POST', $token);
        $options['headers']['Content-Type'] = 'application/json';
        $options['body'] = $metadata;

        $response = wp_remote_post(self::GDRIVE_API . '/files', $options);
        $body     = $this->googleDriveParseResponse($response);

        return $body['id'] ?? '';
    }

    /** Simple upload for files ≤5 MB using multipart. */
    private function googleDriveUploadSimple(string $token, string $folderId, string $localPath, string $fileName): array
    {
        $boundary    = wp_generate_password(24, false);
        $fileContent = file_get_contents($localPath);

        $metadata = wp_json_encode(array(
            'name'    => $fileName,
            'parents' => array($folderId),
        ));

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/zip\r\n\r\n"
            . $fileContent . "\r\n"
            . "--{$boundary}--";

        $options = $this->googleDriveBuildOptions('POST', $token);
        $options['headers']['Content-Type'] = 'multipart/related; boundary=' . $boundary;
        $options['body'] = $body;

        $url      = self::GDRIVE_UPLOAD . '/files?uploadType=multipart&fields=id,name,webViewLink';
        $response = wp_remote_post($url, $options);

        return $this->googleDriveParseResponse($response);
    }

    /** Resumable upload for files >5 MB with chunked streaming. */
    private function googleDriveUploadResumable(string $token, string $folderId, string $localPath, string $fileName): array
    {
        $fileSize = filesize($localPath);

        $metadata = wp_json_encode(array(
            'name'    => $fileName,
            'parents' => array($folderId),
        ));

        $initOptions = $this->googleDriveBuildOptions('POST', $token);
        $initOptions['headers']['Content-Type']            = 'application/json; charset=UTF-8';
        $initOptions['headers']['X-Upload-Content-Type']   = 'application/zip';
        $initOptions['headers']['X-Upload-Content-Length']  = $fileSize;
        $initOptions['body'] = $metadata;

        $initUrl      = self::GDRIVE_UPLOAD . '/files?uploadType=resumable&fields=id,name,webViewLink';
        $initResponse = wp_remote_post($initUrl, $initOptions);

        $uploadUri = wp_remote_retrieve_header($initResponse, HttpHeaderType::Location->value);
        $hasUri    = !empty($uploadUri);

        if (!$hasUri) {
            throw new RuntimeException('Failed to initiate Google Drive resumable upload — no upload URI returned');
        }

        $handle = fopen($localPath, 'rb');
        $offset = 0;

        while ($offset < $fileSize) {
            $chunk    = fread($handle, self::GDRIVE_CHUNK_SZ);
            $chunkLen = strlen($chunk);
            $rangeEnd = $offset + $chunkLen - 1;

            $chunkOptions = $this->googleDriveBuildOptions('PUT', $token);
            $chunkOptions['headers']['Content-Length'] = $chunkLen;
            $chunkOptions['headers']['Content-Range']  = sprintf('bytes %d-%d/%d', $offset, $rangeEnd, $fileSize);
            $chunkOptions['body'] = $chunk;

            $chunkResponse = wp_remote_request($uploadUri, $chunkOptions);
            $chunkStatus   = (int) wp_remote_retrieve_response_code($chunkResponse);

            $isIncomplete = ($chunkStatus === 308);

            if ($isIncomplete) {
                $offset += $chunkLen;

                continue;
            }

            $isComplete = ($chunkStatus === 200 || $chunkStatus === 201);

            if ($isComplete) {
                fclose($handle);

                return json_decode(wp_remote_retrieve_body($chunkResponse), true) ?? array();
            }

            fclose($handle);

            throw new RuntimeException(
                sprintf('Google Drive chunk upload failed at offset %d [HTTP %d]', $offset, $chunkStatus),
            );
        }

        fclose($handle);

        throw new RuntimeException('Google Drive resumable upload ended unexpectedly');
    }

    /** Build authenticated HTTP options for Google Drive. */
    private function googleDriveBuildOptions(string $method, string $token): array
    {
        $options = HttpConfigType::authenticatedOptions($method, 'Bearer ' . $token);
        $options['headers']['User-Agent'] = PluginConfigType::Slug->value;
        $options['timeout'] = 120;

        return $options;
    }

    /** Parse a Google Drive API response and throw on error. */
    private function googleDriveParseResponse($response): array
    {
        $isWpError = is_wp_error($response);

        if ($isWpError) {
            throw new RuntimeException('Google Drive API request failed: ' . $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $decoded    = json_decode(wp_remote_retrieve_body($response), true) ?? array();

        $isNoContent = ($statusCode === HttpStatusType::NoContent->value);

        if ($isNoContent) {
            return array();
        }

        $isClientError = ($statusCode >= 400);

        if ($isClientError) {
            $errorMessage = $decoded['error']['message'] ?? $decoded['error'] ?? 'Unknown error';

            throw new RuntimeException(
                sprintf('Google Drive API error [%d]: %s', $statusCode, $errorMessage),
            );
        }

        return $decoded;
    }
}
