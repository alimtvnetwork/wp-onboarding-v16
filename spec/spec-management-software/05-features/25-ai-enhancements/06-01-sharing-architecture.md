# Phase 6.1: Sharing Architecture

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Cross-Project Memory](./06-cross-project-memory.md)

---

## Overview

Architecture for sharing specs, folders, files, and knowledge items between projects with permission-based access control, versioning, and conflict resolution.

---

## 1. Sharing Model

### 1.1 Share Hierarchy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SHARING ARCHITECTURE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐                    ┌──────────────────┐              │
│  │  SOURCE PROJECT  │                    │  TARGET PROJECT  │              │
│  │                  │                    │                  │              │
│  │  ┌────────────┐  │    Share Link      │  ┌────────────┐  │              │
│  │  │ Spec File  │──┼────────────────────┼──│ Reference  │  │              │
│  │  └────────────┘  │                    │  └────────────┘  │              │
│  │                  │                    │                  │              │
│  │  ┌────────────┐  │    Share Link      │  ┌────────────┐  │              │
│  │  │  Folder    │──┼────────────────────┼──│ Reference  │  │              │
│  │  │  ├─ spec1  │  │                    │  └────────────┘  │              │
│  │  │  └─ spec2  │  │                    │                  │              │
│  │  └────────────┘  │                    │                  │              │
│  │                  │                    │                  │              │
│  │  ┌────────────┐  │    Share Link      │  ┌────────────┐  │              │
│  │  │  Memory    │──┼────────────────────┼──│ Reference  │  │              │
│  │  └────────────┘  │                    │  └────────────┘  │              │
│  │                  │                    │                  │              │
│  └──────────────────┘                    └──────────────────┘              │
│                                                                             │
│  Permissions: READ │ COPY │ SYNC                                            │
│  Access: PRIVATE │ PROJECT │ WORKSPACE │ PUBLIC                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Data Models

```typescript
// types/sharing.ts

/**
 * Core share record representing a shared resource between projects
 */
export interface MemoryShare {
  id: string;
  
  // Source information
  sourceProjectId: string;
  sourceProjectName: string;
  sourceWorkspaceId: string;
  
  // Target information
  targetProjectId: string;
  targetProjectName: string;
  targetWorkspaceId: string;
  
  // Resource details
  resourceType: ShareResourceType;
  resourcePath: string;
  resourceName: string;
  resourceHash: string; // Content hash for change detection
  
  // Access control
  permissions: SharePermission;
  accessLevel: ShareAccessLevel;
  
  // User tracking
  sharedBy: string;
  sharedByEmail: string;
  acceptedBy?: string;
  
  // Timestamps
  createdAt: Date;
  updatedAt: Date;
  expiresAt?: Date;
  
  // State
  status: ShareStatus;
  syncState?: SyncState;
}

export type ShareResourceType = 
  | 'spec'           // Single spec file
  | 'folder'         // Folder with all contents
  | 'file'           // Non-spec file (image, etc.)
  | 'url'            // URL reference
  | 'memory'         // Knowledge memory item
  | 'collection';    // Curated collection of items

export type SharePermission = 
  | 'read'           // View only, use in AI context
  | 'copy'           // Can duplicate to local project
  | 'sync'           // Bi-directional sync
  | 'edit';          // Can edit source (for collaborators)

export type ShareAccessLevel =
  | 'private'        // Only specific project
  | 'project'        // Anyone with project access
  | 'workspace'      // Anyone in workspace
  | 'public';        // Anyone with link

export type ShareStatus =
  | 'pending'        // Awaiting acceptance
  | 'active'         // Currently active
  | 'paused'         // Temporarily disabled
  | 'expired'        // Past expiration date
  | 'revoked';       // Permanently disabled

export interface SyncState {
  lastSyncedAt: Date;
  sourceVersion: number;
  targetVersion: number;
  hasConflict: boolean;
  conflictDetails?: ConflictInfo;
}

export interface ConflictInfo {
  sourceModifiedAt: Date;
  targetModifiedAt: Date;
  sourceModifiedBy: string;
  targetModifiedBy: string;
  conflictType: 'content' | 'delete' | 'rename';
}

/**
 * Share invitation for pending shares
 */
export interface ShareInvitation {
  id: string;
  shareId: string;
  inviteeEmail: string;
  inviteeProjectId?: string;
  message?: string;
  createdAt: Date;
  expiresAt: Date;
  acceptedAt?: Date;
  declinedAt?: Date;
}

/**
 * Collection of curated items for sharing
 */
export interface ShareCollection {
  id: string;
  name: string;
  description?: string;
  projectId: string;
  items: ShareCollectionItem[];
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface ShareCollectionItem {
  resourceType: ShareResourceType;
  resourcePath: string;
  resourceName: string;
  order: number;
  notes?: string;
}
```

