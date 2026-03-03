# QUpload — File Structure

```
wp-plugins/qupload/
├── qupload.php                          # Entry point (autoloader + hooks only)
├── includes/
│   ├── Autoloader.php                   # PSR-4 autoloader for QUpload\ namespace
│   ├── Core/
│   │   └── Plugin.php                   # Main plugin class (singleton)
│   ├── Enums/
│   │   ├── EndpointType.php             # upload, activate, status
│   │   ├── HookType.php                 # WordPress hook names
│   │   ├── HttpMethodType.php           # GET, POST
│   │   ├── HttpStatusType.php           # 200, 400, 401, 403, 500
│   │   ├── PluginConfigType.php         # Slug, Name, Version, ApiNamespace
│   │   ├── CapabilityType.php           # activate_plugins
│   │   ├── LogLevelType.php             # debug, info, warn, error
│   │   ├── ResponseKeyType.php          # PascalCase response keys
│   │   └── PathLogFileType.php          # Log file names
│   ├── Helpers/
│   │   ├── DateHelper.php               # UTC timestamp formatting
│   │   ├── PathHelper.php               # Path resolution
│   │   └── EnvelopeBuilder.php          # Response envelope builder
│   ├── Logging/
│   │   └── FileLogger.php               # File-based logging with stack traces
│   └── Traits/
│       ├── Auth/
│       │   └── AuthTrait.php            # Authentication + permission checks
│       ├── Route/
│       │   └── RouteRegistrationTrait.php  # REST route registration
│       ├── Upload/
│       │   ├── UploadHandlerTrait.php   # Upload endpoint handler
│       │   └── UploadExtractTrait.php   # ZIP extraction + activation
│       ├── Activate/
│       │   └── ActivateHandlerTrait.php # Activate endpoint handler
│       ├── Deactivate/
│       │   └── DeactivateHandlerTrait.php # Deactivation cleanup handler
│       └── Core/
│           ├── StatusHandlerTrait.php   # Status endpoint handler
│           └── ResponseTrait.php        # Error response helpers
└── README.md
```

## Namespace Map

| Namespace | Directory |
|-----------|-----------|
| `QUpload\Core` | `includes/Core/` |
| `QUpload\Enums` | `includes/Enums/` |
| `QUpload\Helpers` | `includes/Helpers/` |
| `QUpload\Logging` | `includes/Logging/` |
| `QUpload\Traits\Auth` | `includes/Traits/Auth/` |
| `QUpload\Traits\Route` | `includes/Traits/Route/` |
| `QUpload\Traits\Upload` | `includes/Traits/Upload/` |
| `QUpload\Traits\Activate` | `includes/Traits/Activate/` |
| `QUpload\Traits\Deactivate` | `includes/Traits/Deactivate/` |
| `QUpload\Traits\Core` | `includes/Traits/Core/` |
