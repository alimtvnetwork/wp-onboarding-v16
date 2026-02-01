# Permissions System

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Role-based access control (RBAC) system for pipelines and automation resources. Implements granular permissions with inheritance, supporting both individual and team-based access patterns while maintaining security through proper role separation.

---

## Security Architecture

### Role Separation Principle

**CRITICAL**: Roles are stored in a dedicated table, never embedded in user profiles or localStorage. This prevents privilege escalation attacks.

```sql
-- Role enumeration
CREATE TYPE PipelineRole AS ENUM (
  'VIEWER',           -- Read-only access
  'EXECUTOR',         -- Can run pipelines
  'EDITOR',           -- Can modify pipelines
  'ADMIN',            -- Full control except delete
  'OWNER'             -- Full control including delete
);
```

---

## Database Schema

### PipelinePermission Table

```sql
CREATE TABLE PipelinePermission (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Grantee (one of these must be set)
  UserId          TEXT,                     -- Individual user
  TeamId          TEXT,                     -- Team grant
  
  -- Permission
  Role            TEXT NOT NULL,            -- PipelineRole enum value
  
  -- Grant metadata
  GrantedBy       TEXT NOT NULL,
  GrantedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  ExpiresAt       TEXT,                     -- Optional expiration
  
  -- Constraints
  CHECK (UserId IS NOT NULL OR TeamId IS NOT NULL),
  CHECK (NOT (UserId IS NOT NULL AND TeamId IS NOT NULL)),
  UNIQUE(PipelineId, UserId),
  UNIQUE(PipelineId, TeamId)
);

CREATE INDEX idx_perm_pipeline ON PipelinePermission(PipelineId);
CREATE INDEX idx_perm_user ON PipelinePermission(UserId);
CREATE INDEX idx_perm_team ON PipelinePermission(TeamId);
```

### Team Table

```sql
CREATE TABLE Team (
  Id              TEXT PRIMARY KEY,
  Name            TEXT NOT NULL,
  Description     TEXT,
  
  CreatedBy       TEXT NOT NULL,
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);
```

### TeamMember Table

```sql
CREATE TABLE TeamMember (
  Id              TEXT PRIMARY KEY,
  TeamId          TEXT NOT NULL REFERENCES Team(Id) ON DELETE CASCADE,
  UserId          TEXT NOT NULL,
  
  TeamRole        TEXT NOT NULL DEFAULT 'MEMBER', -- 'OWNER', 'ADMIN', 'MEMBER'
  
  JoinedAt        TEXT NOT NULL DEFAULT (datetime('now')),
  
  UNIQUE(TeamId, UserId)
);

CREATE INDEX idx_team_member_team ON TeamMember(TeamId);
CREATE INDEX idx_team_member_user ON TeamMember(UserId);
```

### PermissionAuditLog Table

```sql
CREATE TABLE PermissionAuditLog (
  Id              TEXT PRIMARY KEY,
  
  -- Action
  Action          TEXT NOT NULL,            -- 'GRANT', 'REVOKE', 'MODIFY'
  PipelineId      TEXT NOT NULL,
  TargetType      TEXT NOT NULL,            -- 'USER', 'TEAM'
  TargetId        TEXT NOT NULL,
  
  -- Change details
  OldRole         TEXT,
  NewRole         TEXT,
  
  -- Actor
  PerformedBy     TEXT NOT NULL,
  PerformedAt     TEXT NOT NULL DEFAULT (datetime('now')),
  
  -- Context
  Reason          TEXT,
  IpAddress       TEXT
);

CREATE INDEX idx_audit_pipeline ON PermissionAuditLog(PipelineId);
CREATE INDEX idx_audit_performed ON PermissionAuditLog(PerformedAt);
```

---

## TypeScript Interfaces

### Permission Types

```typescript
enum PipelineRole {
  VIEWER = 'VIEWER',
  EXECUTOR = 'EXECUTOR',
  EDITOR = 'EDITOR',
  ADMIN = 'ADMIN',
  OWNER = 'OWNER'
}

// Role hierarchy (higher index = more permissions)
const ROLE_HIERARCHY: readonly PipelineRole[] = [
  PipelineRole.VIEWER,
  PipelineRole.EXECUTOR,
  PipelineRole.EDITOR,
  PipelineRole.ADMIN,
  PipelineRole.OWNER
];

interface PipelinePermission {
  readonly id: string;
  readonly pipelineId: string;
  readonly userId: string | null;
  readonly teamId: string | null;
  readonly role: PipelineRole;
  readonly grantedBy: string;
  readonly grantedAt: Date;
  readonly expiresAt: Date | null;
}

interface Team {
  readonly id: string;
  readonly name: string;
  readonly description: string | null;
  readonly createdBy: string;
  readonly createdAt: Date;
  readonly updatedAt: Date;
}

interface TeamMember {
  readonly id: string;
  readonly teamId: string;
  readonly userId: string;
  readonly teamRole: TeamRole;
  readonly joinedAt: Date;
}

enum TeamRole {
  OWNER = 'OWNER',
  ADMIN = 'ADMIN',
  MEMBER = 'MEMBER'
}
```

