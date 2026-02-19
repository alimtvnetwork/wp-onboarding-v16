# Licensing Strategy — Future Implementation

> **Status:** Planning / Not yet implemented  
> **Updated:** 2026-02-19

## Overview

Two-tier plugin distribution (Base + Pro) with a two-step validation flow combining local integrity verification and remote license validation.

## Plugin Tiers

| Tier | Distribution | Features |
|------|-------------|----------|
| **Base** | Free / standard | Core uploader functionality |
| **Pro** | Licensed | Snapshots, remote plugin activation, advanced features |

Both tiers share the **same version number** (e.g., `1.53.0`).

## Two-Step Validation Flow

### Step 1 — Local Integrity (File Manifest Hash)

Instead of hashing ZIP files, the system uses a **file manifest approach**:

#### Algorithm (Pseudocode)

```
function generateManifestHash(pluginDir):
    files = recursiveScan(pluginDir)
    files = excludeIgnored(files)          // remove .git/, cache/, logs, etc.
    files = excludeSymlinks(files)         // skip all symbolic links
    files = normalizeSlashes(files)        // convert \ to / (Windows compat)
    files = sortAlphabetically(files)      // by relative path, case-sensitive ASCII

    fileHashes = []
    for file in files:
        relativePath = removePrefix(file, pluginDir)  // e.g. "includes/Core/Boot.php"
        contentHash  = lowercase(hex(SHA-256(readBinary(file))))
        fileHashes.append(relativePath + ":" + contentHash)

    // Concatenate all "path:hash" strings with newline separator
    // NO trailing newline — join only places \n between items
    combined = join(fileHashes, "\n")

    return lowercase(hex(SHA-256(combined)))
```

#### Detailed Steps

1. **Recursive directory scan** — Traverse the plugin directory depth-first
2. **Exclude ignored paths** — Skip files/dirs matching exclusion rules (see below)
3. **Skip symlinks** — Do not follow or hash symbolic links (security + determinism)
4. **Normalize path separators** — All paths use forward slash `/` regardless of OS
5. **Sort alphabetically** — Case-sensitive ASCII order (`A` 0x41 < `a` 0x61) by relative path
6. **Hash each file** — Read raw binary contents, compute `SHA-256(contents)` → **lowercase hex** string (64 chars)
7. **Build hash string** — For each file: `"relative/path.php:a1b2c3d4..."`, join with `\n` (**no trailing newline**)
8. **Final hash** — `SHA-256(concatenated_string)` → **lowercase hex** → this is the **manifest hash**

> **Empty directories are ignored** — only files contribute to the hash. This is intentional; directory-only changes don't affect plugin behavior.

#### Normalization Rules (Critical for Cross-Platform Determinism)

| Rule | Specification |
|------|---------------|
| **Path separator** | Always `/` — convert `\` to `/` before hashing |
| **Hash case** | Always **lowercase hex** (`a1b2c3`, never `A1B2C3`) |
| **Sort order** | Case-sensitive ASCII (`A` < `Z` < `a` < `z`) |
| **Trailing newline** | **None** — `join()` places `\n` between items only |
| **Symlinks** | **Skipped entirely** — not followed, not hashed |
| **Empty dirs** | **Ignored** — only regular files are hashed |
| **Encoding** | File contents read as raw bytes (no charset conversion) |

#### Exclusion Rules

| Pattern | Reason |
|---------|--------|
| `.git/` | Version control metadata |
| `node_modules/` | JS dependencies (not shipped) |
| `*.log` | Runtime logs |
| `.DS_Store`, `Thumbs.db` | OS artifacts |
| `cache/`, `tmp/` | Transient runtime files |
| `.uploadignore` | Plugin-specific ignore file |

> **`vendor/` is NOT excluded** — Composer dependencies shipped with the plugin MUST be included in the hash to prevent dependency-swapping attacks. Only exclude `vendor/` if dependencies are installed at runtime by the host, not bundled.

> **Note:** The exclusion list is defined as a constant in the plugin config enum, making it auditable and deterministic.

#### Manifest JSON Structure

The manifest is stored locally for debugging/audit purposes, **not** used as input to the hash (the hash is computed from raw files):

```json
{
  "version": "1.53.0",
  "tier": "pro",
  "generated_at": "2026-02-19T10:30:00Z",
  "file_count": 87,
  "files": [
    { "path": "includes/Admin/AdminPage.php", "hash": "a1b2c3..." },
    { "path": "includes/Core/Bootstrap.php", "hash": "d4e5f6..." },
    { "path": "riseup-asia-uploader.php", "hash": "f7g8h9..." }
  ],
  "manifest_hash": "final_sha256_of_all_path_hash_pairs"
}
```

#### Computation Trigger

- **On plugin activation** (first install)
- **After every update** (post-update hook)
- **Never recomputed at runtime** — cached until next activation/update

#### Per-Tier Hash Combination (Build Fingerprint)

```
Base plugin dir  →  generateManifestHash()  →  manifest_hash_base   (64 hex chars)
Pro plugin dir   →  generateManifestHash()  →  manifest_hash_pro    (64 hex chars)

