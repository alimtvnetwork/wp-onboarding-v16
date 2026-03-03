# Memory: architecture/php/global-type-syntax-standard
Updated: 2026-03-03

PHP code uses global types and interfaces (e.g., Throwable, PDO, PDOException, Exception, WP_Error, WP_REST_Response, WP_REST_Request, wpdb, ZipArchive) without a leading backslash, imported via `use` statements at the top of each namespaced file. For non-namespaced files (main plugin bootstrap, Autoloader), simply use the unqualified name (no `use` needed since they're already in global scope). Full codebase sweep completed 2026-03-03 — all PHP files across `riseup-asia-uploader`, `qupload`, and `plugins-onboard` are now compliant. Applies to catch blocks, parameter hints, return types, property types, PHPDoc annotations, and `new` instantiations.
