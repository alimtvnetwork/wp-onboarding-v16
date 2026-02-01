# Deployment Guide

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Overview

Comprehensive deployment guide for the AI-Powered Code Generation System, covering infrastructure setup, environment configuration, and deployment procedures for local and production environments.

**Cross-References:**
- [System Architecture](./01-system-architecture.md)
- [Configuration Manifest](./05-configuration.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
- [Error Handling](./07-error-handling.md)

---

## Deployment Philosophy

The Code Generation System follows a **Local-First** design philosophy:

| Principle | Description |
|-----------|-------------|
| Self-Contained | All core functionality runs locally without cloud dependencies |
| Minimal Infrastructure | No mandatory Kubernetes, Docker, or external services |
| Portable | Single binary + SQLite database = easy migration |
| Optional Scaling | Advanced observability/clustering available but not required |

---

## System Requirements

### Minimum Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 4 cores | 8+ cores |
| RAM | 8 GB | 16+ GB |
| Storage | 20 GB SSD | 100+ GB SSD |
| OS | Linux, macOS, Windows | Linux (Ubuntu 22.04+) |

### Software Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| Go | 1.21+ | Backend runtime |
| Node.js | 18+ | Frontend build |
| Git | 2.30+ | Version control integration |
| SQLite | 3.35+ | Database (embedded) |

### LLM Infrastructure

| Component | Requirement | Notes |
|-----------|-------------|-------|
| llama.cpp | Latest | Local LLM inference |
| Ollama | 0.1.0+ | Alternative backend |
| llama-swap | Latest | Proxy for model switching |
| VRAM | 8+ GB | For 7B models |

---

## Directory Structure

### Development Layout

```
project-root/
├── BE/                          # Go backend
│   ├── cmd/
│   │   └── server/
│   │       └── main.go
│   ├── internal/
│   ├── go.mod
│   └── go.sum
├── FE/                          # React frontend
│   ├── src/
│   ├── package.json
│   └── vite.config.ts
├── spec/                        # Specifications
├── data/                        # Runtime data
│   ├── db/
│   │   ├── settings.db
│   │   ├── projects.db
│   │   └── users.db
│   ├── cache/
│   └── logs/
├── models/                      # LLM models
│   ├── thinking/
│   ├── writing/
│   ├── coding/
│   └── voice/
└── config/
    ├── config.json
    └── llama-swap.yaml
```

### Production Layout (Linux)

```
/opt/codegen/
├── bin/
│   ├── codegen-server           # Main server binary
│   ├── brun                     # Build runner CLI
│   └── gsearch                  # Search CLI
├── web/                         # Frontend static files
│   └── dist/
└── models/                      # LLM models

/var/lib/codegen/
├── db/                          # SQLite databases
├── cache/                       # Cache files
└── projects/                    # Project workspaces

/etc/codegen/
├── config.json                  # Main configuration
└── llama-swap.yaml              # LLM proxy config

/var/log/codegen/
├── server.log
├── brun.log
└── gsearch.log
```

---

## Environment Configuration

### Configuration File

```json
// config.json
{
  "server": {
    "host": "0.0.0.0",
    "port": 8080,
    "mode": "production"
  },
  "database": {
    "path": "/var/lib/codegen/db",
    "pool.maxOpen": 25,
    "pool.maxIdle": 5,
    "pool.maxLifetime": "30m"
  },
  "llm": {
    "backend": "llama-swap",
    "server.host": "127.0.0.1",
    "server.port": 8081,
    "models.thinking": "models/thinking/deepseek-r1-8b.gguf",
    "models.writing": "models/writing/llama-3.1-8b.gguf",
    "models.coding": "models/coding/qwen-2.5-coder-7b.gguf"
  },
  "git": {
    "enabled": true,
    "autoCommit": true,
    "autoPush": false,
    "remote.type": "github"
  },
  "logging": {
    "level": "info",
    "format": "json",
    "output": "/var/log/codegen/server.log"
  },
  "security": {
    "jwt.secret": "${CODEGEN_JWT_SECRET}",
    "jwt.expiry": "24h",
    "cors.origins": ["http://localhost:5173"]
  }
}
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `CODEGEN_CONFIG_PATH` | Path to config.json | `./config/config.json` |
| `CODEGEN_JWT_SECRET` | JWT signing secret | (required) |
| `CODEGEN_DB_PATH` | Database directory | `./data/db` |
| `CODEGEN_LOG_LEVEL` | Logging level | `info` |
| `CODEGEN_LLM_HOST` | LLM server host | `127.0.0.1` |
| `CODEGEN_LLM_PORT` | LLM server port | `8081` |
| `GITHUB_TOKEN` | GitHub OAuth token | (optional) |
| `GITLAB_TOKEN` | GitLab OAuth token | (optional) |

### Configuration Hierarchy

```
1. Default values (compiled)
     ↓
2. config.json file
     ↓
3. Environment variables (CODEGEN_* prefix)
     ↓
4. Command-line flags (highest priority)
```

---

## Build Process

### Backend Build

```bash
# Development build
cd BE
go build -o bin/codegen-server ./cmd/server

# Production build with optimizations
CGO_ENABLED=1 go build \
  -ldflags="-s -w -X main.version=1.0.0" \
  -o bin/codegen-server \
  ./cmd/server

# Cross-compile for Linux (from macOS/Windows)
GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build \
  -ldflags="-s -w" \
  -o bin/codegen-server-linux \
  ./cmd/server
```

### Frontend Build

```bash
cd FE

# Install dependencies
npm ci

# Development
npm run dev

# Production build
npm run build

# Output: dist/ directory
```

### CLI Tools Build

```bash
# Build Runner CLI
cd tools/brun
go build -ldflags="-s -w" -o ../../bin/brun .

# Search CLI
cd tools/gsearch
go build -ldflags="-s -w" -o ../../bin/gsearch .
```

---

## Deployment Procedures

### Local Development

```bash
# 1. Start LLM server
./bin/llama-swap --config config/llama-swap.yaml &

# 2. Start backend
./bin/codegen-server --config config/config.json &

# 3. Start frontend dev server
cd FE && npm run dev
```

### Production Deployment

#### Step 1: Prepare Infrastructure

```bash
# Create directories
sudo mkdir -p /opt/codegen/{bin,web,models}
sudo mkdir -p /var/lib/codegen/{db,cache,projects}
sudo mkdir -p /etc/codegen
sudo mkdir -p /var/log/codegen

# Set permissions
sudo chown -R codegen:codegen /opt/codegen
sudo chown -R codegen:codegen /var/lib/codegen
sudo chown -R codegen:codegen /var/log/codegen
```

#### Step 2: Deploy Binaries

```bash
# Copy binaries
sudo cp bin/codegen-server /opt/codegen/bin/
sudo cp bin/brun /opt/codegen/bin/
sudo cp bin/gsearch /opt/codegen/bin/

# Copy frontend
sudo cp -r FE/dist/* /opt/codegen/web/

# Copy configuration
sudo cp config/config.production.json /etc/codegen/config.json
sudo cp config/llama-swap.yaml /etc/codegen/
```

#### Step 3: Deploy Models

```bash
# Copy LLM models (example)
sudo cp -r models/* /opt/codegen/models/
```

#### Step 4: Configure Systemd

```ini
# /etc/systemd/system/codegen.service
[Unit]
Description=Code Generation System
After=network.target

[Service]
Type=simple
User=codegen
Group=codegen
WorkingDirectory=/opt/codegen
ExecStart=/opt/codegen/bin/codegen-server --config /etc/codegen/config.json
Restart=always
RestartSec=5
Environment=CODEGEN_JWT_SECRET=your-secret-here

# Resource limits
LimitNOFILE=65535
LimitNPROC=4096

# Logging
StandardOutput=append:/var/log/codegen/server.log
StandardError=append:/var/log/codegen/server.log

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/llama-swap.service
[Unit]
Description=LLM Proxy Server
Before=codegen.service

[Service]
Type=simple
User=codegen
Group=codegen
WorkingDirectory=/opt/codegen
ExecStart=/opt/codegen/bin/llama-swap --config /etc/codegen/llama-swap.yaml
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

#### Step 5: Start Services

```bash
sudo systemctl daemon-reload
sudo systemctl enable llama-swap codegen
sudo systemctl start llama-swap codegen

# Verify
sudo systemctl status codegen
curl http://localhost:8080/health
```

---

## Database Initialization

### First Run Seeding

On first startup, the system automatically:

1. Creates SQLite databases in configured path
2. Runs all pending migrations
3. Seeds configuration with 80+ default keys
4. Creates default admin user (if configured)

```bash
# Manual database initialization
./bin/codegen-server migrate up

# Seed configuration
./bin/codegen-server seed config

# Verify database
sqlite3 /var/lib/codegen/db/settings.db ".tables"
```

### Migration Management

```bash
# Check migration status
./bin/codegen-server migrate status

# Apply pending migrations
./bin/codegen-server migrate up

# Rollback last migration
./bin/codegen-server migrate down 1

# Create new migration
./bin/codegen-server migrate create add_user_preferences
```

---

## Health Monitoring

### Health Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /health` | Basic health check |
| `GET /health/ready` | Readiness check (all dependencies) |
| `GET /health/live` | Liveness check |
| `GET /metrics` | Prometheus metrics |

### Health Check Response

```json
{
  "status": "healthy",
  "version": "1.0.0",
  "uptime": "2h30m15s",
  "checks": {
    "database": { "status": "up", "latency": "2ms" },
    "llm": { "status": "up", "model": "qwen-2.5-coder-7b" },
    "git": { "status": "up" }
  }
}
```

### Monitoring with Prometheus (Optional)

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'codegen'
    static_configs:
      - targets: ['localhost:8080']
    metrics_path: '/metrics'
```

---

## Reverse Proxy Configuration

### Nginx

```nginx
# /etc/nginx/sites-available/codegen
upstream codegen_backend {
    server 127.0.0.1:8080;
    keepalive 32;
}

server {
    listen 80;
    server_name codegen.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name codegen.example.com;

    ssl_certificate /etc/ssl/certs/codegen.crt;
    ssl_certificate_key /etc/ssl/private/codegen.key;

    # Frontend static files
    location / {
        root /opt/codegen/web;
        try_files $uri $uri/ /index.html;
        expires 1d;
        add_header Cache-Control "public, immutable";
    }

    # API proxy
    location /api/ {
        proxy_pass http://codegen_backend;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket proxy
    location /ws {
        proxy_pass http://codegen_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
}
```

---

## Backup & Recovery

### Backup Strategy

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backup/codegen/$(date +%Y%m%d)"
mkdir -p "$BACKUP_DIR"

# Backup databases
sqlite3 /var/lib/codegen/db/settings.db ".backup '$BACKUP_DIR/settings.db'"
sqlite3 /var/lib/codegen/db/projects.db ".backup '$BACKUP_DIR/projects.db'"
sqlite3 /var/lib/codegen/db/users.db ".backup '$BACKUP_DIR/users.db'"

# Backup configuration
cp /etc/codegen/config.json "$BACKUP_DIR/"

# Backup project workspaces
tar -czf "$BACKUP_DIR/projects.tar.gz" /var/lib/codegen/projects/

# Retention: keep 30 days
find /backup/codegen -type d -mtime +30 -exec rm -rf {} +
```

### Recovery Procedure

```bash
# Stop services
sudo systemctl stop codegen

# Restore databases
cp /backup/codegen/20260129/settings.db /var/lib/codegen/db/
cp /backup/codegen/20260129/projects.db /var/lib/codegen/db/

# Restore projects
tar -xzf /backup/codegen/20260129/projects.tar.gz -C /

# Start services
sudo systemctl start codegen
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] System requirements verified
- [ ] Dependencies installed (Go, Node.js, Git)
- [ ] LLM models downloaded and verified
- [ ] Configuration file prepared
- [ ] Environment variables set
- [ ] JWT secret generated securely
- [ ] Database backup completed (if upgrading)

### Deployment

- [ ] Backend binary built and deployed
- [ ] Frontend built and deployed
- [ ] CLI tools deployed (brun, gsearch)
- [ ] Configuration files copied
- [ ] Directory permissions set
- [ ] Systemd services configured
- [ ] Services started

### Post-Deployment

- [ ] Health endpoint responding
- [ ] Database migrations applied
- [ ] Configuration seeded
- [ ] LLM connectivity verified
- [ ] WebSocket connections working
- [ ] Git integration tested (if enabled)
- [ ] Logs rotating correctly

### Security Checklist

- [ ] JWT secret is unique and secure
- [ ] Database files have restricted permissions
- [ ] HTTPS configured (production)
- [ ] CORS origins restricted
- [ ] Rate limiting enabled
- [ ] Admin credentials changed from defaults

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Service won't start | Missing config | Check `CODEGEN_CONFIG_PATH` |
| Database locked | Concurrent access | Check for zombie processes |
| LLM timeout | Model not loaded | Verify llama-swap is running |
| WebSocket fails | Nginx config | Check upgrade headers |
| Permission denied | File ownership | Run `chown` on data directories |

### Log Analysis

```bash
# View recent logs
journalctl -u codegen -f

# Search for errors
journalctl -u codegen --since "1 hour ago" | grep -i error

# Check structured logs
jq '.level == "error"' /var/log/codegen/server.log
```

### Debug Mode

```bash
# Start with debug logging
CODEGEN_LOG_LEVEL=debug ./bin/codegen-server --config config.json
```

---

## Future Considerations (TBD)

The following advanced deployment options are planned but not yet specified:

- [ ] Docker containerization
- [ ] Kubernetes orchestration
- [ ] Multi-node clustering
- [ ] Centralized logging (ELK stack)
- [ ] Advanced Grafana dashboards
- [ ] Blue-green deployment strategy
- [ ] Auto-scaling configuration

---

## Related Specs

- [System Architecture](./01-system-architecture.md)
- [Configuration Manifest](./05-configuration.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
- [Search CLI](../22-golang-search-cli/00-overview.md)

---

## Implementation Checklist

- [ ] Directory structure scripts
- [ ] Systemd service files
- [ ] Nginx configuration template
- [ ] Backup/restore scripts
- [ ] Health check implementation
- [ ] Migration CLI commands
- [ ] Deployment automation scripts
- [ ] Monitoring dashboards (optional)