### Permission Capabilities

```typescript
interface PermissionCapabilities {
  readonly canView: boolean;
  readonly canExecute: boolean;
  readonly canEdit: boolean;
  readonly canManagePermissions: boolean;
  readonly canDelete: boolean;
  readonly canTransferOwnership: boolean;
}

// Role → Capabilities mapping
const ROLE_CAPABILITIES: Record<PipelineRole, PermissionCapabilities> = {
  [PipelineRole.VIEWER]: {
    canView: true,
    canExecute: false,
    canEdit: false,
    canManagePermissions: false,
    canDelete: false,
    canTransferOwnership: false
  },
  [PipelineRole.EXECUTOR]: {
    canView: true,
    canExecute: true,
    canEdit: false,
    canManagePermissions: false,
    canDelete: false,
    canTransferOwnership: false
  },
  [PipelineRole.EDITOR]: {
    canView: true,
    canExecute: true,
    canEdit: true,
    canManagePermissions: false,
    canDelete: false,
    canTransferOwnership: false
  },
  [PipelineRole.ADMIN]: {
    canView: true,
    canExecute: true,
    canEdit: true,
    canManagePermissions: true,
    canDelete: false,
    canTransferOwnership: false
  },
  [PipelineRole.OWNER]: {
    canView: true,
    canExecute: true,
    canEdit: true,
    canManagePermissions: true,
    canDelete: true,
    canTransferOwnership: true
  }
};
```

---

## Permission Engine

### PermissionService

```typescript
interface PermissionService {
  // Check permissions
  getUserRole(pipelineId: string, userId: string): Promise<PipelineRole | null>;
  getEffectiveRole(pipelineId: string, userId: string): Promise<PipelineRole | null>;
  hasPermission(pipelineId: string, userId: string, capability: keyof PermissionCapabilities): Promise<boolean>;
  
  // Grant/revoke
  grantPermission(grant: PermissionGrant): Promise<PipelinePermission>;
  revokePermission(pipelineId: string, targetId: string, targetType: 'USER' | 'TEAM'): Promise<void>;
  updateRole(pipelineId: string, targetId: string, targetType: 'USER' | 'TEAM', newRole: PipelineRole): Promise<void>;
  
  // Bulk operations
  grantBulk(grants: readonly PermissionGrant[]): Promise<readonly PipelinePermission[]>;
  revokeBulk(revocations: readonly PermissionRevocation[]): Promise<void>;
  
  // Query
  listPermissions(pipelineId: string): Promise<readonly PermissionWithDetails[]>;
  listUserPipelines(userId: string): Promise<readonly PipelineWithRole[]>;
  
  // Ownership
  transferOwnership(pipelineId: string, newOwnerId: string): Promise<void>;
}

interface PermissionGrant {
  readonly pipelineId: string;
  readonly targetType: 'USER' | 'TEAM';
  readonly targetId: string;
  readonly role: PipelineRole;
  readonly expiresAt?: Date;
  readonly reason?: string;
}

interface PermissionRevocation {
  readonly pipelineId: string;
  readonly targetType: 'USER' | 'TEAM';
  readonly targetId: string;
  readonly reason?: string;
}

interface PermissionWithDetails extends PipelinePermission {
  readonly targetName: string;           // User/team name
  readonly targetEmail?: string;         // For users
  readonly targetAvatar?: string;
  readonly grantedByName: string;
}

interface PipelineWithRole {
  readonly pipeline: Pipeline;
  readonly role: PipelineRole;
  readonly grantType: 'DIRECT' | 'TEAM';
  readonly teamName?: string;
}
```

### Security Definer Functions

```typescript
// Backend implementation using SECURITY DEFINER pattern
interface SecurePermissionChecker {
  // Called from SECURITY DEFINER SQL function
  // Bypasses RLS to check permissions without recursion
  hasRole(userId: string, pipelineId: string, requiredRole: PipelineRole): boolean;
  
  // Get highest role from direct + team grants
  getEffectiveRole(userId: string, pipelineId: string): PipelineRole | null;
}
```

### SQL Security Functions

