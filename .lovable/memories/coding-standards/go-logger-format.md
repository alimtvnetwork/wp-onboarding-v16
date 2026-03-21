# Memory: coding-standards/go-logger-format

Log line format: `[prefix timestamp] [LEVEL] message key=value pairs [file:line]`

- Timestamp and prefix come first in brackets
- Level (INFO/DEBUG/ERROR/etc.) immediately after timestamp
- Message text follows the level
- Key-value pairs are appended inline after the message
- Caller location `[file:line]` is always last, after all keyvals
- The `funcName` from callerContext is NOT included in the log line
