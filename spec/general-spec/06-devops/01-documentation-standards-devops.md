# Documentation Standards

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This specification defines documentation requirements for code, APIs, and user-facing content across PHP, TypeScript, and Python projects.

---

## 1. Code Documentation

### 1.1 Function/Method Documentation

Every public function MUST include:
- **Purpose**: What the function does
- **Parameters**: Type and description for each
- **Return value**: Type and description
- **Exceptions**: What errors can be thrown

**PHP (PHPDoc):**
```php
/**
 * Calculate the total price including tax and discounts.
 *
 * @param float $basePrice The original price before modifications
 * @param float $taxRate Tax rate as decimal (e.g., 0.08 for 8%)
 * @param float|null $discountPercent Optional discount percentage
 * 
 * @return float The final calculated price
 * 
 * @throws InvalidArgumentException When basePrice is negative
 * @throws InvalidArgumentException When taxRate is outside 0-1 range
 */
public function calculateTotalPrice(
    float $basePrice,
    float $taxRate,
    ?float $discountPercent = null
): float {
    // Implementation
}
```

**TypeScript (TSDoc):**
```typescript
/**
 * Calculate the total price including tax and discounts.
 *
 * @param basePrice - The original price before modifications
 * @param taxRate - Tax rate as decimal (e.g., 0.08 for 8%)
 * @param discountPercent - Optional discount percentage
 * @returns The final calculated price
 * @throws {@link ValidationError} When basePrice is negative
 *
 * @example
 * ```ts
 * const total = calculateTotalPrice(100, 0.08, 10);
 * // Returns: 97.2 (100 - 10% discount + 8% tax)
 * ```
 */
export function calculateTotalPrice(
  basePrice: number,
  taxRate: number,
  discountPercent?: number
): number {
  // Implementation
}
```

**Python (Google Style):**
```python
def calculate_total_price(
    base_price: float,
    tax_rate: float,
    discount_percent: float | None = None
) -> float:
    """Calculate the total price including tax and discounts.

    Args:
        base_price: The original price before modifications.
        tax_rate: Tax rate as decimal (e.g., 0.08 for 8%).
        discount_percent: Optional discount percentage.

    Returns:
        The final calculated price.

    Raises:
        ValueError: When base_price is negative.
        ValueError: When tax_rate is outside 0-1 range.

    Example:
        >>> calculate_total_price(100, 0.08, 10)
        97.2
    """
    # Implementation
```

### 1.2 Class Documentation

```typescript
/**
 * Manages user session lifecycle and authentication state.
 *
 * Handles session creation, validation, refresh, and termination.
 * Sessions are stored in Redis with configurable TTL.
 *
 * @example
 * ```ts
 * const sessionManager = new SessionManager(redisClient);
 * const session = await sessionManager.create(userId);
 * ```
 *
 * @see {@link AuthService} for authentication logic
 * @see {@link TokenService} for JWT operations
 */
export class SessionManager {
  // ...
}
```

### 1.3 Interface/Type Documentation

```typescript
/**
 * Configuration options for the email service.
 */
export interface EmailConfig {
  /** SMTP server hostname */
  host: string;
  
  /** SMTP server port (typically 587 for TLS) */
  port: number;
  
  /** Authentication credentials */
  auth: {
    /** SMTP username or email address */
    user: string;
    /** SMTP password or app-specific password */
    pass: string;
  };
  
  /** 
   * Connection timeout in milliseconds.
   * @defaultValue 30000
   */
  timeout?: number;
}
```

### 1.4 Inline Comments

Use inline comments sparingly for:
- **Why**, not what (code shows what)
- Complex algorithms
- Non-obvious business rules
- Workarounds with references

```typescript
// BAD: Describes what code does
// Loop through users and check if active
for (const user of users) {
  if (user.isActive) { ... }
}

// GOOD: Explains why
// Filter inactive users first to reduce API calls - 
// the external service charges per request
const activeUsers = users.filter(u => u.isActive);
```

---

## 2. API Documentation