### 1.3 Database Schema

```sql
-- Core shares table
CREATE TABLE IF NOT EXISTS memory_shares (
  id TEXT PRIMARY KEY,
  
  -- Source
  source_project_id TEXT NOT NULL,
  source_project_name TEXT NOT NULL,
  source_workspace_id TEXT NOT NULL,
  
  -- Target
  target_project_id TEXT NOT NULL,
  target_project_name TEXT NOT NULL,
  target_workspace_id TEXT NOT NULL,
  
  -- Resource
  resource_type TEXT NOT NULL 
    CHECK (resource_type IN ('spec', 'folder', 'file', 'url', 'memory', 'collection')),
  resource_path TEXT NOT NULL,
  resource_name TEXT NOT NULL,
  resource_hash TEXT,
  
  -- Access
  permissions TEXT DEFAULT 'read' 
    CHECK (permissions IN ('read', 'copy', 'sync', 'edit')),
  access_level TEXT DEFAULT 'private'
    CHECK (access_level IN ('private', 'project', 'workspace', 'public')),
  
  -- Users
  shared_by TEXT NOT NULL,
  shared_by_email TEXT NOT NULL,
  accepted_by TEXT,
  
  -- Timestamps
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  
  -- State
  status TEXT DEFAULT 'active'
    CHECK (status IN ('pending', 'active', 'paused', 'expired', 'revoked')),
  
  -- Constraints
  UNIQUE(source_project_id, target_project_id, resource_path),
  FOREIGN KEY (source_project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (target_project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Indexes for efficient queries
CREATE INDEX idx_shares_source_project ON memory_shares(source_project_id);
CREATE INDEX idx_shares_target_project ON memory_shares(target_project_id);
CREATE INDEX idx_shares_source_workspace ON memory_shares(source_workspace_id);
CREATE INDEX idx_shares_target_workspace ON memory_shares(target_workspace_id);
CREATE INDEX idx_shares_status ON memory_shares(status);
CREATE INDEX idx_shares_type ON memory_shares(resource_type);
CREATE INDEX idx_shares_shared_by ON memory_shares(shared_by);

-- Sync state tracking
CREATE TABLE IF NOT EXISTS share_sync_state (
  share_id TEXT PRIMARY KEY,
  last_synced_at DATETIME,
  source_version INTEGER DEFAULT 1,
  target_version INTEGER DEFAULT 1,
  has_conflict BOOLEAN DEFAULT FALSE,
  conflict_details TEXT, -- JSON
  FOREIGN KEY (share_id) REFERENCES memory_shares(id) ON DELETE CASCADE
);

-- Share invitations
CREATE TABLE IF NOT EXISTS share_invitations (
  id TEXT PRIMARY KEY,
  share_id TEXT NOT NULL,
  invitee_email TEXT NOT NULL,
  invitee_project_id TEXT,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME,
  declined_at DATETIME,
  FOREIGN KEY (share_id) REFERENCES memory_shares(id) ON DELETE CASCADE
);

CREATE INDEX idx_invitations_email ON share_invitations(invitee_email);
CREATE INDEX idx_invitations_expires ON share_invitations(expires_at);

-- Share collections
CREATE TABLE IF NOT EXISTS share_collections (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT,
  project_id TEXT NOT NULL,
  created_by TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS share_collection_items (
  id TEXT PRIMARY KEY,
  collection_id TEXT NOT NULL,
  resource_type TEXT NOT NULL,
  resource_path TEXT NOT NULL,
  resource_name TEXT NOT NULL,
  item_order INTEGER DEFAULT 0,
  notes TEXT,
  FOREIGN KEY (collection_id) REFERENCES share_collections(id) ON DELETE CASCADE
);

-- Cached content for offline access
CREATE TABLE IF NOT EXISTS share_content_cache (
  share_id TEXT PRIMARY KEY,
  content TEXT NOT NULL,
  content_hash TEXT NOT NULL,
  cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  FOREIGN KEY (share_id) REFERENCES memory_shares(id) ON DELETE CASCADE
);

-- Audit log for share activities
CREATE TABLE IF NOT EXISTS share_audit_log (
  id TEXT PRIMARY KEY,
  share_id TEXT NOT NULL,
  action TEXT NOT NULL,
  actor_id TEXT NOT NULL,
  actor_email TEXT NOT NULL,
  details TEXT, -- JSON
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (share_id) REFERENCES memory_shares(id) ON DELETE CASCADE
);

CREATE INDEX idx_audit_share ON share_audit_log(share_id);
CREATE INDEX idx_audit_actor ON share_audit_log(actor_id);
CREATE INDEX idx_audit_created ON share_audit_log(created_at);
```

