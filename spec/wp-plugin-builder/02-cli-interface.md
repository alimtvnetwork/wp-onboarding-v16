# CLI Interface

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Command-line interface design for WP Plugin Builder, following patterns from GSearch and BRun CLIs.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Configuration](./03-configuration.md)
- [BRun CLI Interface](../brun-cli/02-cli-interface.md)

---

## Command Structure

```
wpb <command> [subcommand] [flags]
```

---

## Global Flags

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--config` | `-c` | string | `./wpb.json` | Config file path |
| `--verbose` | `-v` | bool | `false` | Verbose output |
| `--json` | `-j` | bool | `false` | JSON output format |
| `--quiet` | `-q` | bool | `false` | Suppress non-error output |
| `--help` | `-h` | bool | | Show help |
| `--version` | | bool | | Show version |

---

## Commands

### 1. project

Manage plugin projects.

#### project create

Create a new plugin project.

```bash
wpb project create <name> [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--author` | `-a` | string | | Plugin author name |
| `--email` | `-e` | string | | Author email |
| `--website` | `-w` | string | | Plugin/author website |
| `--description` | `-d` | string | | Plugin description |
| `--version` | | string | `1.0.0` | Initial version |
| `--text-domain` | | string | (from name) | Plugin text domain |
| `--namespace` | | string | (from name) | PHP namespace |
| `--output` | `-o` | string | `./plugins` | Output directory |

**Example:**
```bash
wpb project create exam-manager \
  --author "John Doe" \
  --email "john@example.com" \
  --website "https://example.com" \
  --description "Exam management system for WordPress"
```

#### project list

List all projects.

```bash
wpb project list [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--format` | string | `table` | Output format: table, json, csv |

#### project open

Open/activate a project for operations.

```bash
wpb project open <name>
```

#### project delete

Delete a project and its database.

```bash
wpb project delete <name> [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--force` | bool | `false` | Skip confirmation |
| `--keep-files` | bool | `false` | Keep generated files |

#### project clone

Clone an existing project.

```bash
wpb project clone <source> <target> [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--include-history` | bool | `true` | Include generation history |

#### project export

Export project to portable format.

```bash
wpb project export <name> [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--output` | `-o` | string | `./<name>.sqlite` | Output path |
| `--format` | | string | `sqlite` | Export format: sqlite, zip |
| `--include-files` | | bool | `true` | Include generated files (zip only) |

#### project import

Import project from file.

```bash
wpb project import <path> [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--name` | string | (from file) | Override project name |
| `--overwrite` | bool | `false` | Overwrite if exists |

---

### 2. preset

Manage learning presets.

#### preset import

Import markdown as learning preset.

```bash
wpb preset import <path> [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--name` | `-n` | string | (from file) | Preset name |
| `--category` | | string | `general` | Category: core, admin, api, shortcode, block |
| `--project` | `-p` | string | | Apply to specific project only |

**Example:**
```bash
wpb preset import ./docs/wordpress-security-best-practices.md \
  --name "security-guidelines" \
  --category "core"
```

#### preset list

List available presets.

```bash
wpb preset list [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--category` | string | | Filter by category |
| `--project` | string | | Filter by project |

#### preset apply

Apply preset(s) to a project.

```bash
wpb preset apply <preset-name> --project <project-name>
```

#### preset export

Export preset to markdown.

```bash
wpb preset export <name> --output <path>
```

---

### 3. generate

Generate plugin code.

```bash
wpb generate [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--project` | `-p` | string | (active) | Target project |
| `--spec` | `-s` | string | | Specification file/folder |
| `--output` | `-o` | string | | Output directory |
| `--component` | | string | | Specific component to generate |
| `--validate` | | bool | `true` | Validate against spec |
| `--overwrite` | | string | `backup` | skip, overwrite, backup |
| `--dry-run` | | bool | `false` | Preview without writing |
| `--stream` | | bool | `false` | Stream AI output |

**Example:**
```bash
wpb generate \
  --project exam-manager \
  --spec ./specs/exam-crud.md \
  --output ./plugins/exam-manager \
  --validate
```

---

### 4. validate

Validate generated code.

```bash
wpb validate [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--project` | `-p` | string | (active) | Target project |
| `--spec` | `-s` | string | | Specification to validate against |
| `--path` | | string | | Path to code files |
| `--fix` | | bool | `false` | Auto-fix issues |

---

### 5. spec

Manage specifications.

#### spec import

Import specification files.

```bash
wpb spec import <path> [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--project` | `-p` | string | (active) | Target project |
| `--format` | | string | `auto` | Format: md, zip, folder |

**Supports:**
- Single markdown file
- Folder of markdown files
- Zip archive containing markdown

#### spec list

List imported specifications.

```bash
wpb spec list --project <name>
```

#### spec show

Display specification content.

```bash
wpb spec show <spec-name> --project <name>
```

---

### 6. server

Run as HTTP server.

#### server start

Start the REST API server.

```bash
wpb server start [flags]
```

| Flag | Short | Type | Default | Description |
|------|-------|------|---------|-------------|
| `--port` | `-p` | int | `8090` | Listen port |
| `--host` | | string | `localhost` | Listen host |
| `--cors` | | bool | `true` | Enable CORS |

#### server stop

Stop the running server (daemon mode).

```bash
wpb server stop
```

#### server status

Check server status.

```bash
wpb server status
```

---

### 7. config

Manage configuration.

#### config init

Initialize configuration (seeding).

```bash
wpb config init [flags]
```

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--force` | bool | `false` | Overwrite existing config |

#### config show

Display current configuration.

```bash
wpb config show [--json]
```

#### config set

Set configuration value.

```bash
wpb config set <key> <value>
```

---

### 8. help

Show help for commands.

```bash
wpb help [command]
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General error |
| 2 | Configuration error |
| 3 | Database error |
| 4 | AI Bridge error |
| 5 | Validation error |
| 126 | Invalid command/flag |
| 127 | Binary/dependency not found |
| 130 | Interrupted (SIGINT) |

---

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `WPB_CONFIG` | Config file path | `./wpb.json` |
| `WPB_ROOT_DB` | Root database path | `~/.wpb/wpb.sqlite` |
| `WPB_PROJECT_DIR` | Project storage directory | `~/.wpb/projects` |
| `WPB_AI_BRIDGE_URL` | AI Bridge endpoint | `http://localhost:8089` |
| `WPB_LOG_LEVEL` | Log level (debug, info, warn, error) | `info` |

---

## See Also

- [Configuration](./03-configuration.md)
- [API Interface](./11-api-interface.md)
- [Error Handling](./10-error-handling.md)