```sql
-- Check if user has at least the specified role
CREATE OR REPLACE FUNCTION has_pipeline_role(
  _user_id TEXT,
  _pipeline_id TEXT,
  _required_role TEXT
)
RETURNS BOOLEAN
LANGUAGE SQL
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  WITH user_teams AS (
    SELECT TeamId FROM TeamMember WHERE UserId = _user_id
  ),
  all_roles AS (
    -- Direct grants
    SELECT Role FROM PipelinePermission 
    WHERE PipelineId = _pipeline_id 
      AND UserId = _user_id
      AND (ExpiresAt IS NULL OR ExpiresAt > datetime('now'))
    UNION
    -- Team grants
    SELECT Role FROM PipelinePermission
    WHERE PipelineId = _pipeline_id
      AND TeamId IN (SELECT TeamId FROM user_teams)
      AND (ExpiresAt IS NULL OR ExpiresAt > datetime('now'))
  )
  SELECT EXISTS (
    SELECT 1 FROM all_roles
    WHERE CASE Role
      WHEN 'OWNER' THEN 5
      WHEN 'ADMIN' THEN 4
      WHEN 'EDITOR' THEN 3
      WHEN 'EXECUTOR' THEN 2
      WHEN 'VIEWER' THEN 1
      ELSE 0
    END >= CASE _required_role
      WHEN 'OWNER' THEN 5
      WHEN 'ADMIN' THEN 4
      WHEN 'EDITOR' THEN 3
      WHEN 'EXECUTOR' THEN 2
      WHEN 'VIEWER' THEN 1
      ELSE 0
    END
  )
$$;

-- Get user's effective role (highest from all grants)
CREATE OR REPLACE FUNCTION get_pipeline_role(
  _user_id TEXT,
  _pipeline_id TEXT
)
RETURNS TEXT
LANGUAGE SQL
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  WITH user_teams AS (
    SELECT TeamId FROM TeamMember WHERE UserId = _user_id
  ),
  all_roles AS (
    SELECT Role, 
      CASE Role
        WHEN 'OWNER' THEN 5
        WHEN 'ADMIN' THEN 4
        WHEN 'EDITOR' THEN 3
        WHEN 'EXECUTOR' THEN 2
        WHEN 'VIEWER' THEN 1
        ELSE 0
      END as priority
    FROM PipelinePermission 
    WHERE PipelineId = _pipeline_id 
      AND (UserId = _user_id OR TeamId IN (SELECT TeamId FROM user_teams))
      AND (ExpiresAt IS NULL OR ExpiresAt > datetime('now'))
  )
  SELECT Role FROM all_roles
  ORDER BY priority DESC
  LIMIT 1
$$;
```

---

## React Components

### PermissionManager

```typescript
interface PermissionManagerProps {
  readonly pipelineId: string;
  readonly currentUserRole: PipelineRole;
}

const PermissionManager: React.FC<PermissionManagerProps> = ({
  pipelineId,
  currentUserRole
}) => {
  const [isAddOpen, setIsAddOpen] = useState(false);
  
  const canManage = ROLE_CAPABILITIES[currentUserRole].canManagePermissions;
  
  // Query permissions
  const { data: permissions } = useQuery({
    queryKey: ['pipeline-permissions', pipelineId],
    queryFn: () => fetchPipelinePermissions(pipelineId)
  });
  
  // Group by type
  const { users, teams } = useMemo(() => ({
    users: permissions?.filter(p => p.userId !== null) ?? [],
    teams: permissions?.filter(p => p.teamId !== null) ?? []
  }), [permissions]);
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold">Permissions</h3>
          <p className="text-sm text-muted-foreground">
            Manage who can access this pipeline
          </p>
        </div>
        {canManage && (
          <Button onClick={() => setIsAddOpen(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Add
          </Button>
        )}
      </div>
      
      {/* User permissions */}
      <div className="space-y-2">
        <h4 className="text-sm font-medium flex items-center gap-2">
          <User className="h-4 w-4" />
          Users ({users.length})
        </h4>
        {users.map(permission => (
          <PermissionRow
            key={permission.id}
            permission={permission}
            canEdit={canManage}
            canRemove={canManage && permission.role !== PipelineRole.OWNER}
            onRoleChange={(role) => updateRole(permission.id, role)}
            onRemove={() => revokePermission(permission.id)}
          />
        ))}
      </div>
      
      {/* Team permissions */}
      <div className="space-y-2">
        <h4 className="text-sm font-medium flex items-center gap-2">
          <Users className="h-4 w-4" />
          Teams ({teams.length})
        </h4>
        {teams.map(permission => (
          <PermissionRow
            key={permission.id}
            permission={permission}
            canEdit={canManage}
            canRemove={canManage}
            onRoleChange={(role) => updateRole(permission.id, role)}
            onRemove={() => revokePermission(permission.id)}
          />
        ))}
      </div>
      
      {/* Add permission dialog */}
      <AddPermissionDialog
        pipelineId={pipelineId}
        open={isAddOpen}
        onOpenChange={setIsAddOpen}
        existingUserIds={users.map(u => u.userId!)}
        existingTeamIds={teams.map(t => t.teamId!)}
      />
    </div>
  );
};
```

