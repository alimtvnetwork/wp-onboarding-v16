# Memory: architecture/backend/request-attribution
Updated: 2026-02-06

## Overview

All HTTP requests from the Go backend to WordPress sites include the `X-Riseup-Source-Machine` header containing the server's hostname. This enables audit trails on remote WordPress sites to track which management server triggered each action.

## Go Backend Implementation

### Header Constant

Located in `backend/internal/wordpress/constants.go`:

```go
// HeaderSourceMachine is a custom header identifying the source machine (hostname).
// This enables audit trails on remote WordPress sites to track which server triggered actions.
HeaderSourceMachine = "X-Riseup-Source-Machine"
```

### Hostname Caching

The hostname is computed once at package initialization to avoid repeated syscalls:

```go
var sourceMachineHostname string

func init() {
    var err error
    sourceMachineHostname, err = os.Hostname()
    if err != nil || sourceMachineHostname == "" {
        sourceMachineHostname = "unknown"
    }
}
```

### Standard Headers Helper

All outgoing requests use `setStandardHeaders()` which includes the source machine:

```go
func (c *Client) setStandardHeaders(req *http.Request, contentType string) {
    auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
    req.Header.Set(HeaderAuthorization, "Basic "+auth)
    req.Header.Set(HeaderContentType, contentType)
    req.Header.Set(HeaderUserAgent, UserAgentValue)
    req.Header.Set(HeaderSourceMachine, sourceMachineHostname)
}
```

## WordPress Plugin Implementation

### Header Capture

Located in `wp-plugins/riseup-asia-uploader/includes/class-logger.php`:

```php
private function get_source_machine() {
    $header_key = 'HTTP_X_RISEUP_SOURCE_MACHINE';
    if (!empty($_SERVER[$header_key])) {
        // Sanitize: allow alphanumeric, dots, hyphens, underscores
        $machine = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_SERVER[$header_key]);
        return !empty($machine) ? $machine : null;
    }
    return null;
}
```

### Database Schema

Migration v4 in `class-database.php` adds the `source_machine` column:

```sql
ALTER TABLE transactions ADD COLUMN source_machine TEXT;
CREATE INDEX IF NOT EXISTS idx_source_machine ON transactions(source_machine);
```

### Transaction Logging

All plugin/post actions automatically capture and store the source machine:

```php
public function log_plugin_action($action, $plugin_slug, ...) {
    $source_machine = $this->get_source_machine();
    $enhanced = array();
    if ($source_machine) {
        $enhanced['source_machine'] = $source_machine;
    }
    return $this->get_db()->log_transaction(..., $enhanced);
}
```

## Query Examples

```sql
-- Find all actions from a specific management server
SELECT * FROM transactions 
WHERE source_machine = 'my-dev-machine'
ORDER BY created_at DESC;

-- Group actions by source machine
SELECT source_machine, COUNT(*) as count 
FROM transactions 
WHERE source_machine IS NOT NULL
GROUP BY source_machine;
```

## Related Files

- `backend/internal/wordpress/constants.go` - Header constant
- `backend/internal/wordpress/client.go` - setStandardHeaders(), hostname caching
- `backend/internal/wordpress/uploader.go` - Uses setStandardHeaders()
- `backend/internal/wordpress/remote_files.go` - Uses setStandardHeaders()
- `wp-plugins/riseup-asia-uploader/includes/class-logger.php` - Header capture
- `wp-plugins/riseup-asia-uploader/includes/class-database.php` - Migration v4
