# Chronicle Service OpenAPI Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Port:** 8083  
**Error Range:** 4xxx  

---

## Overview

The Chronicle service manages version control, commit history, and audit trails for all specifications. It provides Git-like semantics with optional local Git integration, Myers-based diff generation, and full rollback capabilities.

**Cross-References:**
- [Chronicle Service Spec](./03-chronicle.md)
- [Shared Packages](../13-shared-packages/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)

---

## OpenAPI 3.1.0 Specification

```yaml
openapi: 3.1.0
info:
  title: Chronicle Service API
  description: Version control and audit history for specifications
  version: 1.0.0
  contact:
    name: SpecBuilder Pro
    
servers:
  - url: http://localhost:8083
    description: Development server
  - url: http://chronicle:8083
    description: Docker network

tags:
  - name: Commits
    description: Commit management operations
  - name: History
    description: History browsing and search
  - name: Diff
    description: Diff generation and comparison
  - name: Rollback
    description: Rollback and restore operations
  - name: Audit
    description: Audit trail and compliance
  - name: Git
    description: Optional Git integration
  - name: Health
    description: Service health endpoints

paths:
  # ============================================================
  # COMMIT OPERATIONS
  # ============================================================
  
  /api/v1/projects/{projectId}/commits:
    post:
      operationId: createCommit
      summary: Create a new commit
      description: |
        Creates a new commit capturing the current state of specified files.
        Automatically generates diffs against the parent commit.
      tags: [Commits]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateCommitRequest'
      responses:
        '201':
          description: Commit created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitResponse'
        '400':
          $ref: '#/components/responses/ValidationError'
        '409':
          description: Conflict - concurrent modification detected
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
    
    get:
      operationId: listCommits
      summary: List commits for a project
      description: Returns paginated commit history with filtering options
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Cursor'
        - $ref: '#/components/parameters/Limit'
        - name: file_path
          in: query
          description: Filter commits affecting a specific file
          schema:
            type: string
        - name: author
          in: query
          description: Filter by author
          schema:
            type: string
        - name: since
          in: query
          description: Commits after this timestamp (RFC3339)
          schema:
            type: string
            format: date-time
        - name: until
          in: query
          description: Commits before this timestamp (RFC3339)
          schema:
            type: string
            format: date-time
        - name: message
          in: query
          description: Full-text search in commit messages
          schema:
            type: string
      responses:
        '200':
          description: Commit list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitListResponse'

  /api/v1/projects/{projectId}/commits/{commitId}:
    get:
      operationId: getCommit
      summary: Get commit details
      description: Returns full commit details including changed files and stats
      tags: [Commits]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/CommitId'
      responses:
        '200':
          description: Commit details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitDetailResponse'
        '404':
          $ref: '#/components/responses/NotFound'

  /api/v1/projects/{projectId}/commits/{commitId}/files:
    get:
      operationId: getCommitFiles
      summary: List files in a commit
      description: Returns all files changed in a specific commit
      tags: [Commits]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/CommitId'
      responses:
        '200':
          description: Changed files
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitFilesResponse'

  /api/v1/projects/{projectId}/commits/{commitId}/files/{filePath}:
    get:
      operationId: getFileAtCommit
      summary: Get file content at commit
      description: Returns the content of a file as it existed at a specific commit
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/CommitId'
        - name: filePath
          in: path
          required: true
          description: File path (URL-encoded)
          schema:
            type: string
      responses:
        '200':
          description: File content
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileContentResponse'
            text/plain:
              schema:
                type: string
        '404':
          $ref: '#/components/responses/NotFound'

  # ============================================================
  # DIFF OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/diff:
    post:
      operationId: generateDiff
      summary: Generate diff between two states
      description: |
        Creates a Myers-based diff between two commits, a commit and working tree,
        or two arbitrary content strings.
      tags: [Diff]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/DiffRequest'
      responses:
        '200':
          description: Diff result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DiffResponse'

  /api/v1/projects/{projectId}/commits/{commitId}/diff:
    get:
      operationId: getCommitDiff
      summary: Get diff for a commit
      description: Returns the diff between a commit and its parent
      tags: [Diff]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/CommitId'
        - name: context_lines
          in: query
          description: Number of context lines around changes
          schema:
            type: integer
            default: 3
            minimum: 0
            maximum: 10
        - name: format
          in: query
          description: Diff output format
          schema:
            type: string
            enum: [unified, split, json]
            default: unified
      responses:
        '200':
          description: Commit diff
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitDiffResponse'

  /api/v1/projects/{projectId}/files/{filePath}/history:
    get:
      operationId: getFileHistory
      summary: Get file change history
      description: Returns all commits that modified a specific file with diffs
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: filePath
          in: path
          required: true
          schema:
            type: string
        - $ref: '#/components/parameters/Cursor'
        - $ref: '#/components/parameters/Limit'
        - name: include_diffs
          in: query
          description: Include inline diffs for each commit
          schema:
            type: boolean
            default: false
      responses:
        '200':
          description: File history
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileHistoryResponse'

  # ============================================================
  # ROLLBACK OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/rollback:
    post:
      operationId: rollbackToCommit
      summary: Rollback to a specific commit
      description: |
        Reverts the project state to a previous commit.
        Creates a new rollback commit preserving history.
      tags: [Rollback]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RollbackRequest'
      responses:
        '200':
          description: Rollback successful
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RollbackResponse'
        '400':
          $ref: '#/components/responses/ValidationError'
        '409':
          description: Conflict - uncommitted changes exist
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

  /api/v1/projects/{projectId}/rollback/preview:
    post:
      operationId: previewRollback
      summary: Preview rollback changes
      description: Shows what would change if rolling back to a commit
      tags: [Rollback]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RollbackPreviewRequest'
      responses:
        '200':
          description: Rollback preview
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RollbackPreviewResponse'

  /api/v1/projects/{projectId}/files/{filePath}/restore:
    post:
      operationId: restoreFile
      summary: Restore a single file
      description: Restores a file to its state at a specific commit
      tags: [Rollback]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: filePath
          in: path
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RestoreFileRequest'
      responses:
        '200':
          description: File restored
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RestoreFileResponse'

  # ============================================================
  # AUDIT TRAIL
  # ============================================================

  /api/v1/projects/{projectId}/audit:
    get:
      operationId: getAuditLog
      summary: Get audit log
      description: Returns comprehensive audit trail for compliance
      tags: [Audit]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Cursor'
        - $ref: '#/components/parameters/Limit'
        - name: action
          in: query
          description: Filter by action type
          schema:
            type: string
            enum: [create, update, delete, rollback, restore, merge]
        - name: resource_type
          in: query
          description: Filter by resource type
          schema:
            type: string
            enum: [spec, file, commit, project]
        - name: actor
          in: query
          description: Filter by actor (user or system)
          schema:
            type: string
        - name: since
          in: query
          schema:
            type: string
            format: date-time
        - name: until
          in: query
          schema:
            type: string
            format: date-time
      responses:
        '200':
          description: Audit log entries
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/AuditLogResponse'

  /api/v1/projects/{projectId}/audit/{entryId}:
    get:
      operationId: getAuditEntry
      summary: Get audit entry details
      description: Returns full details of an audit log entry
      tags: [Audit]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: entryId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Audit entry details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/AuditEntryResponse'

  /api/v1/projects/{projectId}/audit/export:
    post:
      operationId: exportAuditLog
      summary: Export audit log
      description: Exports audit log in various formats for compliance
      tags: [Audit]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/AuditExportRequest'
      responses:
        '200':
          description: Exported audit log
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/AuditExportResponse'
            text/csv:
              schema:
                type: string
            application/pdf:
              schema:
                type: string
                format: binary

  # ============================================================
  # GIT INTEGRATION (OPTIONAL)
  # ============================================================

  /api/v1/projects/{projectId}/git/status:
    get:
      operationId: getGitStatus
      summary: Get Git repository status
      description: Returns current Git status if Git integration is enabled
      tags: [Git]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Git status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/GitStatusResponse'
        '404':
          description: Git not enabled for this project

  /api/v1/projects/{projectId}/git/sync:
    post:
      operationId: syncWithGit
      summary: Sync with Git repository
      description: Synchronizes Chronicle commits with the local Git repository
      tags: [Git]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/GitSyncRequest'
      responses:
        '200':
          description: Sync completed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/GitSyncResponse'
        '409':
          description: Sync conflict
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/GitConflictResponse'

  /api/v1/projects/{projectId}/git/branches:
    get:
      operationId: listGitBranches
      summary: List Git branches
      tags: [Git]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Branch list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/GitBranchListResponse'

  /api/v1/projects/{projectId}/git/checkout:
    post:
      operationId: checkoutBranch
      summary: Checkout Git branch
      tags: [Git]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/GitCheckoutRequest'
      responses:
        '200':
          description: Checkout successful
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/GitCheckoutResponse'

  # ============================================================
  # COMPARISON & MERGE
  # ============================================================

  /api/v1/projects/{projectId}/compare:
    get:
      operationId: compareCommits
      summary: Compare two commits
      description: Returns detailed comparison between two commits
      tags: [Diff]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: base
          in: query
          required: true
          description: Base commit ID
          schema:
            type: string
        - name: head
          in: query
          required: true
          description: Head commit ID
          schema:
            type: string
        - name: include_content
          in: query
          description: Include full file content in response
          schema:
            type: boolean
            default: false
      responses:
        '200':
          description: Comparison result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CompareResponse'

  /api/v1/projects/{projectId}/merge:
    post:
      operationId: mergeCommits
      summary: Merge commits
      description: Three-way merge between commits
      tags: [Rollback]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/MergeRequest'
      responses:
        '200':
          description: Merge successful
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/MergeResponse'
        '409':
          description: Merge conflict
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/MergeConflictResponse'

  # ============================================================
  # SNAPSHOTS
  # ============================================================

  /api/v1/projects/{projectId}/snapshots:
    post:
      operationId: createSnapshot
      summary: Create a named snapshot
      description: Creates a tagged snapshot of the current state
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateSnapshotRequest'
      responses:
        '201':
          description: Snapshot created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SnapshotResponse'
    
    get:
      operationId: listSnapshots
      summary: List snapshots
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Snapshot list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SnapshotListResponse'

  /api/v1/projects/{projectId}/snapshots/{snapshotId}:
    get:
      operationId: getSnapshot
      summary: Get snapshot details
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: snapshotId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Snapshot details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SnapshotDetailResponse'
    
    delete:
      operationId: deleteSnapshot
      summary: Delete a snapshot
      tags: [History]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: snapshotId
          in: path
          required: true
          schema:
            type: string
      responses:
        '204':
          description: Snapshot deleted

  # ============================================================
  # HEALTH
  # ============================================================

  /health:
    get:
      operationId: healthCheck
      summary: Health check
      tags: [Health]
      responses:
        '200':
          $ref: '#/components/responses/HealthOK'

  /ready:
    get:
      operationId: readinessCheck
      summary: Readiness check
      tags: [Health]
      responses:
        '200':
          $ref: '#/components/responses/ReadyOK'
        '503':
          $ref: '#/components/responses/NotReady'

  /live:
    get:
      operationId: livenessCheck
      summary: Liveness check
      tags: [Health]
      responses:
        '200':
          $ref: '#/components/responses/LiveOK'

components:
  parameters:
    ProjectId:
      name: projectId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Project identifier
    
    CommitId:
      name: commitId
      in: path
      required: true
      schema:
        type: string
      description: Commit identifier (SHA-256 hash prefix or full)
    
    Cursor:
      name: cursor
      in: query
      schema:
        type: string
      description: Pagination cursor
    
    Limit:
      name: limit
      in: query
      schema:
        type: integer
        default: 50
        minimum: 1
        maximum: 100

  schemas:
    # ============================================================
    # COMMIT SCHEMAS
    # ============================================================
    
    CreateCommitRequest:
      type: object
      required:
        - message
        - files
      properties:
        message:
          type: string
          description: Commit message
          maxLength: 500
        files:
          type: array
          description: Files to include in commit
          items:
            $ref: '#/components/schemas/CommitFile'
        author:
          type: string
          description: Author name (defaults to current user)
        parent_id:
          type: string
          description: Parent commit ID (defaults to HEAD)
        metadata:
          type: object
          additionalProperties: true
          description: Additional commit metadata
    
    CommitFile:
      type: object
      required:
        - path
        - action
      properties:
        path:
          type: string
          description: File path relative to project root
        action:
          type: string
          enum: [add, modify, delete, rename]
        content:
          type: string
          description: File content (required for add/modify)
        old_path:
          type: string
          description: Previous path (for rename action)
    
    CommitResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Commit'
    
    Commit:
      type: object
      properties:
        id:
          type: string
          description: Commit SHA-256 hash
        short_id:
          type: string
          description: Short commit ID (first 8 chars)
        message:
          type: string
        author:
          type: string
        parent_id:
          type: string
          nullable: true
        created_at:
          type: string
          format: date-time
        stats:
          $ref: '#/components/schemas/CommitStats'
    
    CommitStats:
      type: object
      properties:
        files_changed:
          type: integer
        insertions:
          type: integer
        deletions:
          type: integer
    
    CommitListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Commit'
        pagination:
          $ref: '#/components/schemas/Pagination'
    
    CommitDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/Commit'
            - type: object
              properties:
                files:
                  type: array
                  items:
                    $ref: '#/components/schemas/ChangedFile'
                metadata:
                  type: object
    
    ChangedFile:
      type: object
      properties:
        path:
          type: string
        action:
          type: string
          enum: [added, modified, deleted, renamed]
        old_path:
          type: string
          nullable: true
        insertions:
          type: integer
        deletions:
          type: integer
    
    CommitFilesResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/ChangedFile'
    
    FileContentResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            path:
              type: string
            content:
              type: string
            size:
              type: integer
            commit_id:
              type: string
            encoding:
              type: string
              enum: [utf-8, base64]

    # ============================================================
    # DIFF SCHEMAS
    # ============================================================
    
    DiffRequest:
      type: object
      properties:
        base:
          type: string
          description: Base commit ID, "HEAD", or "WORKING"
        head:
          type: string
          description: Head commit ID, "HEAD", or "WORKING"
        base_content:
          type: string
          description: Direct content comparison (alternative to commits)
        head_content:
          type: string
          description: Direct content comparison (alternative to commits)
        file_path:
          type: string
          description: Specific file to diff
        context_lines:
          type: integer
          default: 3
        algorithm:
          type: string
          enum: [myers, patience, histogram]
          default: myers
    
    DiffResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Diff'
    
    Diff:
      type: object
      properties:
        base_id:
          type: string
        head_id:
          type: string
        files:
          type: array
          items:
            $ref: '#/components/schemas/FileDiff'
        stats:
          $ref: '#/components/schemas/DiffStats'
    
    FileDiff:
      type: object
      properties:
        path:
          type: string
        old_path:
          type: string
          nullable: true
        action:
          type: string
          enum: [added, modified, deleted, renamed]
        hunks:
          type: array
          items:
            $ref: '#/components/schemas/DiffHunk'
        binary:
          type: boolean
    
    DiffHunk:
      type: object
      properties:
        old_start:
          type: integer
        old_lines:
          type: integer
        new_start:
          type: integer
        new_lines:
          type: integer
        header:
          type: string
        lines:
          type: array
          items:
            $ref: '#/components/schemas/DiffLine'
    
    DiffLine:
      type: object
      properties:
        type:
          type: string
          enum: [context, addition, deletion]
        old_line:
          type: integer
          nullable: true
        new_line:
          type: integer
          nullable: true
        content:
          type: string
    
    DiffStats:
      type: object
      properties:
        files_changed:
          type: integer
        insertions:
          type: integer
        deletions:
          type: integer
    
    CommitDiffResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Diff'
    
    FileHistoryResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              commit:
                $ref: '#/components/schemas/Commit'
              diff:
                $ref: '#/components/schemas/FileDiff'
        pagination:
          $ref: '#/components/schemas/Pagination'

    # ============================================================
    # ROLLBACK SCHEMAS
    # ============================================================
    
    RollbackRequest:
      type: object
      required:
        - target_commit_id
      properties:
        target_commit_id:
          type: string
          description: Commit to rollback to
        message:
          type: string
          description: Rollback commit message
        strategy:
          type: string
          enum: [revert, reset]
          default: revert
          description: |
            - revert: Creates new commit that undoes changes (preserves history)
            - reset: Moves HEAD to target (destructive, loses history)
        files:
          type: array
          items:
            type: string
          description: Specific files to rollback (empty = all)
    
    RollbackResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            rollback_commit:
              $ref: '#/components/schemas/Commit'
            files_restored:
              type: integer
            files_deleted:
              type: integer
    
    RollbackPreviewRequest:
      type: object
      required:
        - target_commit_id
      properties:
        target_commit_id:
          type: string
        files:
          type: array
          items:
            type: string
    
    RollbackPreviewResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            changes:
              type: array
              items:
                $ref: '#/components/schemas/ChangedFile'
            warnings:
              type: array
              items:
                type: string
    
    RestoreFileRequest:
      type: object
      required:
        - commit_id
      properties:
        commit_id:
          type: string
        create_commit:
          type: boolean
          default: true
        message:
          type: string
    
    RestoreFileResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            path:
              type: string
            restored_from:
              type: string
            commit:
              $ref: '#/components/schemas/Commit'

    # ============================================================
    # AUDIT SCHEMAS
    # ============================================================
    
    AuditLogResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/AuditEntry'
        pagination:
          $ref: '#/components/schemas/Pagination'
    
    AuditEntry:
      type: object
      properties:
        id:
          type: string
          format: uuid
        timestamp:
          type: string
          format: date-time
        action:
          type: string
          enum: [create, update, delete, rollback, restore, merge, export]
        resource_type:
          type: string
          enum: [spec, file, commit, project, snapshot]
        resource_id:
          type: string
        actor:
          type: string
        actor_type:
          type: string
          enum: [user, system, api]
        ip_address:
          type: string
        user_agent:
          type: string
        changes_summary:
          type: string
        request_id:
          type: string
    
    AuditEntryResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/AuditEntry'
            - type: object
              properties:
                before_state:
                  type: object
                after_state:
                  type: object
                metadata:
                  type: object
    
    AuditExportRequest:
      type: object
      properties:
        format:
          type: string
          enum: [json, csv, pdf]
          default: json
        since:
          type: string
          format: date-time
        until:
          type: string
          format: date-time
        filters:
          type: object
          properties:
            actions:
              type: array
              items:
                type: string
            actors:
              type: array
              items:
                type: string
    
    AuditExportResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            download_url:
              type: string
            expires_at:
              type: string
              format: date-time
            entry_count:
              type: integer

    # ============================================================
    # GIT SCHEMAS
    # ============================================================
    
    GitStatusResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            enabled:
              type: boolean
            current_branch:
              type: string
            remote:
              type: string
            ahead:
              type: integer
            behind:
              type: integer
            staged:
              type: array
              items:
                type: string
            modified:
              type: array
              items:
                type: string
            untracked:
              type: array
              items:
                type: string
    
    GitSyncRequest:
      type: object
      properties:
        direction:
          type: string
          enum: [push, pull, both]
          default: both
        force:
          type: boolean
          default: false
    
    GitSyncResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            commits_pushed:
              type: integer
            commits_pulled:
              type: integer
            conflicts:
              type: array
              items:
                type: string
    
    GitConflictResponse:
      type: object
      properties:
        success:
          type: boolean
        error:
          $ref: '#/components/schemas/Error'
        data:
          type: object
          properties:
            conflicts:
              type: array
              items:
                $ref: '#/components/schemas/GitConflict'
    
    GitConflict:
      type: object
      properties:
        path:
          type: string
        ours:
          type: string
        theirs:
          type: string
        base:
          type: string
    
    GitBranchListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/GitBranch'
    
    GitBranch:
      type: object
      properties:
        name:
          type: string
        current:
          type: boolean
        remote:
          type: boolean
        last_commit:
          type: string
        last_commit_date:
          type: string
          format: date-time
    
    GitCheckoutRequest:
      type: object
      required:
        - branch
      properties:
        branch:
          type: string
        create:
          type: boolean
          default: false
    
    GitCheckoutResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            branch:
              type: string
            head_commit:
              type: string

    # ============================================================
    # COMPARE & MERGE SCHEMAS
    # ============================================================
    
    CompareResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            base:
              $ref: '#/components/schemas/Commit'
            head:
              $ref: '#/components/schemas/Commit'
            commits:
              type: array
              items:
                $ref: '#/components/schemas/Commit'
            diff:
              $ref: '#/components/schemas/Diff'
            merge_base:
              type: string
    
    MergeRequest:
      type: object
      required:
        - source_commit_id
      properties:
        source_commit_id:
          type: string
        target_commit_id:
          type: string
          description: Defaults to HEAD
        message:
          type: string
        strategy:
          type: string
          enum: [recursive, ours, theirs]
          default: recursive
    
    MergeResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            merge_commit:
              $ref: '#/components/schemas/Commit'
            files_merged:
              type: integer
    
    MergeConflictResponse:
      type: object
      properties:
        success:
          type: boolean
        error:
          $ref: '#/components/schemas/Error'
        data:
          type: object
          properties:
            conflicts:
              type: array
              items:
                type: object
                properties:
                  path:
                    type: string
                  base_content:
                    type: string
                  ours_content:
                    type: string
                  theirs_content:
                    type: string

    # ============================================================
    # SNAPSHOT SCHEMAS
    # ============================================================
    
    CreateSnapshotRequest:
      type: object
      required:
        - name
      properties:
        name:
          type: string
          maxLength: 100
        description:
          type: string
          maxLength: 500
        commit_id:
          type: string
          description: Commit to snapshot (defaults to HEAD)
        tags:
          type: array
          items:
            type: string
    
    SnapshotResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Snapshot'
    
    Snapshot:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        description:
          type: string
        commit_id:
          type: string
        tags:
          type: array
          items:
            type: string
        created_at:
          type: string
          format: date-time
        created_by:
          type: string
    
    SnapshotListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Snapshot'
    
    SnapshotDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/Snapshot'
            - type: object
              properties:
                commit:
                  $ref: '#/components/schemas/Commit'
                file_count:
                  type: integer

    # ============================================================
    # COMMON SCHEMAS
    # ============================================================
    
    Pagination:
      type: object
      properties:
        next_cursor:
          type: string
          nullable: true
        has_more:
          type: boolean
        total:
          type: integer
    
    Error:
      type: object
      properties:
        code:
          type: integer
          description: Error code in 4xxx range
        constant:
          type: string
        message:
          type: string
        details:
          type: object
        retryable:
          type: boolean
        stackTrace:
          type: array
          items:
            $ref: '#/components/schemas/StackFrame'
    
    StackFrame:
      type: object
      properties:
        function:
          type: string
        file:
          type: string
        line:
          type: integer

  responses:
    ValidationError:
      description: Validation error
      content:
        application/json:
          schema:
            type: object
            properties:
              success:
                type: boolean
                example: false
              error:
                $ref: '#/components/schemas/Error'
    
    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            type: object
            properties:
              success:
                type: boolean
                example: false
              error:
                $ref: '#/components/schemas/Error'
    
    HealthOK:
      description: Service healthy
      content:
        application/json:
          schema:
            type: object
            properties:
              status:
                type: string
                example: healthy
              service:
                type: string
                example: chronicle
              version:
                type: string
    
    ReadyOK:
      description: Service ready
      content:
        application/json:
          schema:
            type: object
            properties:
              status:
                type: string
                example: ready
              checks:
                type: object
                properties:
                  database:
                    type: boolean
                  git:
                    type: boolean
    
    NotReady:
      description: Service not ready
      content:
        application/json:
          schema:
            type: object
            properties:
              status:
                type: string
                example: not_ready
              checks:
                type: object
    
    LiveOK:
      description: Service alive
      content:
        application/json:
          schema:
            type: object
            properties:
              status:
                type: string
                example: alive
```

