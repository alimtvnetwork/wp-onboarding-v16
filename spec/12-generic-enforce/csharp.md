# Generic Enforce — C#

**Applies to**: All `.cs` files. C# 10+ recommended for `global using` aliases.

---

## Mechanism: Inheritance or `using` Aliases

C# does not support direct type aliases for generic instantiations the same way TypeScript or Go do. Two approaches:

### Approach A: Inheritance (Preferred for classes)

```csharp
// Base generic
public class Student<TRights, TKey> where TKey : notnull
{
    public TKey Id { get; set; }
    public TRights Rights { get; set; }
    public string Name { get; set; }
}

// ✅ Named instantiations via inheritance
public class TeacherBasicRights : Student<BasicRights, int> { }
public class TeacherBasicRightsV2 : Student<BasicRightsV2, int> { }
```

### Approach B: `using` Aliases (C# 12+, for records/structs)

```csharp
// File-scoped or global using
global using TeacherBasicRights = Student<BasicRights, int>;
global using TeacherBasicRightsV2 = Student<BasicRightsV2, int>;
```

---

## Prohibited Patterns

```csharp
// ❌ NEVER: object as field type
public class SessionInfo
{
    public object Metadata { get; set; }
}

// ❌ NEVER: dynamic
public dynamic GetData() { ... }

// ❌ NEVER: Dictionary<string, object>
public Dictionary<string, object> Context { get; set; }

// ❌ NEVER: Raw generic in signatures (when used repeatedly)
public Student<BasicRights, int> GetTeacher() { ... }  // if used 3+ times
```

## Required Replacements

### `Dictionary<string, object>` → Typed Class

```csharp
// BEFORE (prohibited)
public class ApiError
{
    public Dictionary<string, object> Context { get; set; }
}

// AFTER (required)
public class ErrorContext
{
    public string? Endpoint { get; set; }
    public int? StatusCode { get; set; }
    public string? RequestId { get; set; }
    public int? PluginId { get; set; }
    public string? SessionId { get; set; }
}

public class ApiError
{
    public ErrorContext? Context { get; set; }
}
```

### Exception handling

```csharp
// BEFORE (prohibited — catching base Exception without narrowing)
catch (Exception ex)
{
    logger.LogError(ex.Message);
}

// AFTER (required — catch specific exceptions)
catch (HttpRequestException ex)
{
    logger.LogError("HTTP error: {Message}", ex.Message);
}
catch (InvalidOperationException ex)
{
    logger.LogError("Operation error: {Message}", ex.Message);
}
```

---

## The Student-Teacher Pattern in C#

```csharp
// Base generic
public record Student<TRights, TKey>(
    TKey Id,
    TRights Rights,
    string Name,
    DateTime EnrolledAt
) where TKey : notnull;

// Rights types
public record BasicRights(bool CanRead, bool CanWrite);

public record BasicRightsV2(
    bool CanRead, bool CanWrite,
    bool CanAdmin, bool CanExport
) : BasicRights(CanRead, CanWrite);

// ✅ Named instantiations (REQUIRED)
public record TeacherBasicRights(
    int Id, BasicRights Rights, string Name, DateTime EnrolledAt
) : Student<BasicRights, int>(Id, Rights, Name, EnrolledAt);

public record TeacherBasicRightsV2(
    int Id, BasicRightsV2 Rights, string Name, DateTime EnrolledAt
) : Student<BasicRightsV2, int>(Id, Rights, Name, EnrolledAt);

// ✅ Usage
public TeacherBasicRights GetTeacher(int id) { ... }
public TeacherBasicRightsV2 GetTeacherV2(int id) { ... }
```

---

## C#-Specific Notes

1. **No true type aliases for generics** until C# 12 `using` directives. Prefer inheritance for domain types.
2. **`global using` aliases** (C# 12+) work project-wide and are the cleanest approach for simple aliases.
3. **`object` and `dynamic`** are the C# equivalents of `any`/`unknown` — equally prohibited.
4. **Records** are preferred over classes for data-carrying types (immutability, value equality).
