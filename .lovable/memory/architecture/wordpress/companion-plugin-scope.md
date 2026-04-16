# Memory: architecture/wordpress/companion-plugin-scope
Updated: 2026-04-16

The following plugin folders are explicitly out of scope for all code improvements, migrations, audits, and refactoring:

- `wp-plugins/plugins-onboard/` — Legacy companion plugin (ignored-plugins)
- `wp-plugins/category-generator/` — Category Generator plugin

Do not modify, audit, or suggest changes to files under these folders. All coding standards, enum migrations, and naming conventions apply only to the main `riseup-asia-uploader` and `qupload` plugins.
