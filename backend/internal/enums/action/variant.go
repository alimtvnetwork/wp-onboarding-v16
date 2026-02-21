package action

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents a transaction logging action.
type Variant byte

const (
	Invalid        Variant = iota
	Upload
	UploadActive
	Enable
	Disable
	Delete
	FileReplace
	FileDelete
	Sync
	PostCreate
	PostUpdate
	CategoryCreate
	MediaUpload
	AuthFailed
	ExportSelf
	ExportPlugin
)

var variantLabels = [...]string{
	Invalid:        "invalid",
	Upload:         "upload",
	UploadActive:   "upload_active",
	Enable:         "enable",
	Disable:        "disable",
	Delete:         "delete",
	FileReplace:    "file_replace",
	FileDelete:     "file_delete",
	Sync:           "sync",
	PostCreate:     "post_create",
	PostUpdate:     "post_update",
	CategoryCreate: "category_create",
	MediaUpload:    "media_upload",
	AuthFailed:     "auth_failed",
	ExportSelf:     "export_self",
	ExportPlugin:   "export_plugin",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Label() string {
	return v.String()
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsUpload() bool         { return v == Upload }
func (v Variant) IsUploadActive() bool   { return v == UploadActive }
func (v Variant) IsEnable() bool         { return v == Enable }
func (v Variant) IsDisable() bool        { return v == Disable }
func (v Variant) IsDelete() bool         { return v == Delete }
func (v Variant) IsFileReplace() bool    { return v == FileReplace }
func (v Variant) IsFileDelete() bool     { return v == FileDelete }
func (v Variant) IsSync() bool           { return v == Sync }
func (v Variant) IsPostCreate() bool     { return v == PostCreate }
func (v Variant) IsPostUpdate() bool     { return v == PostUpdate }
func (v Variant) IsCategoryCreate() bool { return v == CategoryCreate }
func (v Variant) IsMediaUpload() bool    { return v == MediaUpload }
func (v Variant) IsAuthFailed() bool     { return v == AuthFailed }
func (v Variant) IsExportSelf() bool     { return v == ExportSelf }
func (v Variant) IsExportPlugin() bool   { return v == ExportPlugin }
func (v Variant) IsInvalid() bool        { return v == Invalid }

// IsSnapshot checks if this is a snapshot-related action.
func (v Variant) IsSnapshot() bool {
	return strings.HasPrefix(v.String(), "snapshot_")
}

// IsAgent checks if this is an agent-related action.
func (v Variant) IsAgent() bool {
	return strings.HasPrefix(v.String(), "agent_")
}

// IsUpdate checks if this is an update-related action.
func (v Variant) IsUpdate() bool {
	return strings.HasPrefix(v.String(), "update_")
}

// IsLifecycle checks if this is a plugin lifecycle action (enable/disable/delete).
func (v Variant) IsLifecycle() bool {
	return v == Enable || v == Disable || v == Delete
}

func All() []Variant {
	return []Variant{
		Upload, UploadActive, Enable, Disable, Delete,
		FileReplace, FileDelete, Sync,
		PostCreate, PostUpdate, CategoryCreate, MediaUpload,
		AuthFailed, ExportSelf, ExportPlugin,
	}
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantLabels) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	lower := strings.ToLower(strings.TrimSpace(s))
	for i, str := range variantLabels {
		if str == lower {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid action: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)
	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.String())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	if err := json.Unmarshal(data, &s); err != nil {
		return err
	}
	parsed, err := Parse(s)
	if err != nil {
		return err
	}
	*v = parsed
	return nil
}
