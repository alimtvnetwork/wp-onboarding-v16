# Cloud Storage Providers — GitLab Implementation

## Authentication

GitLab uses **Personal Access Tokens (PATs)**.

### Required Scopes

| Scope | Purpose |
|---|---|
| `api` | Full API access (create projects, read/write files) |

*Alternatively, for minimal permissions*:

| Scope | Purpose |
|---|---|
| `read_api` | Read API (list projects, read files) |
| `write_repository` | Push commits to repositories |

### UI Field Examples

| Field | Placeholder | Help Text |
|---|---|---|
| Account Label | `Work GitLab` | A friendly name to identify this account |
| Username | `john.doe` | Your GitLab username |
| Access Token | `glpat-xxxxxxxxxxxxxxxxxxxx` | Generate at gitlab.com → Edit Profile → Access Tokens |
| Base URL | `https://gitlab.com` | Leave blank for gitlab.com, or enter your self-hosted GitLab URL |
| Project Name | `wp-backups` | Project (repository) to store backups |
| Namespace | `john.doe` | Your username or group path (e.g., `my-org/sub-group`) |

---

## API Base URL

```
https://gitlab.com/api/v4          (gitlab.com)
https://your-instance.com/api/v4   (self-hosted)
```

The base URL is derived from the `BaseUrl` account field:
```php
$apiBase = rtrim($account['BaseUrl'] ?: 'https://gitlab.com', '/') . '/api/v4';
```

All requests include:
```php
$options = HttpConfigType::authenticatedOptions(
    'GET',
    '', // GitLab uses PRIVATE-TOKEN header instead of Bearer
);
$options['headers']['PRIVATE-TOKEN'] = $decryptedToken;
$options['headers']['Content-Type']  = 'application/json';
```

---

## Operations

### 1. Test Connection

```
GET /api/v4/user
```

**Success**: HTTP 200, response contains `username`.
**Failure**: HTTP 401 (bad token), HTTP 403 (revoked).

---

### 2. Check If Project Exists

GitLab uses URL-encoded project path:

```php
$projectPath = urlencode($namespace . '/' . $projectName);

$response = wp_remote_get(
    $apiBase . '/projects/' . $projectPath,
    $options,
);

$statusCode  = wp_remote_retrieve_response_code($response);
$projectExists = ($statusCode === HttpStatusType::Ok->value);
```

---

### 3. Create Project (If Missing)

```
POST /api/v4/projects
```

```php
$body = wp_json_encode(array(
    'name'                   => $projectName,
    'description'            => 'WordPress site backups managed by Riseup Asia Uploader',
    'visibility'             => 'private',
    'initialize_with_readme' => true,
));

$createOptions = HttpConfigType::authenticatedOptions('POST', '');
$createOptions['headers']['PRIVATE-TOKEN'] = $token;
$createOptions['headers']['Content-Type']  = 'application/json';
$createOptions['body'] = $body;

$response = wp_remote_post($apiBase . '/projects', $createOptions);
```

**For group projects**, add `namespace_id` to the body:
```php
// First resolve the group ID
$groupResponse = wp_remote_get($apiBase . '/groups/' . urlencode($namespace), $options);
$groupData = json_decode(wp_remote_retrieve_body($groupResponse), true);
$body['namespace_id'] = $groupData['id'];
```

---

### 4. Upload File (Repository Files API)

GitLab's Repository Files API handles files up to the server's configured limit (default 100 MB on gitlab.com).

#### Create a new file

```
POST /api/v4/projects/{id}/repository/files/{file_path}
```

```php
$projectPath = urlencode($namespace . '/' . $projectName);
$encodedFilePath = urlencode($remotePath);

$body = wp_json_encode(array(
    'branch'         => 'main',
    'commit_message' => sprintf('Backup: %s', basename($remotePath)),
    'content'        => base64_encode(file_get_contents($localFilePath)),
    'encoding'       => 'base64',
));

$uploadOptions = HttpConfigType::authenticatedOptions('POST', '');
$uploadOptions['headers']['PRIVATE-TOKEN'] = $token;
$uploadOptions['headers']['Content-Type']  = 'application/json';
$uploadOptions['body'] = $body;

$url = sprintf('%s/projects/%s/repository/files/%s', $apiBase, $projectPath, $encodedFilePath);

$response = wp_remote_post($url, $uploadOptions);
```

#### Update existing file

```
PUT /api/v4/projects/{id}/repository/files/{file_path}
```

Same body, but use `PUT` method. Check file existence first with:

```
HEAD /api/v4/projects/{id}/repository/files/{file_path}?ref=main
```

---

### 5. For Large Files: Commits API (Multiple Actions)

```
POST /api/v4/projects/{id}/repository/commits
```

```php
$body = wp_json_encode(array(
    'branch'         => 'main',
    'commit_message' => sprintf('Backup: %s', basename($remotePath)),
    'actions'        => array(
        array(
            'action'   => 'create',  // or 'update'
            'file_path' => $remotePath,
            'content'   => base64_encode(file_get_contents($localFilePath)),
            'encoding'  => 'base64',
        ),
    ),
));
```

---

### 6. List Files in Repository Path

```
GET /api/v4/projects/{id}/repository/tree?path={dir}&ref=main
```

Returns array of objects with `name`, `path`, `type` (blob/tree), `id` (SHA).

To get file size, use:
```
GET /api/v4/projects/{id}/repository/files/{file_path}?ref=main
```

Response includes `size` field.

---

### 7. Delete File

```
DELETE /api/v4/projects/{id}/repository/files/{file_path}
```

Body:
```json
{
    "branch": "main",
    "commit_message": "Remove old backup: wp-backup-2026-03-01.zip"
}
```

---

### 8. Rotation

Same rotation logic as GitHub — list files, sort chronologically, delete excess.

---

## Key Differences from GitHub

| Aspect | GitHub | GitLab |
|---|---|---|
| Auth header | `Authorization: Bearer <token>` | `PRIVATE-TOKEN: <token>` |
| Project identification | `{owner}/{repo}` in URL path | URL-encoded `{namespace}/{project}` or numeric ID |
| File upload | Contents API (PUT) | Repository Files API (POST create / PUT update) |
| Large file upload | Git Data API (blob→tree→commit→ref) | Commits API with actions array |
| Self-hosted | No (always github.com) | Yes (`BaseUrl` field) |
| File delete | Requires file SHA | Requires commit message only |
| Token prefix | `ghp_` (fine-grained: `github_pat_`) | `glpat-` |

---

## PHP Trait

`CloudStorageGitLabTrait.php` implements:

```php
trait CloudStorageGitLabTrait {
    private function gitlabTestConnection(array $account): array { ... }
    private function gitlabEnsureProject(array $account): array { ... }
    private function gitlabUploadFile(array $account, string $localPath, string $remotePath): array { ... }
    private function gitlabListFiles(array $account, string $dir): array { ... }
    private function gitlabDeleteFile(array $account, string $remotePath): bool { ... }
    private function gitlabGetApiBase(array $account): string { ... }
    private function gitlabBuildOptions(string $method, string $token): array { ... }
}
```

Each method follows the same error handling pattern as GitHub (try-catch with Throwable-first logging).