---

## Error Codes (4xxx Range)

| Code | Constant | Description |
|------|----------|-------------|
| 4001 | `ERR_COMMIT_NOT_FOUND` | Commit does not exist |
| 4002 | `ERR_COMMIT_CONFLICT` | Concurrent modification detected |
| 4003 | `ERR_COMMIT_EMPTY` | No changes to commit |
| 4004 | `ERR_COMMIT_MESSAGE_REQUIRED` | Commit message is required |
| 4005 | `ERR_COMMIT_PARENT_INVALID` | Parent commit does not exist |
| 4010 | `ERR_DIFF_GENERATION_FAILED` | Failed to generate diff |
| 4011 | `ERR_DIFF_BINARY_FILE` | Cannot diff binary files |
| 4012 | `ERR_DIFF_FILE_TOO_LARGE` | File too large for diff |
| 4020 | `ERR_ROLLBACK_FAILED` | Rollback operation failed |
| 4021 | `ERR_ROLLBACK_UNCOMMITTED` | Uncommitted changes exist |
| 4022 | `ERR_ROLLBACK_TARGET_INVALID` | Target commit invalid |
| 4023 | `ERR_ROLLBACK_CONFLICT` | Rollback would cause conflicts |
| 4030 | `ERR_FILE_NOT_FOUND` | File does not exist at commit |
| 4031 | `ERR_FILE_RESTORE_FAILED` | Failed to restore file |
| 4040 | `ERR_GIT_NOT_ENABLED` | Git integration not enabled |
| 4041 | `ERR_GIT_SYNC_FAILED` | Git sync failed |
| 4042 | `ERR_GIT_CONFLICT` | Git merge conflict |
| 4043 | `ERR_GIT_BRANCH_NOT_FOUND` | Branch does not exist |
| 4044 | `ERR_GIT_CHECKOUT_FAILED` | Branch checkout failed |
| 4050 | `ERR_MERGE_CONFLICT` | Merge conflict detected |
| 4051 | `ERR_MERGE_FAILED` | Merge operation failed |
| 4052 | `ERR_MERGE_STRATEGY_INVALID` | Invalid merge strategy |
| 4060 | `ERR_SNAPSHOT_NOT_FOUND` | Snapshot does not exist |
| 4061 | `ERR_SNAPSHOT_NAME_DUPLICATE` | Snapshot name already exists |
| 4070 | `ERR_AUDIT_EXPORT_FAILED` | Audit export failed |
| 4071 | `ERR_AUDIT_ENTRY_NOT_FOUND` | Audit entry not found |
| 4080 | `ERR_HISTORY_CORRUPTED` | History integrity check failed |
| 4081 | `ERR_DATABASE_LOCKED` | Database locked by another operation |

