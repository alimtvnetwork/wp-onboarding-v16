# Deployment Guide: gsearch CLI

**Parent:** [Golang Search CLI](./00-overview.md)  
**Version:** 1.1.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Simple local deployment guide for the gsearch CLI tool. This guide focuses on running the Go binary locally without complex infrastructure.

**Design Philosophy:** Keep it simple — build, configure, run.

**Cross-References:**
- [Configuration](./02-configuration.md) — Config file management
- [Error Codes](./15-error-codes.md) — Error handling reference
- [Implementation Guide](./13-implementation-guide.md) — Build setup
- [Skipped Features](../../11-skipped-features/00-overview.md) — Deferred complex features

---

## Quick Start

```bash
# Build
go build -o gsearch ./main.go

# Configure
cp configs/config.development.json ./config.json

# Run
./gsearch search "your query"
```

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Build Process](#build-process)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Database Setup](#database-setup)
6. [Running the Application](#running-the-application)
7. [Logging](#logging)
8. [Health Checks](#health-checks)
9. [Backup & Maintenance](#backup--maintenance)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Linux, macOS, Windows | Linux or macOS |
| CPU | 2 cores | 4 cores |
| RAM | 512 MB | 2 GB |
| Disk | 500 MB | 5 GB (with caching) |
| Go | 1.21+ | 1.22+ |

### Install Go

```bash
# macOS (Homebrew)
brew install go

# Linux (Debian/Ubuntu)
sudo apt-get update
sudo apt-get install -y golang-go

# Or download from https://go.dev/dl/
wget https://go.dev/dl/go1.22.0.linux-amd64.tar.gz
sudo tar -C /usr/local -xzf go1.22.0.linux-amd64.tar.gz
export PATH=$PATH:/usr/local/go/bin

# Verify
go version
```

---

## Build Process

### Development Build

```bash
# Clone and enter directory
cd gsearch

# Download dependencies
go mod download
go mod verify

# Build
go build -o gsearch ./main.go

# Verify
./gsearch version
```

### Production Build (Optimized)

```bash
# Build with optimizations
CGO_ENABLED=1 go build \
    -ldflags="-s -w" \
    -trimpath \
    -o gsearch \
    ./main.go

# Binary size will be smaller (~10-15 MB vs ~20 MB)
```

### Build Flags

| Flag | Purpose |
|------|---------|
| `CGO_ENABLED=1` | Required for SQLite |
| `-ldflags="-s -w"` | Strip debug info, smaller binary |
| `-trimpath` | Remove local paths from binary |

---

## Installation

### Option 1: Local Directory (Recommended for Development)

```bash
# Keep binary in project directory
./gsearch search "query"
```

### Option 2: User Bin (Personal Use)

```bash
# Copy to user bin
mkdir -p ~/bin
cp gsearch ~/bin/
export PATH="$HOME/bin:$PATH"

# Add to ~/.bashrc or ~/.zshrc
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
```

### Option 3: System-Wide

```bash
# Copy to system bin (requires sudo)
sudo cp gsearch /usr/local/bin/
sudo chmod 755 /usr/local/bin/gsearch

# Verify
which gsearch
gsearch version
```

---

## Configuration

### Configuration File Location

```bash
# Default search order:
# 1. ./config.json (current directory)
# 2. ~/.config/gsearch/config.json
# 3. /etc/gsearch/config.json

# Or specify explicitly:
gsearch --config /path/to/config.json search "query"
```

### Create Configuration

```bash
# Copy development config
cp configs/config.development.json ./config.json

# Or copy production config for optimized settings
cp configs/config.production.json ./config.json
```

### Minimal Configuration

```json
{
  "environment": "development",
  
  "database": {
    "path": "./search.db.sqlite"
  },
  
  "search": {
    "maxConcurrency": 5,
    "requestDelay": "1s",
    "timeout": "30s"
  },
  
  "cache": {
    "enabled": true,
    "ttlDays": 5
  },
  
  "logging": {
    "level": "info",
    "format": "text"
  }
}
```

### Environment Variables

```bash
# Override config with environment variables
export GSEARCH_LOG_LEVEL=debug
export GSEARCH_CACHE_ENABLED=true

# API keys (keep out of config files)
export GOOGLE_CUSTOM_SEARCH_API_KEY=your-key
export BING_SEARCH_API_KEY=your-key

# Token encryption key (32-byte hex)
export GSEARCH_TOKEN_KEY=$(openssl rand -hex 32)
```

### Validate Configuration

```bash
gsearch config validate
# ✓ Configuration valid
# ✓ Database path writable
# ✓ Selectors file valid
```

---

## Database Setup

### Initialize Database

```bash
# Auto-creates database on first run
gsearch search "test query"

# Or explicitly initialize
gsearch db init
# ✓ Database created at ./search.db.sqlite
# ✓ Tables created (8 tables)
```

### Database Location

```bash
# Default: current directory
./search.db.sqlite

# Custom location (in config.json)
{
  "database": {
    "path": "/path/to/search.db.sqlite"
  }
}
```

### View Database

```bash
# Using SQLite CLI
sqlite3 search.db.sqlite

# List tables
.tables

# View schema
.schema SearchRequest

# Query recent searches
SELECT * FROM SearchRequest ORDER BY CreatedAt DESC LIMIT 10;
```

---

## Running the Application

### Basic Usage

```bash
# Single search
gsearch search "machine learning"

# Multiple keywords
gsearch search "AI,ML,deep learning"

# With options
gsearch search "golang tutorials" \
    --engine google,duckduckgo \
    --output json \
    --save-db
```

### Common Commands

```bash
# Search with caching
gsearch search "query" --cache

# Skip cache (fresh results)
gsearch search "query" --no-cache

# Nested search (recursive)
gsearch search "query" --nested --max-depth 2

# Export to RAG format
gsearch rag export --format json --output ./rag-memory.json

# Check status
gsearch status

# Cache management
gsearch cache stats
gsearch cache clear --older-than 7d
```

### Run as Background Process (Optional)

```bash
# Simple background run
nohup ./gsearch daemon > gsearch.log 2>&1 &

# Check if running
pgrep gsearch

# Stop
pkill gsearch
```

---

## Logging

### Console Logging (Default)

```bash
# Info level (default)
gsearch search "query"

# Debug level (verbose)
gsearch search "query" --log-level debug

# Quiet mode
gsearch search "query" --log-level error
```

### File Logging

```bash
# Configure in config.json
{
  "logging": {
    "level": "info",
    "format": "json",
    "outputPath": "./logs/gsearch.log"
  }
}

# Create log directory
mkdir -p ./logs

# View logs
tail -f ./logs/gsearch.log

# Filter errors
grep '"level":"error"' ./logs/gsearch.log | jq .
```

### Log Levels

| Level | Use Case |
|-------|----------|
| `debug` | Development, troubleshooting |
| `info` | Normal operation |
| `warn` | Recoverable issues |
| `error` | Failures |
| `silent` | No logging |

### Log Format

```bash
# Text format (human-readable)
2026-01-29 10:30:00 INFO  Search completed engine=google results=10

# JSON format (machine-readable)
{"timestamp":"2026-01-29T10:30:00Z","level":"info","message":"Search completed","engine":"google","results":10}
```

---

## Health Checks

### CLI Health Check

```bash
# Quick check
gsearch health
# Status: healthy

# Detailed check
gsearch health --verbose
# Database: healthy (2ms)
# Disk: healthy (5.2 GB available)
# Cache: healthy (85% hit rate)
# Engines: google=healthy, duckduckgo=healthy, bing=blocked
```

### Check Engine Status

```bash
gsearch status --engines
# google: healthy (last success: 2 min ago)
# duckduckgo: healthy (last success: 5 min ago)
# bing: blocked (cooldown: 12 min remaining)
```

### Check Cache Stats

```bash
gsearch cache stats
# Entries: 1,234
# Hit rate: 85%
# Size: 45 MB
# Oldest: 5 days ago
```

---

## Backup & Maintenance

### Backup Database

```bash
# Simple copy (stop writes first for consistency)
cp search.db.sqlite search.db.sqlite.backup

# With timestamp
cp search.db.sqlite "search.db.sqlite.$(date +%Y%m%d)"

# Using SQLite backup command (safe for active databases)
sqlite3 search.db.sqlite ".backup 'search.db.backup.sqlite'"
```

### Restore Database

```bash
# Stop the application first
cp search.db.backup.sqlite search.db.sqlite
```

### Database Maintenance

```bash
# Vacuum (reclaim space after deletes)
gsearch db vacuum

# Or directly with SQLite
sqlite3 search.db.sqlite "VACUUM;"

# Analyze (optimize query performance)
sqlite3 search.db.sqlite "ANALYZE;"
```

### Cache Cleanup

```bash
# Remove old cache entries
gsearch cache clear --older-than 7d

# Clear all cache
gsearch cache clear --all

# View what would be deleted
gsearch cache clear --older-than 7d --dry-run
```

---

## Troubleshooting

### Common Issues

#### Issue: "database is locked"

```bash
# Cause: Multiple processes accessing database

# Solution 1: Check for other processes
pgrep gsearch
lsof search.db.sqlite

# Solution 2: Enable WAL mode (better concurrency)
sqlite3 search.db.sqlite "PRAGMA journal_mode=WAL;"
```

#### Issue: "All engines blocked"

```bash
# Cause: Rate limiting from search engines

# Solution 1: Wait for cooldown
gsearch status --engines
# Shows cooldown remaining

# Solution 2: Reset engine status
gsearch engine reset --all

# Solution 3: Increase delays in config
{
  "search": {
    "requestDelay": "3s"
  }
}
```

#### Issue: "selector mismatch" / No results

```bash
# Cause: Search engine HTML changed

# Solution 1: Reload selectors
gsearch selectors reload

# Solution 2: Update selectors
gsearch selectors update --engine google

# Solution 3: Use fallback
# (automatic if configured in selectors.json)
```

#### Issue: High memory usage

```bash
# Cause: Large result sets or cache

# Solution 1: Reduce concurrency
gsearch search "query" --concurrency 3

# Solution 2: Clear cache
gsearch cache clear --all

# Solution 3: Limit results
gsearch search "query" --max-results 20
```

### Debug Mode

```bash
# Run with full debug output
gsearch search "test" \
    --log-level debug \
    --no-cache \
    --verbose

# Check configuration
gsearch config show
```

### Get Help

```bash
# General help
gsearch --help

# Command-specific help
gsearch search --help
gsearch cache --help
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| DP-01 | Binary builds without errors | Critical | `go build` succeeds |
| DP-02 | Binary runs on target OS | Critical | `gsearch version` works |
| DP-03 | Config file loads | Critical | `gsearch config validate` passes |
| DP-04 | Database initializes | Critical | `gsearch db init` creates tables |
| DP-05 | Search returns results | Critical | `gsearch search "test"` returns data |
| DP-06 | Logs write to file | High | Log file created |
| DP-07 | Health check passes | High | `gsearch health` shows healthy |
| DP-08 | Cache works | High | Second search is faster |
| DP-09 | Backup/restore works | Medium | Database restores correctly |

---

## Skipped Features

The following features are deferred for simplicity. See [Skipped Features](../../11-skipped-features/00-overview.md) for details:

| Feature | Reason |
|---------|--------|
| Grafana/Prometheus | Requires external infrastructure |
| Kubernetes | Overkill for local use |
| Centralized logging | ELK stack not needed locally |
| Docker | Direct binary is simpler |

---

## Related Specifications

| Document | Purpose |
|----------|---------|
| [00-overview.md](./00-overview.md) | Main specification |
| [02-configuration.md](./02-configuration.md) | Config details |
| [13-implementation-guide.md](./13-implementation-guide.md) | Build setup |
| [15-error-codes.md](./15-error-codes.md) | Error reference |

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.1.0 | 2026-01-29 | Simplified for local deployment; moved complex features to skipped |
| 1.0.0 | 2026-01-29 | Initial deployment guide |
