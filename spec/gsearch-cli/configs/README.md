# Configuration Presets

This directory contains configuration presets for different deployment environments.

## Available Presets

| Preset | File | Use Case |
|--------|------|----------|
| Development | `config.development.json` | Local development with verbose logging |
| Production | `config.production.json` | Production deployment with optimized settings |
| Testing | `config.testing.json` | Unit/integration tests with mocking |

## Quick Start

```bash
# Use development config
./gsearch --config=./configs/config.development.json search "query"

# Use production config  
./gsearch --config=./configs/config.production.json search "query"

# Use testing config (for test suite)
SEARCH_CONFIG=./configs/config.testing.json go test ./...
```

## Environment Variable Overrides

All configuration values support environment variable substitution using `${VAR_NAME}` or `${VAR_NAME:default}` syntax.

### Common Environment Variables

| Variable | Description | Used In |
|----------|-------------|---------|
| `SEARCH_DB_PATH` | Database file location | Production |
| `GOOGLE_CUSTOM_SEARCH_API_KEY` | Google Custom Search API key | Production |
| `GOOGLE_SEARCH_ENGINE_ID` | Google Search Engine ID | Production |
| `GOOGLE_CREDENTIALS_PATH` | Path to Google service account JSON | Production |
| `BING_SEARCH_API_KEY` | Bing Search API key | Production |
| `LOG_PATH` | Log file output path | Production |

### Example

```bash
export SEARCH_DB_PATH=/data/search.db
export GOOGLE_CUSTOM_SEARCH_API_KEY=your-api-key
./gsearch --config=./configs/config.production.json search "query"
```

## Preset Comparison

| Setting | Development | Production | Testing |
|---------|-------------|------------|---------|
| Database | File | File (env path) | In-memory |
| Concurrency | 2 | 10 | 1 |
| Request Delay | `"2s"` | `"500ms"` | `"0ms"` |
| Cache TTL | 1 day | 6 days | 1 day |
| Nested Depth | 1 | 3 | 1 |
| API Methods | Disabled | Enabled | Disabled |
| Logging | Debug/Text | Warn/JSON | Debug/Text |
| Progress Output | Yes | No | No |
| Mock Mode | No | No | Yes |
| Backoff Initial | `"1s"` | `"1s"` | `"10ms"` |

## Type Standards

### Weight Values (Normalized)

All weight values use `float64` in range `0.0` to `1.0` and **MUST sum to 1.0**:

```json
{
  "methodWeights": {
    "html": 0.40,
    "google_api": 0.30,
    "duckduckgo": 0.20,
    "bing": 0.10
  }
}
```

### Duration Values

All duration values support both formats:
- **String format** (preferred): `"2s"`, `"500ms"`, `"30m"`, `"1h"`
- **Numeric format** (backward compatible): milliseconds as integer

```json
{
  "requestDelay": "2s",
  "timeout": "30s",
  "cooldown": "15m"
}
```

## Schema Validation

Use the JSON Schema (`config.schema.json`) to validate configurations:

```bash
# With ajv-cli
npx ajv validate -s config.schema.json -d config.development.json

# With Go validator (built into CLI)
./gsearch config validate --config=./configs/config.development.json
```

## Creating Custom Presets

1. Copy an existing preset as a base
2. Modify values according to your needs
3. Validate against the schema
4. Reference with `--config` flag

```bash
cp config.development.json config.staging.json
# Edit config.staging.json
./gsearch config validate --config=./configs/config.staging.json
```

## Key Differences by Environment

### Development
- **Focus**: Fast iteration, detailed debugging
- **API Keys**: Optional (HTML parsing preferred)
- **Rate Limits**: Strict to avoid blocks during testing
- **Logging**: Verbose with colored output

### Production
- **Focus**: Performance, reliability, coverage
- **API Keys**: Required for full functionality
- **Rate Limits**: Optimized for throughput
- **Logging**: Structured JSON for log aggregation

### Testing
- **Focus**: Deterministic, fast, isolated
- **API Keys**: Mock values only
- **Rate Limits**: Disabled for speed
- **Database**: In-memory for test isolation
- **Mock Mode**: Enabled for reproducible tests
