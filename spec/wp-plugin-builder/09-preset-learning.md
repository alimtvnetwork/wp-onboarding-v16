# Preset Learning

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Preset learning system for ingesting WordPress plugin best practices, coding standards, and patterns into the RAG knowledge base.

**Cross-References:**
- [RAG System](./05-rag-system.md)
- [Database Schema](./04-database-schema.md)
- [Coding Guidelines](./12-coding-guidelines.md)

---

## Preset Categories

| Category | Description | Examples |
|----------|-------------|----------|
| `core` | Core WordPress plugin patterns | Main plugin file, activation hooks, autoloading |
| `admin` | Admin panel patterns | Settings pages, admin notices, menus |
| `api` | REST API patterns | Custom endpoints, authentication, responses |
| `shortcode` | Shortcode patterns | Shortcode registration, attributes, output |
| `block` | Gutenberg blocks | Block registration, attributes, save/edit |
| `security` | Security practices | Nonces, sanitization, escaping |
| `database` | Database operations | Custom tables, queries, migrations |
| `general` | General best practices | Coding standards, documentation |

---

## Preset Structure

### Markdown Format

```markdown
---
name: WordPress Security Best Practices
category: security
version: 1.0.0
author: WP Plugin Builder
tags: [security, sanitization, nonces]
---

# WordPress Security Best Practices

## Nonce Verification

Always verify nonces for form submissions and AJAX requests.

### Example: Form Nonce

\`\`\`php
// Create nonce field
wp_nonce_field( 'my_plugin_action', 'my_plugin_nonce' );

// Verify nonce
if ( ! wp_verify_nonce( $_POST['my_plugin_nonce'], 'my_plugin_action' ) ) {
    wp_die( 'Security check failed' );
}
\`\`\`

## Data Sanitization

Always sanitize user input before processing.

### Text Input
\`\`\`php
$title = sanitize_text_field( $_POST['title'] );
\`\`\`

### HTML Content
\`\`\`php
$content = wp_kses_post( $_POST['content'] );
\`\`\`

## Output Escaping

Always escape output to prevent XSS.

\`\`\`php
echo esc_html( $user_input );
echo esc_attr( $attribute_value );
echo esc_url( $url );
\`\`\`
```

---

## Preset Manager

```go
type PresetManager struct {
    rootDB     *gorm.DB
    ragService *RAGService
    presetDir  string
}

type Preset struct {
    ID          uint      `gorm:"primaryKey"`
    Name        string    `gorm:"uniqueIndex"`
    Category    string    `gorm:"index"`
    Description string
    SourcePath  string
    ContentHash string
    ChunkCount  int
    Tags        JSON
    IsActive    bool      `gorm:"default:true"`
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

type PresetMetadata struct {
    Name     string   `yaml:"name"`
    Category string   `yaml:"category"`
    Version  string   `yaml:"version"`
    Author   string   `yaml:"author"`
    Tags     []string `yaml:"tags"`
}
```

---

## Import Flow

```go
func (pm *PresetManager) Import(path string, opts ImportPresetOptions) (*Preset, error) {
    // 1. Read file
    content, err := os.ReadFile(path)
    if err != nil {
        return nil, errors.Wrap(err, 10405, "preset file read failed")
    }
    
    // 2. Parse frontmatter
    meta, body := parseFrontmatter(string(content))
    
    // 3. Override with options
    if opts.Name != "" {
        meta.Name = opts.Name
    }
    if opts.Category != "" {
        meta.Category = opts.Category
    }
    
    // 4. Check for duplicate
    var existing Preset
    if err := pm.rootDB.Where("name = ?", meta.Name).First(&existing).Error; err == nil {
        if !opts.Update {
            return nil, errors.New(10406, "preset already exists").
                WithField("name", meta.Name)
        }
        // Update existing
        return pm.updatePreset(&existing, body, meta)
    }
    
    // 5. Create preset record
    preset := &Preset{
        Name:        meta.Name,
        Category:    meta.Category,
        Description: extractDescription(body),
        SourcePath:  path,
        ContentHash: hash(content),
        Tags:        meta.Tags,
    }
    
    // 6. Index content for RAG
    source := SourceInfo{
        Type: "preset",
        ID:   meta.Name,
        Name: meta.Name,
    }
    if err := pm.ragService.IndexDocument(source, body); err != nil {
        return nil, errors.Wrap(err, 10407, "preset indexing failed")
    }
    
    // 7. Count chunks
    preset.ChunkCount = pm.ragService.CountChunks("preset", meta.Name)
    
    // 8. Save to database
    if err := pm.rootDB.Create(preset).Error; err != nil {
        return nil, errors.Wrap(err, 10408, "preset creation failed")
    }
    
    return preset, nil
}
```

---

## Built-in Presets