---

## 2. Backend Services

### 2.1 Share Service

```go
// internal/sharing/share_service.go

package sharing

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"time"

	"specmgmt/internal/db"
	"specmgmt/internal/files"
	"specmgmt/internal/projects"
)

type ShareService struct {
	db       *db.DB
	files    *files.Service
	projects *projects.Service
	sync     *SyncService
	audit    *AuditService
}

func NewShareService(
	db *db.DB,
	files *files.Service,
	projects *projects.Service,
) *ShareService {
	svc := &ShareService{
		db:       db,
		files:    files,
		projects: projects,
	}
	svc.sync = NewSyncService(db, files)
	svc.audit = NewAuditService(db)
	return svc
}

// CreateShare establishes a new share between projects
func (s *ShareService) CreateShare(ctx context.Context, req CreateShareRequest) (*MemoryShare, error) {
	// Validate source resource exists
	exists, err := s.files.Exists(ctx, req.SourceProjectID, req.ResourcePath)
	if err != nil || !exists {
		return nil, fmt.Errorf("resource not found: %s", req.ResourcePath)
	}

	// Validate user has permission on source project
	hasAccess, err := s.projects.HasAccess(ctx, req.UserID, req.SourceProjectID, "admin")
	if err != nil || !hasAccess {
		return nil, fmt.Errorf("insufficient permissions on source project")
	}

	// Get project names for denormalization
	sourceProject, _ := s.projects.Get(ctx, req.SourceProjectID)
	targetProject, _ := s.projects.Get(ctx, req.TargetProjectID)

	// Calculate content hash
	content, err := s.files.GetContent(ctx, req.SourceProjectID, req.ResourcePath)
	if err != nil {
		return nil, fmt.Errorf("failed to read content: %w", err)
	}
	hash := s.hashContent(content)

	share := &MemoryShare{
		ID:                generateID(),
		SourceProjectID:   req.SourceProjectID,
		SourceProjectName: sourceProject.Name,
		SourceWorkspaceID: sourceProject.WorkspaceID,
		TargetProjectID:   req.TargetProjectID,
		TargetProjectName: targetProject.Name,
		TargetWorkspaceID: targetProject.WorkspaceID,
		ResourceType:      req.ResourceType,
		ResourcePath:      req.ResourcePath,
		ResourceName:      req.ResourceName,
		ResourceHash:      hash,
		Permissions:       req.Permissions,
		AccessLevel:       req.AccessLevel,
		SharedBy:          req.UserID,
		SharedByEmail:     req.UserEmail,
		CreatedAt:         time.Now(),
		UpdatedAt:         time.Now(),
		Status:            "active",
	}

	// Handle expiration
	if req.ExpiresIn > 0 {
		expiry := time.Now().Add(req.ExpiresIn)
		share.ExpiresAt = &expiry
	}

	// Insert share
	if err := s.insertShare(ctx, share); err != nil {
		return nil, fmt.Errorf("failed to create share: %w", err)
	}

	// Initialize sync state if sync permission
	if req.Permissions == "sync" {
		if err := s.sync.InitializeSyncState(ctx, share.ID); err != nil {
			// Log but don't fail
			fmt.Printf("Warning: failed to init sync state: %v\n", err)
		}
	}

	// Cache content for read shares
	if err := s.cacheContent(ctx, share.ID, content, hash); err != nil {
		fmt.Printf("Warning: failed to cache content: %v\n", err)
	}

	// Audit log
	s.audit.Log(ctx, share.ID, "created", req.UserID, req.UserEmail, map[string]interface{}{
		"permissions":  req.Permissions,
		"access_level": req.AccessLevel,
	})

	return share, nil
}

// GetSharesForProject returns all shares where project is target
func (s *ShareService) GetSharesForProject(ctx context.Context, projectID string) ([]MemoryShare, error) {
	query := `
		SELECT * FROM memory_shares 
		WHERE target_project_id = ? AND status = 'active'
		ORDER BY created_at DESC
	`
	return s.queryShares(ctx, query, projectID)
}

// GetSharesFromProject returns all shares where project is source
func (s *ShareService) GetSharesFromProject(ctx context.Context, projectID string) ([]MemoryShare, error) {
	query := `
		SELECT * FROM memory_shares 
		WHERE source_project_id = ? AND status != 'revoked'
		ORDER BY created_at DESC
	`
	return s.queryShares(ctx, query, projectID)
}

// GetShareContent retrieves the actual content of a shared resource
func (s *ShareService) GetShareContent(ctx context.Context, shareID, userID string) (string, error) {
	share, err := s.GetShare(ctx, shareID)
	if err != nil {
		return "", err
	}

	// Validate user has access
	if err := s.validateAccess(ctx, share, userID); err != nil {
		return "", err
	}

	// Try cache first
	if cached, err := s.getCachedContent(ctx, shareID); err == nil {
		return cached, nil
	}

	// Fetch from source
	content, err := s.files.GetContent(ctx, share.SourceProjectID, share.ResourcePath)
	if err != nil {
		return "", fmt.Errorf("failed to fetch content: %w", err)
	}

	// Update cache
	hash := s.hashContent(content)
	s.cacheContent(ctx, shareID, content, hash)

	return content, nil
}

// RevokeShare permanently disables a share
func (s *ShareService) RevokeShare(ctx context.Context, shareID, userID, userEmail string) error {
	share, err := s.GetShare(ctx, shareID)
	if err != nil {
		return err
	}

	// Only sharer can revoke
	if share.SharedBy != userID {
		// Check if user is project admin
		hasAccess, _ := s.projects.HasAccess(ctx, userID, share.SourceProjectID, "admin")
		if !hasAccess {
			return fmt.Errorf("insufficient permissions to revoke share")
		}
	}

	_, err = s.db.ExecContext(ctx, `
		UPDATE memory_shares 
		SET status = 'revoked', updated_at = ?
		WHERE id = ?
	`, time.Now(), shareID)

	if err == nil {
		s.audit.Log(ctx, shareID, "revoked", userID, userEmail, nil)
	}

	return err
}

// UpdatePermissions changes share permissions
func (s *ShareService) UpdatePermissions(ctx context.Context, shareID, userID string, permissions string) error {
	share, err := s.GetShare(ctx, shareID)
	if err != nil {
		return err
	}

	if share.SharedBy != userID {
		return fmt.Errorf("only the sharer can update permissions")
	}

	_, err = s.db.ExecContext(ctx, `
		UPDATE memory_shares 
		SET permissions = ?, updated_at = ?
		WHERE id = ?
	`, permissions, time.Now(), shareID)

	// Initialize sync if upgrading to sync
	if permissions == "sync" && share.Permissions != "sync" {
		s.sync.InitializeSyncState(ctx, shareID)
	}

	return err
}

func (s *ShareService) hashContent(content string) string {
	hash := sha256.Sum256([]byte(content))
	return hex.EncodeToString(hash[:])
}

func (s *ShareService) cacheContent(ctx context.Context, shareID, content, hash string) error {
	expires := time.Now().Add(24 * time.Hour)
	_, err := s.db.ExecContext(ctx, `
		INSERT OR REPLACE INTO share_content_cache 
		(share_id, content, content_hash, cached_at, expires_at)
		VALUES (?, ?, ?, ?, ?)
	`, shareID, content, hash, time.Now(), expires)
	return err
}
```

