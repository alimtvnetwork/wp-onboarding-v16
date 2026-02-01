# 29 - AI Provider Settings

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** ✅ Complete  
> **Depends On:** `04-database-schema.md`, `66-shared-constants.md`

---

## 🎯 Purpose

Manage AI provider connections for Link Manager's AI-powered features (keyword suggestions, anchor text generation, content analysis). Supports multiple providers with seedable defaults, custom OAuth/API configurations, and user-customizable model names.

---

## 📋 Features

1. **Multi-Provider Support**: OpenAI, Gemini, Anthropic, Mistral, Groq, Ollama (local)
2. **Flexible Authentication**: Bearer Token, OAuth 2.0 Client Credentials, Custom Headers
3. **Seedable Configuration**: Default values from `config.json`, user-modifiable in SQLite
4. **Custom Providers**: Add unlimited custom AI endpoints
5. **Model Aliasing**: User-defined names for models
6. **Connection Testing**: Validate credentials before saving

---

## 🗄️ Database Schema

### Table: `AiProviders` (Main DB)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `Id` | INTEGER | PK AUTOINCREMENT | Unique identifier |
| `ProviderKey` | TEXT | NOT NULL UNIQUE | Provider slug (e.g., `openai`, `gemini`, `custom_1`) |
| `DisplayName` | TEXT | NOT NULL | User-facing name |
| `ProviderType` | TEXT | NOT NULL | Enum: `openai`, `gemini`, `anthropic`, `mistral`, `groq`, `ollama`, `custom` |
| `BaseUrl` | TEXT | NOT NULL | API base URL |
| `AuthType` | TEXT | NOT NULL | Enum: `bearer`, `oauth2_client`, `oauth2_code`, `api_key_header`, `custom_header` |
| `IsEnabled` | INTEGER | DEFAULT 0 | 0=disabled, 1=enabled |
| `IsSeeded` | INTEGER | DEFAULT 0 | 0=user-created, 1=seeded from config |
| `IsUserModified` | INTEGER | DEFAULT 0 | 0=pristine, 1=user changed |
| `SeedVersion` | TEXT | NULL | Version from config.json |
| `Priority` | INTEGER | DEFAULT 100 | Sort order (lower = higher priority) |
| `CreatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |
| `UpdatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |

### Table: `AiProviderCredentials` (Main DB)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `Id` | INTEGER | PK AUTOINCREMENT | Unique identifier |
| `ProviderId` | INTEGER | FK → AiProviders.Id | Parent provider |
| `CredentialKey` | TEXT | NOT NULL | Key name (e.g., `api_key`, `client_id`, `access_token`) |
| `CredentialValue` | TEXT | NOT NULL | Encrypted value |
| `IsRequired` | INTEGER | DEFAULT 1 | 0=optional, 1=required |
| `FieldType` | TEXT | NOT NULL | Enum: `text`, `password`, `textarea`, `select` |
| `FieldLabel` | TEXT | NOT NULL | UI label |
| `FieldPlaceholder` | TEXT | NULL | Placeholder text |
| `FieldOrder` | INTEGER | DEFAULT 0 | Display order in form |
| `ValidationRegex` | TEXT | NULL | Optional validation pattern |
| `CreatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |
| `UpdatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |

**Unique Constraint:** `(ProviderId, CredentialKey)`

### Table: `AiModels` (Main DB)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `Id` | INTEGER | PK AUTOINCREMENT | Unique identifier |
| `ProviderId` | INTEGER | FK → AiProviders.Id | Parent provider |
| `ModelId` | TEXT | NOT NULL | Official model ID (e.g., `gpt-4o`, `gemini-2.0-flash`) |
| `DisplayName` | TEXT | NOT NULL | User-customizable name |
| `ModelCategory` | TEXT | NOT NULL | Enum: `chat`, `embedding`, `vision`, `code` |
| `IsDefault` | INTEGER | DEFAULT 0 | Default model for this provider |
| `IsEnabled` | INTEGER | DEFAULT 1 | 0=hidden, 1=available |
| `MaxTokens` | INTEGER | NULL | Context window size |
| `CostPer1kInput` | REAL | NULL | Cost tracking (optional) |
| `CostPer1kOutput` | REAL | NULL | Cost tracking (optional) |
| `CreatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |
| `UpdatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |

