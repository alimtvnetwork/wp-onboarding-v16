# AI-Assisted Configuration Generation

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Specification for AI-assisted automatic generation of brun configuration files. The main Spec Management application feeds the JSON schema documentation to an AI model, which then generates configuration for new applications automatically.

**Cross-References:**
- [Configuration](./03-configuration.md)
- [Build Profiles](./07-build-profiles.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## Architecture Clarification

> **Important:** The `brun` CLI does NOT contain any AI capabilities. It is a pure build runner tool. All AI processing occurs in the **main Spec Management application**, which:
> 1. Hosts the LLM models (via Ollama/llama.cpp)
> 2. Reads brun's config schema documentation
> 3. Generates config.json for applications being built
> 4. Analyzes build errors returned by brun

---

## Complete AI + brun Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MAIN SPEC MANAGEMENT APPLICATION                      │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │  LLM Models │    │  AI Service │    │  Config Gen │    │  Error      │  │
│  │  (Local)    │◄──►│  (Reasoning)│◄──►│  Service    │◄──►│  Analyzer   │  │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
│         │                  │                  │                  │          │
│         │    ┌─────────────┴──────────────────┴──────────────────┘          │
│         │    │                                                              │
│         │    ▼                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                      brun Schema Documentation                       │   │
│  │   • config.json schema with _aiDescription fields                   │   │
│  │   • Application definitions and health check options                │   │
│  │   • Build profile templates and examples                            │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ Subprocess call
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              brun CLI (No AI)                                │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │  Config     │    │  Runtime    │    │  Port       │    │  Error      │  │
│  │  Loader     │    │  Executors  │    │  Manager    │    │  Capture    │  │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
│         │                  │                  │                  │          │
│         └──────────────────┴──────────────────┴──────────────────┘          │
│                                      │                                       │
│                                      ▼                                       │
│                           JSON Output / Log Files                            │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Workflow: Config Generation

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              MAIN APPLICATION                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐                                                       │
│  │ 1. User Input    │  "I have a Go API at ./cmd/server on port 8080       │
│  │    (Voice/Text)  │   and a React app in ./frontend"                      │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐     ┌──────────────────────────────────────┐         │
│  │ 2. Load brun     │────►│  brun Config Schema Documentation    │         │
│  │    Schema Docs   │     │  • JSON Schema with AI descriptions  │         │
│  └────────┬─────────┘     │  • Examples for each field           │         │
│           │               │  • Validation rules                   │         │
│           │               └──────────────────────────────────────┘         │
│           ▼                                                                  │
│  ┌──────────────────┐     ┌──────────────────────────────────────┐         │
│  │ 3. AI Generates  │────►│  Generated config.json entries:      │         │
│  │    Config        │     │  {                                    │         │
│  │    (Local LLM)   │     │    "applications": [...],            │         │
│  └────────┬─────────┘     │    "profiles": [...]                 │         │
│           │               │  }                                    │         │
│           │               └──────────────────────────────────────┘         │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 4. UI Preview    │  User reviews, modifies fields via form editor       │
│  │    & Edit        │                                                       │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐     ┌──────────────────────────────────────┐         │
│  │ 5. Validate      │────►│  brun config validate                │         │
│  │    via brun      │     │  (subprocess, returns JSON)          │         │
│  └────────┬─────────┘     └──────────────────────────────────────┘         │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 6. Save Config   │  Write to config.json in project directory            │
│  └──────────────────┘                                                       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Workflow: AI-Assisted Build Error Fixing Loop

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              MAIN APPLICATION                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐                                                       │
│  │ 1. User triggers │  "Build my application" / "Check for errors"          │
│  │    build check   │                                                       │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐     ┌──────────────────────────────────────┐         │
│  │ 2. Execute brun  │────►│  $ brun check --profile backend --json│         │
│  │    (subprocess)  │     │                                       │         │
│  └────────┬─────────┘     └──────────────────────────────────────┘         │
│           │                                                                  │
│           │◄─────────────── JSON Response ──────────────────────────────────┤
│           │               {                                                  │
│           │                 "success": false,                                │
│           │                 "errors": [                                      │
│           │                   {"file": "main.go", "line": 15, ...}          │
│           │                 ]                                                │
│           │               }                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 3. Parse Errors  │  Extract file, line, message, context                 │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐     ┌──────────────────────────────────────┐         │
│  │ 4. AI Analyzes   │────►│  Prompt: "Fix these build errors:    │         │
│  │    Errors        │     │  - main.go:15 undefined: NewRouter   │         │
│  │    (Local LLM)   │     │  Provide corrected code."            │         │
│  └────────┬─────────┘     └──────────────────────────────────────┘         │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 5. AI Generates  │  Returns code patches / file modifications            │
│  │    Fix           │                                                       │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 6. Apply Fix     │  Write corrected code to source files                 │
│  │    to Files      │                                                       │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│  ┌──────────────────┐                                                       │
│  │ 7. Re-run brun   │  Loop back to step 2                                  │
│  │    (retry)       │                                                       │
│  └────────┬─────────┘                                                       │
│           │                                                                  │
│           ▼                                                                  │
│      ┌────┴────┐                                                            │
│      │ Success │───► Build Complete                                          │
│      │   ?     │                                                             │
│      └────┬────┘                                                            │
│           │ No (max retries not reached)                                     │
│           └──────────────────────► Loop to step 2                           │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ All brun calls are subprocess
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  brun CLI                                                                    │
│  • Receives commands via subprocess                                          │
│  • Returns JSON to stdout                                                    │
│  • Writes logs to filesystem                                                 │
│  • NO AI PROCESSING - pure build execution                                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Sequence Diagram: Full Integration

```
┌─────────┐     ┌────────────┐     ┌───────────┐     ┌──────────┐
│  User   │     │ Main App   │     │ AI (LLM)  │     │  brun    │
└────┬────┘     └─────┬──────┘     └─────┬─────┘     └────┬─────┘
     │                │                   │                │
     │ "Create config │                   │                │
     │  for my Go app"│                   │                │
     │───────────────►│                   │                │
     │                │                   │                │
     │                │ Load brun schema  │                │
     │                │ documentation     │                │
     │                │──────────────────►│                │
     │                │                   │                │
     │                │ Generate config   │                │
     │                │◄──────────────────│                │
     │                │                   │                │
     │ Preview config │                   │                │
     │◄───────────────│                   │                │
     │                │                   │                │
     │ Approve/Edit   │                   │                │
     │───────────────►│                   │                │
     │                │                   │                │
     │                │ Validate config   │                │
     │                │───────────────────────────────────►│
     │                │                   │                │
     │                │ Validation result │                │
     │                │◄───────────────────────────────────│
     │                │                   │                │
     │ Config saved   │                   │                │
     │◄───────────────│                   │                │
     │                │                   │                │
     │ "Build it"     │                   │                │
     │───────────────►│                   │                │
     │                │                   │                │
     │                │ brun check --json │                │
     │                │───────────────────────────────────►│
     │                │                   │                │
     │                │ {errors: [...]}   │                │
     │                │◄───────────────────────────────────│
     │                │                   │                │
     │                │ Analyze & fix     │                │
     │                │──────────────────►│                │
     │                │                   │                │
     │                │ Code patches      │                │
     │                │◄──────────────────│                │
     │                │                   │                │
     │                │ Apply fixes       │                │
     │                │─────────┐         │                │
     │                │◄────────┘         │                │
     │                │                   │                │
     │                │ brun check --json │                │
     │                │───────────────────────────────────►│
     │                │                   │                │
     │                │ {success: true}   │                │
     │                │◄───────────────────────────────────│
     │                │                   │                │
     │ Build success! │                   │                │
     │◄───────────────│                   │                │
     │                │                   │                │
```

---

## Schema Documentation Format

The configuration schema includes detailed descriptions that AI models can interpret:

### Application Definition Schema (for AI)

```json
{
  "applications": {
    "_aiDescription": "Named application definitions. Create one entry per runnable service.",
    "_aiExamples": [
      "Go backend API server",
      "React frontend dev server",
      "PowerShell deployment script"
    ],
    "items": {
      "name": {
        "_aiDescription": "Unique identifier for this application (snake_case, no spaces)",
        "_aiExamples": ["backend_api", "frontend_dev", "deploy_prod"]
      },
      "healthCheck": {
        "_aiDescription": "HTTP endpoint to verify application is running correctly",
        "url": {
          "_aiDescription": "Full URL or host:port/path format",
          "_aiExamples": ["http://localhost:8080/health", "localhost:3000", "127.0.0.1:5000/api/status"]
        },
        "expectedStatus": {
          "_aiDescription": "HTTP status codes that indicate healthy state",
          "_aiDefault": [200],
          "_aiExamples": [[200], [200, 201], [200, 204]]
        }
      }
    }
  }
}
```

### Build Profile Schema (for AI)

```json
{
  "profiles": {
    "_aiDescription": "Build configurations. Each profile defines how to build/run an application.",
    "items": {
      "name": {
        "_aiDescription": "Profile name matching application name or descriptive build name",
        "_aiExamples": ["backend-api", "frontend-prod", "test-runner"]
      },
      "runtime": {
        "_aiDescription": "Execution environment",
        "_aiOptions": {
          "golang": "For Go applications using 'go build' or 'go run'",
          "nodejs": "For npm/yarn/bun projects",
          "powershell": "For PowerShell scripts (.ps1)"
        }
      },
      "source": {
        "_aiDescription": "Path to source code or script relative to workDirectory",
        "_aiExamples": ["./cmd/api", "./frontend", "./scripts/deploy.ps1"]
      },
      "assets": {
        "_aiDescription": "Files to copy after build (static files, configs, etc.)",
        "mode": {
          "_aiOptions": {
            "copy": "Copy files without clearing destination",
            "clear-copy": "Clear destination folder first, then copy",
            "override": "Overwrite existing files only",
            "skip-existing": "Skip files that already exist"
          }
        }
      }
    }
  }
}
```

---

## AI Prompt Template

The main application uses this prompt template when asking AI to generate configuration:

```markdown
You are a build configuration expert. Generate a brun config.json entry based on the following:

## Application Description
{{USER_DESCRIPTION}}

## Available Configuration Schema
{{JSON_SCHEMA_WITH_AI_DESCRIPTIONS}}

## Current Configuration (if any)
{{EXISTING_CONFIG}}

## Requirements
1. Generate valid JSON that conforms to the schema
2. Include appropriate health check configuration
3. Set reasonable timeout values
4. Configure asset copying if static files are mentioned
5. Use descriptive profile names

## Output Format
Return ONLY valid JSON, no explanations. Structure:
{
  "applications": [...new entries...],
  "profiles": [...new entries...]
}
```

---

## Example Generation

### User Input
> "I have a Go backend at ./cmd/server that runs on port 8080 with a /health endpoint, and a React frontend in ./web that builds to ./web/dist which needs to be copied to ./public"

### AI-Generated Output

```json
{
  "applications": [
    {
      "name": "backend_server",
      "description": "Go backend API server",
      "healthCheck": {
        "enabled": true,
        "host": "localhost",
        "port": 8080,
        "path": "/health",
        "timeout": "5s",
        "retries": 30
      },
      "profile": "backend-server",
      "ports": {
        "primary": 8080,
        "fallback": [8081, 8082]
      }
    },
    {
      "name": "frontend_dev",
      "description": "React frontend development server",
      "healthCheck": {
        "enabled": true,
        "host": "localhost",
        "port": 3000,
        "path": "/",
        "timeout": "10s"
      },
      "profile": "frontend-build"
    }
  ],
  "profiles": [
    {
      "name": "backend-server",
      "description": "Build and run Go backend server",
      "runtime": "golang",
      "source": "./cmd/server",
      "port": 8080,
      "preCommands": ["go mod tidy"],
      "env": {
        "CGO_ENABLED": "0"
      }
    },
    {
      "name": "frontend-build",
      "description": "Build React frontend and copy to public",
      "runtime": "nodejs",
      "source": "./web",
      "command": "build",
      "assets": {
        "enabled": true,
        "operations": [
          {
            "source": "./web/dist",
            "destination": "./public",
            "mode": "clear-copy"
          }
        ]
      }
    }
  ]
}
```

---

## UI Integration

### Config Editor Features

The main application UI provides:

1. **Visual Config Editor**
   - Form-based editing of all configuration fields
   - Real-time validation against JSON schema
   - Autocomplete for paths and known values

2. **AI Generation Panel**
   - Text input for natural language description
   - "Generate Config" button triggers AI
   - Side-by-side preview of generated vs. existing config
   - "Merge" or "Replace" options

3. **Profile Manager**
   - List view of all profiles and applications
   - Drag-drop reordering
   - Duplicate/clone functionality
   - Quick enable/disable toggles

4. **Validation Feedback**
   - Inline error highlighting
   - Suggestions for missing fields
   - Schema compliance indicator

### API Endpoints

```typescript
// Generate config via AI
POST /api/brun/config/generate
{
  "description": "User's natural language description",
  "existingConfig": { /* current config.json contents */ },
  "mergeMode": "extend" | "replace"
}

// Validate config
POST /api/brun/config/validate
{
  "config": { /* config.json contents */ }
}

// Get schema with AI descriptions
GET /api/brun/config/schema

// Apply config to brun
PUT /api/brun/config
{
  "config": { /* config.json contents */ },
  "path": "./config.json"  // optional custom path
}
```

---

## Working Directory Flexibility

### CLI Parameters

```bash
# Run with specific working directory
brun run --app backend-api --workdir /projects/other-app

# Check build in different directory
brun check --go ./cmd/app --workdir /projects/experimental

# Override config's workDirectory
brun build --profile frontend --workdir /tmp/build-test
```

### Config Options

```json
{
  "workDirectory": "/projects/main-app",
  "allowExternalDirs": true,
  "applications": [
    {
      "name": "external_service",
      "workdir": "/opt/external-service",
      "profile": "external-build"
    }
  ]
}
```

### Security Considerations

| Setting | Value | Behavior |
|---------|-------|----------|
| `allowExternalDirs: false` | Default | Only allow paths within workDirectory |
| `allowExternalDirs: true` | Enabled | Allow absolute paths and paths outside workDirectory |

When `allowExternalDirs` is false:
- All paths are resolved relative to workDirectory
- Absolute paths are rejected
- Path traversal (`../`) that escapes workDirectory is blocked

---

## See Also

- [Configuration](./03-configuration.md)
- [Build Profiles](./07-build-profiles.md)
- [Integration API](./09-integration-api.md)
- [AI Integration](../06-ai-integration/00-overview.md)
