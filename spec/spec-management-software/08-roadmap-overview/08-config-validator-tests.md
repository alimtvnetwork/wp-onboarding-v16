# ConfigValidator Test Specification

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This document specifies unit tests for the `ConfigValidator` component defined in [09-seeding-configuration.md](./09-seeding-configuration.md) section 9.2.1. Tests cover range validation, format validation, cross-key dependencies, cron parsing, and path validation.

**Cross-References:**
- [Seeding Configuration](./09-seeding-configuration.md) - ConfigValidator implementation
- [Import/Export System](./29-import-export-system.md) - Uses validated config
- [Path Manager](./17-path-manager.md) - Path validation patterns

---

## 30.1 Test Structure

### Test File Organization

```
tests/
├── config/
│   ├── validator_test.go
│   ├── range_validation_test.go
│   ├── format_validation_test.go
│   ├── cross_key_validation_test.go
│   ├── cron_validation_test.go
│   └── path_validation_test.go
└── fixtures/
    └── config_fixtures.go
```

### Test Fixtures

```go
// config_fixtures.go
package fixtures

var ValidImportConfig = map[string]interface{}{
    "import.maxFileSizeBytes":       104857600,  // 100MB
    "import.maxZipSizeBytes":        524288000,  // 500MB
    "import.allowedExtensions":      []string{".md", ".json"},
    "import.maxFilesPerArchive":     500,
    "import.maxConcurrentJobs":      3,
    "import.jobTimeoutSeconds":      300,
    "import.tempDirectory":          "./data/import-temp",
    "import.cleanupTempAfterSeconds": 3600,
    "import.prdSectionSeparator":    "\n## ",
    "import.autoGenerateProjectJson": true,
}

var ValidExportConfig = map[string]interface{}{
    "export.retentionDays":          30,
    "export.maxConcurrentJobs":      3,
    "export.jobTimeoutSeconds":      600,
    "export.outputDirectory":        "./data/exports",
    "export.includeChecksum":        true,
    "export.compressionLevel":       6,
    "export.maxExportsPerProject":   10,
    "export.scheduledExportEnabled": false,
    "export.scheduledExportCron":    "0 2 * * 0",
}
```

---

## 30.2 Range Validation Tests

### Integer Range Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| RNG-001 | `import.maxFileSizeBytes` | 1048576 (1MB) | ✅ Pass (min bound) | - |
| RNG-002 | `import.maxFileSizeBytes` | 1073741824 (1GB) | ✅ Pass (max bound) | - |
| RNG-003 | `import.maxFileSizeBytes` | 1048575 | ❌ Fail (below min) | `ERR_CONFIG_INVALID_RANGE` |
| RNG-004 | `import.maxFileSizeBytes` | 1073741825 | ❌ Fail (above max) | `ERR_CONFIG_INVALID_RANGE` |
| RNG-005 | `import.maxFileSizeBytes` | 0 | ❌ Fail (zero) | `ERR_CONFIG_INVALID_RANGE` |
| RNG-006 | `import.maxFileSizeBytes` | -1 | ❌ Fail (negative) | `ERR_CONFIG_INVALID_RANGE` |
| RNG-007 | `import.maxZipSizeBytes` | 10485760 (10MB) | ✅ Pass (min bound) | - |
| RNG-008 | `import.maxZipSizeBytes` | 5368709120 (5GB) | ✅ Pass (max bound) | - |
| RNG-009 | `import.maxConcurrentJobs` | 1 | ✅ Pass (min bound) | - |
| RNG-010 | `import.maxConcurrentJobs` | 10 | ✅ Pass (max bound) | - |
| RNG-011 | `import.maxConcurrentJobs` | 0 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-012 | `import.maxConcurrentJobs` | 11 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-013 | `import.jobTimeoutSeconds` | 30 | ✅ Pass (min bound) | - |
| RNG-014 | `import.jobTimeoutSeconds` | 3600 | ✅ Pass (max bound) | - |
| RNG-015 | `import.jobTimeoutSeconds` | 29 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-016 | `import.maxFilesPerArchive` | 1 | ✅ Pass (min bound) | - |
| RNG-017 | `import.maxFilesPerArchive` | 10000 | ✅ Pass (max bound) | - |
| RNG-018 | `export.retentionDays` | 1 | ✅ Pass (min bound) | - |
| RNG-019 | `export.retentionDays` | 365 | ✅ Pass (max bound) | - |
| RNG-020 | `export.retentionDays` | 0 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-021 | `export.retentionDays` | 366 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-022 | `export.compressionLevel` | 0 | ✅ Pass (min bound - no compression) | - |
| RNG-023 | `export.compressionLevel` | 9 | ✅ Pass (max bound) | - |
| RNG-024 | `export.compressionLevel` | -1 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |
| RNG-025 | `export.compressionLevel` | 10 | ❌ Fail | `ERR_CONFIG_INVALID_RANGE` |

