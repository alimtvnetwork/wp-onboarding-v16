package contenttype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents HTTP Content-Type values.
type Variant byte

const (
	Invalid        Variant = iota
	JSON
	Multipart
	FormURLEncoded
)

var variantLabels = [...]string{
	Invalid:        "Invalid",
	JSON:           "JSON",
	Multipart:      "Multipart",
	FormURLEncoded: "FormURLEncoded",
}

var variantValues = [...]string{
	Invalid:        "invalid",
	JSON:           "application/json",
	Multipart:      "multipart/form-data",
	FormURLEncoded: "application/x-www-form-urlencoded",
}

func (v Variant) String() string {
	return v.Value()
}

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

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsJSON() bool           { return v == JSON }
func (v Variant) IsMultipart() bool      { return v == Multipart }
func (v Variant) IsFormURLEncoded() bool  { return v == FormURLEncoded }
func (v Variant) IsInvalid() bool        { return v == Invalid }
func (v Variant) IsDefined() bool        { return v != Invalid }
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

func All() []Variant {
	return []Variant{JSON, Multipart, FormURLEncoded}
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

	lower := strings.ToLower(trimmed)

	for i, str := range variantValues {
		if str == lower {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid content type: %q", s)
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
