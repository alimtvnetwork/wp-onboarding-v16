# LLM Server Configuration Update Plan

> **Status**: Planning  
> **Date**: 2026-01-28  
> **Target Specs**: Backend AI Integration, Seeding Configuration

---

## Objective

Update specs to support flexible multi-model LLM server configuration:
- Run multiple servers (Ollama and/or llama.cpp) simultaneously
- Configure via seeding config
- Support different port allocations
- Enable runtime model switching

---

## Phase 1: Update Seeding Configuration Spec

**File**: `01-backend/09-seeding-configuration.md`

### Changes:
1. Add `llm.servers` array configuration structure
2. Define server types: `ollama`, `llama`, `llama-swap`
3. Add port allocation strategy options
4. Define model routing configuration

### New Config Keys:
```
llm.servers[].id
llm.servers[].type
llm.servers[].host
llm.servers[].port
llm.servers[].portRange.start
llm.servers[].portRange.end
llm.servers[].mode (for llama: router/single/swap)
llm.servers[].models_dir
llm.servers[].max_loaded_models
llm.servers[].keep_alive
llm.servers[].models[]
llm.routing.defaultServer
llm.routing.rules[]
```

---

## Phase 2: Create LLM Server Management Spec

**File**: `01-backend/28-llm-server-management.md` (NEW)

### Sections:
1. **Server Types & Modes**
   - Ollama server configuration
   - llama.cpp router mode
   - llama.cpp single-model mode
   - llama-swap proxy mode

2. **ServerRegistry Service**
   - Track active servers
   - Health monitoring
   - Port allocation

3. **ModelSlotManager**
   - LRU eviction for port range
   - Model loading/unloading
   - Warm model tracking

4. **API Abstraction Layer**
   - Unified interface for both server types
   - Model routing logic
   - Fallback handling

---

## Phase 3: Update AI Integration Spec

**File**: `01-backend/08-ai-integration.md`

### Changes:
1. Reference new server management spec
2. Update LLMService to use ServerRegistry
3. Add model selection API
4. Document server lifecycle hooks

---

## Phase 4: Update LLM Live Logging Spec

**File**: `01-backend/27-llm-live-logging.md`

### Changes:
1. Add multi-server log aggregation
2. Server-specific log filtering
3. Port-based log routing

---

## Phase 5: Update Frontend Monitoring Spec

**File**: `02-frontend/29-monitoring-dashboard.md`

### Changes:
1. Multi-server status display
2. Server-specific model tabs
3. Port allocation visualization

---

## Implementation Order

| Phase | File | Priority | Dependencies |
|-------|------|----------|--------------|
| 1 | 09-seeding-configuration.md | HIGH | None |
| 2 | 28-llm-server-management.md (NEW) | HIGH | Phase 1 |
| 3 | 08-ai-integration.md | MEDIUM | Phase 2 |
| 4 | 27-llm-live-logging.md | MEDIUM | Phase 2 |
| 5 | 29-monitoring-dashboard.md | LOW | Phase 4 |

---

## Validation Checklist

- [ ] Seeding config supports Ollama servers
- [ ] Seeding config supports llama.cpp servers
- [ ] Mixed server configurations work
- [ ] Port range allocation documented
- [ ] Model routing rules defined
- [ ] API abstraction covers both server types
- [ ] Logging aggregates from all servers
- [ ] Frontend displays multi-server status
