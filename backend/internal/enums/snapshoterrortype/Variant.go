package snapshoterrortype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents snapshot operation error codes.
type Variant byte

const (
	Invalid             Variant = iota
	LockExists
	NotFound
	Corrupt
	TooLarge
	RestoreFailed
	RestoreNoConfirm
	ProviderNotAvail
	IncrementalNoParent
	ExportNotFound
	ExportBuildFailed
	ExportTokenInvalid
)

var variantLabels = [...]string{
	Invalid:             "Invalid",
	LockExists:          "LockExists",
	NotFound:            "NotFound",
	Corrupt:             "Corrupt",
	TooLarge:            "TooLarge",
	RestoreFailed:       "RestoreFailed",
	RestoreNoConfirm:    "RestoreNoConfirm",
	ProviderNotAvail:    "ProviderNotAvail",
	IncrementalNoParent: "IncrementalNoParent",
	ExportNotFound:      "ExportNotFound",
	ExportBuildFailed:   "ExportBuildFailed",
	ExportTokenInvalid:  "ExportTokenInvalid",
}

func (v Variant) String() string {
	if v.IsInvalid() {
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

func (v Variant) IsLockExists() bool          { return v == LockExists }
func (v Variant) IsNotFound() bool            { return v == NotFound }
func (v Variant) IsCorrupt() bool             { return v == Corrupt }
func (v Variant) IsTooLarge() bool            { return v == TooLarge }
func (v Variant) IsRestoreFailed() bool       { return v == RestoreFailed }
func (v Variant) IsRestoreNoConfirm() bool    { return v == RestoreNoConfirm }
func (v Variant) IsProviderNotAvail() bool    { return v == ProviderNotAvail }
func (v Variant) IsIncrementalNoParent() bool { return v == IncrementalNoParent }
func (v Variant) IsExportNotFound() bool      { return v == ExportNotFound }
func (v Variant) IsExportBuildFailed() bool   { return v == ExportBuildFailed }
func (v Variant) IsExportTokenInvalid() bool  { return v == ExportTokenInvalid }
func (v Variant) IsInvalid() bool         { return v == Invalid }
func (v Variant) IsDefined() bool         { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool { return v.IsDefined() && v.IsValid() }

func (v Variant) IsOther(other Variant) bool { return v != other }

func (v Variant) IsAnyOf(others ...Variant) bool {
	for _, o := range others {
		if v == o {
			return true
		}
	}
	return false
}

// IsExport returns true if this is an export-related error.
func (v Variant) IsExport() bool {
	return v == ExportNotFound || v == ExportBuildFailed || v == ExportTokenInvalid
}

// IsRestore returns true if this is a restore-related error.
func (v Variant) IsRestore() bool {
	return v == RestoreFailed || v == RestoreNoConfirm
}

func All() []Variant {
	return []Variant{
		LockExists, NotFound, Corrupt, TooLarge,
		RestoreFailed, RestoreNoConfirm, ProviderNotAvail, IncrementalNoParent,
		ExportNotFound, ExportBuildFailed, ExportTokenInvalid,
	}
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantLabels) {
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
	return Invalid, fmt.Errorf("invalid snapshot error: %q", s)
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
