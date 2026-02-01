# AI Bridge: Startup Modes

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

AI Bridge supports two execution modes to accommodate different workflows:

| Mode | Use Case | Lifecycle |
|------|----------|-----------|
| **Local Binary** | Single execution, CI/CD pipelines, scripts | Start → Execute → Exit |
| **Background Daemon** | Long-running service, REST API, WebSocket | Start → Run continuously → Stop on signal |

---

## 1. Local Binary Mode

### Description

Single-execution mode where AI Bridge processes one request (or batch) and exits. Ideal for scripting, CI/CD pipelines, and one-off operations.

### Commands

```bash
# Process a single file
aibridge run prompt.md

# Process with output redirection
aibridge run prompt.json --output result.md

# Process with explicit backend
aibridge run prompt.yaml --backend ollama

# Batch processing from CSV
aibridge run data.csv --config data.config.yaml

# Streaming output to stdout
aibridge run prompt.md --stream

# JSON output mode
aibridge run prompt.md --json

# Dry run (validate only, no LLM call)
aibridge run prompt.md --dry-run
```

### CLI Flags

| Flag | Short | Default | Description |
|------|-------|---------|-------------|
| `--output` | `-o` | stdout | Output file path |
| `--backend` | `-b` | (config) | Force specific backend |
| `--config` | `-c` | - | Config file for CSV input |
| `--stream` | `-s` | false | Stream output to stdout |
| `--json` | `-j` | false | Output as JSON |
| `--dry-run` | `-d` | false | Validate without execution |
| `--timeout` | `-t` | 5m | Request timeout |
| `--verbose` | `-v` | false | Verbose logging |

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General error |
| 2 | Input parsing error |
| 3 | Backend connection error |
| 4 | Request timeout |
| 5 | Invalid configuration |

### Implementation

```go
// cmd/aibridge/run.go
var runCmd = &cobra.Command{
    Use:   "run <file>",
    Short: "Process a single prompt file",
    Args:  cobra.ExactArgs(1),
    RunE: func(cmd *cobra.Command, args []string) error {
        ctx, cancel := context.WithTimeout(context.Background(), timeout)
        defer cancel()
        
        // 1. Load configuration
        cfg, err := config.Load(configPath)
        if err != nil {
            return cli.Exit("config error", 5)
        }
        
        // 2. Read input file
        content, err := os.ReadFile(args[0])
        if err != nil {
            return cli.Exit("file read error", 1)
        }
        
        // 3. Create bridge
        bridge, err := bridge.New(cfg)
        if err != nil {
            return cli.Exit("bridge init error", 1)
        }
        defer bridge.Close()
        
        // 4. Parse and normalize request
        req, err := bridge.Parse(args[0], content)
        if err != nil {
            return cli.Exit("parse error", 2)
        }
        
        // 5. Dry run check
        if dryRun {
            fmt.Println("Validation successful")
            return nil
        }
        
        // 6. Apply CLI overrides
        if backendFlag != "" {
            req.BackendOverride = backendFlag
        }
        req.Stream = streamFlag
        
        // 7. Execute
        if streamFlag {
            return executeStreaming(ctx, bridge, req)
        }
        
        resp, err := bridge.Execute(ctx, req)
        if err != nil {
            if errors.Is(err, context.DeadlineExceeded) {
                return cli.Exit("timeout", 4)
            }
            return cli.Exit("execution error", 3)
        }
        
        // 8. Output
        return outputResult(resp, outputPath, jsonFlag)
    },
}

func executeStreaming(ctx context.Context, bridge *bridge.Bridge, req *bridge.NormalizedRequest) error {
    chunks, err := bridge.ExecuteStream(ctx, req)
    if err != nil {
        return err
    }
    
    for chunk := range chunks {
        if chunk.Error != nil {
            return chunk.Error
        }
        fmt.Print(chunk.Delta)
    }
    fmt.Println()
    return nil
}
```

---

## 2. Background Daemon Mode

### Description

Long-running service mode providing REST API and WebSocket interfaces. Ideal for integration with other applications, real-time streaming, and high-throughput scenarios.

### Commands

```bash
# Start daemon (foreground)
aibridge daemon start

# Start daemon (background)
aibridge daemon start --detach

# Check daemon status
aibridge daemon status

# Stop daemon
aibridge daemon stop

# Restart daemon
aibridge daemon restart

# View logs
aibridge daemon logs --tail 100

# Health check
aibridge daemon health
```

### Configuration

```yaml
# config.yaml - daemon section
daemon:
  port: 8089
  host: "127.0.0.1"           # Bind address
  pidFile: "/var/run/aibridge.pid"
  logFile: "/var/log/aibridge.log"
  
  # TLS (optional)
  tls:
    enabled: false
    certFile: ""
    keyFile: ""
  
  # Rate limiting
  rateLimit:
    enabled: true
    requestsPerMinute: 60
    burstSize: 10
  
  # WebSocket
  websocket:
    enabled: true
    pingInterval: 30s
    writeTimeout: 10s
  
  # Graceful shutdown
  shutdownTimeout: 30s
```

### REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/generate` | Synchronous generation |
| POST | `/api/v1/generate/stream` | SSE streaming generation |
| POST | `/api/v1/batch` | Batch processing |
| GET | `/api/v1/batch/{id}` | Get batch status |
| GET | `/api/v1/models` | List available models |
| POST | `/api/v1/models/{id}/load` | Load a model |
| DELETE | `/api/v1/models/{id}` | Unload a model |
| GET | `/api/v1/backends` | List backend status |
| GET | `/health` | Health check |
| GET | `/metrics` | Prometheus metrics |

