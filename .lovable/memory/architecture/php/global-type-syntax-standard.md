# Memory: architecture/php/global-type-syntax-standard
Updated: 2026-02-17

PHP code uses global types and interfaces (e.g., Throwable, PDO, PDOException, Exception, WP_Error, WP_REST_Response, WP_REST_Request, wpdb, ZipArchive) without a leading backslash, imported via `use` statements at the top of each file. This standard is fully enforced across all ~70 PHP files in `riseup-asia-uploader` as of 2026-02-17. Applies to catch blocks, parameter hints, return types, property types, and `new` instantiations.