**Unique Constraint:** `(ProviderId, ModelId)`

### Table: `AiOAuthSessions` (Main DB)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `Id` | INTEGER | PK AUTOINCREMENT | Unique identifier |
| `ProviderId` | INTEGER | FK → AiProviders.Id | Parent provider |
| `AccessToken` | TEXT | NOT NULL | Encrypted access token |
| `RefreshToken` | TEXT | NULL | Encrypted refresh token |
| `TokenType` | TEXT | DEFAULT 'Bearer' | Token type |
| `ExpiresAt` | TEXT | NULL | Token expiry (ISO 8601) |
| `Scope` | TEXT | NULL | Granted scopes |
| `State` | TEXT | NULL | CSRF state for OAuth flow |
| `CreatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |
| `UpdatedAt` | TEXT | NOT NULL | ISO 8601 timestamp |

---

## 🔧 Enums (add to `66-shared-constants.md`)

```php
// AI Provider Types
enum AiProviderType: string {
    case OPENAI = 'openai';
    case GEMINI = 'gemini';
    case ANTHROPIC = 'anthropic';
    case MISTRAL = 'mistral';
    case GROQ = 'groq';
    case OLLAMA = 'ollama';
    case CUSTOM = 'custom';
}

// AI Authentication Types
enum AiAuthType: string {
    case BEARER = 'bearer';                    // Simple API key as Bearer token
    case OAUTH2_CLIENT = 'oauth2_client';      // OAuth 2.0 Client Credentials
    case OAUTH2_CODE = 'oauth2_code';          // OAuth 2.0 Authorization Code
    case API_KEY_HEADER = 'api_key_header';    // API key in custom header
    case CUSTOM_HEADER = 'custom_header';      // Custom header(s)
}

// AI Model Categories
enum AiModelCategory: string {
    case CHAT = 'chat';
    case EMBEDDING = 'embedding';
    case VISION = 'vision';
    case CODE = 'code';
}