### 2.2 Permission Validator

```go
// internal/sharing/permission_validator.go

package sharing

import (
	"context"
	"fmt"
)

type PermissionValidator struct {
	shares   *ShareService
	projects *projects.Service
}

// ValidateAccess checks if a user can access a share
func (v *PermissionValidator) ValidateAccess(
	ctx context.Context,
	share *MemoryShare,
	userID string,
	requiredPermission string,
) error {
	// Check share status
	if share.Status != "active" {
		return fmt.Errorf("share is not active: %s", share.Status)
	}

	// Check expiration
	if share.ExpiresAt != nil && time.Now().After(*share.ExpiresAt) {
		return fmt.Errorf("share has expired")
	}

	// Check access level
	switch share.AccessLevel {
	case "public":
		// Anyone can access
		return nil
		
	case "workspace":
		// Check workspace membership
		hasAccess, _ := v.projects.IsWorkspaceMember(ctx, userID, share.TargetWorkspaceID)
		if hasAccess {
			return nil
		}
		return fmt.Errorf("not a workspace member")
		
	case "project":
		// Check project membership
		hasAccess, _ := v.projects.HasAccess(ctx, userID, share.TargetProjectID, "viewer")
		if hasAccess {
			return nil
		}
		return fmt.Errorf("not a project member")
		
	case "private":
		// Check specific project access
		hasAccess, _ := v.projects.HasAccess(ctx, userID, share.TargetProjectID, "viewer")
		if hasAccess {
			return nil
		}
		return fmt.Errorf("access denied")
	}

	return fmt.Errorf("unknown access level")
}

// CanPerformAction checks if user can perform specific action
func (v *PermissionValidator) CanPerformAction(
	share *MemoryShare,
	action string,
) bool {
	switch action {
	case "read":
		return true // All permission levels can read
		
	case "copy":
		return share.Permissions == "copy" || 
			   share.Permissions == "sync" || 
			   share.Permissions == "edit"
		
	case "sync":
		return share.Permissions == "sync" || share.Permissions == "edit"
		
	case "edit":
		return share.Permissions == "edit"
	}
	
	return false
}
```

