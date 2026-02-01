# 08 - Role-Based Access Control (RBAC) System

> **Phase:** RBAC  
> **Dependencies:** `06-entity-models.md`, `07-validation-utilities.md`  
> **Estimated Time:** 4-5 hours

---

## 📋 Scope

Implement the three-tier role system:
- **Admin** - Full access to all plugin features
- **Exam Editor** - Can manage exams and participants
- **Examinee** - Can take exams only

---

## 🔖 Role Definitions

| Role | Capabilities |
|------|-------------|
| **ADMIN** | Full access, manage roles, settings, logs, templates, database |
| **EXAM_EDITOR** | Manage exams, participants, wiki (limited), secret keys |
| **EXAMINEE** | Signup for exams, view content, request extensions |

---

## 🔧 RoleService

**File:** `src/Services/RoleService.php`

```php
<?php
namespace ExamQuestionsManager\Services;

use ExamQuestionsManager\ORM\Models\UserRole;
use ExamQuestionsManager\Enums\UserRoleType;
use ExamQuestionsManager\Utils\Logger;

class RoleService {
    /**
     * Get user's plugin role
     */
    public static function getUserRole(int $userId): ?UserRoleType {
        $userRole = UserRole::findByUserId($userId);
        
        if (!$userRole) {
            return null;
        }
        
        return UserRoleType::from($userRole->role);
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole(int $userId, UserRoleType $role): bool {
        $userRole = self::getUserRole($userId);
        return $userRole === $role;
    }
    
    /**
     * Check if user has at least the specified role level
     */
    public static function hasAtLeastRole(int $userId, UserRoleType $minRole): bool {
        $userRole = self::getUserRole($userId);
        
        if (!$userRole) {
            return false;
        }
        
        return $userRole->priority() <= $minRole->priority();
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin(int $userId): bool {
        return self::hasRole($userId, UserRoleType::ADMIN);
    }
    
    /**
     * Check if user can manage exams
     */
    public static function canManageExams(int $userId): bool {
        $role = self::getUserRole($userId);
        return $role && $role->canManageExams();
    }
    
    /**
     * Check if user can manage roles
     */
    public static function canManageRoles(int $userId): bool {
        return self::isAdmin($userId);
    }
    
    /**
     * Check if user can manage settings
     */
    public static function canManageSettings(int $userId): bool {
        return self::isAdmin($userId);
    }
    
    /**
     * Check if user can view logs
     */
    public static function canViewLogs(int $userId): bool {
        return self::isAdmin($userId);
    }
    
    /**
     * Assign role to user
     */
    public static function assignRole(
        int $userId, 
        UserRoleType $role, 
        ?int $assignedBy = null
    ): UserRole {
        // Check if user already has a role
        $existing = UserRole::findByUserId($userId);
        
        if ($existing) {
            $existing->role = $role->value;
            $existing->assignedAt = date('Y-m-d H:i:s');
            $existing->assignedBy = $assignedBy;
            $existing->save();
            
            Logger::info('Role updated', [
                'userId' => $userId,
                'role' => $role->value,
                'assignedBy' => $assignedBy
            ]);
            
            return $existing;
        }
        
        $userRole = UserRole::create([
            'userId' => $userId,
            'role' => $role->value,
            'assignedAt' => date('Y-m-d H:i:s'),
            'assignedBy' => $assignedBy,
        ]);
        
        Logger::info('Role assigned', [
            'userId' => $userId,
            'role' => $role->value,
            'assignedBy' => $assignedBy
        ]);
        
        return $userRole;
    }
    
    /**
     * Revoke role from user
     */
    public static function revokeRole(int $userId): bool {
        $userRole = UserRole::findByUserId($userId);
        
        if (!$userRole) {
            return false;
        }
        
        $result = $userRole->delete();
        
        if ($result) {
            Logger::info('Role revoked', ['userId' => $userId]);
        }
        
        return $result;
    }
    
    /**
     * Get all users with roles
     */
    public static function getAllRoleAssignments(): array {
        return UserRole::all();
    }
    
    /**
     * Get users by role
     */
    public static function getUsersByRole(UserRoleType $role): array {
        return UserRole::findByRole($role);
    }
    
    /**
     * Seed default admin role on activation
     */
    public static function seedDefaultAdmin(): void {
        // Get first WordPress administrator
        $wpAdmins = get_users([
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        if (empty($wpAdmins)) {
            Logger::warning('No WordPress administrator found for seeding');
            return;
        }
        
        $admin = $wpAdmins[0];
        
        // Check if already seeded
        $existing = UserRole::findByUserId($admin->ID);
        
        if ($existing) {
            Logger::info('Default admin role already exists', ['userId' => $admin->ID]);
            return;
        }
        
        // Assign admin role
        self::assignRole($admin->ID, UserRoleType::ADMIN, null);
        
        Logger::info('Default admin role seeded', [
            'userId' => $admin->ID,
            'email' => $admin->user_email
        ]);
    }
    
    /**
     * Require specific role (throw exception if not authorized)
     */
    public static function requireRole(int $userId, UserRoleType $role): void {
        if (!self::hasAtLeastRole($userId, $role)) {
            throw new \RuntimeException('Unauthorized: Insufficient permissions');
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin(int $userId): void {
        self::requireRole($userId, UserRoleType::ADMIN);
    }
    
    /**
     * Require exam management permission
     */
    public static function requireExamManagement(int $userId): void {
        if (!self::canManageExams($userId)) {
            throw new \RuntimeException('Unauthorized: Cannot manage exams');
        }
    }
}
```

