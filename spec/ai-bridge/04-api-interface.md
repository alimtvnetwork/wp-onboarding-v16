# AI Bridge: API Interface

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

When running in daemon mode, AI Bridge exposes a REST API and WebSocket interface for programmatic access.

---

## Base URL

```
http://localhost:8089/api/v1
```

---

## Authentication

Optional API key authentication:

```yaml
# config.yaml
daemon:
  auth:
    enabled: true
    apiKeys:
      - key: "sk_live_abc123..."
        name: "Production"
        rateLimit: 100
      - key: "sk_test_xyz789..."
        name: "Development"
        rateLimit: 1000
```

```bash
curl -H "Authorization: Bearer sk_live_abc123..." \
     http://localhost:8089/api/v1/generate
```

---

## Endpoints

### POST /generate

Synchronous text generation.

**Request:**
```json
{
  "systemPrompt": "You are a helpful assistant.",
  "userPrompt": "Explain the concept of recursion.",
  "model": "writing",
  "temperature": 0.7,
  "maxTokens": 2048,
  "outputFormat": "markdown"
}
```

**Response:**
```json
{
  "id": "gen_abc123",
  "content": "# Recursion\n\nRecursion is a programming technique...",
  "finishReason": "stop",
  "tokensUsed": {
    "prompt": 45,
    "completion": 312,
    "total": 357
  },
  "durationMs": 1523,
  "modelUsed": "llama-3.1-8b"
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8089/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "systemPrompt": "You are a code reviewer.",
    "userPrompt": "Review this function for bugs.",
    "model": "coding"
  }'
```

---

### POST /generate/stream

Server-Sent Events (SSE) streaming generation.

**Request:** Same as `/generate`

**Response:** SSE stream

```
event: chunk
data: {"delta": "# "}

event: chunk
data: {"delta": "Recursion"}

event: chunk
data: {"delta": "\n\n"}

event: done
data: {"id": "gen_abc123", "finishReason": "stop", "tokensUsed": {"total": 357}}
```

**JavaScript Example:**
```javascript
const eventSource = new EventSource('/api/v1/generate/stream', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    userPrompt: 'Write a poem about coding.',
    model: 'writing',
    stream: true
  })
});

eventSource.addEventListener('chunk', (e) => {
  const data = JSON.parse(e.data);
  process.stdout.write(data.delta);
});

eventSource.addEventListener('done', (e) => {
  console.log('\n--- Done ---');
  eventSource.close();
});
```

---

### POST /batch

Submit a batch of requests for parallel processing.

**Request:**
```json
{
  "systemPrompt": "Generate a product tagline.",
  "userPromptTemplate": "Create a tagline for {{productName}} targeting {{audience}}.",
  "model": "writing",
  "items": [
    { "id": "p1", "variables": { "productName": "Widget A", "audience": "developers" } },
    { "id": "p2", "variables": { "productName": "Widget B", "audience": "designers" } },
    { "id": "p3", "variables": { "productName": "Widget C", "audience": "managers" } }
  ],
  "parallelism": 3
}
```

**Response:**
```json
{
  "batchId": "batch_xyz789",
  "status": "processing",
  "submitted": 3,
  "completed": 0,
  "failed": 0,
  "estimatedDurationMs": 5000
}
```

---

### GET /batch/{id}

Get batch processing status and results.

**Response:**
```json
{
  "batchId": "batch_xyz789",
  "status": "completed",
  "submitted": 3,
  "completed": 3,
  "failed": 0,
  "results": [
    {
      "itemId": "p1",
      "status": "success",
      "content": "Widget A: Code smarter, not harder.",
      "durationMs": 892
    },
    {
      "itemId": "p2",
      "status": "success",
      "content": "Widget B: Design without limits.",
      "durationMs": 756
    },
    {
      "itemId": "p3",
      "status": "success",
      "content": "Widget C: Lead with clarity.",
      "durationMs": 823
    }
  ],
  "totalDurationMs": 2471
}
```

---

### GET /models

List available models across all backends.

**Response:**
```json
{
  "models": [
    {
      "id": "llama-3.1-8b",
      "displayName": "Llama 3.1 8B",
      "category": "writing",
      "backend": "ollama",
      "loaded": true,
      "contextSize": 8192,
      "fileSizeBytes": 4500000000
    },
    {
      "id": "deepseek-coder-6.7b",
      "displayName": "DeepSeek Coder 6.7B",
      "category": "coding",
      "backend": "ollama",
      "loaded": false,
      "contextSize": 16384,
      "fileSizeBytes": 3800000000
    }
  ]
}
```

---

### POST /models/{id}/load

Load a model into memory.

**Response:**
```json
{
  "modelId": "deepseek-coder-6.7b",
  "status": "loading",
  "estimatedDurationMs": 15000
}
```

---

### DELETE /models/{id}

Unload a model from memory.

**Response:**
```json
{
  "modelId": "deepseek-coder-6.7b",
  "status": "unloaded"
}
```

---

### GET /backends

List backend status.

**Response:**
```json
{
  "backends": [
    {
      "name": "ollama",
      "status": "healthy",
      "url": "http://localhost:11434",
      "loadedModels": 2,
      "gpuMemoryUsedMB": 8500,
      "gpuMemoryTotalMB": 24000
    },
    {
      "name": "llama-cpp",
      "status": "healthy",
      "url": "http://localhost:8080",
      "loadedModels": 1,
      "gpuMemoryUsedMB": 6000,
      "gpuMemoryTotalMB": 24000
    }
  ]
}
```

---

### GET /health

Health check endpoint.

**Response:**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "uptime": "2h34m12s",
  "backends": {
    "ollama": "healthy",
    "llama-cpp": "healthy"
  }
}
```

---

## WebSocket API

### Connection

```javascript
const ws = new WebSocket('ws://localhost:8089/api/v1/ws');
```

### Message Types

#### Request: generate

```json
{
  "type": "generate",
  "id": "req-123",
  "payload": {
    "systemPrompt": "You are a helpful assistant.",
    "userPrompt": "Write a haiku about programming.",
    "model": "writing",
    "stream": true
  }
}
```

#### Response: chunk

```json
{
  "type": "chunk",
  "id": "req-123",
  "payload": {
    "delta": "Code flows like water"
  }
}
```

#### Response: done

```json
{
  "type": "done",
  "id": "req-123",
  "payload": {
    "content": "Code flows like water\nBugs emerge then fade away\nTests turn green at last",
    "finishReason": "stop",
    "tokensUsed": { "total": 47 }
  }
}
```

#### Response: error

```json
{
  "type": "error",
  "id": "req-123",
  "payload": {
    "code": 9201,
    "message": "Backend connection failed"
  }
}
```

#### Request: cancel

```json
{
  "type": "cancel",
  "id": "req-123"
}
```

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "error": {
    "code": 9101,
    "message": "Invalid JSON in request body",
    "details": "unexpected end of JSON input at position 45"
  }
}
```

---

## Rate Limiting

When rate limited, responses include:

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1706745600
Retry-After: 45
```

---

## See Also

- [Startup Modes](./03-startup-modes.md)
- [Error Codes](./05-error-codes.md)