---

## 3. Frontend Components

### 3.1 Share Dialog

```typescript
// components/sharing/ShareDialog.tsx

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Share2, Link, Users, Globe, Lock, Calendar } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import type { SharePermission, ShareAccessLevel, ShareResourceType } from '@/types/sharing';

interface ShareDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  sourceProjectId: string;
  resource: {
    type: ShareResourceType;
    path: string;
    name: string;
  };
}

export function ShareDialog({
  open,
  onOpenChange,
  sourceProjectId,
  resource,
}: ShareDialogProps) {
  const [shareType, setShareType] = useState<'project' | 'link'>('project');
  const [targetProjectId, setTargetProjectId] = useState('');
  const [permissions, setPermissions] = useState<SharePermission>('read');
  const [accessLevel, setAccessLevel] = useState<ShareAccessLevel>('private');
  const [hasExpiry, setHasExpiry] = useState(false);
  const [expiryDays, setExpiryDays] = useState(30);
  
  const queryClient = useQueryClient();
  const { toast } = useToast();
  
  // Fetch available projects
  const { data: projects = [] } = useQuery({
    queryKey: ['projects'],
    queryFn: async () => {
      const res = await fetch('/api/v1/projects');
      return res.json();
    },
  });
  
  const targetProjects = projects.filter((p: any) => p.id !== sourceProjectId);
  
  // Create share mutation
  const createShare = useMutation({
    mutationFn: async () => {
      const body: any = {
        source_project_id: sourceProjectId,
        resource_type: resource.type,
        resource_path: resource.path,
        resource_name: resource.name,
        permissions,
        access_level: accessLevel,
      };
      
      if (shareType === 'project') {
        body.target_project_id = targetProjectId;
      }
      
      if (hasExpiry) {
        body.expires_in_days = expiryDays;
      }
      
      const res = await fetch('/api/v1/sharing/shares', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      
      if (!res.ok) {
        const error = await res.text();
        throw new Error(error);
      }
      
      return res.json();
    },
    onSuccess: (data) => {
      toast({ title: 'Shared successfully' });
      queryClient.invalidateQueries({ queryKey: ['shares'] });
      onOpenChange(false);
      
      // Copy link if link share
      if (shareType === 'link' && data.shareUrl) {
        navigator.clipboard.writeText(data.shareUrl);
        toast({ title: 'Link copied to clipboard' });
      }
    },
    onError: (error) => {
      toast({
        variant: 'destructive',
        title: 'Failed to share',
        description: error.message,
      });
    },
  });
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Share2 className="h-5 w-5" />
            Share {resource.type}
          </DialogTitle>
          <DialogDescription>
            Share "{resource.name}" with another project or create a shareable link.
          </DialogDescription>
        </DialogHeader>
        
        <Tabs value={shareType} onValueChange={(v) => setShareType(v as 'project' | 'link')}>
          <TabsList className="grid grid-cols-2">
            <TabsTrigger value="project" className="gap-2">
              <Users className="h-4 w-4" />
              Share to Project
            </TabsTrigger>
            <TabsTrigger value="link" className="gap-2">
              <Link className="h-4 w-4" />
              Create Link
            </TabsTrigger>
          </TabsList>
          
          <TabsContent value="project" className="space-y-4 mt-4">
            <div className="space-y-2">
              <Label>Target Project</Label>
              <Select value={targetProjectId} onValueChange={setTargetProjectId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select a project" />
                </SelectTrigger>
                <SelectContent>
                  {targetProjects.map((project: any) => (
                    <SelectItem key={project.id} value={project.id}>
                      {project.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </TabsContent>
          
          <TabsContent value="link" className="space-y-4 mt-4">
            <div className="space-y-2">
              <Label>Access Level</Label>
              <RadioGroup value={accessLevel} onValueChange={(v) => setAccessLevel(v as ShareAccessLevel)}>
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="workspace" id="workspace" />
                  <Label htmlFor="workspace" className="flex items-center gap-2 font-normal">
                    <Users className="h-4 w-4" />
                    <div>
                      <span className="font-medium">Workspace</span>
                      <p className="text-xs text-muted-foreground">Anyone in your workspace</p>
                    </div>
                  </Label>
                </div>
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="public" id="public" />
                  <Label htmlFor="public" className="flex items-center gap-2 font-normal">
                    <Globe className="h-4 w-4" />
                    <div>
                      <span className="font-medium">Public</span>
                      <p className="text-xs text-muted-foreground">Anyone with the link</p>
                    </div>
                  </Label>
                </div>
              </RadioGroup>
            </div>
          </TabsContent>
        </Tabs>
        
        {/* Common options */}
        <div className="space-y-4 pt-4 border-t">
          {/* Permissions */}
          <div className="space-y-2">
            <Label>Permissions</Label>
            <RadioGroup value={permissions} onValueChange={(v) => setPermissions(v as SharePermission)}>
              <div className="grid grid-cols-2 gap-2">
                <div className="flex items-center space-x-2 p-3 border rounded-lg">
                  <RadioGroupItem value="read" id="perm-read" />
                  <Label htmlFor="perm-read" className="font-normal cursor-pointer">
                    <span className="font-medium">Read</span>
                    <p className="text-xs text-muted-foreground">View and reference in AI</p>
                  </Label>
                </div>
                <div className="flex items-center space-x-2 p-3 border rounded-lg">
                  <RadioGroupItem value="copy" id="perm-copy" />
                  <Label htmlFor="perm-copy" className="font-normal cursor-pointer">
                    <span className="font-medium">Copy</span>
                    <p className="text-xs text-muted-foreground">Can duplicate locally</p>
                  </Label>
                </div>
                <div className="flex items-center space-x-2 p-3 border rounded-lg">
                  <RadioGroupItem value="sync" id="perm-sync" />
                  <Label htmlFor="perm-sync" className="font-normal cursor-pointer">
                    <span className="font-medium">Sync</span>
                    <p className="text-xs text-muted-foreground">Auto-sync changes</p>
                  </Label>
                </div>
              </div>
            </RadioGroup>
          </div>
          
          {/* Expiration */}
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label className="flex items-center gap-2">
                <Calendar className="h-4 w-4" />
                Set expiration
              </Label>
              <p className="text-xs text-muted-foreground">
                Share will expire after specified days
              </p>
            </div>
            <Switch checked={hasExpiry} onCheckedChange={setHasExpiry} />
          </div>
          
          {hasExpiry && (
            <div className="flex items-center gap-2">
              <Input
                type="number"
                min={1}
                max={365}
                value={expiryDays}
                onChange={(e) => setExpiryDays(Number(e.target.value))}
                className="w-20"
              />
              <span className="text-sm text-muted-foreground">days</span>
            </div>
          )}
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            onClick={() => createShare.mutate()}
            disabled={
              createShare.isPending ||
              (shareType === 'project' && !targetProjectId)
            }
          >
            {shareType === 'link' ? 'Create Link' : 'Share'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 4. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Create share | Share created with correct data | Critical |
| Permission validation | Access denied without permission | Critical |
| Cross-workspace share | Shares work across workspaces | High |
| Share expiration | Expired shares inaccessible | High |
| Revoke share | Revoked shares stop working | High |
| Content caching | Cache speeds up access | Medium |
| Audit logging | All actions logged | Medium |

---

## Related Specs

- [Sync Mechanism](./06-02-sync-mechanism.md)
- [RAG Integration](./06-03-rag-integration.md)
- [UI Components](./06-04-sharing-ui.md)
