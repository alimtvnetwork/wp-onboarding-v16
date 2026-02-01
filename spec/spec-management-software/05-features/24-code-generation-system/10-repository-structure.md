# Repository Structure

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

This document defines the standardized folder structure for generated code repositories, including the root directory configuration, language-specific layouts, and README requirements.

**Cross-References:**
- [Git Integration](./07-git-integration.md)
- [Project Settings](../02-project-management/03-project-settings.md)
- [Spec Folder Guideline](./12-spec-folder-guideline.md)

---

## Root Directory Configuration

### Project Settings

```go
type ProjectCodeSettings struct {
    ID                  string    `gorm:"primaryKey;type:text"`
    ProjectID           string    `gorm:"type:text;not null;uniqueIndex"`
    CodeRepoRootDir     string    `gorm:"type:text;not null"`        // Root for all repos
    UseDefaultStructure bool      `gorm:"type:boolean;default:true"` // Use standard layout
    CustomStructure     string    `gorm:"type:text"`                 // JSON if custom
    CreatedAt           time.Time
    UpdatedAt           time.Time
}
```

### Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `codegen.repo.rootDirectory` | string | `./code-repos` | Root directory for all generated repositories |
| `codegen.repo.useProjectSubdirs` | bool | true | Create subdirectory per project |
| `codegen.repo.defaultBranch` | string | `main` | Default git branch name |

---

## Standard Repository Layout

```
{code-repo-root}/
└── {project-id}/                    # Repository root for project
    ├── .git/                        # Git repository data
    ├── .gitignore                   # Git ignore rules
    ├── README.md                    # Project documentation
    │
    ├── spec/                        # Copied specification files
    │   ├── 00-overview.md
    │   ├── 01-feature-spec.md
    │   └── ...
    │
    ├── BE/                          # Backend code (Go)
    │   ├── cmd/                     # Application entry points
    │   │   └── server/
    │   │       └── main.go
    │   ├── internal/                # Private packages
    │   │   ├── api/                 # HTTP handlers
    │   │   ├── service/             # Business logic
    │   │   ├── repository/          # Data access
    │   │   └── model/               # Domain models
    │   ├── pkg/                     # Public packages
    │   ├── go.mod
    │   ├── go.sum
    │   └── README.md
    │
    ├── FE/                          # Frontend code (React)
    │   ├── src/
    │   │   ├── components/          # React components
    │   │   ├── pages/               # Page components
    │   │   ├── hooks/               # Custom hooks
    │   │   ├── services/            # API services
    │   │   ├── types/               # TypeScript types
    │   │   ├── utils/               # Utility functions
    │   │   ├── App.tsx
    │   │   └── main.tsx
    │   ├── public/
    │   ├── package.json
    │   ├── tsconfig.json
    │   ├── vite.config.ts
    │   └── README.md
    │
    └── docs/                        # Additional documentation
        ├── API.md                   # API documentation
        ├── ARCHITECTURE.md          # Architecture overview
        └── DEPLOYMENT.md            # Deployment guide
```

---

## Directory Purposes

### `/spec` - Specification Files

Contains copies of the project specifications used to generate the code.

```go
type SpecCopier struct {
    specManager *SpecManager
}

func (c *SpecCopier) CopyToRepo(projectID, repoPath string) error {
    specDir := filepath.Join(repoPath, "spec")
    if err := os.MkdirAll(specDir, 0755); err != nil {
        return err
    }
    
    // Get all spec files for project
    specs, err := c.specManager.ListSpecs(projectID)
    if err != nil {
        return err
    }
    
    for _, spec := range specs {
        destPath := filepath.Join(specDir, spec.FileName)
        if err := copyFile(spec.FilePath, destPath); err != nil {
            return err
        }
    }
    
    return nil
}
```

### `/BE` - Backend (Go)

