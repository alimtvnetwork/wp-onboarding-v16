package publishsteptype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents a publish pipeline step identifier.
type Variant byte

const (
	Invalid    Variant = iota
	Init
	Backup
	Package
	Packaging
	Connect
	Upload
	Uploading
	Activate
	Activating
	Cleanup
	PreBackup
	Complete
	Rollback
	Started
	Completed
	Failed
	Running
	RemoteBackup
)

var variantLabels = [...]string{
	Invalid:      "Invalid",
	Init:         "Init",
	Backup:       "Backup",
	Package:      "Package",
	Packaging:    "Packaging",
	Connect:      "Connect",
	Upload:       "Upload",
	Uploading:    "Uploading",
	Activate:     "Activate",
	Activating:   "Activating",
	Cleanup:      "Cleanup",
	PreBackup:    "PreBackup",
	Complete:     "Complete",
	Rollback:     "Rollback",
	Started:      "Started",
	Completed:    "Completed",
	Failed:       "Failed",
	Running:      "Running",
	RemoteBackup: "RemoteBackup",
}

var variantValues = [...]string{
	Invalid:      "invalid",
	Init:         "init",
	Backup:       "backup",
	Package:      "package",
	Packaging:    "packaging",
	Connect:      "connect",
	Upload:       "upload",
	Uploading:    "uploading",
	Activate:     "activate",
	Activating:   "activating",
	Cleanup:      "cleanup",
	PreBackup:    "pre-backup",
	Complete:     "complete",
	Rollback:     "rollback",
	Started:      "started",
	Completed:    "completed",
	Failed:       "failed",
	Running:      "running",
	RemoteBackup: "remote-backup",
}

func (v Variant) String() string {
	if v.IsInvalid() {
		return variantValues[Invalid]
	}
	return variantValues[v]
}

func (v Variant) Label() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Value() string { return v.String() }

// stageMap maps action-in-progress variants to their base stage variant value.
// Variants not listed here resolve to their own Value().
var stageMap = map[Variant]Variant{
	Started:    Backup,
	Packaging:  Package,
	Uploading:  Upload,
	Activating: Activate,
}

// Stage returns the base stage value for this step.
// Action-in-progress variants (e.g. Packaging) resolve to their base stage (Package).
func (v Variant) Stage() string {
	base, isFound := stageMap[v]

	if isFound {

		return base.Value()
	}

	return v.Value()
}

func (v Variant) IsValid() bool            { return v > Invalid && v < Variant(len(variantLabels)) }
func (v Variant) IsInvalid() bool           { return v == Invalid }
func (v Variant) IsDefined() bool           { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool   { return v.IsDefined() && v.IsValid() }
func (v Variant) IsOther(other Variant) bool { return v != other }

func (v Variant) IsInit() bool       { return v == Init }
func (v Variant) IsBackup() bool     { return v == Backup }
func (v Variant) IsPackage() bool    { return v == Package }
func (v Variant) IsPackaging() bool  { return v == Packaging }
func (v Variant) IsConnect() bool    { return v == Connect }
func (v Variant) IsUpload() bool     { return v == Upload }
func (v Variant) IsUploading() bool  { return v == Uploading }
func (v Variant) IsActivate() bool   { return v == Activate }
func (v Variant) IsActivating() bool { return v == Activating }
func (v Variant) IsCleanup() bool    { return v == Cleanup }
func (v Variant) IsPreBackup() bool  { return v == PreBackup }
func (v Variant) IsComplete() bool   { return v == Complete }
func (v Variant) IsRollback() bool   { return v == Rollback }
func (v Variant) IsStarted() bool    { return v == Started }
func (v Variant) IsCompleted() bool  { return v == Completed }
func (v Variant) IsFailed() bool     { return v == Failed }
func (v Variant) IsRunning() bool       { return v == Running }
func (v Variant) IsRemoteBackup() bool  { return v == RemoteBackup }

func (v Variant) IsAnyOf(others ...Variant) bool {
	for _, o := range others {
		if v == o {
			return true
		}
	}
	return false
}

func All() []Variant {
	return []Variant{
		Init, Backup, Package, Packaging, Connect,
		Upload, Uploading, Activate, Activating, Cleanup,
		PreBackup, Complete, Rollback, Started, Completed, Failed, Running,
		RemoteBackup,
	}
}

func ByIndex(i int) Variant {
	isOutOfRange := i < 0 || i >= len(variantLabels)

	if isOutOfRange {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)
	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	for i, str := range variantValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid publish step: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)
	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	err := json.Unmarshal(data, &s)

	if err != nil {
		return err
	}
	parsed, err := Parse(s)
	if err != nil {
		return err
	}
	*v = parsed
	return nil
}