### Test Implementation

```go
func TestRangeValidation_ImportMaxFileSize(t *testing.T) {
    validator := NewConfigValidator()
    
    tests := []struct {
        name      string
        value     int64
        wantError bool
        errorCode string
    }{
        {"min bound valid", 1048576, false, ""},
        {"max bound valid", 1073741824, false, ""},
        {"below min", 1048575, true, "ERR_CONFIG_INVALID_RANGE"},
        {"above max", 1073741825, true, "ERR_CONFIG_INVALID_RANGE"},
        {"zero", 0, true, "ERR_CONFIG_INVALID_RANGE"},
        {"negative", -1, true, "ERR_CONFIG_INVALID_RANGE"},
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateKey("import.maxFileSizeBytes", tt.value)
            if tt.wantError {
                assert.Error(t, err)
                var configErr *ConfigError
                assert.ErrorAs(t, err, &configErr)
                assert.Equal(t, tt.errorCode, configErr.Code)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

---

## 30.3 Format Validation Tests

### Extension Format Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| FMT-001 | `import.allowedExtensions` | `[".md", ".json"]` | ✅ Pass | - |
| FMT-002 | `import.allowedExtensions` | `[".md"]` | ✅ Pass (single) | - |
| FMT-003 | `import.allowedExtensions` | `[]` | ❌ Fail (empty array) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-004 | `import.allowedExtensions` | `["md"]` | ❌ Fail (no dot prefix) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-005 | `import.allowedExtensions` | `[".md", "json"]` | ❌ Fail (mixed) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-006 | `import.allowedExtensions` | `["."]` | ❌ Fail (dot only) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-007 | `import.allowedExtensions` | `[""]` | ❌ Fail (empty string) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-008 | `import.allowedExtensions` | `[".MD", ".JSON"]` | ✅ Pass (uppercase) | - |

### Archive Type Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| FMT-009 | `import.allowedArchiveTypes` | `[".zip"]` | ✅ Pass | - |
| FMT-010 | `import.allowedArchiveTypes` | `[".zip", ".tar"]` | ✅ Pass | - |
| FMT-011 | `import.allowedArchiveTypes` | `[".rar"]` | ❌ Fail (not in allowed set) | `ERR_CONFIG_INVALID_VALUE` |
| FMT-012 | `import.allowedArchiveTypes` | `[]` | ❌ Fail (empty) | `ERR_CONFIG_INVALID_FORMAT` |

### PRD Section Separator Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| FMT-013 | `import.prdSectionSeparator` | `"\n## "` | ✅ Pass (default) | - |
| FMT-014 | `import.prdSectionSeparator` | `"---"` | ✅ Pass | - |
| FMT-015 | `import.prdSectionSeparator` | `""` | ❌ Fail (empty) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-016 | `import.prdSectionSeparator` | `"12345678901234567890X"` | ❌ Fail (>20 chars) | `ERR_CONFIG_INVALID_FORMAT` |
| FMT-017 | `import.prdSectionSeparator` | `"\n"` | ✅ Pass (single char) | - |

### Test Implementation

```go
func TestFormatValidation_AllowedExtensions(t *testing.T) {
    validator := NewConfigValidator()
    
    tests := []struct {
        name      string
        value     []string
        wantError bool
        errorCode string
    }{
        {"valid multiple", []string{".md", ".json"}, false, ""},
        {"valid single", []string{".md"}, false, ""},
        {"empty array", []string{}, true, "ERR_CONFIG_INVALID_FORMAT"},
        {"no dot prefix", []string{"md"}, true, "ERR_CONFIG_INVALID_FORMAT"},
        {"mixed valid invalid", []string{".md", "json"}, true, "ERR_CONFIG_INVALID_FORMAT"},
        {"dot only", []string{"."}, true, "ERR_CONFIG_INVALID_FORMAT"},
        {"empty string", []string{""}, true, "ERR_CONFIG_INVALID_FORMAT"},
        {"uppercase valid", []string{".MD", ".JSON"}, false, ""},
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateKey("import.allowedExtensions", tt.value)
            if tt.wantError {
                assert.Error(t, err)
                var configErr *ConfigError
                assert.ErrorAs(t, err, &configErr)
                assert.Equal(t, tt.errorCode, configErr.Code)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

---

## 30.4 Cross-Key Dependency Tests

### ZIP Size vs File Size Dependency

| Test ID | maxFileSizeBytes | maxZipSizeBytes | Expected Result | Error Code |
|---------|-----------------|-----------------|-----------------|------------|
| CRS-001 | 100MB | 500MB | ✅ Pass (zip > file) | - |
| CRS-002 | 100MB | 100MB | ✅ Pass (zip = file) | - |
| CRS-003 | 100MB | 50MB | ❌ Fail (zip < file) | `ERR_CONFIG_CROSS_KEY_VIOLATION` |
| CRS-004 | 1GB | 500MB | ❌ Fail (zip < file) | `ERR_CONFIG_CROSS_KEY_VIOLATION` |

### Temp Directory vs Output Directory Dependency

| Test ID | tempDirectory | outputDirectory | Expected Result | Error Code |
|---------|--------------|-----------------|-----------------|------------|
| CRS-005 | `./data/import-temp` | `./data/exports` | ✅ Pass (different) | - |
| CRS-006 | `./data/temp` | `./data/temp` | ❌ Fail (same path) | `ERR_CONFIG_CROSS_KEY_VIOLATION` |
| CRS-007 | `./data/temp/` | `./data/temp` | ❌ Fail (normalized same) | `ERR_CONFIG_CROSS_KEY_VIOLATION` |
| CRS-008 | `./data/temp` | `./data/temp/exports` | ⚠️ Warn (subdirectory) | - |

### Cron Enabled Dependency

| Test ID | scheduledExportEnabled | scheduledExportCron | Expected Result | Error Code |
|---------|----------------------|---------------------|-----------------|------------|
| CRS-009 | `false` | `"invalid cron"` | ✅ Pass (cron not validated) | - |
| CRS-010 | `true` | `"0 2 * * 0"` | ✅ Pass (valid cron) | - |
| CRS-011 | `true` | `"invalid cron"` | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRS-012 | `true` | `""` | ❌ Fail (empty when enabled) | `ERR_CONFIG_REQUIRED` |

### Timeout vs File Size Warning

| Test ID | maxFileSizeBytes | jobTimeoutSeconds | Expected Result |
|---------|-----------------|-------------------|-----------------|
| CRS-013 | 100MB | 300 | ✅ Pass (adequate) |
| CRS-014 | 500MB | 30 | ⚠️ Warn (low timeout for size) |
| CRS-015 | 1GB | 30 | ⚠️ Warn (likely insufficient) |

### Test Implementation

```go
func TestCrossKeyValidation_ZipSizeVsFileSize(t *testing.T) {
    validator := NewConfigValidator()
    
    tests := []struct {
        name          string
        maxFileSize   int64
        maxZipSize    int64
        wantError     bool
        errorCode     string
    }{
        {"zip greater than file", 104857600, 524288000, false, ""},
        {"zip equal to file", 104857600, 104857600, false, ""},
        {"zip less than file", 104857600, 52428800, true, "ERR_CONFIG_CROSS_KEY_VIOLATION"},
        {"large file small zip", 1073741824, 524288000, true, "ERR_CONFIG_CROSS_KEY_VIOLATION"},
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            config := map[string]interface{}{
                "import.maxFileSizeBytes": tt.maxFileSize,
                "import.maxZipSizeBytes":  tt.maxZipSize,
            }
            
            errors := validator.ValidateAll(config)
            
            if tt.wantError {
                assert.NotEmpty(t, errors)
                found := false
                for _, err := range errors {
                    var configErr *ConfigError
                    if errors.As(err, &configErr) && configErr.Code == tt.errorCode {
                        found = true
                        break
                    }
                }
                assert.True(t, found, "expected error code %s not found", tt.errorCode)
            } else {
                crossKeyErrors := filterCrossKeyErrors(errors)
                assert.Empty(t, crossKeyErrors)
            }
        })
    }
}

func TestCrossKeyValidation_CronEnabledDependency(t *testing.T) {
    validator := NewConfigValidator()
    
    tests := []struct {
        name        string
        enabled     bool
        cronExpr    string
        wantError   bool
    }{
        {"disabled with invalid cron", false, "invalid", false},
        {"enabled with valid cron", true, "0 2 * * 0", false},
        {"enabled with invalid cron", true, "invalid", true},
        {"enabled with empty cron", true, "", true},
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            config := map[string]interface{}{
                "export.scheduledExportEnabled": tt.enabled,
                "export.scheduledExportCron":    tt.cronExpr,
            }
            
            errors := validator.ValidateAll(config)
            
            if tt.wantError {
                assert.NotEmpty(t, errors)
            } else {
                assert.Empty(t, filterCrossKeyErrors(errors))
            }
        })
    }
}
```

---

## 30.5 Cron Expression Validation Tests

### Valid Cron Expressions

| Test ID | Expression | Description | Expected Result |
|---------|-----------|-------------|-----------------|
| CRN-001 | `"* * * * *"` | Every minute | ✅ Pass |
| CRN-002 | `"0 * * * *"` | Every hour | ✅ Pass |
| CRN-003 | `"0 0 * * *"` | Daily at midnight | ✅ Pass |
| CRN-004 | `"0 2 * * 0"` | Sunday 2am | ✅ Pass |
| CRN-005 | `"30 4 1 * *"` | 1st of month 4:30am | ✅ Pass |
| CRN-006 | `"0 0 1 1 *"` | Jan 1st midnight | ✅ Pass |
| CRN-007 | `"*/15 * * * *"` | Every 15 minutes | ✅ Pass |
| CRN-008 | `"0 9-17 * * 1-5"` | Hourly 9-5 weekdays | ✅ Pass |
| CRN-009 | `"0 0 * * 7"` | Sunday (alt notation) | ✅ Pass |

### Invalid Cron Expressions

| Test ID | Expression | Reason | Expected Result | Error Code |
|---------|-----------|--------|-----------------|------------|
| CRN-010 | `""` | Empty | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-011 | `"* * * *"` | Only 4 fields | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-012 | `"* * * * * *"` | 6 fields | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-013 | `"60 * * * *"` | Minute > 59 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-014 | `"* 24 * * *"` | Hour > 23 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-015 | `"* * 32 * *"` | Day > 31 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-016 | `"* * * 13 *"` | Month > 12 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-017 | `"* * * * 8"` | Weekday > 7 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-018 | `"* * 0 * *"` | Day = 0 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-019 | `"* * * 0 *"` | Month = 0 | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-020 | `"-1 * * * *"` | Negative minute | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-021 | `"abc * * * *"` | Non-numeric | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-022 | `"0,60 * * * *"` | List with invalid | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |
| CRN-023 | `"5-65 * * * *"` | Range end invalid | ❌ Fail | `ERR_CONFIG_INVALID_FORMAT` |

### Test Implementation

```go
func TestCronValidation(t *testing.T) {
    validator := NewConfigValidator()
    
    validCrons := []struct {
        name string
        expr string
    }{
        {"every minute", "* * * * *"},
        {"every hour", "0 * * * *"},
        {"daily midnight", "0 0 * * *"},
        {"sunday 2am", "0 2 * * 0"},
        {"first of month", "30 4 1 * *"},
        {"every 15 min", "*/15 * * * *"},
        {"weekday business hours", "0 9-17 * * 1-5"},
        {"sunday alt", "0 0 * * 7"},
    }
    
    for _, tt := range validCrons {
        t.Run("valid_"+tt.name, func(t *testing.T) {
            err := validator.ValidateKey("export.scheduledExportCron", tt.expr)
            assert.NoError(t, err)
        })
    }
    
    invalidCrons := []struct {
        name string
        expr string
    }{
        {"empty", ""},
        {"four fields", "* * * *"},
        {"six fields", "* * * * * *"},
        {"minute 60", "60 * * * *"},
        {"hour 24", "* 24 * * *"},
        {"day 32", "* * 32 * *"},
        {"month 13", "* * * 13 *"},
        {"weekday 8", "* * * * 8"},
        {"day zero", "* * 0 * *"},
        {"month zero", "* * * 0 *"},
        {"negative", "-1 * * * *"},
        {"non-numeric", "abc * * * *"},
    }
    
    for _, tt := range invalidCrons {
        t.Run("invalid_"+tt.name, func(t *testing.T) {
            err := validator.ValidateKey("export.scheduledExportCron", tt.expr)
            assert.Error(t, err)
            var configErr *ConfigError
            assert.ErrorAs(t, err, &configErr)
            assert.Equal(t, "ERR_CONFIG_INVALID_FORMAT", configErr.Code)
        })
    }
}
```

---

## 30.6 Path Validation Tests

### Valid Paths

| Test ID | Path | Description | Expected Result |
|---------|------|-------------|-----------------|
| PTH-001 | `"./data/import-temp"` | Relative with dot | ✅ Pass |
| PTH-002 | `"data/exports"` | Relative no dot | ✅ Pass |
| PTH-003 | `"/var/lib/app/temp"` | Absolute path | ✅ Pass |
| PTH-004 | `"./data"` | Short path | ✅ Pass |
| PTH-005 | `"temp"` | Single segment | ✅ Pass |

### Invalid Paths

| Test ID | Path | Reason | Expected Result | Error Code |
|---------|------|--------|-----------------|------------|
| PTH-006 | `""` | Empty | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-007 | `"../../../etc/passwd"` | Path traversal | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-008 | `"data/../../../secret"` | Hidden traversal | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-009 | `"data/./../../etc"` | Mixed traversal | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-010 | `"data\0temp"` | Null byte injection | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-011 | `string(256 chars)` | Exceeds max length | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-012 | `"data//temp"` | Double slash | ⚠️ Normalized | - |
| PTH-013 | `"data\\temp"` | Backslash | ⚠️ Normalized | - |

### Path Writability Tests

| Test ID | Path | Condition | Expected Result | Error Code |
|---------|------|-----------|-----------------|------------|
| PTH-014 | `"./data/exports"` | Directory exists, writable | ✅ Pass | - |
| PTH-015 | `"./data/new-dir"` | Parent exists, can create | ✅ Pass | - |
| PTH-016 | `"/nonexistent/path"` | Parent doesn't exist | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |
| PTH-017 | `"/root/protected"` | No write permission | ❌ Fail | `ERR_CONFIG_PATH_INVALID` |

### Test Implementation

```go
func TestPathValidation_TraversalPrevention(t *testing.T) {
    validator := NewConfigValidator()
    
    traversalPaths := []string{
        "../../../etc/passwd",
        "data/../../../secret",
        "data/./../../etc",
        "..\\..\\windows\\system32",
        "data/subdir/../../../root",
    }
    
    for _, path := range traversalPaths {
        t.Run("traversal_blocked_"+sanitizeTestName(path), func(t *testing.T) {
            err := validator.ValidateKey("import.tempDirectory", path)
            assert.Error(t, err)
            var configErr *ConfigError
            assert.ErrorAs(t, err, &configErr)
            assert.Equal(t, "ERR_CONFIG_PATH_INVALID", configErr.Code)
            assert.Contains(t, configErr.Message, "traversal")
        })
    }
}

func TestPathValidation_NullByteInjection(t *testing.T) {
    validator := NewConfigValidator()
    
    paths := []string{
        "data\x00temp",
        "data/sub\x00/dir",
        "\x00/etc/passwd",
    }
    
    for i, path := range paths {
        t.Run(fmt.Sprintf("null_byte_%d", i), func(t *testing.T) {
            err := validator.ValidateKey("export.outputDirectory", path)
            assert.Error(t, err)
            var configErr *ConfigError
            assert.ErrorAs(t, err, &configErr)
            assert.Equal(t, "ERR_CONFIG_PATH_INVALID", configErr.Code)
        })
    }
}

func TestPathValidation_MaxLength(t *testing.T) {
    validator := NewConfigValidator()
    
    // Generate path at exactly max length (255)
    maxPath := "./" + strings.Repeat("a", 253)
    err := validator.ValidateKey("import.tempDirectory", maxPath)
    assert.NoError(t, err)
    
    // Generate path exceeding max length
    tooLongPath := "./" + strings.Repeat("a", 254)
    err = validator.ValidateKey("import.tempDirectory", tooLongPath)
    assert.Error(t, err)
}
```

---

## 30.7 Type Validation Tests

### Boolean Type Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| TYP-001 | `import.autoGenerateProjectJson` | `true` | ✅ Pass | - |
| TYP-002 | `import.autoGenerateProjectJson` | `false` | ✅ Pass | - |
| TYP-003 | `import.autoGenerateProjectJson` | `"true"` | ❌ Fail (string) | `ERR_CONFIG_INVALID_TYPE` |
| TYP-004 | `import.autoGenerateProjectJson` | `1` | ❌ Fail (integer) | `ERR_CONFIG_INVALID_TYPE` |
| TYP-005 | `import.autoGenerateProjectJson` | `null` | ❌ Fail (null) | `ERR_CONFIG_INVALID_TYPE` |

### Integer Type Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| TYP-006 | `export.compressionLevel` | `6` | ✅ Pass | - |
| TYP-007 | `export.compressionLevel` | `6.5` | ❌ Fail (float) | `ERR_CONFIG_INVALID_TYPE` |
| TYP-008 | `export.compressionLevel` | `"6"` | ❌ Fail (string) | `ERR_CONFIG_INVALID_TYPE` |

### Array Type Tests

| Test ID | Key | Input | Expected Result | Error Code |
|---------|-----|-------|-----------------|------------|
| TYP-009 | `import.allowedExtensions` | `[".md"]` | ✅ Pass | - |
| TYP-010 | `import.allowedExtensions` | `".md"` | ❌ Fail (string) | `ERR_CONFIG_INVALID_TYPE` |
| TYP-011 | `import.allowedExtensions` | `null` | ❌ Fail | `ERR_CONFIG_INVALID_TYPE` |

---

## 30.8 Edge Cases & Regression Tests

### Unicode and Special Characters

| Test ID | Key | Input | Expected Result |
|---------|-----|-------|-----------------|
| EDG-001 | `import.prdSectionSeparator` | `"## 章节"` | ✅ Pass (Unicode) |
| EDG-002 | `import.tempDirectory` | `"./data/日本語"` | ✅ Pass (Unicode path) |
| EDG-003 | `import.allowedExtensions` | `[".日本語"]` | ✅ Pass (Unicode ext) |

### Boundary Precision

| Test ID | Key | Input | Expected Result |
|---------|-----|-------|-----------------|
| EDG-004 | `import.maxFileSizeBytes` | `1048576` (exactly 1MB) | ✅ Pass |
| EDG-005 | `import.maxFileSizeBytes` | `1073741824` (exactly 1GB) | ✅ Pass |
| EDG-006 | `export.retentionDays` | `1` (minimum) | ✅ Pass |
| EDG-007 | `export.retentionDays` | `365` (maximum) | ✅ Pass |

### Concurrent Validation

```go
func TestConcurrentValidation(t *testing.T) {
    validator := NewConfigValidator()
    
    var wg sync.WaitGroup
    errors := make(chan error, 100)
    
    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func(idx int) {
            defer wg.Done()
            config := map[string]interface{}{
                "import.maxFileSizeBytes": int64(1048576 + idx*1000),
                "import.maxZipSizeBytes":  int64(524288000),
            }
            errs := validator.ValidateAll(config)
            for _, err := range errs {
                errors <- err
            }
        }(i)
    }
    
    wg.Wait()
    close(errors)
    
    // Should have no race conditions or panics
    for err := range errors {
        t.Logf("Validation error: %v", err)
    }
}
```

---

## 30.9 Test Coverage Requirements

| Component | Minimum Coverage | Critical Paths |
|-----------|-----------------|----------------|
| `ValidateKey()` | 90% | All branches for each type |
| `ValidateAll()` | 85% | Cross-key dependency chains |
| `validateCronExpression()` | 95% | All field validations |
| `validatePath()` | 95% | Traversal, null byte, length |
| Error code mapping | 100% | All error codes exercised |

### Coverage Commands

```bash
# Run tests with coverage
go test -coverprofile=coverage.out ./config/...

# Generate HTML report
go tool cover -html=coverage.out -o coverage.html

# Check minimum coverage threshold
go tool cover -func=coverage.out | grep total | awk '{print $3}' | \
  awk -F% '{if ($1 < 90) exit 1}'
```

---

## 30.10 Acceptance Criteria

- [ ] All RNG-xxx range validation tests pass
- [ ] All FMT-xxx format validation tests pass
- [ ] All CRS-xxx cross-key dependency tests pass
- [ ] All CRN-xxx cron validation tests pass
- [ ] All PTH-xxx path validation tests pass
- [ ] All TYP-xxx type validation tests pass
- [ ] All EDG-xxx edge case tests pass
- [ ] Concurrent validation test passes without race conditions
- [ ] Code coverage meets minimum thresholds
- [ ] Error messages are descriptive and include the key name
- [ ] Error codes match the 7xxx Configuration range
