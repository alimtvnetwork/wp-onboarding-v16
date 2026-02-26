# Memory: coding-standards/multi-line-calls-and-enum-error-codes
Updated: 2026-02-26

## Rule: Multi-Line Function Calls (3+ Arguments)

All function/method calls with 3 or more arguments MUST use one argument per line. This applies across Go, PHP, and TypeScript.

```go
// ❌ FORBIDDEN
respondError(w, wordpress.HttpStatusBadRequest, "E1002", responsemessage.InvalidId.String())

// ✅ REQUIRED
respondError(
    w,
    wordpress.HttpStatusBadRequest,
    apperror.ErrConfigParse,
    responsemessage.InvalidId.String(),
)
```

## Rule: Error Codes Must Use Typed Enum Constants

Never use raw `"Exxxx"` strings in handler code. Always use `apperror.ErrorCode` constants from `Codes.go`.

```go
// ❌ FORBIDDEN
respondError(w, wordpress.HttpStatusBadRequest, "E1002", msg)

// ✅ REQUIRED
respondError(
    w,
    wordpress.HttpStatusBadRequest,
    apperror.ErrConfigParse,
    msg,
)
```

Handler config structs (`handlerIDConfig`, `noArgsConfig`, `twoIDConfig`) use `apperror.ErrorCode` type for the `ErrCode` field.

## Migration Status

- **Response.go**: ✅ `respondError` and `respondErrorWithSession` accept `apperror.ErrorCode`
- **HandlerFactory.go**: ✅ All config structs use `apperror.ErrorCode`
- **PluginHandlers.go**: ✅ Fixed
- **SiteHandlers.go**: ✅ Fixed
- **SiteRemoteHandlers.go**: ✅ Fixed
- **PluginScanHandlers.go**: ✅ Fixed
- **SnapshotHandlers.go**: ❌ Pending — needs E3020-E3040 codes added to Codes.go
- **PublishBackupHandlers.go**: ❌ Pending — needs E8004 added
- **PublishHistoryHandlers.go**: ❌ Pending — needs E8005 added
- **SyncGitHandlers.go**: ❌ Pending
- **E2eHandlers.go**: ❌ Pending
- **ErrorHistoryHandlers.go**: ❌ Pending
- **ErrorSettingsHandlers.go**: ❌ Pending
- **SiteHealthHandlers.go**: ❌ Pending
- **RequestSessionHandlers.go**: ❌ Pending
- **Sessions.go**: ❌ Pending
