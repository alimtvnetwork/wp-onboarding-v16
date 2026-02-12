

## Plan: Go Upload Performance Optimization + Core Plugin Dashboard

> Created: 2026-02-12  
> Status: **Phases A1-A4 complete. A5 done. Feature B pending.**

---

## Feature A: Go Upload Performance Optimization (5 fixes)

### Problem Statement
The Go backend upload pipeline is significantly slower than the PowerShell script. Root cause: base64 encoding (+33% payload), unnecessary pre-upload HTTP round-trip, max compression level, and verbose WebSocket broadcasting.

### Phase A1: Switch upload from Base64 JSON to Multipart Form-Data ✅ DONE

**Files:**
- `backend/internal/wordpress/uploader.go` — `UploadPluginViaUploader()`

**Current:** Reads entire ZIP → `base64.StdEncoding.EncodeToString()` → sends as JSON body  
**Target:** Stream ZIP bytes directly as multipart/form-data

**Changes:**
1. Replace `os.ReadFile()` + `base64.StdEncoding.EncodeToString()` with `multipart.NewWriter` + `CreateFormFile`
2. Stream the file using `io.Copy` instead of loading into memory
3. Add `plugin_slug`, `activate`, `upload_source` as form fields
4. Update `Content-Type` header to use `writer.FormDataContentType()`

**Impact:** ~33% less data transmitted, no base64 CPU overhead, streaming instead of full-memory load

**⚠️ PHP dependency:** The `riseup-asia-uploader` PHP `handle_upload()` currently expects `plugin_zip` as base64 in JSON. Must update PHP handler to also accept multipart uploads OR keep backward compatibility with both formats.

### Phase A2: Remove Pre-Upload Status Check ✅ DONE (merged into A1)

**Files:**
- `backend/internal/wordpress/uploader.go` — lines 223-262

**Current:** Extra `GET /status` round-trip before every upload  
**Target:** Remove it. If the upload endpoint fails, the error surfaces directly.

**Impact:** Save ~200-500ms per publish

### Phase A3: Reduce ZIP Compression Level ✅ DONE

**Changed:** `flate.BestCompression` (level 9) → `flate.DefaultCompression` (level 6)  
**Result:** ~2-3x faster ZIP creation with only ~2-5% larger output

### Phase A4: Reduce Verbose Broadcasting During Upload ✅ DONE

**Changed:** Consolidated ~7 broadcast calls to 3: start progress, result log (success/error/simulated with retry metadata), stage complete.

### Phase A5: Update Memory ✅ DONE

Update `.lovable/memory/architecture/compression/zip-standards-and-logging.md` to reflect dual-level strategy.

---

## Feature B: Core Plugin Dashboard (Rise Up Asia Uploader Detail View)

### Problem Statement
The core Rise Up Asia Uploader plugin needs a dedicated dashboard showing health, version, deployment status — distinct from third-party plugin cards.

### Phase B1: Core Plugin Dashboard Component

**New file:** `src/components/plugins/CorePluginDashboard.tsx`

**Sections:**
1. **Header** — Plugin name, version badge, status indicator (active/inactive/error)
2. **Health Panel** — Connection status per mapped site, last deploy time, uploader version vs expected
3. **Version Info** — Local version, remote version per-site, update availability
4. **Quick Actions** — Deploy to new site, Update all outdated sites, View activity log
5. **Deployment History** — Recent publish history filtered to this plugin
6. **Endpoint Status** — Health of key endpoints (status, upload, enable/disable)

### Phase B2: Route + Navigation

**Files:**
- `src/App.tsx` — Add route `/plugins/core`
- `src/pages/Plugins.tsx` — Visual distinction for core plugin card + link to dashboard

### Phase B3: API Integration

All data sources already exist:
- `api.getPlugins()` — local plugin info
- `api.getRemotePlugins(siteId)` — remote version per-site
- `api.getPublishHistory()` — filtered by pluginId
- `api.testConnection(siteId)` — endpoint health

No new backend endpoints needed.

---

## Execution Order

| Phase | Feature | Est. Complexity |
|-------|---------|----------------|
| A1 | Multipart upload (+ PHP check) | High |
| A2 | Remove status pre-check | Low |
| A3 | Compression level | Low |
| A4 | Broadcast reduction | Medium |
| A5 | Memory update | Trivial |
| B1 | Dashboard component | High |
| B2 | Route + navigation | Low |
| B3 | API integration | Medium |

---

## Dependencies & Risks

| Risk | Mitigation |
|------|-----------|
| PHP upload handler only accepts base64 JSON | Check PHP `handle_upload()` first — may need multipart support |
| Reducing broadcasts may lose debug visibility | Keep file logger detail, only reduce WS broadcasts |
| Core plugin dashboard needs design consistency | Reuse existing card/panel patterns from site detail view |
