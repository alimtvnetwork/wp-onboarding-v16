# Skipped: Centralized Logging

**Status:** ⏸️ Skipped  
**Complexity:** Medium  
**Updated:** 2026-01-29  

---

## Why Skipped

For simplicity, we are not using centralized logging infrastructure. The application uses simple file-based logging that can be viewed with standard Unix tools.

---

## What This Would Include

If implemented, centralized logging would provide:

### Components Required

| Component | Purpose | Complexity |
|-----------|---------|------------|
| Elasticsearch | Log storage & search | High |
| Filebeat/Fluentd | Log shipping | Medium |
| Kibana | Log visualization | Medium |
| Logstash | Log processing | Medium |

### Filebeat Configuration

```yaml
# Would require /etc/filebeat/inputs.d/gsearch.yml
- type: log
  enabled: true
  paths:
    - /var/log/gsearch/*.log
  json.keys_under_root: true
  json.add_error_key: true
  fields:
    service: gsearch
    environment: production
```

### Fluentd Configuration

```yaml
# Would require fluentd setup
<source>
  @type tail
  path /var/log/gsearch/search.log
  pos_file /var/log/fluentd/gsearch.pos
  tag gsearch
  <parse>
    @type json
  </parse>
</source>

<match gsearch>
  @type elasticsearch
  host elasticsearch.example.com
  port 9200
  index_name gsearch-logs
</match>
```

### Elasticsearch Index Template

```json
{
  "index_patterns": ["gsearch-*"],
  "template": {
    "mappings": {
      "properties": {
        "timestamp": { "type": "date" },
        "level": { "type": "keyword" },
        "message": { "type": "text" },
        "trace_id": { "type": "keyword" },
        "engine": { "type": "keyword" }
      }
    }
  }
}
```

---

## Simple Alternative (What We Use Instead)

```bash
# View logs in real-time
tail -f ./logs/gsearch.log

# Search logs with grep
grep "error" ./logs/gsearch.log

# Filter by JSON field with jq
cat ./logs/gsearch.log | jq 'select(.level == "error")'

# Log rotation with logrotate (built-in to most Linux systems)
# /etc/logrotate.d/gsearch
/var/log/gsearch/*.log {
    daily
    rotate 7
    compress
    missingok
}
```

### Built-in Logging Features

| Feature | Implementation |
|---------|----------------|
| JSON format | Structured logs with `--log-format json` |
| Log levels | `--log-level debug/info/warn/error` |
| File output | `--log-file ./logs/gsearch.log` |
| Console output | Default stdout |
| Log rotation | Native file rotation or logrotate |

---

## Revisit Criteria

Consider implementing if:
- Multiple services generating logs that need correlation
- Compliance requirements for log retention
- Need for advanced log search/analytics
- Distributed team needs shared log access

---

## Cross-References

- [Deployment Guide](../05-features/22-golang-search-cli/17-deployment-guide.md) — Simple logging setup
- [Skipped Features Overview](./00-overview.md) — Why features are skipped