### 2.1 OpenAPI/Swagger Specification

All REST APIs MUST have OpenAPI 3.0+ documentation:

```yaml
openapi: 3.0.3
info:
  title: User Management API
  description: |
    API for managing user accounts, authentication, and profiles.
    
    ## Authentication
    All endpoints except `/auth/login` require a Bearer token.
    
    ## Rate Limits
    - Anonymous: 100 requests/hour
    - Authenticated: 1000 requests/hour
  version: 1.0.0
  contact:
    email: api-support@example.com

servers:
  - url: https://api.example.com/v1
    description: Production
  - url: https://api-staging.example.com/v1
    description: Staging

paths:
  /users:
    get:
      summary: List users
      description: |
        Retrieve a paginated list of users. Results can be filtered
        by status and sorted by various fields.
      operationId: listUsers
      tags:
        - Users
      parameters:
        - name: status
          in: query
          description: Filter by user status
          schema:
            type: string
            enum: [active, inactive, pending]
        - name: page
          in: query
          description: Page number (1-indexed)
          schema:
            type: integer
            minimum: 1
            default: 1
        - name: limit
          in: query
          description: Items per page
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/UserListResponse'
              example:
                success: true
                data:
                  - id: "usr_abc123"
                    email: "user@example.com"
                    status: "active"
                meta:
                  page: 1
                  limit: 20
                  total: 150
        '401':
          $ref: '#/components/responses/Unauthorized'
        '429':
          $ref: '#/components/responses/RateLimited'

components:
  schemas:
    User:
      type: object
      required:
        - id
        - email
        - status
      properties:
        id:
          type: string
          description: Unique user identifier
          example: "usr_abc123"
        email:
          type: string
          format: email
          description: User's email address
        status:
          type: string
          enum: [active, inactive, pending]
          description: Current account status
        createdAt:
          type: string
          format: date-time
          description: Account creation timestamp
```

### 2.2 Endpoint Documentation Requirements

Each endpoint MUST document:

| Element | Required | Description |
|---------|----------|-------------|
| Summary | Yes | One-line description |
| Description | Yes | Detailed explanation |
| Parameters | Yes | All query/path/header params |
| Request Body | If applicable | Schema with examples |
| Responses | Yes | All possible status codes |
| Authentication | Yes | Required auth method |
| Rate Limits | Yes | Applicable limits |
| Examples | Yes | Request/response examples |

---

## 3. README Structure

### 3.1 Project README Template

```markdown
# Project Name

Brief description of what this project does.

## Features

- Feature 1: Brief description
- Feature 2: Brief description

## Quick Start

\```bash
# Installation
npm install project-name

# Configuration
cp .env.example .env

# Run
npm start
\```

## Requirements

- Node.js >= 18.0.0
- PostgreSQL >= 14
- Redis >= 7.0

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DATABASE_URL` | Yes | - | PostgreSQL connection string |
| `REDIS_URL` | No | `localhost:6379` | Redis connection URL |
| `LOG_LEVEL` | No | `info` | Logging verbosity |

## Usage

### Basic Example

\```typescript
import { Client } from 'project-name';

const client = new Client({ apiKey: 'xxx' });
const result = await client.doSomething();
\```

### Advanced Configuration

[Link to detailed docs]

## API Reference

See [API Documentation](./docs/api.md)

## Development

\```bash
# Install dependencies
npm install

# Run tests
npm test

# Run linter
npm run lint

# Build
npm run build
\```

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md)

## License

MIT - See [LICENSE](./LICENSE)
```

### 3.2 Package/Module README

For internal packages or modules:

```markdown
# @company/validation

Input validation utilities with TypeScript support.

## Installation

\```bash
npm install @company/validation
\```

## API

### `validate(schema, data)`

Validates data against a schema.

**Parameters:**
- `schema`: ValidationSchema - The schema to validate against
- `data`: unknown - The data to validate

**Returns:** `ValidationResult<T>`

**Example:**
\```typescript
const result = validate(userSchema, formData);
if (result.success) {
  console.log(result.data);
}
\```

### `createSchema(definition)`

Creates a reusable validation schema.

[...]
```

