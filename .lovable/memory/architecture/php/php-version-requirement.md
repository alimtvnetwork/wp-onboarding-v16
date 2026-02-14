# Memory: architecture/php/php-version-requirement
Updated: 2026-02-14

All PHP code across both WordPress plugins (riseup-asia-uploader and plugins-onboard) targets PHP 8.2+ as the minimum version. PHP 7.x compatibility is not required and must not influence design decisions. This enables unrestricted use of:
- Native backed enums (PHP 8.1+)
- Readonly properties (PHP 8.1+)
- Intersection types (PHP 8.1+)
- Fibers (PHP 8.1+)
- Disjunctive Normal Form types (PHP 8.2+)
- Readonly classes (PHP 8.2+)
- True/false/null standalone types (PHP 8.2+)
- Constants in traits (PHP 8.2+)

No backward-compatibility shims, polyfills, or PHP 7.x fallback code should be written.