---

## 🔧 Permission Middleware

**File:** `src/API/Middleware/RoleMiddleware.php`

```php
<?php
namespace ExamQuestionsManager\API\Middleware;

use ExamQuestionsManager\Services\RoleService;
use ExamQuestionsManager\Enums\UserRoleType;
use WP_REST_Request;

class RoleMiddleware {
    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool {
        $userId = get_current_user_id();
        return $userId && RoleService::isAdmin($userId);
    }
    
    /**
     * Check if current user can manage exams
     */
    public static function canManageExams(): bool {
        $userId = get_current_user_id();
        return $userId && RoleService::canManageExams($userId);
    }
    
    /**
     * Permission callback for admin-only endpoints
     */
    public static function adminOnly(): callable {
        return function(WP_REST_Request $request): bool {
            return self::isAdmin();
        };
    }
    
    /**
     * Permission callback for exam management endpoints
     */
    public static function examManagement(): callable {
        return function(WP_REST_Request $request): bool {
            return self::canManageExams();
        };
    }
    
    /**
     * Permission callback for authenticated users
     */
    public static function authenticated(): callable {
        return function(WP_REST_Request $request): bool {
            return is_user_logged_in();
        };
    }
    
    /**
     * Permission callback for public endpoints
     */
    public static function public(): callable {
        return function(WP_REST_Request $request): bool {
            return true;
        };
    }
    
    /**
     * Check role and return WP_Error if unauthorized
     */
    public static function checkPermission(
        WP_REST_Request $request, 
        UserRoleType $requiredRole
    ): true|\WP_Error {
        $userId = get_current_user_id();
        
        if (!$userId) {
            return new \WP_Error(
                'rest_forbidden',
                'Authentication required',
                ['status' => 401]
            );
        }
        
        if (!RoleService::hasAtLeastRole($userId, $requiredRole)) {
            return new \WP_Error(
                'rest_forbidden',
                'Insufficient permissions',
                ['status' => 403]
            );
        }
        
        return true;
    }
}
```

---

## 📋 Permission Matrix

| Feature | Admin | Editor | Examinee | Public |
|---------|-------|--------|----------|--------|
| **Exams** |
| List all exams | ✅ | ✅ | ❌ | ❌ |
| Create exam | ✅ | ✅ | ❌ | ❌ |
| Edit exam | ✅ | ✅ | ❌ | ❌ |
| Delete exam | ✅ | ✅ | ❌ | ❌ |
| **Participants** |
| List participants | ✅ | ✅ | ❌ | ❌ |
| Manage participants | ✅ | ✅ | ❌ | ❌ |
| View own progress | ✅ | ✅ | ✅ | ❌ |
| **Extensions** |
| Approve/reject | ✅ | ✅ | ❌ | ❌ |
| Request extension | ✅ | ✅ | ✅ | ❌ |
| **Wiki** |
| Create any visibility | ✅ | ❌ | ❌ | ❌ |
| Create role-restricted | ✅ | ✅ | ❌ | ❌ |
| View public wiki | ✅ | ✅ | ✅ | ✅ |
| **Secret Keys** |
| Create/manage keys | ✅ | ✅ | ❌ | ❌ |
| View analytics | ✅ | ✅ | ❌ | ❌ |
| **Settings** |
| Manage plugin settings | ✅ | ❌ | ❌ | ❌ |
| **Roles** |
| Assign/revoke roles | ✅ | ❌ | ❌ | ❌ |
| **Logs** |
| View logs | ✅ | ❌ | ❌ | ❌ |
| Clear logs | ✅ | ❌ | ❌ | ❌ |
| **Email Templates** |
| Edit templates | ✅ | ❌ | ❌ | ❌ |

