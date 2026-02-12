# Generic Enforce — Rust

**Applies to**: All `.rs` files.

---

## Mechanism: `type` Aliases

Rust has first-class support for type aliases:

```rust
// Base generic
struct Student<TRights, TKey> {
    id: TKey,
    rights: TRights,
    name: String,
    enrolled_at: String,
}

// ✅ Named instantiations
type TeacherBasicRights = Student<BasicRights, i32>;
type TeacherBasicRightsV2 = Student<BasicRightsV2, i32>;
type StudentByUUID = Student<BasicRights, String>;
```

---

## Prohibited Patterns

```rust
// ❌ NEVER: Box<dyn Any> as a field type (unless truly needed for type erasure)
struct SessionInfo {
    metadata: Box<dyn Any>,
}

// ❌ NEVER: serde_json::Value as a typed field
struct ApiError {
    context: serde_json::Value,  // This is Rust's "Record<string, unknown>"
}

// ❌ NEVER: Raw generic in function signatures (when used 3+ times)
fn get_teacher() -> Student<BasicRights, i32> { ... }
```

## Required Replacements

### `serde_json::Value` → Typed Struct

```rust
// BEFORE (prohibited)
#[derive(Serialize, Deserialize)]
struct ApiError {
    context: Option<serde_json::Value>,
}

// AFTER (required)
#[derive(Serialize, Deserialize)]
struct ErrorContext {
    endpoint: Option<String>,
    status_code: Option<u16>,
    request_id: Option<String>,
    plugin_id: Option<i32>,
    session_id: Option<String>,
}

#[derive(Serialize, Deserialize)]
struct ApiError {
    context: Option<ErrorContext>,
}
```

### `Box<dyn Any>` → Enum (discriminated union)

```rust
// BEFORE (prohibited)
struct Event {
    payload: Box<dyn Any>,
}

// AFTER: Use an enum for type-safe variants
enum EventPayload {
    Publish(PublishMetadata),
    Snapshot(SnapshotMetadata),
    Plugin(PluginMetadata),
}

struct Event {
    payload: EventPayload,
}
```

---

## The Student-Teacher Pattern in Rust

```rust
// Base generic
#[derive(Debug, Clone, Serialize, Deserialize)]
struct Student<TRights, TKey>
where
    TKey: Clone + PartialEq,
{
    id: TKey,
    rights: TRights,
    name: String,
    enrolled_at: String,
}

// Rights types
#[derive(Debug, Clone, Serialize, Deserialize)]
struct BasicRights {
    can_read: bool,
    can_write: bool,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
struct BasicRightsV2 {
    can_read: bool,
    can_write: bool,
    can_admin: bool,
    can_export: bool,
}

// ✅ Named instantiations (REQUIRED)
type TeacherBasicRights = Student<BasicRights, i32>;
type TeacherBasicRightsV2 = Student<BasicRightsV2, i32>;
type StudentByUUID = Student<BasicRights, String>;

// ✅ Usage
fn get_teacher(id: i32) -> TeacherBasicRights { ... }
fn get_teacher_v2(id: i32) -> TeacherBasicRightsV2 { ... }
```

---

## Rust-Specific Notes

1. **`type` aliases** are zero-cost — they are erased at compile time, no runtime overhead.
2. **Trait objects (`dyn Trait`)** are Rust's polymorphism mechanism. Prefer enums (sum types) over `dyn Any` for known variant sets.
3. **`serde_json::Value`** is the Rust equivalent of `Record<string, unknown>`. It's acceptable ONLY at deserialization boundaries, never in domain structs.
4. **Enums** are Rust's discriminated unions — they are the idiomatic replacement for `Box<dyn Any>` when the set of variants is known.
