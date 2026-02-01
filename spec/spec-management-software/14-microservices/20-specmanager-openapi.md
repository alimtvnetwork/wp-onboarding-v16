# SpecManager Service OpenAPI Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Port:** 8081  
**Error Range:** 3xxx  

---

## Overview

The SpecManager service is the core data engine for project and specification management. It handles project-level CRUD operations, file management with strict path validation, and enforces the dual-layer SQLite architecture (global project index + per-project data).

**Cross-References:**
- [SpecManager Service Spec](./02-specmanager.md)
- [Shared Packages](../13-shared-packages/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [File Operation Safety](../.lovable/memories/constraints/file-operation-safety.md)

---

## OpenAPI 3.1.0 Specification

```yaml
openapi: 3.1.0
info:
  title: SpecManager Service API
  description: Core data engine for project and specification management
  version: 1.0.0
  contact:
    name: SpecBuilder Pro
    
servers:
  - url: http://localhost:8081
    description: Development server
  - url: http://specmanager:8081
    description: Docker network

tags:
  - name: Projects
    description: Project lifecycle management
  - name: Specs
    description: Specification CRUD operations
  - name: Files
    description: File system operations
  - name: Folders
    description: Folder management
  - name: Templates
    description: Spec templates
  - name: Import
    description: Bulk import operations
  - name: Export
    description: Export and packaging
  - name: Validation
    description: Content validation
  - name: Health
    description: Service health endpoints

paths:
  # ============================================================
  # PROJECT OPERATIONS
  # ============================================================
  
  /api/v1/projects:
    post:
      operationId: createProject
      summary: Create a new project
      description: |
        Creates a new project with its own SQLite database.
        Initializes folder structure and default settings.
      tags: [Projects]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateProjectRequest'
      responses:
        '201':
          description: Project created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectResponse'
        '400':
          $ref: '#/components/responses/ValidationError'
        '409':
          description: Project with this name already exists
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
    
    get:
      operationId: listProjects
      summary: List all projects
      description: Returns paginated list of projects with summary stats
      tags: [Projects]
      parameters:
        - $ref: '#/components/parameters/Cursor'
        - $ref: '#/components/parameters/Limit'
        - name: sort
          in: query
          schema:
            type: string
            enum: [name, created_at, updated_at, spec_count]
            default: updated_at
        - name: order
          in: query
          schema:
            type: string
            enum: [asc, desc]
            default: desc
        - name: search
          in: query
          description: Search in project name and description
          schema:
            type: string
      responses:
        '200':
          description: Project list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectListResponse'

  /api/v1/projects/{projectId}:
    get:
      operationId: getProject
      summary: Get project details
      description: Returns full project details including stats and settings
      tags: [Projects]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Project details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectDetailResponse'
        '404':
          $ref: '#/components/responses/NotFound'
    
    patch:
      operationId: updateProject
      summary: Update project
      description: Updates project metadata and settings
      tags: [Projects]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateProjectRequest'
      responses:
        '200':
          description: Project updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectResponse'
    
    delete:
      operationId: deleteProject
      summary: Delete project
      description: |
        Moves project to trash (.trash/ directory) with 32-day retention.
        Requires type-to-confirm verification.
      tags: [Projects]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: X-Confirm-Delete
          in: header
          required: true
          description: Must match project name for confirmation
          schema:
            type: string
      responses:
        '200':
          description: Project moved to trash
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DeleteResponse'
        '400':
          description: Confirmation header missing or incorrect

  /api/v1/projects/{projectId}/stats:
    get:
      operationId: getProjectStats
      summary: Get project statistics
      tags: [Projects]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Project statistics
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectStatsResponse'

  # ============================================================
  # SPEC OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/specs:
    post:
      operationId: createSpec
      summary: Create a new specification
      description: Creates a new spec file with optional template
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateSpecRequest'
      responses:
        '201':
          description: Spec created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'
        '400':
          $ref: '#/components/responses/ValidationError'
        '409':
          description: Spec with this path already exists
    
    get:
      operationId: listSpecs
      summary: List specifications
      description: Returns paginated list of specs with filtering
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Cursor'
        - $ref: '#/components/parameters/Limit'
        - name: folder
          in: query
          description: Filter by folder path
          schema:
            type: string
        - name: status
          in: query
          description: Filter by spec status
          schema:
            type: string
            enum: [draft, review, approved, archived]
        - name: tags
          in: query
          description: Filter by tags (comma-separated)
          schema:
            type: string
        - name: search
          in: query
          description: Full-text search in title and content
          schema:
            type: string
        - name: sort
          in: query
          schema:
            type: string
            enum: [title, path, created_at, updated_at, status]
            default: path
      responses:
        '200':
          description: Spec list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecListResponse'

  /api/v1/projects/{projectId}/specs/{specId}:
    get:
      operationId: getSpec
      summary: Get specification details
      description: Returns spec metadata and content
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - name: include_content
          in: query
          description: Include full file content
          schema:
            type: boolean
            default: true
      responses:
        '200':
          description: Spec details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecDetailResponse'
        '404':
          $ref: '#/components/responses/NotFound'
    
    put:
      operationId: updateSpec
      summary: Update specification
      description: Updates spec content and/or metadata
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateSpecRequest'
      responses:
        '200':
          description: Spec updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'
        '409':
          description: Concurrent modification conflict
    
    delete:
      operationId: deleteSpec
      summary: Delete specification
      description: Moves spec to trash with 32-day retention
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      responses:
        '200':
          description: Spec moved to trash
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DeleteResponse'

  /api/v1/projects/{projectId}/specs/{specId}/content:
    get:
      operationId: getSpecContent
      summary: Get raw spec content
      description: Returns only the file content without metadata
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      responses:
        '200':
          description: Raw content
          content:
            text/markdown:
              schema:
                type: string
            text/plain:
              schema:
                type: string
    
    put:
      operationId: updateSpecContent
      summary: Update spec content only
      description: Efficiently updates just the file content
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        required: true
        content:
          text/markdown:
            schema:
              type: string
          application/json:
            schema:
              type: object
              required:
                - content
              properties:
                content:
                  type: string
                base_version:
                  type: integer
                  description: For optimistic locking
      responses:
        '200':
          description: Content updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'

  /api/v1/projects/{projectId}/specs/{specId}/move:
    post:
      operationId: moveSpec
      summary: Move specification
      description: |
        Moves spec to a new location. Triggers Git commit if enabled.
        Requires user consent for moves outside project root.
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/MoveRequest'
      responses:
        '200':
          description: Spec moved
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'
        '400':
          description: Invalid destination path
        '409':
          description: File already exists at destination

  /api/v1/projects/{projectId}/specs/{specId}/rename:
    post:
      operationId: renameSpec
      summary: Rename specification
      description: Renames spec file, updating all internal references
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RenameRequest'
      responses:
        '200':
          description: Spec renamed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'

  /api/v1/projects/{projectId}/specs/{specId}/duplicate:
    post:
      operationId: duplicateSpec
      summary: Duplicate specification
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/DuplicateRequest'
      responses:
        '201':
          description: Spec duplicated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecResponse'

  /api/v1/projects/{projectId}/specs/batch:
    post:
      operationId: batchSpecOperation
      summary: Batch spec operations
      description: Perform operations on multiple specs atomically
      tags: [Specs]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/BatchSpecRequest'
      responses:
        '200':
          description: Batch operation result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/BatchOperationResponse'

  # ============================================================
  # FILE OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/files:
    get:
      operationId: listFiles
      summary: List all files
      description: Returns flat or hierarchical file listing
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: path
          in: query
          description: Directory path to list
          schema:
            type: string
            default: /
        - name: recursive
          in: query
          description: Include subdirectories
          schema:
            type: boolean
            default: false
        - name: include_hidden
          in: query
          description: Include hidden files (.files)
          schema:
            type: boolean
            default: false
        - name: pattern
          in: query
          description: Glob pattern filter (e.g., *.md)
          schema:
            type: string
      responses:
        '200':
          description: File listing
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileListResponse'

  /api/v1/projects/{projectId}/files/tree:
    get:
      operationId: getFileTree
      summary: Get file tree
      description: Returns hierarchical tree structure
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: max_depth
          in: query
          schema:
            type: integer
            default: 10
            maximum: 20
      responses:
        '200':
          description: File tree
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileTreeResponse'

  /api/v1/projects/{projectId}/files/read:
    get:
      operationId: readFile
      summary: Read file content
      description: |
        Reads file content with optional line range.
        Enforces path validation to prevent directory traversal.
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: path
          in: query
          required: true
          description: File path (validated and sanitized)
          schema:
            type: string
        - name: start_line
          in: query
          schema:
            type: integer
            minimum: 1
        - name: end_line
          in: query
          schema:
            type: integer
        - name: encoding
          in: query
          schema:
            type: string
            enum: [utf-8, base64]
            default: utf-8
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
        '400':
          description: Invalid path (traversal attempt blocked)
        '404':
          $ref: '#/components/responses/NotFound'

  /api/v1/projects/{projectId}/files/write:
    post:
      operationId: writeFile
      summary: Write file content
      description: Creates or overwrites a file
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/WriteFileRequest'
      responses:
        '200':
          description: File written
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileResponse'
        '400':
          description: Invalid path or content

  /api/v1/projects/{projectId}/files/copy:
    post:
      operationId: copyFile
      summary: Copy file or directory
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CopyRequest'
      responses:
        '200':
          description: Copy successful
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileResponse'

  /api/v1/projects/{projectId}/files/move:
    post:
      operationId: moveFile
      summary: Move file or directory
      description: |
        Moves file with path validation.
        Requires consent for operations outside project root.
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/MoveFileRequest'
      responses:
        '200':
          description: Move successful
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileResponse'

  /api/v1/projects/{projectId}/files/delete:
    post:
      operationId: deleteFile
      summary: Delete file
      description: Moves to .trash/ with 32-day retention
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/DeleteFileRequest'
      responses:
        '200':
          description: File moved to trash
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DeleteResponse'

  /api/v1/projects/{projectId}/files/search:
    get:
      operationId: searchFiles
      summary: Search files by name or content
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: query
          in: query
          required: true
          schema:
            type: string
        - name: type
          in: query
          schema:
            type: string
            enum: [name, content, both]
            default: both
        - name: pattern
          in: query
          description: File pattern filter
          schema:
            type: string
        - $ref: '#/components/parameters/Limit'
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileSearchResponse'

  # ============================================================
  # FOLDER OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/folders:
    post:
      operationId: createFolder
      summary: Create folder
      description: Creates folder with optional 00-overview.md
      tags: [Folders]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateFolderRequest'
      responses:
        '201':
          description: Folder created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FolderResponse'
    
    get:
      operationId: listFolders
      summary: List folders
      tags: [Folders]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: parent
          in: query
          description: Parent folder path
          schema:
            type: string
      responses:
        '200':
          description: Folder list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FolderListResponse'

  /api/v1/projects/{projectId}/folders/rename:
    post:
      operationId: renameFolder
      summary: Rename folder
      description: |
        Renames folder maintaining numeric prefix convention.
        Updates all internal references and spec paths.
      tags: [Folders]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RenameFolderRequest'
      responses:
        '200':
          description: Folder renamed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FolderResponse'

  /api/v1/projects/{projectId}/folders/reorder:
    post:
      operationId: reorderFolders
      summary: Reorder folders
      description: Updates numeric prefixes to reorder folders
      tags: [Folders]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ReorderFoldersRequest'
      responses:
        '200':
          description: Folders reordered
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FolderListResponse'

  # ============================================================
  # TRASH OPERATIONS
  # ============================================================

  /api/v1/projects/{projectId}/trash:
    get:
      operationId: listTrash
      summary: List trash items
      description: Returns items in .trash/ with deletion dates
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Trash contents
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TrashListResponse'

  /api/v1/projects/{projectId}/trash/restore:
    post:
      operationId: restoreFromTrash
      summary: Restore from trash
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RestoreTrashRequest'
      responses:
        '200':
          description: Item restored
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileResponse'

  /api/v1/projects/{projectId}/trash/empty:
    post:
      operationId: emptyTrash
      summary: Permanently delete all trash
      description: Requires confirmation header
      tags: [Files]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: X-Confirm-Delete
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Trash emptied
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      deleted_count:
                        type: integer
                      freed_bytes:
                        type: integer

  # ============================================================
  # TEMPLATES
  # ============================================================

  /api/v1/templates:
    get:
      operationId: listTemplates
      summary: List available templates
      tags: [Templates]
      parameters:
        - name: category
          in: query
          schema:
            type: string
            enum: [spec, project, folder]
      responses:
        '200':
          description: Template list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TemplateListResponse'
    
    post:
      operationId: createTemplate
      summary: Create custom template
      tags: [Templates]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateTemplateRequest'
      responses:
        '201':
          description: Template created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TemplateResponse'

  /api/v1/templates/{templateId}:
    get:
      operationId: getTemplate
      summary: Get template details
      tags: [Templates]
      parameters:
        - name: templateId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Template details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TemplateDetailResponse'
    
    delete:
      operationId: deleteTemplate
      summary: Delete custom template
      tags: [Templates]
      parameters:
        - name: templateId
          in: path
          required: true
          schema:
            type: string
      responses:
        '204':
          description: Template deleted

  # ============================================================
  # IMPORT / EXPORT
  # ============================================================

  /api/v1/projects/{projectId}/import:
    post:
      operationId: importContent
      summary: Import content
      description: |
        Imports files from various sources.
        Files stored in .upload/import/ directory.
      tags: [Import]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              $ref: '#/components/schemas/ImportRequest'
          application/json:
            schema:
              $ref: '#/components/schemas/ImportUrlRequest'
      responses:
        '202':
          description: Import started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ImportJobResponse'

  /api/v1/projects/{projectId}/import/{jobId}:
    get:
      operationId: getImportStatus
      summary: Get import job status
      tags: [Import]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: jobId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Import status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ImportJobStatusResponse'

  /api/v1/projects/{projectId}/export:
    post:
      operationId: exportProject
      summary: Export project
      description: Exports project in various formats
      tags: [Export]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ExportRequest'
      responses:
        '202':
          description: Export started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExportJobResponse'

  /api/v1/projects/{projectId}/export/{jobId}:
    get:
      operationId: getExportStatus
      summary: Get export job status
      tags: [Export]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: jobId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Export status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExportJobStatusResponse'

  /api/v1/projects/{projectId}/export/{jobId}/download:
    get:
      operationId: downloadExport
      summary: Download exported file
      tags: [Export]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: jobId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Export file
          content:
            application/zip:
              schema:
                type: string
                format: binary
            application/pdf:
              schema:
                type: string
                format: binary

  # ============================================================
  # VALIDATION
  # ============================================================

  /api/v1/projects/{projectId}/validate:
    post:
      operationId: validateProject
      summary: Validate project structure
      description: Checks for broken links, missing files, naming violations
      tags: [Validation]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ValidationRequest'
      responses:
        '200':
          description: Validation results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationResponse'

  /api/v1/projects/{projectId}/specs/{specId}/validate:
    post:
      operationId: validateSpec
      summary: Validate single spec
      tags: [Validation]
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      responses:
        '200':
          description: Validation results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SpecValidationResponse'

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
    
    SpecId:
      name: specId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Specification identifier
    
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
    # PROJECT SCHEMAS
    # ============================================================
    
    CreateProjectRequest:
      type: object
      required:
        - name
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 100
          pattern: '^[a-zA-Z0-9][a-zA-Z0-9-_ ]*$'
        description:
          type: string
          maxLength: 500
        template:
          type: string
          description: Template ID to use
        root_path:
          type: string
          description: Custom root path for project files
        settings:
          $ref: '#/components/schemas/ProjectSettings'
    
    UpdateProjectRequest:
      type: object
      properties:
        name:
          type: string
        description:
          type: string
        settings:
          $ref: '#/components/schemas/ProjectSettings'
    
    ProjectSettings:
      type: object
      properties:
        auto_save:
          type: boolean
          default: true
        auto_commit:
          type: boolean
          default: false
        git_integration:
          type: boolean
          default: false
        naming_convention:
          type: string
          enum: [numeric-prefix, hyphen-case, snake_case]
          default: numeric-prefix
        default_file_extension:
          type: string
          default: .md
    
    Project:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        description:
          type: string
        root_path:
          type: string
        created_at:
          type: string
          format: date-time
        updated_at:
          type: string
          format: date-time
        settings:
          $ref: '#/components/schemas/ProjectSettings'
    
    ProjectResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Project'
    
    ProjectListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            allOf:
              - $ref: '#/components/schemas/Project'
              - type: object
                properties:
                  spec_count:
                    type: integer
                  file_count:
                    type: integer
        pagination:
          $ref: '#/components/schemas/Pagination'
    
    ProjectDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/Project'
            - type: object
              properties:
                stats:
                  $ref: '#/components/schemas/ProjectStats'
                recent_specs:
                  type: array
                  items:
                    $ref: '#/components/schemas/SpecSummary'
    
    ProjectStats:
      type: object
      properties:
        spec_count:
          type: integer
        file_count:
          type: integer
        folder_count:
          type: integer
        total_size_bytes:
          type: integer
        last_modified:
          type: string
          format: date-time
        by_status:
          type: object
          additionalProperties:
            type: integer
    
    ProjectStatsResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/ProjectStats'

    # ============================================================
    # SPEC SCHEMAS
    # ============================================================
    
    CreateSpecRequest:
      type: object
      required:
        - title
      properties:
        title:
          type: string
          maxLength: 200
        path:
          type: string
          description: Custom path (auto-generated if not provided)
        folder:
          type: string
          description: Parent folder path
        template:
          type: string
          description: Template ID
        content:
          type: string
          description: Initial content
        status:
          type: string
          enum: [draft, review, approved]
          default: draft
        tags:
          type: array
          items:
            type: string
        metadata:
          type: object
    
    UpdateSpecRequest:
      type: object
      properties:
        title:
          type: string
        content:
          type: string
        status:
          type: string
          enum: [draft, review, approved, archived]
        tags:
          type: array
          items:
            type: string
        metadata:
          type: object
        version:
          type: integer
          description: For optimistic locking
    
    Spec:
      type: object
      properties:
        id:
          type: string
          format: uuid
        title:
          type: string
        path:
          type: string
        folder:
          type: string
        status:
          type: string
        tags:
          type: array
          items:
            type: string
        version:
          type: integer
        word_count:
          type: integer
        created_at:
          type: string
          format: date-time
        updated_at:
          type: string
          format: date-time
    
    SpecSummary:
      type: object
      properties:
        id:
          type: string
        title:
          type: string
        path:
          type: string
        status:
          type: string
        updated_at:
          type: string
          format: date-time
    
    SpecResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Spec'
    
    SpecListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Spec'
        pagination:
          $ref: '#/components/schemas/Pagination'
    
    SpecDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/Spec'
            - type: object
              properties:
                content:
                  type: string
                metadata:
                  type: object
                links:
                  type: array
                  items:
                    $ref: '#/components/schemas/SpecLink'
    
    SpecLink:
      type: object
      properties:
        type:
          type: string
          enum: [internal, external, reference]
        target:
          type: string
        text:
          type: string
        valid:
          type: boolean
    
    MoveRequest:
      type: object
      required:
        - destination
      properties:
        destination:
          type: string
          description: New path or folder
        update_references:
          type: boolean
          default: true
    
    RenameRequest:
      type: object
      required:
        - new_name
      properties:
        new_name:
          type: string
        preserve_prefix:
          type: boolean
          default: true
    
    DuplicateRequest:
      type: object
      properties:
        new_title:
          type: string
        destination_folder:
          type: string
    
    BatchSpecRequest:
      type: object
      required:
        - operation
        - spec_ids
      properties:
        operation:
          type: string
          enum: [delete, move, update_status, add_tags, remove_tags]
        spec_ids:
          type: array
          items:
            type: string
        params:
          type: object
          description: Operation-specific parameters

    # ============================================================
    # FILE SCHEMAS
    # ============================================================
    
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
            encoding:
              type: string
            line_count:
              type: integer
            modified_at:
              type: string
              format: date-time
    
    WriteFileRequest:
      type: object
      required:
        - path
        - content
      properties:
        path:
          type: string
        content:
          type: string
        encoding:
          type: string
          enum: [utf-8, base64]
          default: utf-8
        create_directories:
          type: boolean
          default: true
        overwrite:
          type: boolean
          default: true
    
    FileResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/FileInfo'
    
    FileInfo:
      type: object
      properties:
        path:
          type: string
        name:
          type: string
        type:
          type: string
          enum: [file, directory]
        size:
          type: integer
        extension:
          type: string
        modified_at:
          type: string
          format: date-time
    
    FileListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/FileInfo'
    
    FileTreeResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/TreeNode'
    
    TreeNode:
      type: object
      properties:
        name:
          type: string
        path:
          type: string
        type:
          type: string
          enum: [file, directory]
        children:
          type: array
          items:
            $ref: '#/components/schemas/TreeNode'
        spec_id:
          type: string
          description: If this file is a tracked spec
    
    CopyRequest:
      type: object
      required:
        - source
        - destination
      properties:
        source:
          type: string
        destination:
          type: string
        overwrite:
          type: boolean
          default: false
    
    MoveFileRequest:
      type: object
      required:
        - source
        - destination
      properties:
        source:
          type: string
        destination:
          type: string
        consent_token:
          type: string
          description: Required for moves outside project root
    
    DeleteFileRequest:
      type: object
      required:
        - path
      properties:
        path:
          type: string
        permanent:
          type: boolean
          default: false
          description: Skip trash (requires confirmation)
    
    FileSearchResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              path:
                type: string
              name:
                type: string
              matches:
                type: array
                items:
                  type: object
                  properties:
                    line:
                      type: integer
                    content:
                      type: string
                    highlight:
                      type: string

    # ============================================================
    # FOLDER SCHEMAS
    # ============================================================
    
    CreateFolderRequest:
      type: object
      required:
        - name
      properties:
        name:
          type: string
          pattern: '^[0-9]{2}-[a-z0-9-]+$'
        parent:
          type: string
        create_overview:
          type: boolean
          default: true
    
    FolderResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Folder'
    
    Folder:
      type: object
      properties:
        path:
          type: string
        name:
          type: string
        prefix:
          type: string
        file_count:
          type: integer
        subfolder_count:
          type: integer
        has_overview:
          type: boolean
    
    FolderListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Folder'
    
    RenameFolderRequest:
      type: object
      required:
        - path
        - new_name
      properties:
        path:
          type: string
        new_name:
          type: string
        update_references:
          type: boolean
          default: true
    
    ReorderFoldersRequest:
      type: object
      required:
        - parent
        - order
      properties:
        parent:
          type: string
        order:
          type: array
          items:
            type: string
          description: Folder names in desired order

    # ============================================================
    # TRASH SCHEMAS
    # ============================================================
    
    TrashListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/TrashItem'
    
    TrashItem:
      type: object
      properties:
        id:
          type: string
        original_path:
          type: string
        name:
          type: string
        type:
          type: string
          enum: [file, directory, spec]
        size:
          type: integer
        deleted_at:
          type: string
          format: date-time
        expires_at:
          type: string
          format: date-time
        deleted_by:
          type: string
    
    RestoreTrashRequest:
      type: object
      required:
        - item_id
      properties:
        item_id:
          type: string
        restore_to:
          type: string
          description: Alternative restore path

    # ============================================================
    # TEMPLATE SCHEMAS
    # ============================================================
    
    CreateTemplateRequest:
      type: object
      required:
        - name
        - category
        - content
      properties:
        name:
          type: string
        category:
          type: string
          enum: [spec, project, folder]
        description:
          type: string
        content:
          type: string
        variables:
          type: array
          items:
            $ref: '#/components/schemas/TemplateVariable'
    
    TemplateVariable:
      type: object
      properties:
        name:
          type: string
        type:
          type: string
          enum: [string, number, boolean, date, select]
        required:
          type: boolean
        default:
          type: string
        options:
          type: array
          items:
            type: string
    
    Template:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        category:
          type: string
        description:
          type: string
        built_in:
          type: boolean
        created_at:
          type: string
          format: date-time
    
    TemplateResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Template'
    
    TemplateListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Template'
    
    TemplateDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: '#/components/schemas/Template'
            - type: object
              properties:
                content:
                  type: string
                variables:
                  type: array
                  items:
                    $ref: '#/components/schemas/TemplateVariable'

    # ============================================================
    # IMPORT/EXPORT SCHEMAS
    # ============================================================
    
    ImportRequest:
      type: object
      properties:
        files:
          type: array
          items:
            type: string
            format: binary
        destination:
          type: string
        flatten:
          type: boolean
          default: false
    
    ImportUrlRequest:
      type: object
      required:
        - url
      properties:
        url:
          type: string
          format: uri
        destination:
          type: string
        type:
          type: string
          enum: [git, zip, single-file]
    
    ImportJobResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            job_id:
              type: string
            status:
              type: string
              enum: [pending, processing, completed, failed]
    
    ImportJobStatusResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            job_id:
              type: string
            status:
              type: string
            progress:
              type: number
            files_imported:
              type: integer
            errors:
              type: array
              items:
                type: string
    
    ExportRequest:
      type: object
      required:
        - format
      properties:
        format:
          type: string
          enum: [zip, pdf, html, docx]
        include:
          type: array
          items:
            type: string
          description: Paths to include (empty = all)
        exclude:
          type: array
          items:
            type: string
        options:
          type: object
          properties:
            include_metadata:
              type: boolean
            include_history:
              type: boolean
            toc:
              type: boolean
    
    ExportJobResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            job_id:
              type: string
            status:
              type: string
    
    ExportJobStatusResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            job_id:
              type: string
            status:
              type: string
              enum: [pending, processing, completed, failed]
            progress:
              type: number
            download_url:
              type: string
            expires_at:
              type: string
              format: date-time

    # ============================================================
    # VALIDATION SCHEMAS
    # ============================================================
    
    ValidationRequest:
      type: object
      properties:
        checks:
          type: array
          items:
            type: string
            enum: [links, naming, structure, content, references]
          default: [links, naming, structure]
        paths:
          type: array
          items:
            type: string
          description: Paths to validate (empty = all)
    
    ValidationResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            valid:
              type: boolean
            issues:
              type: array
              items:
                $ref: '#/components/schemas/ValidationIssue'
            summary:
              type: object
              properties:
                total_files:
                  type: integer
                files_with_issues:
                  type: integer
                by_severity:
                  type: object
                  additionalProperties:
                    type: integer
    
    ValidationIssue:
      type: object
      properties:
        path:
          type: string
        line:
          type: integer
        severity:
          type: string
          enum: [error, warning, info]
        type:
          type: string
        message:
          type: string
        suggestion:
          type: string
    
    SpecValidationResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            valid:
              type: boolean
            issues:
              type: array
              items:
                $ref: '#/components/schemas/ValidationIssue'

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
    
    DeleteResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            deleted_path:
              type: string
            trash_path:
              type: string
            expires_at:
              type: string
              format: date-time
    
    BatchOperationResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            succeeded:
              type: integer
            failed:
              type: integer
            results:
              type: array
              items:
                type: object
                properties:
                  id:
                    type: string
                  success:
                    type: boolean
                  error:
                    type: string
    
    Error:
      type: object
      properties:
        code:
          type: integer
          description: Error code in 3xxx range
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
                example: specmanager
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
                  filesystem:
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

## Error Codes (3xxx Range)

| Code | Constant | Description |
|------|----------|-------------|
| 3001 | `ERR_PROJECT_NOT_FOUND` | Project does not exist |
| 3002 | `ERR_PROJECT_NAME_EXISTS` | Project name already taken |
| 3003 | `ERR_PROJECT_NAME_INVALID` | Invalid project name format |
| 3004 | `ERR_PROJECT_DELETE_FAILED` | Failed to delete project |
| 3005 | `ERR_PROJECT_CONFIRMATION_REQUIRED` | Delete confirmation missing |
| 3010 | `ERR_SPEC_NOT_FOUND` | Specification does not exist |
| 3011 | `ERR_SPEC_PATH_EXISTS` | Spec path already exists |
| 3012 | `ERR_SPEC_TITLE_REQUIRED` | Spec title is required |
| 3013 | `ERR_SPEC_CONTENT_INVALID` | Invalid spec content |
| 3014 | `ERR_SPEC_VERSION_CONFLICT` | Concurrent modification detected |
| 3015 | `ERR_SPEC_MOVE_FAILED` | Failed to move specification |
| 3016 | `ERR_SPEC_RENAME_FAILED` | Failed to rename specification |
| 3020 | `ERR_FILE_NOT_FOUND` | File does not exist |
| 3021 | `ERR_FILE_READ_FAILED` | Failed to read file |
| 3022 | `ERR_FILE_WRITE_FAILED` | Failed to write file |
| 3023 | `ERR_FILE_DELETE_FAILED` | Failed to delete file |
| 3024 | `ERR_FILE_COPY_FAILED` | Failed to copy file |
| 3025 | `ERR_FILE_MOVE_FAILED` | Failed to move file |
| 3026 | `ERR_FILE_EXISTS` | File already exists at destination |
| 3027 | `ERR_FILE_TOO_LARGE` | File exceeds size limit |
| 3030 | `ERR_PATH_INVALID` | Invalid file path format |
| 3031 | `ERR_PATH_TRAVERSAL` | Directory traversal attempt blocked |
| 3032 | `ERR_PATH_OUTSIDE_ROOT` | Path outside project root |
| 3033 | `ERR_PATH_CONSENT_REQUIRED` | User consent required for operation |
| 3040 | `ERR_FOLDER_NOT_FOUND` | Folder does not exist |
| 3041 | `ERR_FOLDER_EXISTS` | Folder already exists |
| 3042 | `ERR_FOLDER_NAME_INVALID` | Invalid folder name (must match pattern) |
| 3043 | `ERR_FOLDER_NOT_EMPTY` | Folder is not empty |
| 3050 | `ERR_TEMPLATE_NOT_FOUND` | Template does not exist |
| 3051 | `ERR_TEMPLATE_BUILTIN` | Cannot modify built-in template |
| 3052 | `ERR_TEMPLATE_VARIABLE_MISSING` | Required template variable missing |
| 3060 | `ERR_IMPORT_FAILED` | Import operation failed |
| 3061 | `ERR_IMPORT_FORMAT_UNSUPPORTED` | Unsupported import format |
| 3062 | `ERR_IMPORT_SIZE_EXCEEDED` | Import exceeds size limit |
| 3070 | `ERR_EXPORT_FAILED` | Export operation failed |
| 3071 | `ERR_EXPORT_FORMAT_UNSUPPORTED` | Unsupported export format |
| 3072 | `ERR_EXPORT_JOB_NOT_FOUND` | Export job not found |
| 3080 | `ERR_VALIDATION_FAILED` | Validation check failed |
| 3081 | `ERR_LINK_BROKEN` | Broken internal link detected |
| 3082 | `ERR_NAMING_VIOLATION` | Naming convention violation |
| 3090 | `ERR_TRASH_NOT_FOUND` | Trash item not found |
| 3091 | `ERR_TRASH_RESTORE_FAILED` | Failed to restore from trash |
| 3092 | `ERR_TRASH_EXPIRED` | Trash item has expired |
| 3099 | `ERR_DATABASE_ERROR` | Database operation failed |

---

## Database Schema

```sql
-- Global project index (projects.db)

CREATE TABLE Projects (
    Id              TEXT PRIMARY KEY,
    Name            TEXT NOT NULL UNIQUE,
    Description     TEXT,
    RootPath        TEXT NOT NULL,
    Settings        TEXT, -- JSON
    SpecCount       INTEGER NOT NULL DEFAULT 0,
    FileCount       INTEGER NOT NULL DEFAULT 0,
    TotalSize       INTEGER NOT NULL DEFAULT 0,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_projects_name ON Projects(Name);
CREATE INDEX idx_projects_updated ON Projects(UpdatedAt DESC);

-- Per-project database (project.db)

CREATE TABLE Specs (
    Id              TEXT PRIMARY KEY,
    Title           TEXT NOT NULL,
    Path            TEXT NOT NULL UNIQUE,
    Folder          TEXT,
    Status          TEXT NOT NULL DEFAULT 'draft' CHECK (Status IN ('draft', 'review', 'approved', 'archived')),
    Tags            TEXT, -- JSON array
    Metadata        TEXT, -- JSON
    Version         INTEGER NOT NULL DEFAULT 1,
    WordCount       INTEGER NOT NULL DEFAULT 0,
    ContentHash     TEXT,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE Folders (
    Path            TEXT PRIMARY KEY,
    Name            TEXT NOT NULL,
    Prefix          TEXT,
    ParentPath      TEXT,
    HasOverview     INTEGER NOT NULL DEFAULT 0,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE Templates (
    Id              TEXT PRIMARY KEY,
    Name            TEXT NOT NULL,
    Category        TEXT NOT NULL CHECK (Category IN ('spec', 'project', 'folder')),
    Description     TEXT,
    Content         TEXT NOT NULL,
    Variables       TEXT, -- JSON
    BuiltIn         INTEGER NOT NULL DEFAULT 0,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE Trash (
    Id              TEXT PRIMARY KEY,
    OriginalPath    TEXT NOT NULL,
    TrashPath       TEXT NOT NULL,
    Name            TEXT NOT NULL,
    Type            TEXT NOT NULL CHECK (Type IN ('file', 'directory', 'spec')),
    Size            INTEGER NOT NULL DEFAULT 0,
    Metadata        TEXT, -- JSON (stores spec data if applicable)
    DeletedBy       TEXT,
    DeletedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    ExpiresAt       TEXT NOT NULL
);

-- Indexes
CREATE INDEX idx_specs_path ON Specs(Path);
CREATE INDEX idx_specs_folder ON Specs(Folder);
CREATE INDEX idx_specs_status ON Specs(Status);
CREATE INDEX idx_specs_updated ON Specs(UpdatedAt DESC);
CREATE INDEX idx_folders_parent ON Folders(ParentPath);
CREATE INDEX idx_trash_expires ON Trash(ExpiresAt);

-- FTS for spec search
CREATE VIRTUAL TABLE SpecsFts USING fts5(
    Title,
    Path,
    Tags,
    content='Specs',
    content_rowid='rowid'
);

-- Triggers for FTS sync
CREATE TRIGGER specs_ai AFTER INSERT ON Specs BEGIN
    INSERT INTO SpecsFts(rowid, Title, Path, Tags) 
    VALUES (NEW.rowid, NEW.Title, NEW.Path, NEW.Tags);
END;

CREATE TRIGGER specs_ad AFTER DELETE ON Specs BEGIN
    INSERT INTO SpecsFts(SpecsFts, rowid, Title, Path, Tags) 
    VALUES ('delete', OLD.rowid, OLD.Title, OLD.Path, OLD.Tags);
END;

CREATE TRIGGER specs_au AFTER UPDATE ON Specs BEGIN
    INSERT INTO SpecsFts(SpecsFts, rowid, Title, Path, Tags) 
    VALUES ('delete', OLD.rowid, OLD.Title, OLD.Path, OLD.Tags);
    INSERT INTO SpecsFts(rowid, Title, Path, Tags) 
    VALUES (NEW.rowid, NEW.Title, NEW.Path, NEW.Tags);
END;
```

---

## See Also

- [SpecManager Service Spec](./02-specmanager.md)
- [Microservices Overview](./00-overview.md)
- [Error Management](../06-error-management/00-overview.md)
- [File Operation Safety](../.lovable/memories/constraints/file-operation-safety.md)