---

## Database Schema

```sql
-- Chronicle database tables (per-project: history.db)

CREATE TABLE Commits (
    Id              TEXT PRIMARY KEY,
    ShortId         TEXT NOT NULL,
    ParentId        TEXT,
    Message         TEXT NOT NULL,
    Author          TEXT NOT NULL,
    FilesChanged    INTEGER NOT NULL DEFAULT 0,
    Insertions      INTEGER NOT NULL DEFAULT 0,
    Deletions       INTEGER NOT NULL DEFAULT 0,
    Metadata        TEXT, -- JSON
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (ParentId) REFERENCES Commits(Id)
);

CREATE TABLE CommitFiles (
    Id          TEXT PRIMARY KEY,
    CommitId    TEXT NOT NULL,
    Path        TEXT NOT NULL,
    OldPath     TEXT,
    Action      TEXT NOT NULL CHECK (Action IN ('add', 'modify', 'delete', 'rename')),
    ContentHash TEXT,
    Insertions  INTEGER NOT NULL DEFAULT 0,
    Deletions   INTEGER NOT NULL DEFAULT 0,
    
    FOREIGN KEY (CommitId) REFERENCES Commits(Id) ON DELETE CASCADE
);

CREATE TABLE FileContents (
    Hash        TEXT PRIMARY KEY,
    Content     BLOB NOT NULL,
    Size        INTEGER NOT NULL,
    Encoding    TEXT NOT NULL DEFAULT 'utf-8',
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE Snapshots (
    Id          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL UNIQUE,
    Description TEXT,
    CommitId    TEXT NOT NULL,
    Tags        TEXT, -- JSON array
    CreatedBy   TEXT NOT NULL,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (CommitId) REFERENCES Commits(Id)
);

CREATE TABLE AuditLog (
    Id              TEXT PRIMARY KEY,
    Timestamp       TEXT NOT NULL DEFAULT (datetime('now')),
    Action          TEXT NOT NULL,
    ResourceType    TEXT NOT NULL,
    ResourceId      TEXT NOT NULL,
    Actor           TEXT NOT NULL,
    ActorType       TEXT NOT NULL DEFAULT 'user',
    IpAddress       TEXT,
    UserAgent       TEXT,
    ChangesSummary  TEXT,
    BeforeState     TEXT, -- JSON
    AfterState      TEXT, -- JSON
    Metadata        TEXT, -- JSON
    RequestId       TEXT
);

-- Indexes
CREATE INDEX idx_commits_parent ON Commits(ParentId);
CREATE INDEX idx_commits_created ON Commits(CreatedAt DESC);
CREATE INDEX idx_commit_files_commit ON CommitFiles(CommitId);
CREATE INDEX idx_commit_files_path ON CommitFiles(Path);
CREATE INDEX idx_snapshots_commit ON Snapshots(CommitId);
CREATE INDEX idx_audit_timestamp ON AuditLog(Timestamp DESC);
CREATE INDEX idx_audit_action ON AuditLog(Action);
CREATE INDEX idx_audit_resource ON AuditLog(ResourceType, ResourceId);
CREATE INDEX idx_audit_actor ON AuditLog(Actor);
```

---

## See Also

- [Chronicle Service Spec](./03-chronicle.md)
- [Microservices Overview](./00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