### PermissionRow

```typescript
interface PermissionRowProps {
  readonly permission: PermissionWithDetails;
  readonly canEdit: boolean;
  readonly canRemove: boolean;
  readonly onRoleChange: (role: PipelineRole) => void;
  readonly onRemove: () => void;
}

const PermissionRow: React.FC<PermissionRowProps> = ({
  permission,
  canEdit,
  canRemove,
  onRoleChange,
  onRemove
}) => {
  const isExpired = permission.expiresAt && new Date(permission.expiresAt) < new Date();
  
  return (
    <div className={cn(
      "flex items-center justify-between p-3 rounded-lg border",
      isExpired && "opacity-50"
    )}>
      <div className="flex items-center gap-3">
        <Avatar className="h-8 w-8">
          <AvatarImage src={permission.targetAvatar} />
          <AvatarFallback>
            {permission.targetName.charAt(0).toUpperCase()}
          </AvatarFallback>
        </Avatar>
        <div>
          <div className="flex items-center gap-2">
            <span className="font-medium text-sm">{permission.targetName}</span>
            {permission.role === PipelineRole.OWNER && (
              <Badge variant="default" className="text-xs">Owner</Badge>
            )}
            {isExpired && (
              <Badge variant="destructive" className="text-xs">Expired</Badge>
            )}
          </div>
          {permission.targetEmail && (
            <span className="text-xs text-muted-foreground">
              {permission.targetEmail}
            </span>
          )}
        </div>
      </div>
      
      <div className="flex items-center gap-2">
        {/* Role selector */}
        {canEdit && permission.role !== PipelineRole.OWNER ? (
          <Select
            value={permission.role}
            onValueChange={(v) => onRoleChange(v as PipelineRole)}
          >
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {Object.values(PipelineRole)
                .filter(r => r !== PipelineRole.OWNER)
                .map(role => (
                  <SelectItem key={role} value={role}>
                    {formatRole(role)}
                  </SelectItem>
                ))}
            </SelectContent>
          </Select>
        ) : (
          <Badge variant="outline">{formatRole(permission.role)}</Badge>
        )}
        
        {/* Remove button */}
        {canRemove && (
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-destructive"
            onClick={onRemove}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        )}
      </div>
    </div>
  );
};
```

### usePermissions Hook

```typescript
interface UsePermissionsResult {
  readonly role: PipelineRole | null;
  readonly capabilities: PermissionCapabilities | null;
  readonly isLoading: boolean;
  readonly canView: boolean;
  readonly canExecute: boolean;
  readonly canEdit: boolean;
  readonly canManagePermissions: boolean;
  readonly canDelete: boolean;
}

function usePermissions(pipelineId: string): UsePermissionsResult {
  const { data: currentUser } = useCurrentUser();
  
  const { data: role, isLoading } = useQuery({
    queryKey: ['pipeline-role', pipelineId, currentUser?.id],
    queryFn: () => fetchUserRole(pipelineId, currentUser!.id),
    enabled: !!currentUser
  });
  
  const capabilities = role ? ROLE_CAPABILITIES[role] : null;
  
  return {
    role,
    capabilities,
    isLoading,
    canView: capabilities?.canView ?? false,
    canExecute: capabilities?.canExecute ?? false,
    canEdit: capabilities?.canEdit ?? false,
    canManagePermissions: capabilities?.canManagePermissions ?? false,
    canDelete: capabilities?.canDelete ?? false
  };
}
```

---

## API Endpoints

```typescript
// Permissions
GET    /api/pipelines/:id/permissions       // List all permissions
POST   /api/pipelines/:id/permissions       // Grant permission
PUT    /api/pipelines/:id/permissions/:pid  // Update permission role
DELETE /api/pipelines/:id/permissions/:pid  // Revoke permission

// Role check
GET    /api/pipelines/:id/my-role           // Get current user's role
POST   /api/pipelines/:id/check-permission  // Check specific capability

// Ownership
POST   /api/pipelines/:id/transfer          // Transfer ownership

// Teams
GET    /api/teams                           // List user's teams
POST   /api/teams                           // Create team
GET    /api/teams/:id                       // Get team details
PUT    /api/teams/:id                       // Update team
DELETE /api/teams/:id                       // Delete team
GET    /api/teams/:id/members               // List team members
POST   /api/teams/:id/members               // Add team member
DELETE /api/teams/:id/members/:uid          // Remove team member

// Audit
GET    /api/pipelines/:id/permissions/audit // Permission change history
```

---

## See Also

- [Sharing](./23-sharing.md) — Share links and invitations
- [Collaboration](./24-collaboration.md) — Real-time editing
- [Version Control](./21-version-control.md) — Branch permissions