---

## 📝 Usage Examples

```php
use ExamQuestionsManager\Services\RoleService;
use ExamQuestionsManager\Enums\UserRoleType;
use ExamQuestionsManager\API\Middleware\RoleMiddleware;

// Check permissions
$userId = get_current_user_id();

if (RoleService::canManageExams($userId)) {
    // Allow exam management
}

if (RoleService::isAdmin($userId)) {
    // Show admin-only features
}

// Require permission (throws exception)
RoleService::requireAdmin($userId);

// Assign role
RoleService::assignRole($userId, UserRoleType::EXAM_EDITOR, $currentUserId);

// In REST API registration
register_rest_route('eqm/v1', '/exams', [
    'methods' => 'GET',
    'callback' => [$this, 'listExams'],
    'permission_callback' => RoleMiddleware::examManagement(),
]);

register_rest_route('eqm/v1', '/settings', [
    'methods' => 'PUT',
    'callback' => [$this, 'updateSettings'],
    'permission_callback' => RoleMiddleware::adminOnly(),
]);
```

---

## 🔧 Update Plugin Activation

Add to `eqm_activate()` in main plugin file:

```php
function eqm_activate() {
    // Create upload directories
    $directories = [
        EQM_UPLOADS_DIR,
        EQM_UPLOADS_DIR . 'questions/',
        EQM_UPLOADS_DIR . 'extensions/',
        EQM_UPLOADS_DIR . 'seeding/',
        EQM_UPLOADS_DIR . 'seeding/email-templates/',
        EQM_UPLOADS_DIR . 'db/',
        EQM_UPLOADS_DIR . 'logs/',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Add .htaccess protection for sensitive directories
    $htaccess_content = "Order deny,allow\nDeny from all";
    $protected_dirs = [
        EQM_UPLOADS_DIR . 'db/',
        EQM_UPLOADS_DIR . 'logs/',
    ];
    
    foreach ($protected_dirs as $dir) {
        $htaccess_file = $dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    // Initialize database
    \ExamQuestionsManager\Database\Schema::initialize();
    
    // Seed default admin role
    \ExamQuestionsManager\Services\RoleService::seedDefaultAdmin();
    
    // Log activation
    \ExamQuestionsManager\Utils\Logger::info('Plugin activated');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
```

---

## ✅ Acceptance Criteria

### RoleService
- [ ] `getUserRole()` returns UserRoleType enum or null
- [ ] `hasRole()` checks exact role match
- [ ] `hasAtLeastRole()` checks role hierarchy
- [ ] `isAdmin()` shortcut works
- [ ] `canManageExams()` returns true for Admin and Editor

### Role Assignment
- [ ] `assignRole()` creates new role record
- [ ] `assignRole()` updates existing role if present
- [ ] `revokeRole()` removes role record
- [ ] All role changes are logged

### Seeding
- [ ] `seedDefaultAdmin()` assigns Admin role to first WP admin
- [ ] Seeding is idempotent (safe to run multiple times)
- [ ] Logs seeding activity

### Middleware
- [ ] `adminOnly()` blocks non-admin users
- [ ] `examManagement()` blocks examinees
- [ ] `authenticated()` blocks anonymous users
- [ ] `public()` allows all users

### Security
- [ ] Role checks use database, not client-side storage
- [ ] No hardcoded credentials
- [ ] Permission errors return proper HTTP status codes

---

## 📝 Notes

- Roles are stored in plugin's SQLite database, separate from WordPress roles
- WordPress user ID is linked, but role is independent
- Only one plugin role per WordPress user
- Role hierarchy: Admin (1) > Editor (2) > Examinee (3)

---

*Next: `09-rbac-admin-ui.md`*
