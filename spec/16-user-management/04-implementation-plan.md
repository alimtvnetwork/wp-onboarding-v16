# User Management — Implementation Plan

## Phase 1: PHP Plugin (Riseup Asia Uploader)

### New Files

| File                                                           | Purpose                                |
|----------------------------------------------------------------|----------------------------------------|
| `includes/Enums/UserRoleType.php`                              | Enum for WP role slugs                 |
| `includes/Enums/UserMetaKeyType.php`                           | Enum for social + Yoast meta keys      |
| `includes/Traits/User/UserCrudTrait.php`                       | Shell trait composing sub-traits       |
| `includes/Traits/User/UserReadTrait.php`                       | GET /users, GET /users/{id}            |
| `includes/Traits/User/UserWriteTrait.php`                      | POST /users, PUT /users/{id}           |
| `includes/Traits/User/UserDeleteTrait.php`                     | DELETE /users/{id}                     |
| `includes/Traits/User/UserAppPasswordTrait.php`                | App password create/revoke             |
| `includes/Traits/User/UserExportCsvTrait.php`                  | CSV export                             |
| `includes/Traits/User/UserImportCsvTrait.php`                  | CSV import                             |
| `includes/Traits/User/UserExportSqliteTrait.php`               | SQLite ZIP export                      |
| `includes/Traits/User/UserImportSqliteTrait.php`               | SQLite ZIP import                      |
| `includes/Traits/User/UserYoastTrait.php`                      | Yoast meta read/write helpers          |
| `includes/Traits/User/UserSocialTrait.php`                     | Social meta read/write helpers         |
| `includes/Traits/User/UserFieldMapperTrait.php`                | Maps WP user to JSON response object   |

### Modified Files

| File                                               | Change                                     |
|----------------------------------------------------|--------------------------------------------|
| `includes/Enums/EndpointType.php`                  | Add User* endpoint cases                   |
| `includes/Enums/ResponseKeyType.php`               | Add user-related response keys             |
| `includes/Traits/Route/RouteRegistrationTrait.php`  | Add `registerUserRoutes()` call            |
| `includes/Core/Plugin.php`                         | Compose `UserCrudTrait`                    |
| `templates/admin-dashboard.php`                    | Add user endpoint rows to dashboard        |

### Endpoint Registration Pattern

```php
private function registerUserRoutes(callable $safeRegister): void {
    $userPerm = array($this, 'checkUserPermission');

    // GET + POST /users
    $safeRegister(EndpointType::Users->route(), array(
        array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleListUsers'),
            'permission_callback' => $this->buildPermissionCallback('users_list', $userPerm),
        ),
        array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleCreateUser'),
            'permission_callback' => $this->buildPermissionCallback('users_create', $userPerm),
        ),
    ));

    // GET + PUT + DELETE /users/{id}
    $safeRegister(EndpointType::UserId->route(), array(
        array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleGetUser'),
            'permission_callback' => $this->buildPermissionCallback('users_get', $userPerm),
        ),
        array(
            'methods'             => HttpMethodType::Put->value,
            'callback'            => array($this, 'handleUpdateUser'),
            'permission_callback' => $this->buildPermissionCallback('users_update', $userPerm),
        ),
        array(
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => array($this, 'handleDeleteUser'),
            'permission_callback' => $this->buildPermissionCallback('users_delete', $userPerm),
        ),
    ));

    // ... app-password, export, import routes
}
```

### Permission Callback

```php
public function checkUserPermission(): bool|\WP_Error {
    $user = wp_get_current_user();
    $hasCapability = $user->has_cap('edit_users');

    if (!$hasCapability) {
        return new \WP_Error(
            'rest_forbidden',
            'You do not have permission to manage users.',
            array('status' => 403)
        );
    }

    return true;
}
```

## Phase 2: Go Backend

### New Files

| File                                          | Purpose                            |
|-----------------------------------------------|-------------------------------------|
| `backend/internal/wordpress/UserTypes.go`     | User request/response structs       |
| `backend/internal/wordpress/UserClient.go`    | WP REST client methods for users    |

### User Structs

```go
type UserSocial struct {
    Facebook   string `json:"Facebook,omitempty"`
    Instagram  string `json:"Instagram,omitempty"`
    LinkedIn   string `json:"LinkedIn,omitempty"`
    // ... all 11 social platforms
}

type UserYoast struct {
    HonorificPrefix      string `json:"HonorificPrefix,omitempty"`
    HonorificSuffix      string `json:"HonorificSuffix,omitempty"`
    BirthDate            string `json:"BirthDate,omitempty"`
    Gender               string `json:"Gender,omitempty"`
    Awards               string `json:"Awards,omitempty"`
    ExpertiseIn          string `json:"ExpertiseIn,omitempty"`
    LanguagesSpoken      string `json:"LanguagesSpoken,omitempty"`
    JobTitle             string `json:"JobTitle,omitempty"`
    EmployerName         string `json:"EmployerName,omitempty"`
    AuthorTitle          string `json:"AuthorTitle,omitempty"`
    AuthorMetaDescription string `json:"AuthorMetaDescription,omitempty"`
    Pronouns             string `json:"Pronouns,omitempty"`
}

type UserResponse struct {
    Id           int         `json:"Id"`
    Username     string      `json:"Username"`
    Email        string      `json:"Email"`
    FirstName    string      `json:"FirstName,omitempty"`
    LastName     string      `json:"LastName,omitempty"`
    DisplayName  string      `json:"DisplayName,omitempty"`
    Nickname     string      `json:"Nickname,omitempty"`
    Website      string      `json:"Website,omitempty"`
    Bio          string      `json:"Bio,omitempty"`
    Role         string      `json:"Role"`
    RegisteredAt string      `json:"RegisteredAt,omitempty"`
    Social       *UserSocial `json:"Social,omitempty"`
    Yoast        *UserYoast  `json:"Yoast,omitempty"`
}
```

## Phase 3: React Dashboard

### New Components

| Component                       | Purpose                              |
|---------------------------------|--------------------------------------|
| `UserManagementPage.tsx`        | Main user list with CRUD actions     |
| `UserFormDialog.tsx`            | Create/Edit user modal               |
| `UserImportExportPanel.tsx`     | CSV/SQLite import/export controls    |
| `UserYoastFields.tsx`           | Yoast metadata form section          |
| `UserSocialFields.tsx`          | Social links form section            |

## Logging

All user endpoints must log activity via `fileLogger`:

```php
$this->fileLogger->info('User endpoint accessed', array(
    'endpoint' => 'GET /users',
    'requestor' => wp_get_current_user()->user_login,
));
```

Mutations (create, update, delete) must additionally log:

```php
$this->fileLogger->info('User created', array(
    'userId'   => $newUserId,
    'username' => $username,
    'role'     => $role,
    'by'       => wp_get_current_user()->user_login,
));
```

Failed operations must log with error level and include the external failure
disclaimer pattern established in the codebase when the error originates from
WordPress core functions.
