# Configuration

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Configuration schema for WP Plugin Builder, with automatic seeding on first run or version change.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [BRun Configuration](../brun-cli/03-configuration.md)
- [GSearch Configuration](../gsearch-cli/02-configuration.md)

---

## Configuration File Location

Default search order:
1. `--config` flag value
2. `./wpb.json` (current directory)
3. `~/.wpb/wpb.json` (user home)
4. `/etc/wpb/wpb.json` (system-wide, Linux/macOS)

---

## Seeding Behavior

Configuration is seeded automatically when:
1. **First Run:** No config file exists
2. **Version Change:** Config version differs from binary version
3. **Manual:** `wpb config init --force`

### Seeding Process

```go
func SeedConfig(force bool) error {
    configPath := getConfigPath()
    
    // Check if seeding needed
    if !force && fileExists(configPath) {
        existing := loadConfig(configPath)
        if existing.Version == BinaryVersion {
            return nil // Already seeded with current version
        }
        // Backup and migrate
        backup(configPath)
    }
    
    // Create default config
    config := DefaultConfig()
    config.Version = BinaryVersion
    config.SeededAt = time.Now()
    
    return writeConfig(configPath, config)
}
```

---

## Complete Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "wpb-config-schema",
  "title": "WP Plugin Builder Configuration",
  "type": "object",
  "properties": {
    "version": {
      "type": "string",
      "description": "Configuration schema version",
      "default": "1.0.0"
    },
    "seededAt": {
      "type": "string",
      "format": "date-time",
      "description": "When config was last seeded"
    },
    "database": {
      "type": "object",
      "description": "Database configuration",
      "properties": {
        "rootPath": {
          "type": "string",
          "default": "~/.wpb/wpb.sqlite",
          "description": "Path to root database"
        },
        "projectDir": {
          "type": "string",
          "default": "~/.wpb/projects",
          "description": "Directory for project databases"
        },
        "backupEnabled": {
          "type": "boolean",
          "default": true
        },
        "backupRetention": {
          "type": "integer",
          "default": 7,
          "description": "Days to keep backups"
        }
      }
    },
    "aiBridge": {
      "type": "object",
      "description": "AI Bridge connection settings",
      "properties": {
        "url": {
          "type": "string",
          "default": "http://localhost:8089",
          "description": "AI Bridge endpoint"
        },
        "timeout": {
          "type": "string",
          "default": "60s",
          "description": "Request timeout"
        },
        "retries": {
          "type": "integer",
          "default": 3
        },
        "retryDelay": {
          "type": "string",
          "default": "1s"
        },
        "model": {
          "type": "string",
          "default": "",
          "description": "Preferred model (empty = AI Bridge default)"
        }
      }
    },
    "rag": {
      "type": "object",
      "description": "RAG system configuration",
      "properties": {
        "chunkSize": {
          "type": "integer",
          "default": 1000,
          "description": "Text chunk size for embedding"
        },
        "chunkOverlap": {
          "type": "integer",
          "default": 200,
          "description": "Overlap between chunks"
        },
        "topK": {
          "type": "integer",
          "default": 5,
          "description": "Number of results to retrieve"
        },
        "minSimilarity": {
          "type": "number",
          "default": 0.7,
          "description": "Minimum similarity score (0-1)"
        },
        "embeddingModel": {
          "type": "string",
          "default": "",
          "description": "Embedding model (empty = AI Bridge default)"
        }
      }
    },
    "generation": {
      "type": "object",
      "description": "Code generation settings",
      "properties": {
        "outputDir": {
          "type": "string",
          "default": "./plugins",
          "description": "Default output directory"
        },
        "overwriteMode": {
          "type": "string",
          "enum": ["skip", "overwrite", "backup"],
          "default": "backup"
        },
        "validate": {
          "type": "boolean",
          "default": true,
          "description": "Validate generated code"
        },
        "formatting": {
          "type": "object",
          "properties": {
            "indentStyle": {
              "type": "string",
              "enum": ["tabs", "spaces"],
              "default": "tabs"
            },
            "indentSize": {
              "type": "integer",
              "default": 4
            },
            "lineEnding": {
              "type": "string",
              "enum": ["lf", "crlf"],
              "default": "lf"
            }
          }
        },
        "templates": {
          "type": "object",
          "description": "Custom template paths",
          "properties": {
            "plugin": { "type": "string" },
            "class": { "type": "string" },
            "admin": { "type": "string" },
            "public": { "type": "string" }
          }
        }
      }
    },
    "server": {
      "type": "object",
      "description": "Server mode configuration",
      "properties": {
        "host": {
          "type": "string",
          "default": "localhost"
        },
        "port": {
          "type": "integer",
          "default": 8090
        },
        "cors": {
          "type": "boolean",
          "default": true
        },
        "corsOrigins": {
          "type": "array",
          "items": { "type": "string" },
          "default": ["*"]
        },
        "rateLimit": {
          "type": "object",
          "properties": {
            "enabled": { "type": "boolean", "default": true },
            "requestsPerMinute": { "type": "integer", "default": 60 }
          }
        }
      }
    },
    "logging": {
      "type": "object",
      "description": "Logging configuration",
      "properties": {
        "level": {
          "type": "string",
          "enum": ["debug", "info", "warn", "error"],
          "default": "info"
        },
        "format": {
          "type": "string",
          "enum": ["text", "json"],
          "default": "text"
        },
        "directory": {
          "type": "string",
          "default": "~/.wpb/logs"
        },
        "maxSize": {
          "type": "string",
          "default": "10MB"
        },
        "maxFiles": {
          "type": "integer",
          "default": 5
        },
        "includeStackTrace": {
          "type": "boolean",
          "default": true
        }
      }
    },
    "wordpress": {
      "type": "object",
      "description": "WordPress-specific defaults",
      "properties": {
        "minVersion": {
          "type": "string",
          "default": "6.0"
        },
        "testedUpTo": {
          "type": "string",
          "default": "6.4"
        },
        "requiresPHP": {
          "type": "string",
          "default": "7.4"
        },
        "license": {
          "type": "string",
          "default": "GPL-2.0-or-later"
        },
        "licenseURI": {
          "type": "string",
          "default": "https://www.gnu.org/licenses/gpl-2.0.html"
        }
      }
    },
    "presets": {
      "type": "object",
      "description": "Preset loading configuration",
      "properties": {
        "autoLoad": {
          "type": "array",
          "items": { "type": "string" },
          "default": [],
          "description": "Presets to load on project creation"
        },
        "directory": {
          "type": "string",
          "default": "~/.wpb/presets",
          "description": "Custom presets directory"
        }
      }
    }
  }
}
```

---

## Example Configuration

```json
{
  "version": "1.0.0",
  "seededAt": "2026-02-01T10:00:00Z",
  "database": {
    "rootPath": "~/.wpb/wpb.sqlite",
    "projectDir": "~/.wpb/projects",
    "backupEnabled": true,
    "backupRetention": 7
  },
  "aiBridge": {
    "url": "http://localhost:8089",
    "timeout": "60s",
    "retries": 3,
    "retryDelay": "1s",
    "model": ""
  },
  "rag": {
    "chunkSize": 1000,
    "chunkOverlap": 200,
    "topK": 5,
    "minSimilarity": 0.7,
    "embeddingModel": ""
  },
  "generation": {
    "outputDir": "./plugins",
    "overwriteMode": "backup",
    "validate": true,
    "formatting": {
      "indentStyle": "tabs",
      "indentSize": 4,
      "lineEnding": "lf"
    }
  },
  "server": {
    "host": "localhost",
    "port": 8090,
    "cors": true,
    "corsOrigins": ["*"],
    "rateLimit": {
      "enabled": true,
      "requestsPerMinute": 60
    }
  },
  "logging": {
    "level": "info",
    "format": "text",
    "directory": "~/.wpb/logs",
    "maxSize": "10MB",
    "maxFiles": 5,
    "includeStackTrace": true
  },
  "wordpress": {
    "minVersion": "6.0",
    "testedUpTo": "6.4",
    "requiresPHP": "7.4",
    "license": "GPL-2.0-or-later",
    "licenseURI": "https://www.gnu.org/licenses/gpl-2.0.html"
  },
  "presets": {
    "autoLoad": ["wordpress-core-standards"],
    "directory": "~/.wpb/presets"
  }
}
```

---

## Environment Variable Overrides

All configuration values can be overridden via environment variables with the `WPB_` prefix:

| Config Key | Environment Variable |
|------------|---------------------|
| `database.rootPath` | `WPB_DATABASE_ROOTPATH` |
| `aiBridge.url` | `WPB_AIBRIDGE_URL` |
| `server.port` | `WPB_SERVER_PORT` |
| `logging.level` | `WPB_LOGGING_LEVEL` |

---

## Migration on Version Change

When binary version changes:

1. Backup existing config: `wpb.json.bak.{timestamp}`
2. Load existing values
3. Apply new defaults for new fields
4. Preserve user customizations
5. Write merged config
6. Update `version` and `seededAt`

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Database Schema](./04-database-schema.md)
- [Error Handling](./10-error-handling.md)
