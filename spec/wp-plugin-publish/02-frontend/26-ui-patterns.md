# 26. UI Patterns Specification

This document defines reusable UI patterns and behaviors across the application.

---

## 26.1 Modal/Dialog Behavior

### Scrolling
- All dialogs MUST have `max-h-[90vh]` and `overflow-y-auto` on the content wrapper
- This ensures modals with large content remain usable on small screens
- Implemented in `src/components/ui/dialog.tsx` via the `DialogContent` component

### Example
```tsx
<DialogContent className="sm:max-w-lg">
  {/* Content automatically scrolls if exceeding 90vh */}
</DialogContent>
```

### Key Requirements
| Requirement | Implementation |
|-------------|----------------|
| Max height | `max-h-[90vh]` |
| Vertical scroll | `overflow-y-auto` |
| Background | Must use `bg-background` (not transparent) |
| Z-index | `z-50` (above overlay) |

---

## 26.2 Form Field Persistence (localStorage)

### Purpose
Preserve user input across dialog closes, page refreshes, and session interruptions.

### Implementation Pattern
Create a dedicated hook per form (e.g., `useSiteFormPersistence`, `usePluginFormPersistence`):

```typescript
// src/hooks/use{Entity}FormPersistence.ts
const STORAGE_KEY = "wppp_{entity}_form_draft";

export function use{Entity}FormPersistence() {
  const [formData, setFormData] = useState<FormData>(initialFormData);

  // Load from localStorage on mount
  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      setFormData(JSON.parse(saved));
    }
  }, []);

  // Save on change (except sensitive fields)
  const updateFormData = (updates: Partial<FormData>) => {
    setFormData((prev) => {
      const next = { ...prev, ...updates };
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        ...next,
        password: undefined, // NEVER persist passwords
      }));
      return next;
    });
  };

  const clearForm = () => {
    setFormData(initialFormData);
    localStorage.removeItem(STORAGE_KEY);
  };

  return { formData, updateFormData, clearForm };
}
```

### Security Constraints
| Field Type | Persist to localStorage |
|------------|------------------------|
| Name, URL, username | ✅ Yes |
| Passwords, tokens, secrets | ❌ NEVER |
| Configuration flags | ✅ Yes |
| File paths | ✅ Yes |

### Storage Keys
| Entity | Storage Key |
|--------|-------------|
| Site form | `wppp_site_form_draft` |
| Plugin form | `wppp_plugin_form_draft` |

---

## 26.3 Password/Secret Storage

### At-Rest Encryption
Passwords and application tokens are encrypted using **AES-256-GCM** before database storage.

### Why NOT Hashing?
The system must decrypt passwords to authenticate with external WordPress REST APIs. Hashing (bcrypt, SHA-512) is one-way and would prevent this. AES-256-GCM provides:
- Strong encryption at rest
- Ability to decrypt for API authentication
- Industry-standard symmetric encryption

### Implementation
```go
// backend/internal/services/site/encryption.go
func (s *Service) encryptPassword(plaintext string) (string, error) {
  // AES-256-GCM encryption with random nonce
}

func (s *Service) decryptPassword(ciphertext string) (string, error) {
  // Decrypt for WordPress API authentication
}
```

### Key Management
- Encryption key stored in configuration (`config.json`)
- Key MUST be at least 32 bytes for AES-256
- Never log or expose decrypted passwords

---

## 26.4 Connection Test Live Logs

### Behavior
Real-time streaming of connection test progress via WebSocket.

### Step Update Logic
When a step update arrives:
1. If `step === "start"`: Clear all previous logs, start fresh session
2. If step exists with `status === "running"`: **Update in-place** (don't append)
3. If step is new: Append to log list
4. If `step === "complete"`: Mark session inactive

### Why Update In-Place?
When the backend sends:
```
{step: "auth_check", status: "running", message: "Authenticating..."}
{step: "auth_check", status: "success", message: "Authenticated as admin"}
```

The log should show ONE line that transitions from spinner to checkmark, NOT two separate lines.

### Implementation
```typescript
// src/hooks/useConnectionTestLogs.ts
const existingIndex = prev.steps.findIndex(
  (s) => s.step === step && s.status === "running"
);

if (existingIndex !== -1) {
  // Update existing step in-place
  const updatedSteps = [...prev.steps];
  updatedSteps[existingIndex] = { step, status, message, details, timestamp: new Date() };
  return { ...prev, steps: updatedSteps };
}
```

### Visual States
| Status | Icon | Color |
|--------|------|-------|
| `running` | Spinner (Loader2) | `text-primary` |
| `success` | CheckCircle | `text-primary` |
| `error` | XCircle | `text-destructive` |

---

## 26.5 "Save Anyway" Pattern

### Purpose
Allow users to save configuration even when validation fails (e.g., site connection test fails, plugin path not found).

### Implementation
1. When validation fails, set `validationError` state with error message
2. Show error banner in dialog with warning icon
3. Replace primary button with "Save Anyway" button (warning variant)
4. Pass `forceCreate: true` to API to bypass validation

### UI Structure
```tsx
{validationError && (
  <div className="border border-warning bg-warning/10 p-3 rounded-lg">
    <AlertCircle className="text-warning" />
    <p>{validationError}</p>
  </div>
)}

<DialogFooter>
  <Button variant="outline" onClick={onCancel}>Cancel</Button>
  {validationError ? (
    <Button variant="warning" onClick={handleSaveAnyway}>
      Save Anyway
    </Button>
  ) : (
    <Button onClick={handleSave}>Save</Button>
  )}
</DialogFooter>
```

### Backend Support
```go
type CreateInput struct {
  // ... other fields
  ForceCreate bool `json:"forceCreate"` // Skip validation errors
}

func (s *Service) Create(ctx context.Context, input CreateInput) {
  if !input.ForceCreate {
    if err := s.Validate(input); err != nil {
      return nil, err
    }
  }
  // Proceed with creation
}
```

---

## 26.6 Error Modal Integration

### Purpose
Provide full error details with copy functionality for debugging.

### When to Use
- API errors that users may need to report
- Validation failures with technical details
- Any error where context is important

### Implementation
```typescript
import { useErrorStore } from "@/stores/errorStore";

const { captureError, openErrorModal } = useErrorStore();

// On API error
if (!response.success && response.error) {
  const captured = captureError(response.error, {
    endpoint: '/api/endpoint',
    method: 'POST',
    requestBody: inputData,
  });
  openErrorModal(captured);
}
```

### Modal Features
- Error code and message
- Request details (endpoint, method, body with masked secrets)
- Stack trace (if available)
- "Copy Full Report" button for support tickets

---

## 26.7 Debug Mode Features

### Activation
Set `logging.debugMode: true` in configuration.

### Features When Enabled
| Feature | Description |
|---------|-------------|
| Curl commands | Show equivalent curl for connection test steps |
| Extended logging | Verbose console output |
| Request/response details | Full payloads in connection logs |

### UI Toggle
Connection logs component shows a Terminal icon button when debug mode is active:
```tsx
{debugMode && (
  <Button onClick={() => setShowCurlCommands(!showCurlCommands)}>
    <Terminal />
  </Button>
)}
```
