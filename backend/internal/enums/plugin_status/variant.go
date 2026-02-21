package pluginstatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents WordPress plugin status values.
type Variant byte

const (
	// Invalid is the zero value for an unknown or unset plugin status.
	Invalid Variant = iota

	// Active indicates the plugin is active.
	Active

	// Inactive indicates the plugin is inactive.
	Inactive
)

var variantStrings = [...]string{
	Invalid:  "invalid",
	Active:   "active",
	Inactive: "inactive",
}

var variantLabels = [...]string{
	Invalid:  "Invalid Plugin Status",
	Active:   "Active",
	Inactive: "Inactive",
}

// String returns the lowercase string representation.
func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

// Label returns a human-readable label.
func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

// IsValid checks if the variant is a valid, non-Invalid value.
func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

// IsActive returns true if the plugin status is Active.
func (v Variant) IsActive() bool { return v == Active }

// IsInactive returns true if the plugin status is Inactive.
func (v Variant) IsInactive() bool { return v == Inactive }

// IsInvalid returns true if the plugin status is Invalid.
func (v Variant) IsInvalid() bool { return v == Invalid }

// All returns all valid variants (excludes Invalid).
func All() []Variant {
	return []Variant{Active, Inactive}
}

// ByIndex returns variant by index. Returns Invalid for invalid indices.
func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

// Parse parses a string to variant. Case-insensitive.
func Parse(s string) (Variant, error) {
	lower := strings.ToLower(strings.TrimSpace(s))
	for i, str := range variantStrings {
		if str == lower {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid plugin status: %q", s)
}

// Values returns all string values for documentation or CLI help.
func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
		result = append(result, s)
	}
	return result
}

// MarshalJSON serializes the enum as its string representation.
func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.String())
}

// UnmarshalJSON deserializes from a JSON string back to the byte-based enum.
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
