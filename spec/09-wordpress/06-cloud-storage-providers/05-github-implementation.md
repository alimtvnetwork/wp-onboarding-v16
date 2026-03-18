# Cloud Storage Providers — GitHub Implementation

## Authentication

GitHub uses **Personal Access Tokens (PATs)** — either classic or fine-grained.

### Required Permissions (Fine-Grained PAT)

| Permission | Access | Purpose |
|---|---|---|
| `Contents` | Read and Write | Create/update/delete files in repos |
| `Metadata` | Read-only | List repos, get repo info |

### Required Scopes (Classic PAT)

| Scope | Purpose |
|---|---|
| `repo` | Full control of private repositories |

### UI Field Examples

When user adds a GitHub account, show these fields with placeholders/examples:

| Field | Placeholder | Help Text |
|---|---|---|
| Account Label | `Work GitHub` | A friendly name to identify this account |
| Username | `octocat` | Your GitHub username |
| Access Token | `ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` | Generate at github.com → Settings → Developer settings → Personal access tokens |
| Repository Name | `wp-backups` | Repository to store backups (will be created if it doesn't exist) |
| Repository Owner | `octocat` | Your username or organization name |

---

## API Base URL

```
https://api.github.com
```

All requests include:
```php
$options = HttpConfigType::authenticatedOptions(
    'GET',
    'Bearer ' . $decryptedToken,
);
// Add required headers
$options['headers']['Accept']     = 'application/vnd.github+json';
$options['headers']['User-Agent'] = PluginConfigType::Slug->value;
$options['headers']['X-GitHub-Api-Version'] = '2022-11-28';
```

---

## Operations

### 1. Test Connection

```
GET /user
```

```php
$response = wp_remote_get(
    'https://api.github.com/user',
    $options,
);
```

**Success**: HTTP 200, response contains `login` field matching stored username.
**Failure**: HTTP 401 (bad token), HTTP 403 (insufficient permissions).

---

### 2. Check If Repository Exists

```
GET /repos/{owner}/{repo}
```

```php
$url = sprintf(
    'https://api.github.com/repos/%s/%s',
    urlencode($repoOwner),
    urlencode($repoName),
);

$response = wp_remote_get($url, $options);
$statusCode = wp_remote_retrieve_response_code($response);
$repoExists = ($statusCode === HttpStatusType::Ok->value);
```

---

### 3. Create Repository (If Missing)

```
POST /user/repos
```

```php
$body = wp_json_encode(array(
    'name'        => $repoName,
    'description' => 'WordPress site backups managed by Riseup Asia Uploader',
    'private'     => true,
    'auto_init'   => true,  // Creates initial commit with README
));

$createOptions = HttpConfigType::authenticatedOptions('POST', 'Bearer ' . $token);
$createOptions['headers']['Accept']     = 'application/vnd.github+json';
$createOptions['headers']['User-Agent'] = PluginConfigType::Slug->value;
$createOptions['body'] = $body;

$response = wp_remote_post('https://api.github.com/user/repos', $createOptions);
```

**For organization repos**, use `POST /orgs/{org}/repos` instead.

**Detection logic**: If `RepoOwner` differs from the authenticated user's `login`, assume it's an organization.

---

### 4. Upload File (Create or Update via Contents API)

The GitHub Contents API supports files up to **100 MB**. For most backup ZIPs this is sufficient.

#### Step 1: Check if file already exists (to get SHA for update)

```
GET /repos/{owner}/{repo}/contents/{path}
```

```php
$fileUrl = sprintf(
    'https://api.github.com/repos/%s/%s/contents/%s',
    urlencode($repoOwner),
    urlencode($repoName),
    $remotePath,
);

$existsResponse = wp_remote_get($fileUrl, $options);
$existsCode     = wp_remote_retrieve_response_code($existsResponse);
$existingFileSha = '';

$fileExists = ($existsCode === HttpStatusType::Ok->value);

if ($fileExists) {
    $existingData    = json_decode(wp_remote_retrieve_body($existsResponse), true);
    $existingFileSha = $existingData['sha'] ?? '';
}
```

#### Step 2: Create or Update file

```
PUT /repos/{owner}/{repo}/contents/{path}
```

```php
$fileContent = file_get_contents($localFilePath);
$isFileMissing = PathHelper::isFileMissing($localFilePath);

if ($isFileMissing) {
    throw new RuntimeException('Backup file not found: ' . $localFilePath);
}

$putBody = array(
    'message' => sprintf('Backup: %s', basename($remotePath)),
    'content' => base64_encode($fileContent),
    'branch'  => 'main',
);

$isUpdate = !empty($existingFileSha);

if ($isUpdate) {
    $putBody['sha'] = $existingFileSha;
}

$putOptions = HttpConfigType::authenticatedOptions('PUT', 'Bearer ' . $token);
$putOptions['headers']['Accept']     = 'application/vnd.github+json';
$putOptions['headers']['User-Agent'] = PluginConfigType::Slug->value;
$putOptions['body'] = wp_json_encode($putBody);

$response = wp_remote_request($fileUrl, $putOptions);
```

**Response** (201 Created or 200 OK):
```json
{
    "content": {
        "name": "wp-backup-2026-03-14.zip",
        "path": "backups/wp-backup-2026-03-14.zip",
        "sha": "abc123...",
        "size": 5242880,
        "html_url": "https://github.com/octocat/wp-backups/blob/main/backups/..."
    },
    "commit": {
        "sha": "def456...",
        "message": "Backup: wp-backup-2026-03-14.zip"
    }
}
```

---

### 5. For Large Files (>100 MB): Git Data API (Blob + Tree + Commit)

If the backup ZIP exceeds 100 MB, use the low-level Git Data API:

#### Step 1: Get latest commit SHA on default branch

```
GET /repos/{owner}/{repo}/git/refs/heads/main
```

Extract: `object.sha` → `$lastCommitSha`

#### Step 2: Get the tree SHA from that commit

```
GET /repos/{owner}/{repo}/git/commits/{commit_sha}
```

Extract: `tree.sha` → `$baseTreeSha`

#### Step 3: Create a blob

```
POST /repos/{owner}/{repo}/git/blobs
```

Body:
```json
{
    "content": "<base64-encoded-file-content>",
    "encoding": "base64"
}
```

Extract: `sha` → `$blobSha`

#### Step 4: Create a new tree

```
POST /repos/{owner}/{repo}/git/trees
```

Body:
```json
{
    "base_tree": "<baseTreeSha>",
    "tree": [
        {
            "path": "backups/wp-backup-2026-03-14.zip",
            "mode": "100644",
            "type": "blob",
            "sha": "<blobSha>"
        }
    ]
}
```

Extract: `sha` → `$newTreeSha`

#### Step 5: Create a commit

```
POST /repos/{owner}/{repo}/git/commits
```

Body:
```json
{
    "message": "Backup: wp-backup-2026-03-14.zip",
    "tree": "<newTreeSha>",
    "parents": ["<lastCommitSha>"]
}
```

Extract: `sha` → `$newCommitSha`

#### Step 6: Update the branch reference

```
PATCH /repos/{owner}/{repo}/git/refs/heads/main
```

Body:
```json
{
    "sha": "<newCommitSha>"
}
```

---

### 6. List Files in Repository Path

```
GET /repos/{owner}/{repo}/contents/{path}
```

Returns array of file objects with `name`, `path`, `size`, `sha`, `html_url`.

---

### 7. Delete File

```
DELETE /repos/{owner}/{repo}/contents/{path}
```

Body:
```json
{
    "message": "Remove old backup: wp-backup-2026-03-01.zip",
    "sha": "<file_sha>"
}
```

Must first fetch the file to get its `sha`.

---

### 8. Rotation (Retention Enforcement)

```php
public function applyRotation(
    int $accountId,
    string $backupDir,
    int $retentionCount,
): array
{
    // 1. List all files in backupDir
    $files = $this->listFiles($accountId, $backupDir);

    // 2. Sort by name (date-based naming ensures chronological order)
    usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));

    // 3. Calculate how many to delete
    $excess = count($files) - $retentionCount;
    $isWithinLimit = ($excess <= 0);

    if ($isWithinLimit) {
        return array('deleted' => 0, 'files' => array());
    }

    // 4. Delete oldest files
    $deleted = array();
    $filesToDelete = array_slice($files, 0, $excess);

    foreach ($filesToDelete as $file) {
        $this->deleteFile($accountId, $file['path']);
        $deleted[] = $file['name'];
    }

    return array('deleted' => count($deleted), 'files' => $deleted);
}
```

---

## Token Masking

```php
public static function maskToken(string $provider, string $token): string
{
    $providerType = CloudStorageProviderType::from($provider);
    $suffix = substr($token, -3);

    return match(true) {
        $providerType->isGitHub()  => 'ghp_****' . $suffix,
        $providerType->isGitLab()  => 'glpat-****' . $suffix,
        $providerType->isGoogleDrive() => 'ya29.****' . $suffix,
    };
}
```

---

## Error Handling

All GitHub API calls wrapped in try-catch:

```php
try {
    $response    = wp_remote_get($url, $options);
    $isWpError   = is_wp_error($response);

    if ($isWpError) {
        throw new RuntimeException(
            'GitHub API request failed: ' . $response->get_error_message()
        );
    }

    $statusCode  = wp_remote_retrieve_response_code($response);
    $isRateLimit = ($statusCode === 403);
    $body        = json_decode(wp_remote_retrieve_body($response), true);

    if ($isRateLimit) {
        $resetAt = wp_remote_retrieve_header($response, HttpHeaderType::XRateLimitReset->value);

        throw new RuntimeException(
            sprintf('GitHub API rate limited. Resets at %s', date('Y-m-d H:i:s', (int) $resetAt))
        );
    }

    $isClientError = ($statusCode >= 400);

    if ($isClientError) {
        throw new RuntimeException(
            sprintf('GitHub API error [%d]: %s', $statusCode, $body['message'] ?? 'Unknown error')
        );
    }

    return $body;
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'GitHub API call failed');
    $this->updateAccountLastError($accountId, $e->getMessage());

    throw $e;
}
```

---

## PHP Trait Structure

```
Traits/CloudStorage/
├── CloudStorageTrait.php              // Shell — uses all sub-traits
├── CloudStorageAccountCrudTrait.php   // CRUD for accounts
├── CloudStorageSettingsTrait.php      // Settings handlers
├── CloudStorageUploadTrait.php        // Upload dispatch (routes to provider)
├── CloudStorageFileTrait.php          // List/delete remote files
├── CloudStorageEncryptionTrait.php    // Token encrypt/decrypt helpers
├── CloudStorageGitHubTrait.php        // GitHub-specific API calls
├── CloudStorageGitLabTrait.php        // GitLab-specific API calls (Phase 2)
├── CloudStorageGoogleDriveTrait.php   // Google Drive API calls (Phase 3)
└── CloudStorageOAuthTrait.php         // OAuth2 flow for Google Drive (Phase 3)
```
