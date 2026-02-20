package wordpress

// ResponseKeyType represents standardized response array keys.
// Mirrors PHP: RiseupAsia\Enums\ResponseKeyType (includes/Enums/ResponseKeyType.php).
type ResponseKeyType string

// --- Envelope keys ---

const (
	ResponseKeySuccess ResponseKeyType = "success"
	ResponseKeyError   ResponseKeyType = "error"
	ResponseKeyMessage ResponseKeyType = "message"
	ResponseKeyData    ResponseKeyType = "data"
	ResponseKeyCode    ResponseKeyType = "code"
	ResponseKeyValid   ResponseKeyType = "valid"
	ResponseKeyErrors  ResponseKeyType = "errors"
	ResponseKeyCached  ResponseKeyType = "cached"
	ResponseKeyPhase   ResponseKeyType = "phase"
	ResponseKeyReason  ResponseKeyType = "reason"
)

// --- Domain collection keys ---

const (
	ResponseKeyTotal     ResponseKeyType = "total"
	ResponseKeyAgents    ResponseKeyType = "agents"
	ResponseKeyActions   ResponseKeyType = "actions"
	ResponseKeyLogs      ResponseKeyType = "logs"
	ResponseKeySnapshots ResponseKeyType = "snapshots"
	ResponseKeySql       ResponseKeyType = "sql"
	ResponseKeyParams    ResponseKeyType = "params"
	ResponseKeySets      ResponseKeyType = "sets"
	ResponseKeyPlugins   ResponseKeyType = "plugins"
	ResponseKeyTables    ResponseKeyType = "tables"
)

// --- File and size keys ---

const (
	ResponseKeyRows      ResponseKeyType = "rows"
	ResponseKeyBytes     ResponseKeyType = "bytes"
	ResponseKeySize      ResponseKeyType = "size"
	ResponseKeyFileSize  ResponseKeyType = "file_size"
	ResponseKeyPath      ResponseKeyType = "path"
	ResponseKeyFilename  ResponseKeyType = "filename"
	ResponseKeyChecksum  ResponseKeyType = "checksum"
	ResponseKeyDuration  ResponseKeyType = "duration"
	ResponseKeyCount     ResponseKeyType = "count"
	ResponseKeyFiles     ResponseKeyType = "files"
	ResponseKeyDirectory ResponseKeyType = "directory"
	ResponseKeyScope     ResponseKeyType = "scope"
	ResponseKeyExported  ResponseKeyType = "exported"
	ResponseKeyEntry     ResponseKeyType = "entry"
	ResponseKeyComputed  ResponseKeyType = "computed"
	ResponseKeyRemoved   ResponseKeyType = "removed"
)

// --- Snapshot-domain keys ---

const (
	ResponseKeySnapshotId      ResponseKeyType = "snapshot_id"
	ResponseKeySequence        ResponseKeyType = "sequence"
	ResponseKeyFolderName      ResponseKeyType = "folder_name"
	ResponseKeyTablesChanged   ResponseKeyType = "tables_changed"
	ResponseKeyTotalRows       ResponseKeyType = "total_rows"
	ResponseKeyTotalNewRows    ResponseKeyType = "total_new_rows"
	ResponseKeyZipSize         ResponseKeyType = "zip_size"
	ResponseKeyBackupId        ResponseKeyType = "backup_id"
	ResponseKeyZipFailed       ResponseKeyType = "zip_failed"
	ResponseKeySkipAudit       ResponseKeyType = "skip_audit"
	ResponseKeyTablesRestored  ResponseKeyType = "tables_restored"
)

// IsEqual checks type-safe equality against another ResponseKeyType.
func (r ResponseKeyType) IsEqual(other ResponseKeyType) bool {
	return r == other
}

// IsOtherThan returns true if this key differs from the given key.
func (r ResponseKeyType) IsOtherThan(other ResponseKeyType) bool {
	return r != other
}

// String returns the raw string value.
func (r ResponseKeyType) String() string {
	return string(r)
}
