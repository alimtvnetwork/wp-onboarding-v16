# Minimum Viable Spec Template

> **Updated:** 2026-01-31  
> **Purpose:** Standard template for feature specifications to ensure AI implementation success

---

## Template Structure

Every feature spec MUST include these sections to be considered implementation-ready:

```markdown
# [Feature Name]

> **Status:** Draft | In Progress | Complete  
> **Priority:** Critical | High | Medium | Low  
> **Updated:** YYYY-MM-DD

---

## Purpose

[1-2 sentences: What problem does this solve? Who benefits?]

---

## Core Interfaces

[TypeScript interfaces that define the data structures]

```typescript
interface ExampleEntity {
  id: string;
  name: string;
  createdAt: Date;
  // ... all fields with types
}

interface ExampleService {
  create(data: CreateInput): Promise<ExampleEntity>;
  update(id: string, data: UpdateInput): Promise<ExampleEntity>;
  delete(id: string): Promise<void>;
  // ... all methods with signatures
}
```

---

## API Endpoints

| Method | Path | Request | Response | Auth |
|--------|------|---------|----------|------|
| POST | /api/example | CreateInput | ExampleEntity | Required |
| GET | /api/example/:id | - | ExampleEntity | Required |
| PUT | /api/example/:id | UpdateInput | ExampleEntity | Required |
| DELETE | /api/example/:id | - | void | Required |

---

## Acceptance Criteria

**Done when:**
- [ ] User can [specific action]
- [ ] System validates [specific constraint]
- [ ] Error handling covers [specific cases]
- [ ] Performance: [specific metric, e.g., "< 200ms response time"]

---

## Security

- **Authentication:** [Required/Optional, method]
- **Authorization:** [RLS policies, role checks]
- **Input Sanitization:** [What inputs are validated]
- **Rate Limiting:** [If applicable]

---

## Dependencies

- [Other features this depends on]
- [External services or APIs]
- [Shared packages or utilities]
```

---

## Usage Guidelines

1. **Never skip sections** — Empty sections indicate incomplete thinking
2. **Be specific in acceptance criteria** — "User can login" ❌ → "User can login with email/password and receives JWT token within 2 seconds" ✅
3. **Include error codes** — Reference the error management system (1xxx-13xxx ranges)
4. **Link related specs** — Cross-reference dependencies

---

## Quality Checklist

Before marking a spec as "Complete":

- [ ] All TypeScript interfaces have explicit types (no `any`)
- [ ] API endpoints include request/response shapes
- [ ] Acceptance criteria are testable (can write unit tests from them)
- [ ] Security section addresses auth, authorization, and input validation
- [ ] Dependencies are linked to existing specs

---

## Related Memories

- [Spec Remediation Plan](../project/spec-remediation-plan.md) — Priority tiers
- [Coding Guidelines](../constraints/coding-guidelines.md) — TypeScript standards
- [Error Management](../constraints/error-management.md) — Error code ranges