build_fingerprint = SHA-256(manifest_hash_base + ":" + manifest_hash_pro)
```

**Example:**
```
manifest_hash_base = "3a7f2ce4...e91d"  (lowercase hex, 64 chars)
manifest_hash_pro  = "b4d8f19a...a03c"  (lowercase hex, 64 chars)
input              = "3a7f2ce4...e91d:b4d8f19a...a03c"
build_fingerprint  = SHA-256(input) = "c9e2a71b...f150"  (lowercase hex)
```

The server stores the expected `build_fingerprint` for each version. The plugin computes it locally and compares using **constant-time comparison** (`hash_equals()` in PHP) to prevent timing attacks.

#### Why This Works

- **Deterministic** — same files always produce the same hash regardless of OS, timezone, or build tool
- **Tamper-evident** — modifying, adding, or removing any file changes the manifest hash
- **Version-locked** — base and pro must both be from the same build to produce the expected fingerprint
- **No ZIP dependency** — avoids non-deterministic ZIP metadata issues
- **Cross-platform safe** — explicit normalization rules eliminate OS-specific differences

### Step 2 — Remote License Validation

After the fingerprint passes, the plugin contacts the Go license server to validate the license key (activation status, site count, expiry, etc.).

---

## Security Analysis

### Attack Vectors & Vulnerabilities

| Attack | Risk | Mitigation |
|--------|------|------------|
| **Patch hash-check PHP code** | 🔴 HIGH — attacker modifies `verifyManifest()` to return `true` | Code obfuscation, multiple scattered checks |
| **Modify manifest JSON** | 🟡 MEDIUM — attacker regenerates manifest with tampered files | Server-side manifest comparison |
| **First-load race condition** | 🟡 MEDIUM — files modified before first integrity check | Immediate check on activation, not lazy |
| **Decompile & remove license logic** | 🔴 HIGH — PHP is plain text, trivially editable | Cannot fully prevent; server-gated features mitigate |
| **Share license key across sites** | 🟢 LOW — remote validation enforces site count | Already handled by remote validation |

### Core Weakness

**Any client-side integrity check in PHP can be bypassed** because PHP source is plaintext. An attacker can simply edit the verification function. This is an inherent limitation of all WordPress plugin licensing — even major plugins (ACF, Elementor, WooCommerce) face this.

---

## Suggested Hardening Approaches

### 1. Server-Gated Feature Delivery (Strongest)
Don't ship Pro code in the plugin at all. Pro features are fetched as encrypted code fragments from the server, decrypted with a session key tied to the license. **Unbreakable without valid license** but adds complexity and server dependency.

### 2. License-Key-Encrypted Pro Modules
Pro PHP files are shipped AES-encrypted. The decryption key is derived from the license key + site URL. Without a valid key, Pro code is gibberish. **Strong** but adds runtime decryption overhead.

### 3. Scattered Integrity Checks (Practical)
Instead of one `verifyManifest()` call, embed integrity checks in 10–15 random locations throughout the codebase. Each checks a different subset of files. Attacker must find and patch ALL of them. **Not unbreakable** but raises the effort significantly.

### 4. Server-Side Feature Flags (Recommended Complement)
Pro features require a valid `feature_token` from the server (short-lived, 1–4h TTL). Even if local checks are bypassed, Pro features call the server for authorization tokens. **Strong complement** to local checks.

### 5. Telemetry + Anomaly Detection
Plugin phones home with manifest hash periodically. If the server detects a hash mismatch (tampered files) with a valid license, flag the license for review. **Passive detection**, doesn't prevent but enables enforcement.

---

## Recommended Layered Strategy

```
Layer 1: File manifest hash (deters casual piracy)
Layer 2: Remote license validation (enforces keys/sites)
Layer 3: Server-side feature tokens (Pro features need live auth)
Layer 4: Scattered integrity checks (raises bypass effort)
Layer 5: Telemetry anomaly detection (catches tamperers post-facto)
```

## Architecture Decisions (Pending)

- Remote validation with local cache (Hybrid approach) — confirmed direction
- Grace period duration: TBD (3–7 days recommended)
- Cache TTL: TBD (24–72h recommended)
- Pro feature gating mechanism: TBD (server-gated vs encrypted vs flags)
- Go server endpoint design: TBD
- Manifest generation: on activation / post-update
- File exclusion rules for manifest: TBD
