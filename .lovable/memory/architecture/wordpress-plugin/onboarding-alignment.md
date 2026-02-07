# Memory: architecture/wordpress-plugin/onboarding-alignment
Updated: 2026-02-07

The 'Riseup Asia' plugin follows architectural patterns from the 'WP Onboarding' project to ensure consistency. This includes the use of 'RiseupBooleanHelpers' for logic clarity, 'RiseupInitHelpers' for idempotent system initialization (directory setup with security files, SQLite connection, component startup tracking), and structured dependency loading that provides explicit error tracking for failed component startups via `initComponent()` with timing and error capture.