// AI Credential Field Types
enum AiCredentialFieldType: string {
    case TEXT = 'text';
    case PASSWORD = 'password';
    case TEXTAREA = 'textarea';
    case SELECT = 'select';
}
```

---

## 📁 Seed Configuration (`config.json`)

```json
{
  "ai_providers": {
    "seed_version": "1.0.0",
    "providers": [
      {
        "provider_key": "openai",
        "display_name": "OpenAI",
        "provider_type": "openai",
        "base_url": "https://api.openai.com/v1",
        "auth_type": "bearer",
        "priority": 10,
        "credentials": [
          {
            "credential_key": "api_key",
            "field_type": "password",
            "field_label": "API Key",
            "field_placeholder": "sk-...",
            "is_required": true,
            "field_order": 1
          },
          {
            "credential_key": "organization_id",
            "field_type": "text",
            "field_label": "Organization ID (optional)",
            "field_placeholder": "org-...",
            "is_required": false,
            "field_order": 2
          }
        ],
        "models": [
          {
            "model_id": "gpt-4o",
            "display_name": "GPT-4o",
            "model_category": "chat",
            "is_default": true,
            "max_tokens": 128000
          },
          {
            "model_id": "gpt-4o-mini",
            "display_name": "GPT-4o Mini",
            "model_category": "chat",
            "max_tokens": 128000
          },
          {
            "model_id": "gpt-4-turbo",
            "display_name": "GPT-4 Turbo",
            "model_category": "chat",
            "max_tokens": 128000
          },
          {
            "model_id": "text-embedding-3-large",
            "display_name": "Embeddings Large",
            "model_category": "embedding",
            "max_tokens": 8191
          }
        ]
      },
      {
        "provider_key": "gemini",
        "display_name": "Google Gemini",
        "provider_type": "gemini",
        "base_url": "https://generativelanguage.googleapis.com/v1beta",
        "auth_type": "api_key_header",
        "priority": 20,
        "credentials": [
          {
            "credential_key": "api_key",
            "field_type": "password",
            "field_label": "API Key",
            "field_placeholder": "AIza...",
            "is_required": true,
            "field_order": 1
          }
        ],
        "models": [
          {
            "model_id": "gemini-2.0-flash",
            "display_name": "Gemini 2.0 Flash",
            "model_category": "chat",
            "is_default": true,
            "max_tokens": 1000000
          },
          {
            "model_id": "gemini-2.0-pro",
            "display_name": "Gemini 2.0 Pro",
            "model_category": "chat",
            "max_tokens": 2000000
          },
          {
            "model_id": "gemini-1.5-flash",
            "display_name": "Gemini 1.5 Flash",
            "model_category": "chat",
            "max_tokens": 1000000
          }
        ]
      },
      {
        "provider_key": "anthropic",
        "display_name": "Anthropic Claude",
        "provider_type": "anthropic",
        "base_url": "https://api.anthropic.com/v1",
        "auth_type": "api_key_header",
        "priority": 30,
        "credentials": [
          {
            "credential_key": "api_key",
            "field_type": "password",
            "field_label": "API Key",
            "field_placeholder": "sk-ant-...",
            "is_required": true,
            "field_order": 1
          }
        ],
        "models": [
          {
            "model_id": "claude-3-5-sonnet-20241022",
            "display_name": "Claude 3.5 Sonnet",
            "model_category": "chat",
            "is_default": true,
            "max_tokens": 200000
          },
          {
            "model_id": "claude-3-5-haiku-20241022",
            "display_name": "Claude 3.5 Haiku",
            "model_category": "chat",
            "max_tokens": 200000
          },
          {
            "model_id": "claude-3-opus-20240229",
            "display_name": "Claude 3 Opus",
            "model_category": "chat",
            "max_tokens": 200000
          }
        ]
      },
      {
        "provider_key": "mistral",
        "display_name": "Mistral AI",
        "provider_type": "mistral",
        "base_url": "https://api.mistral.ai/v1",
        "auth_type": "bearer",
        "priority": 40,
        "credentials": [
          {
            "credential_key": "api_key",
            "field_type": "password",
            "field_label": "API Key",
            "is_required": true,
            "field_order": 1
          }
        ],
        "models": [
          {
            "model_id": "mistral-large-latest",
            "display_name": "Mistral Large",
            "model_category": "chat",
            "is_default": true,
            "max_tokens": 128000
          },
          {
            "model_id": "mistral-medium-latest",
            "display_name": "Mistral Medium",
            "model_category": "chat",
            "max_tokens": 32000
          },
          {
            "model_id": "codestral-latest",
            "display_name": "Codestral",
            "model_category": "code",
            "max_tokens": 32000
          }
        ]
      },
      {
        "provider_key": "groq",
        "display_name": "Groq",
        "provider_type": "groq",
        "base_url": "https://api.groq.com/openai/v1",
        "auth_type": "bearer",
        "priority": 50,
        "credentials": [
          {
            "credential_key": "api_key",
            "field_type": "password",
            "field_label": "API Key",
            "field_placeholder": "gsk_...",
            "is_required": true,
            "field_order": 1
          }
        ],
        "models": [
          {
            "model_id": "llama-3.3-70b-versatile",
            "display_name": "LLaMA 3.3 70B",
            "model_category": "chat",
            "is_default": true,
            "max_tokens": 32768
          },
          {
            "model_id": "mixtral-8x7b-32768",
            "display_name": "Mixtral 8x7B",
            "model_category": "chat",
            "max_tokens": 32768
          }
        ]
      },
      {
        "provider_key": "ollama",
        "display_name": "Ollama (Local)",
        "provider_type": "ollama",
        "base_url": "http://localhost:11434/api",
        "auth_type": "bearer",
        "priority": 100,
        "credentials": [
          {
            "credential_key": "base_url",
            "field_type": "text",
            "field_label": "Ollama Server URL",
            "field_placeholder": "http://localhost:11434",
            "is_required": true,
            "field_order": 1
          }
        ],
        "models": [
          {
            "model_id": "llama3.2",
            "display_name": "LLaMA 3.2",
            "model_category": "chat",
            "is_default": true
          },
          {
            "model_id": "codellama",
            "display_name": "Code LLaMA",
            "model_category": "code"
          },
          {
            "model_id": "nomic-embed-text",
            "display_name": "Nomic Embed",
            "model_category": "embedding"
          }
        ]
      }
    ],
    "oauth2_templates": {
      "oauth2_client": {
        "credentials": [
          {"credential_key": "client_id", "field_type": "text", "field_label": "Client ID", "is_required": true, "field_order": 1},
          {"credential_key": "client_secret", "field_type": "password", "field_label": "Client Secret", "is_required": true, "field_order": 2},
          {"credential_key": "token_url", "field_type": "text", "field_label": "Token URL", "is_required": true, "field_order": 3},
          {"credential_key": "scope", "field_type": "text", "field_label": "Scope (optional)", "is_required": false, "field_order": 4}
        ]
      },
      "oauth2_code": {
        "credentials": [
          {"credential_key": "client_id", "field_type": "text", "field_label": "Client ID", "is_required": true, "field_order": 1},
          {"credential_key": "client_secret", "field_type": "password", "field_label": "Client Secret", "is_required": true, "field_order": 2},
          {"credential_key": "authorize_url", "field_type": "text", "field_label": "Authorization URL", "is_required": true, "field_order": 3},
          {"credential_key": "token_url", "field_type": "text", "field_label": "Token URL", "is_required": true, "field_order": 4},
          {"credential_key": "redirect_uri", "field_type": "text", "field_label": "Redirect URI", "is_required": true, "field_order": 5},
          {"credential_key": "scope", "field_type": "text", "field_label": "Scope", "is_required": false, "field_order": 6}
        ]
      },
      "custom_header": {
        "credentials": [
          {"credential_key": "header_name", "field_type": "text", "field_label": "Header Name", "field_placeholder": "X-API-Key", "is_required": true, "field_order": 1},
          {"credential_key": "header_value", "field_type": "password", "field_label": "Header Value", "is_required": true, "field_order": 2}
        ]
      }
    }
  }
}
```

---

## 🔧 PHP Service: `AiProviderService`

```php
namespace LinkManager\Services;

