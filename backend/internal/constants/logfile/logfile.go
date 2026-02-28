// Package logfile defines constant file names for application log files.
// These MUST be used instead of magic strings everywhere log files are referenced.
package logfile

// Log file names used across the application.
const (
	AllLog   = "log.txt"
	ErrorLog = "error.log.txt"
	// SessionErrorLog is the per-session error log file name.
	SessionErrorLog = "error.log"
	// Report is the user-submitted error report file name.
	Report = "report.md"
	// Manifest is the bundle manifest file name.
	Manifest = "manifest.json"
)

// ErrorsDir is the subdirectory name under the data directory for log storage.
const ErrorsDir = "errors"
