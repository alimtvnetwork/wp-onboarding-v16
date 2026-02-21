# Memory: architecture/php/global-type-syntax-standard
Updated: 2026-02-21

PHP code uses global types and interfaces (e.g., Throwable, PDO, PDOException, Exception, WP_Error, WP_REST_Response, WP_REST_Request, wpdb, ZipArchive) without a leading backslash, imported via `use` statements at the top of each file. Full codebase sweep completed 2026-02-21 — all ~70+ PHP files in `riseup-asia-uploader` are now compliant, including both global types (`\Throwable`, `\PDO`) and fully-qualified namespace references (`\RiseupAsia\...`). Applies to catch blocks, parameter hints, return types, property types, PHPDoc annotations, and `new` instantiations. `Autoloader.php` is the sole permitted exception, requiring backslash-qualified references to maintain total self-containment.
