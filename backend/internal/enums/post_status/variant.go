package poststatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents WordPress post status values.
type Variant byte

const (
	// Invalid is the zero value for an unknown or unset post status.
	Invalid Variant = iota

	// Publish represents a published post.
	Publish

	// Draft represents a draft post.
	Draft

	// Pending represents a pending review post.
	Pending
)

var variantStrings = [...]string{
	Invalid: "invalid",
	Publish: "publish",
	Draft:   "draft",
	Pending: "pending",
}

var variantLabels = [...]string{
	Invalid: "Invalid Post Status",
	Publish: "Published",
	Draft:   "Draft",
	Pending: "Pending Review",
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

// IsPublish returns true if the post status is Publish.
func (v Variant) IsPublish() bool { return v == Publish }

// IsDraft returns true if the post status is Draft.
func (v Variant) IsDraft() bool { return v == Draft }

// IsPending returns true if the post status is Pending.
func (v Variant) IsPending() bool { return v == Pending }

// IsInvalid returns true if the post status is Invalid.
func (v Variant) IsInvalid() bool { return v == Invalid }

// All returns all valid variants (excludes Invalid).
func All() []Variant {
	return []Variant{Publish, Draft, Pending}
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
	return Invalid, fmt.Errorf("invalid post status: %q", s)
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