class AiProviderService {
    
    /**
     * Seed providers from config.json (runs on plugin activation/update)
     */
    public function seedFromConfig(array $config): SeedResult {
        $configVersion = $config['ai_providers']['seed_version'];
        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        
        foreach ($config['ai_providers']['providers'] as $providerData) {
            $existing = $this->getByKey($providerData['provider_key']);
            
            if (!$existing) {
                // Create new provider
                $this->createProvider($providerData, $configVersion);
                $results['created']++;
            } elseif (!$existing->isUserModified && version_compare($configVersion, $existing->seedVersion, '>')) {
                // Update if not user-modified and config is newer
                $this->updateProvider($existing->id, $providerData, $configVersion);
                $results['updated']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return new SeedResult($results);
    }
    
    /**
     * Get all providers sorted by priority
     */
    public function getAllProviders(): array;
    
    /**
     * Get enabled providers only
     */
    public function getEnabledProviders(): array;
    
    /**
     * Create custom provider
     */
    public function createCustomProvider(CreateProviderDto $dto): AiProviderEntity;
    
    /**
     * Update provider (marks as user-modified)
     */
    public function updateProvider(int $id, UpdateProviderDto $dto): AiProviderEntity;
    
    /**
     * Delete provider (only custom providers)
     */
    public function deleteProvider(int $id): bool;
    
    /**
     * Test provider connection
     */
    public function testConnection(int $providerId): ConnectionTestResult;
    
    /**
     * Get credentials for provider (decrypted)
     */
    public function getCredentials(int $providerId): array;
    
    /**
     * Save credentials (encrypts before storing)
     */
    public function saveCredentials(int $providerId, array $credentials): bool;
    
    /**
     * Refresh OAuth token if expired
     */
    public function refreshOAuthToken(int $providerId): ?string;
    
    /**
     * Get models for provider
     */
    public function getModels(int $providerId): array;
    
    /**
     * Add custom model to provider
     */
    public function addModel(int $providerId, CreateModelDto $dto): AiModelEntity;
    
    /**
     * Update model display name
     */
    public function updateModelName(int $modelId, string $displayName): AiModelEntity;
}
```

---

## 📡 REST API Endpoints

**Namespace:** `lm/v1/ai-providers`

### Provider Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ai-providers` | List all providers |
| GET | `/ai-providers/{id}` | Get single provider |
| POST | `/ai-providers` | Create custom provider |
| PUT | `/ai-providers/{id}` | Update provider |
| DELETE | `/ai-providers/{id}` | Delete custom provider |
| POST | `/ai-providers/{id}/enable` | Enable provider |
| POST | `/ai-providers/{id}/disable` | Disable provider |
| POST | `/ai-providers/{id}/test` | Test connection |
| POST | `/ai-providers/reseed` | Re-seed from config.json |

### Credentials Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ai-providers/{id}/credentials` | Get credential fields (values masked) |
| PUT | `/ai-providers/{id}/credentials` | Save credentials |
| DELETE | `/ai-providers/{id}/credentials` | Clear all credentials |

### OAuth Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ai-providers/{id}/oauth/authorize` | Get OAuth authorization URL |
| POST | `/ai-providers/{id}/oauth/callback` | Handle OAuth callback |
| POST | `/ai-providers/{id}/oauth/refresh` | Refresh OAuth token |
| DELETE | `/ai-providers/{id}/oauth/revoke` | Revoke OAuth session |

### Model Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ai-providers/{id}/models` | List models for provider |
| POST | `/ai-providers/{id}/models` | Add custom model |
| PUT | `/ai-providers/{id}/models/{modelId}` | Update model |
| DELETE | `/ai-providers/{id}/models/{modelId}` | Delete custom model |
| POST | `/ai-providers/{id}/models/{modelId}/set-default` | Set as default model |

---

## 📋 Request/Response Schemas

### GET `/ai-providers`

**Response:**
```json
{
  "success": true,
  "data": {
    "providers": [
      {
        "id": 1,
        "provider_key": "openai",
        "display_name": "OpenAI",
        "provider_type": "openai",
        "base_url": "https://api.openai.com/v1",
        "auth_type": "bearer",
        "is_enabled": true,
        "is_seeded": true,
        "is_user_modified": false,
        "is_configured": true,
        "priority": 10,
        "model_count": 4,
        "default_model": "gpt-4o"
      }
    ],
    "total": 6
  }
}
```

### POST `/ai-providers` (Create Custom)

**Request:**
```json
{
  "display_name": "Azure OpenAI",
  "provider_type": "custom",
  "base_url": "https://my-resource.openai.azure.com",
  "auth_type": "api_key_header",
  "credentials": [
    {
      "credential_key": "api_key",
      "field_type": "password",
      "field_label": "API Key",
      "is_required": true
    },
    {
      "credential_key": "api_version",
      "field_type": "text",
      "field_label": "API Version",
      "is_required": true,
      "default_value": "2024-02-15-preview"
    }
  ],
  "models": [
    {
      "model_id": "gpt-4-deployment",
      "display_name": "GPT-4 (Azure)",
      "model_category": "chat",
      "is_default": true
    }
  ]
}
```

### PUT `/ai-providers/{id}/credentials`

**Request:**
```json
{
  "credentials": {
    "api_key": "sk-proj-xxxxxxxxxxxx",
    "organization_id": "org-xxxxxxxxxxxx"
  }
}
```

### POST `/ai-providers/{id}/test`

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "status": "connected",
    "latency_ms": 245,
    "model_tested": "gpt-4o",
    "message": "Connection successful"
  }
}
```

**Response (Failure):**
```json
{
  "success": false,
  "error": {
    "code": 14810,
    "message": "Authentication failed",
    "details": "Invalid API key provided"
  }
}
```

---

## 🔒 Security

### Credential Encryption
- All credentials encrypted with WordPress `wp_encrypt()` or OpenSSL AES-256-GCM
- Encryption key derived from `AUTH_KEY` + plugin salt
- Credentials never logged or exposed in responses

### OAuth Security
- CSRF protection via `state` parameter
- Token refresh with sliding window
- Automatic token revocation on provider deletion

### API Security
- All endpoints require `manage_options` capability
- Rate limiting: 60 requests/minute
- Input validation with sanitization

---

## 🚨 Error Codes (add to `66-shared-constants.md`)

| Code | Constant | Message |
|------|----------|---------|
| 14810 | `AI_PROVIDER_AUTH_FAILED` | Authentication failed |
| 14811 | `AI_PROVIDER_NOT_FOUND` | Provider not found |
| 14812 | `AI_PROVIDER_DUPLICATE_KEY` | Provider key already exists |
| 14813 | `AI_PROVIDER_CANNOT_DELETE_SEEDED` | Cannot delete seeded provider |
| 14814 | `AI_PROVIDER_INVALID_AUTH_TYPE` | Invalid authentication type |
| 14815 | `AI_PROVIDER_MISSING_CREDENTIALS` | Required credentials missing |
| 14816 | `AI_PROVIDER_CONNECTION_FAILED` | Connection test failed |
| 14817 | `AI_PROVIDER_OAUTH_STATE_MISMATCH` | OAuth state mismatch |
| 14818 | `AI_PROVIDER_OAUTH_TOKEN_EXPIRED` | OAuth token expired and refresh failed |
| 14819 | `AI_PROVIDER_MODEL_NOT_FOUND` | Model not found |
| 14820 | `AI_PROVIDER_ENCRYPTION_FAILED` | Credential encryption failed |

---

## 🖥️ UI Integration

See `30-ai-provider-settings-page.md` for admin UI specification.

### Settings Page Section
- Located in Settings → AI Providers tab
- Provider cards with enable/disable toggles
- Credential forms with field validation
- OAuth connect buttons with status indicators
- Connection test with real-time feedback
- Model list with rename capability

---

## 📝 Entity Classes (add to `08-entity-models.md`)

```php
class AiProviderEntity extends BaseEntity {
    public int $id;
    public string $providerKey;
    public string $displayName;
    public AiProviderType $providerType;
    public string $baseUrl;
    public AiAuthType $authType;
    public bool $isEnabled;
    public bool $isSeeded;
    public bool $isUserModified;
    public ?string $seedVersion;
    public int $priority;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;
    
