package syncstep

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents sync progress step identifiers.
type Variant byte

const (
	Invalid   Variant = iota
	Checking
	Scanning
	Comparing
	Packaging
	Pushing
	Error
	Complete
)

var variantLabels = [...]string{
	Invalid:   "Invalid",
	Checking:  "Checking",
	Scanning:  "Scanning",
	Comparing: "Comparing",
	Packaging: "Packaging",
	Pushing:   "Pushing",
	Error:     "Error",
	Complete:  "Complete",
}

var variantValues = [...]string{
	Invalid:   "invalid",
	Checking:  "checking",
	Scanning:  "scanning",
	Comparing: "comparing",
	Packaging: "packaging",
	Pushing:   "pushing",
	Error:     "error",
	Complete:  "complete",
}

func (v Variant) String() string { return v.Value() }

func (v Variant) Label() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}

	return variantLabels[v]
}

func (v Variant) Value() string {
	if v.IsInvalid() {
		return variantValues[Invalid]
	}

	return variantValues[v]
}

func (v Variant) IsValid() bool           { return v > Invalid && v < Variant(len(variantLabels)) }
func (v Variant) IsInvalid() bool          { return v == Invalid }
func (v Variant) IsDefined() bool          { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool  { return v.IsDefined() && v.IsValid() }

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)

	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	for i, str := range variantValues {
		if str == trimmed {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid sync step: %q", s)
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
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
