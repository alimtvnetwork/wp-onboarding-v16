# Memory: architecture/wordpress-plugin-development-spec
Updated: 2026-02-04

A comprehensive WordPress plugin development specification is maintained in `spec/09-wordpress-plugin-development/`. This 10-document guide covers:

1. **Initialization Patterns** - Safe loading order, lazy initialization, avoiding WordPress function calls in constructors
2. **Logging Standards** - Two-tier logging (file + database), UTC timestamps with milliseconds, file:line context
3. **Database Patterns** - SQLite in wp-content/uploads, schema versioning, migrations, micro-ORM
4. **API Design** - REST endpoint registration, plain strings (no regex), authentication patterns
5. **Constants & Configuration** - Centralized constants.php, no magic strings anywhere
6. **File Structure** - Standard directory layout, include order, class naming conventions
7. **Error Handling** - Try-catch everywhere, graceful degradation, stack trace logging
8. **Compatibility** - PHP 7.4+ requirements, WordPress 5.6+, early loading constraints
9. **Security** - Application passwords, input sanitization, output escaping, rate limiting
10. **Testing** - Manual checklist, log verification, common issues and solutions

## Key Lessons (Critical for AI Training)

- **Never call WordPress functions in constructors** - Use lazy initialization patterns
- **Always use centralized constants** - No magic strings in code
- **Two-tier logging** - File logger (always works) + DB logger (when available)
- **Schema versioning** - Track migration versions in database
- **Plain string endpoints** - Avoid regex patterns in route registration
- **Lazy dependency loading** - Breaks circular dependencies

## Detection File

The system uses `wp-plugin-detected.json` (not dot-prefixed) to mark detected WordPress plugin directories. The Go scanner checks for this file first, and if found, uses cached metadata instead of re-scanning.