### WebSocket API

```typescript
// Connect
const ws = new WebSocket('ws://localhost:8089/api/v1/ws');

// Send request
ws.send(JSON.stringify({
  type: 'generate',
  id: 'req-123',
  payload: {
    systemPrompt: 'You are a helpful assistant.',
    userPrompt: 'Explain async/await in JavaScript.',
    model: 'writing',
    stream: true
  }
}));

// Receive streaming chunks
ws.onmessage = (event) => {
  const msg = JSON.parse(event.data);
  switch (msg.type) {
    case 'chunk':
      console.log(msg.payload.delta);
      break;
    case 'done':
      console.log('Complete:', msg.payload);
      break;
    case 'error':
      console.error('Error:', msg.payload.message);
      break;
  }
};
```

### Implementation

```go
// cmd/aibridge/daemon.go
var daemonCmd = &cobra.Command{
    Use:   "daemon",
    Short: "Manage the AI Bridge daemon",
}

var daemonStartCmd = &cobra.Command{
    Use:   "start",
    Short: "Start the daemon",
    RunE: func(cmd *cobra.Command, args []string) error {
        cfg, err := config.Load(configPath)
        if err != nil {
            return err
        }
        
        // Check if already running
        if daemon.IsRunning(cfg.Daemon.PidFile) {
            return fmt.Errorf("daemon already running")
        }
        
        // Detach if requested
        if detachFlag {
            return daemon.Detach(os.Args)
        }
        
        // Create and start server
        srv, err := daemon.NewServer(cfg)
        if err != nil {
            return err
        }
        
        // Write PID file
        if err := daemon.WritePID(cfg.Daemon.PidFile); err != nil {
            return err
        }
        defer os.Remove(cfg.Daemon.PidFile)
        
        // Handle signals
        sigChan := make(chan os.Signal, 1)
        signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)
        
        go func() {
            <-sigChan
            ctx, cancel := context.WithTimeout(context.Background(), cfg.Daemon.ShutdownTimeout)
            defer cancel()
            srv.Shutdown(ctx)
        }()
        
        // Start server
        log.Printf("AI Bridge daemon starting on %s:%d", cfg.Daemon.Host, cfg.Daemon.Port)
        return srv.ListenAndServe()
    },
}

// internal/daemon/server.go
type Server struct {
    bridge     *bridge.Bridge
    router     *chi.Mux
    wsUpgrader websocket.Upgrader
    config     *config.DaemonConfig
}

func NewServer(cfg *config.Config) (*Server, error) {
    bridge, err := bridge.New(cfg)
    if err != nil {
        return nil, err
    }
    
    s := &Server{
        bridge: bridge,
        config: &cfg.Daemon,
        wsUpgrader: websocket.Upgrader{
            ReadBufferSize:  1024,
            WriteBufferSize: 1024,
            CheckOrigin:     func(r *http.Request) bool { return true },
        },
    }
    
    s.setupRoutes()
    return s, nil
}

func (s *Server) setupRoutes() {
    r := chi.NewRouter()
    
    // Middleware
    r.Use(middleware.Logger)
    r.Use(middleware.Recoverer)
    r.Use(middleware.Timeout(60 * time.Second))
    
    if s.config.RateLimit.Enabled {
        r.Use(httprate.LimitByIP(
            s.config.RateLimit.RequestsPerMinute,
            time.Minute,
        ))
    }
    
    // Health & metrics
    r.Get("/health", s.handleHealth)
    r.Get("/metrics", promhttp.Handler().ServeHTTP)
    
    // API routes
    r.Route("/api/v1", func(r chi.Router) {
        r.Post("/generate", s.handleGenerate)
        r.Post("/generate/stream", s.handleGenerateStream)
        r.Post("/batch", s.handleBatch)
        r.Get("/batch/{id}", s.handleBatchStatus)
        r.Get("/models", s.handleListModels)
        r.Post("/models/{id}/load", s.handleLoadModel)
        r.Delete("/models/{id}", s.handleUnloadModel)
        r.Get("/backends", s.handleBackends)
        
        // WebSocket
        if s.config.WebSocket.Enabled {
            r.Get("/ws", s.handleWebSocket)
        }
    })
    
    s.router = r
}
```

---

## Mode Comparison

| Feature | Binary Mode | Daemon Mode |
|---------|-------------|-------------|
| Startup time | Per invocation | Once |
| Memory usage | Low (exits after use) | Higher (persistent) |
| Concurrent requests | Single | Multiple |
| Streaming | stdout only | SSE + WebSocket |
| Model loading | Per request | Persistent |
| Best for | Scripts, CI/CD | Applications, APIs |

---

## Hybrid Usage

You can run both modes simultaneously:

```bash
# Start daemon for API access
aibridge daemon start --detach

# Use binary mode for quick one-offs (connects to daemon if available)
aibridge run prompt.md --use-daemon
```

The `--use-daemon` flag makes the binary send requests to the running daemon instead of initializing its own LLM connection.

---

## Systemd Service

```ini
# /etc/systemd/system/aibridge.service
[Unit]
Description=AI Bridge Daemon
After=network.target

[Service]
Type=simple
User=aibridge
Group=aibridge
ExecStart=/usr/local/bin/aibridge daemon start
ExecStop=/usr/local/bin/aibridge daemon stop
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

---

## See Also

- [Architecture](./01-architecture.md)
- [API Interface](./04-api-interface.md)
- [Error Codes](./05-error-codes.md)