    // Computed
    public bool $isConfigured;
    public int $modelCount;
    public ?string $defaultModel;
}

class AiProviderCredentialEntity extends BaseEntity {
    public int $id;
    public int $providerId;
    public string $credentialKey;
    public string $credentialValue; // Encrypted
    public bool $isRequired;
    public AiCredentialFieldType $fieldType;
    public string $fieldLabel;
    public ?string $fieldPlaceholder;
    public int $fieldOrder;
    public ?string $validationRegex;
}

class AiModelEntity extends BaseEntity {
    public int $id;
    public int $providerId;
    public string $modelId;
    public string $displayName;
    public AiModelCategory $modelCategory;
    public bool $isDefault;
    public bool $isEnabled;
    public ?int $maxTokens;
    public ?float $costPer1kInput;
    public ?float $costPer1kOutput;
}

class AiOAuthSessionEntity extends BaseEntity {
    public int $id;
    public int $providerId;
    public string $accessToken; // Encrypted
    public ?string $refreshToken; // Encrypted
    public string $tokenType;
    public ?DateTimeImmutable $expiresAt;
    public ?string $scope;
    public ?string $state;
}
```

---

## 📋 Acceptance Criteria

- [ ] Seeded providers load from config.json on plugin activation
- [ ] User modifications prevent automatic seed updates
- [ ] Manual re-seed option available for admins
- [ ] All 6 default providers configurable
- [ ] Custom providers can be added with any auth type
- [ ] OAuth 2.0 flows complete successfully
- [ ] Credentials encrypted at rest
- [ ] Connection test validates provider accessibility
- [ ] Model names user-customizable
- [ ] Settings persist across plugin updates