Standard Go project layout following [golang-standards/project-layout](https://github.com/golang-standards/project-layout).

```
BE/
├── cmd/                    # Main applications
│   └── server/
│       └── main.go         # Application entry point
│
├── internal/               # Private application code
│   ├── api/                # HTTP/API layer
│   │   ├── handlers/       # Request handlers
│   │   ├── middleware/     # HTTP middleware
│   │   └── routes.go       # Route definitions
│   │
│   ├── service/            # Business logic layer
│   │   └── {feature}/      # Feature-specific services
│   │
│   ├── repository/         # Data access layer
│   │   └── {entity}/       # Entity-specific repositories
│   │
│   ├── model/              # Domain models
│   │   └── {entity}.go
│   │
│   └── config/             # Configuration
│       └── config.go
│
├── pkg/                    # Public libraries
│   └── {library}/
│
├── migrations/             # Database migrations
│
├── go.mod                  # Go modules file
├── go.sum                  # Dependencies checksum
├── Makefile                # Build automation
└── README.md               # Backend documentation
```

### `/FE` - Frontend (React)

Standard Vite + React + TypeScript layout.

```
FE/
├── src/
│   ├── components/         # Reusable UI components
│   │   ├── ui/             # Base UI components (Button, Input, etc.)
│   │   └── {feature}/      # Feature-specific components
│   │
│   ├── pages/              # Page components
│   │   └── {PageName}/
│   │       ├── index.tsx
│   │       └── components/ # Page-specific components
│   │
│   ├── hooks/              # Custom React hooks
│   │   └── use{Hook}.ts
│   │
│   ├── services/           # API and external services
│   │   ├── api.ts          # API client
│   │   └── {feature}.ts    # Feature-specific API calls
│   │
│   ├── types/              # TypeScript type definitions
│   │   └── {domain}.ts
│   │
│   ├── utils/              # Utility functions
│   │   └── {utility}.ts
│   │
│   ├── stores/             # State management (if used)
│   │
│   ├── App.tsx             # Root component
│   ├── main.tsx            # Entry point
│   └── index.css           # Global styles
│
├── public/                 # Static assets
│
├── package.json            # NPM dependencies
├── tsconfig.json           # TypeScript config
├── vite.config.ts          # Vite config
├── tailwind.config.js      # Tailwind config
├── .eslintrc.cjs           # ESLint config
└── README.md               # Frontend documentation
```

---

## Initialization Templates

### Root README.md

```markdown
# {{.ProjectName}}

{{.ProjectDescription}}

## Project Structure

- `/spec` - Project specifications
- `/BE` - Backend service (Go)
- `/FE` - Frontend application (React)
- `/docs` - Additional documentation

## Getting Started

### Prerequisites

- Go 1.21+
- Node.js 20+
- SQLite 3

### Backend

```bash
cd BE
go mod download
go run cmd/server/main.go
```

### Frontend

```bash
cd FE
npm install
npm run dev
```

## Specifications

This project was generated from the following specifications:

{{range .Specifications}}
- [{{.Name}}](./spec/{{.FileName}})
{{end}}

## Generated By

Spec Management Software v{{.Version}}
Generated on {{.GeneratedAt}}
```

### Backend go.mod

```go
module {{.ModuleName}}

go 1.21

require (
    github.com/gin-gonic/gin v1.9.1
    gorm.io/gorm v1.25.5
    gorm.io/driver/sqlite v1.5.4
)
```

### Frontend package.json

```json
{
  "name": "{{.ProjectName}}-frontend",
  "private": true,
  "version": "0.0.1",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "lint": "eslint . --ext ts,tsx",
    "preview": "vite preview"
  },
  "dependencies": {
    "react": "^18.3.1",
    "react-dom": "^18.3.1",
    "react-router-dom": "^6.30.0",
    "@tanstack/react-query": "^5.40.0"
  },
  "devDependencies": {
    "@types/react": "^18.3.3",
    "@types/react-dom": "^18.3.0",
    "@typescript-eslint/eslint-plugin": "^7.0.0",
    "@typescript-eslint/parser": "^7.0.0",
    "@vitejs/plugin-react": "^4.3.0",
    "autoprefixer": "^10.4.19",
    "eslint": "^8.57.0",
    "postcss": "^8.4.38",
    "tailwindcss": "^3.4.4",
    "typescript": "^5.5.0",
    "vite": "^5.3.0"
  }
}
```

---

## .gitignore Template

```gitignore
# Dependencies
node_modules/
vendor/

# Build outputs
dist/
build/
*.exe
*.dll
*.so
*.dylib

# IDE
.idea/
.vscode/
*.swp
*.swo

# Environment
.env
.env.local
*.local

# Logs
*.log
logs/

# Testing
coverage/
*.test

# OS files
.DS_Store
Thumbs.db

# Temporary files
tmp/
temp/
*.tmp
```

---

## Structure Generator

```go
type StructureGenerator struct {
    templateEngine *template.Template
}

type StructureConfig struct {
    ProjectID       string
    ProjectName     string
    Description     string
    ModuleName      string           // Go module name
    IncludeBackend  bool
    IncludeFrontend bool
    Specifications  []SpecReference
}

func (g *StructureGenerator) Generate(repoPath string, config StructureConfig) error {
    // Create root structure
    dirs := []string{"spec", "docs"}
    if config.IncludeBackend {
        dirs = append(dirs, 
            "BE/cmd/server",
            "BE/internal/api/handlers",
            "BE/internal/api/middleware",
            "BE/internal/service",
            "BE/internal/repository",
            "BE/internal/model",
            "BE/internal/config",
            "BE/pkg",
            "BE/migrations",
        )
    }
    if config.IncludeFrontend {
        dirs = append(dirs,
            "FE/src/components/ui",
            "FE/src/pages",
            "FE/src/hooks",
            "FE/src/services",
            "FE/src/types",
            "FE/src/utils",
            "FE/public",
        )
    }
    
    // Create directories
    for _, dir := range dirs {
        if err := os.MkdirAll(filepath.Join(repoPath, dir), 0755); err != nil {
            return err
        }
    }
    
    // Generate files from templates
    files := g.getTemplateFiles(config)
    for path, tmplName := range files {
        if err := g.generateFile(repoPath, path, tmplName, config); err != nil {
            return err
        }
    }
    
    return nil
}

func (g *StructureGenerator) getTemplateFiles(config StructureConfig) map[string]string {
    files := map[string]string{
        "README.md":    "readme.tmpl",
        ".gitignore":   "gitignore.tmpl",
    }
    
    if config.IncludeBackend {
        files["BE/go.mod"] = "go_mod.tmpl"
        files["BE/cmd/server/main.go"] = "main_go.tmpl"
        files["BE/README.md"] = "be_readme.tmpl"
        files["BE/Makefile"] = "makefile.tmpl"
    }
    
    if config.IncludeFrontend {
        files["FE/package.json"] = "package_json.tmpl"
        files["FE/tsconfig.json"] = "tsconfig.tmpl"
        files["FE/vite.config.ts"] = "vite_config.tmpl"
        files["FE/tailwind.config.js"] = "tailwind_config.tmpl"
        files["FE/src/main.tsx"] = "main_tsx.tmpl"
        files["FE/src/App.tsx"] = "app_tsx.tmpl"
        files["FE/src/index.css"] = "index_css.tmpl"
        files["FE/README.md"] = "fe_readme.tmpl"
    }
    
    return files
}
```

---

## Custom Structure Support

For projects requiring non-standard layouts:

```go
type CustomStructure struct {
    Directories []DirectoryDef `json:"directories"`
    Files       []FileDef      `json:"files"`
}

type DirectoryDef struct {
    Path        string `json:"path"`
    Purpose     string `json:"purpose"`
    Required    bool   `json:"required"`
}

type FileDef struct {
    Path        string `json:"path"`
    Template    string `json:"template"`
    Required    bool   `json:"required"`
}

// Example custom structure JSON
/*
{
  "directories": [
    {"path": "src", "purpose": "Source code", "required": true},
    {"path": "lib", "purpose": "Libraries", "required": false},
    {"path": "test", "purpose": "Tests", "required": true}
  ],
  "files": [
    {"path": "src/main.go", "template": "main_go.tmpl", "required": true},
    {"path": "Makefile", "template": "makefile.tmpl", "required": false}
  ]
}
*/
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8700 | ERR_REPO_CREATE_DIR_FAILED | Failed to create repository directory |
| 8701 | ERR_REPO_TEMPLATE_FAILED | Failed to generate template file |
| 8702 | ERR_REPO_INVALID_STRUCTURE | Invalid custom structure definition |
| 8703 | ERR_REPO_COPY_SPEC_FAILED | Failed to copy specification files |

---

## Related Specs

- [Git Integration](./04-git-integration.md)
- [Spec Folder Guideline](./09-spec-folder-guideline.md)
- [Build Verification](./06-build-verification.md)
