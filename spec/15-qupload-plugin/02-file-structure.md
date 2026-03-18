# QUpload — File Structure

```
wp-plugins/qupload/
├── qupload.php                          # Entry point (autoloader + hooks only)
├── uninstall.php                        # Uninstall handler (removes all plugin data)
├── includes/
│   ├── Autoloader.php                   # PSR-4 autoloader for QUpload\ namespace
│   ├── Core/
│   │   └── Plugin.php                   # Main plugin class (singleton, composes traits)
│   ├── Enums/
│   │   ├── AdminPageType.php            # Admin page slug constants
│   │   ├── AdminTabType.php             # Tab identifiers for error log page
│   │   ├── AjaxActionType.php           # AJAX action identifiers
│   │   ├── CapabilityType.php           # WordPress capability strings
│   │   ├── EndpointType.php             # REST endpoint path fragments
│   │   ├── HookType.php                 # WordPress hook name constants
│   │   ├── HttpMethodType.php           # GET, POST, PUT, DELETE
│   │   ├── HttpStatusType.php           # 200, 400, 401, 403, 404, 500
│   │   ├── LogLevelType.php             # Debug, Info, Warn, Error
│   │   ├── NonceType.php                # Nonce action identifiers
│   │   ├── PathLogFileType.php          # Log file path fragments
│   │   ├── PluginConfigType.php         # Slug, Name, Version, ApiNamespace
│   │   ├── RequestFieldType.php         # HTTP request field name constants
│   │   ├── ResponseKeyType.php          # PascalCase response envelope keys
│   │   └── WpErrorCodeType.php          # WordPress error code identifiers
│   ├── Helpers/
│   │   ├── DateHelper.php               # UTC timestamp formatting
│   │   ├── EnvelopeBuilder.php          # Response envelope builder
│   │   ├── ErrorLogHelper.php           # Static error_log wrapper
│   │   ├── PathHelper.php               # Path resolution
│   │   └── UploadBackupHelper.php       # Plugin directory backup/restore during uploads
│   ├── Logging/
│   │   └── FileLogger.php               # File-based logging with stack traces
│   ├── Admin/
│   │   ├── Admin.php                    # WordPress admin pages and AJAX handlers
│   │   └── Traits/
│   │       ├── AdminMenuTrait.php       # Admin menu registration
│   │       └── AdminErrorAjaxTrait.php  # Error log AJAX handlers
│   └── Traits/
│       ├── Auth/
│       │   └── AuthTrait.php            # Authentication + permission checks
│       ├── Route/
│       │   └── RouteRegistrationTrait.php  # REST route registration
│       ├── Upload/
│       │   ├── UploadHandlerTrait.php      # Upload endpoint handler (entry point)
│       │   ├── UploadExtractTrait.php      # ZIP extraction orchestration
│       │   ├── UploadActivationTrait.php   # Activation, syntax validation, plugin discovery
│       │   └── UploadFileSystemTrait.php   # File system operations for extraction
│       ├── Activate/
│       │   ├── ActivateHandlerTrait.php    # PUT /activate endpoint handler
│       │   └── DeactivateEndpointTrait.php # PUT /deactivate endpoint handler
│       ├── Deactivate/
│       │   └── DeactivateHandlerTrait.php  # Deactivation cleanup handler
│       ├── Log/
│       │   ├── LogStatusTrait.php          # GET /logs/status handler
│       │   ├── LogRotationStatusTrait.php  # GET /logs/rotation-status handler
│       │   ├── LogClearingTrait.php        # DELETE /logs/clear + POST /logs/clear/confirm
│       │   └── LogEmailTrait.php           # POST /logs/email handler
│       ├── Machine/
│       │   └── MachineApprovalTrait.php    # PUT /machines/approve handler
│       └── Core/
│           ├── StatusHandlerTrait.php      # GET /status endpoint handler
│           ├── ResponseTrait.php           # Error response helpers
│           └── PluginInventoryTrait.php    # GET /plugins endpoint handler
└── README.md
```

## Namespace Map

| Namespace | Directory |
|-----------|-----------|
| `QUpload\Core` | `includes/Core/` |
| `QUpload\Enums` | `includes/Enums/` |
| `QUpload\Helpers` | `includes/Helpers/` |
| `QUpload\Logging` | `includes/Logging/` |
| `QUpload\Admin` | `includes/Admin/` |
| `QUpload\Admin\Traits` | `includes/Admin/Traits/` |
| `QUpload\Traits\Auth` | `includes/Traits/Auth/` |
| `QUpload\Traits\Route` | `includes/Traits/Route/` |
| `QUpload\Traits\Upload` | `includes/Traits/Upload/` |
| `QUpload\Traits\Activate` | `includes/Traits/Activate/` |
| `QUpload\Traits\Deactivate` | `includes/Traits/Deactivate/` |
| `QUpload\Traits\Log` | `includes/Traits/Log/` |
| `QUpload\Traits\Machine` | `includes/Traits/Machine/` |
| `QUpload\Traits\Core` | `includes/Traits/Core/` |
