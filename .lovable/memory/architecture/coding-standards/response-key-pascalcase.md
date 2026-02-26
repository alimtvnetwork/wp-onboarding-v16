# Memory: architecture/coding-standards/response-key-pascalcase
Updated: 2026-02-26

All response array keys, service result arrays, and REST API response envelope keys must use **PascalCase** via the `ResponseKeyType` enum. This applies to both PHP and Go backends, and frontend consumption must match.

## Rules

1. Every response/result array key must be a `ResponseKeyType` case with a PascalCase value (e.g., `'Success'`, `'TotalRows'`, `'SnapshotId'`).
2. No bare camelCase or snake_case string keys in response arrays — use `ResponseKeyType::X->value`.
3. Values that represent reusable categories or types must use typed enums, not raw strings.
4. Log context keys remain **camelCase** (exempt from PascalCase rule).
5. Database column keys use **PascalCase** matching the schema.
6. WordPress persistence keys (`wp_options`, transients) are **exempt**.

## Go Backend

Go struct JSON tags for our own API responses must use PascalCase (e.g., `json:"Success"`, `json:"PluginSlug"`). Tags marked "external key (WordPress REST API)" that parse WordPress core responses are exempt and retain their native casing.

## Migration Status

- **ResponseKeyType enum**: All 130+ values migrated from camelCase to PascalCase (2026-02-26).
- **Go backend JSON tags**: Pending — must be updated to match PascalCase values.
- **Frontend consumers**: Pending — must update key references to PascalCase.

## Cross-References
- **Spec**: `spec/06-php-standards/naming-conventions.md` (Array Key Conventions section)
- **Response array standard**: `spec/06-php-standards/response-array-standard.md`
- **ResponseKeyType inventory**: `spec/06-php-standards/response-key-type-inventory.md`