---

## 4. Architecture Documentation

### 4.1 Architecture Decision Records (ADRs)

Use ADRs to document significant architectural decisions:

```markdown
# ADR-001: Use PostgreSQL for Primary Database

## Status

Accepted

## Context

We need to choose a primary database for the application. 
Requirements include:
- ACID compliance for financial transactions
- JSON support for flexible schemas
- Strong ecosystem and tooling
- Horizontal scaling capability

## Decision

We will use PostgreSQL 15+ as our primary database.

## Consequences

### Positive
- Full ACID compliance
- Excellent JSON/JSONB support
- Mature ecosystem with many ORMs
- Strong community support

### Negative
- More complex horizontal scaling than NoSQL
- Requires more careful schema design upfront

### Neutral
- Team has existing PostgreSQL experience

## Alternatives Considered

1. **MySQL**: Less capable JSON support
2. **MongoDB**: ACID limitations for multi-document transactions
3. **CockroachDB**: Higher operational complexity

## References

- [PostgreSQL vs MySQL Comparison](...)
- [RFC: Database Selection](...)
```

### 4.2 ADR File Naming

```
docs/
  adr/
    ADR-001-use-postgresql-for-primary-database.md
    ADR-002-adopt-event-sourcing-for-orders.md
    ADR-003-replace-rest-with-graphql.md
    README.md  # Index of all ADRs
```

---

## 5. Changelog Standards

### 5.1 Keep a Changelog Format

Follow [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- New endpoint for bulk user import (#234)

### Changed
- Improved error messages for validation failures

## [2.1.0] - 2026-01-15

### Added
- Two-factor authentication support (#198)
- Email verification flow (#201)
- Rate limiting on login endpoint (#205)

### Changed
- Upgraded to Node.js 20 LTS (#210)
- Migrated from Express to Fastify (#215)

### Deprecated
- `getUserById()` - Use `getUser({ id })` instead

### Fixed
- Session timeout not respecting user timezone (#199)
- Memory leak in WebSocket handler (#203)

### Security
- Updated dependencies to patch CVE-2026-1234

## [2.0.0] - 2025-12-01

### Breaking Changes
- Removed support for Node.js 16
- Changed authentication response format
- Renamed `userId` to `id` in User model

[Unreleased]: https://github.com/org/repo/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/org/repo/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/org/repo/releases/tag/v2.0.0
```

### 5.2 Changelog Categories

| Category | Description |
|----------|-------------|
| Added | New features |
| Changed | Changes in existing functionality |
| Deprecated | Features to be removed in future |
| Removed | Removed features |
| Fixed | Bug fixes |
| Security | Security patches |

---

## 6. Documentation Automation

### 6.1 Generated Documentation

```typescript
// TypeDoc configuration (typedoc.json)
{
  "entryPoints": ["src/index.ts"],
  "out": "docs/api",
  "plugin": ["typedoc-plugin-markdown"],
  "excludePrivate": true,
  "excludeInternal": true,
  "readme": "none",
  "githubPages": false
}
```

### 6.2 Documentation CI Checks

```yaml
# .github/workflows/docs.yml
name: Documentation

on:
  pull_request:
    paths:
      - 'src/**'
      - 'docs/**'

jobs:
  check-docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Check for missing docs
        run: |
          # Ensure all public exports have documentation
          npm run docs:check
          
      - name: Validate OpenAPI spec
        run: |
          npx @redocly/cli lint openapi.yaml
          
      - name: Check broken links
        run: |
          npx markdown-link-check docs/**/*.md
```

---

## 7. Documentation Review Checklist

Before merging documentation:

- [ ] All public APIs are documented
- [ ] Examples are tested and working
- [ ] Links are valid (no 404s)
- [ ] Follows established formatting
- [ ] Spelling and grammar checked
- [ ] Version numbers updated if applicable
- [ ] Changelog updated for user-facing changes
- [ ] API changes reflected in OpenAPI spec
