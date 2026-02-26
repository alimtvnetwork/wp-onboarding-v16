package syncdirection

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents file change direction in sync operations.
type Variant byte

const (
	Invalid     Variant = iota
	LocalOnly
	RemoteOnly
	LocalNewer
	RemoteNewer
)

var variantLabels = [...]string{
	Invalid:     "Invalid",
	LocalOnly:   "LocalOnly",
	RemoteOnly:  "RemoteOnly",
	LocalNewer:  "LocalNewer",
	RemoteNewer: "RemoteNewer",
}

var variantValues = [...]string{
	Invalid:     "invalid",
	LocalOnly:   "local_only",
	RemoteOnly:  "remote_only",
	LocalNewer:  "local_newer",
	RemoteNewer: "remote_newer",
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

	return Invalid, fmt.Errorf("invalid sync direction: %q", s)
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