### WordPress Core Standards

```go
var builtInPresets = []BuiltInPreset{
    {
        Name:     "wordpress-core-standards",
        Category: "core",
        Content:  wordPressCoreStandards,
    },
    {
        Name:     "wordpress-security",
        Category: "security",
        Content:  wordPressSecurity,
    },
    {
        Name:     "wordpress-admin-patterns",
        Category: "admin",
        Content:  wordPressAdminPatterns,
    },
    {
        Name:     "wordpress-rest-api",
        Category: "api",
        Content:  wordPressRestAPI,
    },
    {
        Name:     "wordpress-gutenberg-blocks",
        Category: "block",
        Content:  wordPressGutenbergBlocks,
    },
}

func (pm *PresetManager) LoadBuiltIns() error {
    for _, preset := range builtInPresets {
        // Check if already loaded
        var existing Preset
        if err := pm.rootDB.Where("name = ?", preset.Name).First(&existing).Error; err == nil {
            continue // Already exists
        }
        
        // Import built-in
        if _, err := pm.importFromContent(preset.Name, preset.Category, preset.Content); err != nil {
            log.Warn("built-in preset import failed", "name", preset.Name, "error", err)
        }
    }
    return nil
}
```

---

## Apply Presets to Project

```go
func (pm *PresetManager) ApplyToProject(presetName string, projectDB *gorm.DB) error {
    // 1. Get preset
    var preset Preset
    if err := pm.rootDB.Where("name = ?", presetName).First(&preset).Error; err != nil {
        return errors.New(10409, "preset not found")
    }
    
    // 2. Get preset vectors from root DB
    var vectors []PresetVector
    if err := pm.rootDB.Where("preset_id = ?", preset.ID).Find(&vectors).Error; err != nil {
        return errors.Wrap(err, 10410, "preset vectors fetch failed")
    }
    
    // 3. Copy to project DB as RAG vectors
    for _, vec := range vectors {
        ragVec := RAGVector{
            SourceType:  "preset",
            SourceID:    preset.Name,
            ChunkIndex:  vec.ChunkIndex,
            Content:     vec.Content,
            Embedding:   vec.Embedding,
            Metadata: map[string]any{
                "preset_name": preset.Name,
                "category":    preset.Category,
                "applied_at":  time.Now(),
            },
        }
        if err := projectDB.Create(&ragVec).Error; err != nil {
            return errors.Wrap(err, 10411, "vector copy failed")
        }
    }
    
    return nil
}
```

---

## Auto-Load Presets

Configured in `wpb.json`:

```json
{
  "presets": {
    "autoLoad": [
      "wordpress-core-standards",
      "wordpress-security"
    ]
  }
}
```

Applied during project creation:

```go
func (pm *ProjectManager) applyAutoLoadPresets(projectDB *gorm.DB, project *Project) error {
    for _, presetName := range pm.config.Presets.AutoLoad {
        if err := pm.presetManager.ApplyToProject(presetName, projectDB); err != nil {
            log.Warn("auto-load preset failed", "preset", presetName, "error", err)
            // Continue with other presets
        }
    }
    return nil
}
```

---

## Export Preset

```go
func (pm *PresetManager) Export(name string, outputPath string) error {
    // 1. Get preset
    var preset Preset
    if err := pm.rootDB.Where("name = ?", name).First(&preset).Error; err != nil {
        return errors.New(10409, "preset not found")
    }
    
    // 2. Get vectors and reconstruct content
    var vectors []PresetVector
    if err := pm.rootDB.Where("preset_id = ?", preset.ID).
        Order("chunk_index").Find(&vectors).Error; err != nil {
        return err
    }
    
    // 3. Build markdown
    var content strings.Builder
    content.WriteString("---\n")
    content.WriteString(fmt.Sprintf("name: %s\n", preset.Name))
    content.WriteString(fmt.Sprintf("category: %s\n", preset.Category))
    content.WriteString("---\n\n")
    
    for _, vec := range vectors {
        content.WriteString(vec.Content)
        content.WriteString("\n\n")
    }
    
    // 4. Write file
    return os.WriteFile(outputPath, []byte(content.String()), 0644)
}
```

---

## Learning Pipeline

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Markdown   │───▶│   Parse     │───▶│   Chunk     │
│   File      │    │ Frontmatter │    │   Text      │
└─────────────┘    └─────────────┘    └──────┬──────┘
                                              │
                                              ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Store     │◀───│   Embed     │◀───│  Prepare    │
│  Vectors    │    │  via AI     │    │   Batch     │
└─────────────┘    └─────────────┘    └─────────────┘
```

---

## See Also

- [RAG System](./05-rag-system.md)
- [Coding Guidelines](./12-coding-guidelines.md)
- [Configuration](./03-configuration.md)
