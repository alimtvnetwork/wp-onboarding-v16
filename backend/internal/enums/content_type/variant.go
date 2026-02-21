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

var variantStrings = [...]string{
	Invalid:        "invalid",
	JSON:           "application/json",
	Multipart:      "multipart/form-data",
	FormURLEncoded: "application/x-www-form-urlencoded",
}

var variantLabels = [...]string{
	Invalid:        "Invalid Content Type",
	JSON:           "JSON",
	Multipart:      "Multipart Form Data",
	FormURLEncoded: "URL Encoded Form",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsJSON() bool           { return v == JSON }
func (v Variant) IsMultipart() bool      { return v == Multipart }
func (v Variant) IsFormURLEncoded() bool  { return v == FormURLEncoded }
func (v Variant) IsInvalid() bool         { return v == Invalid }

func All() []Variant {
	return []Variant{JSON, Multipart, FormURLEncoded}
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	lower := strings.ToLower(strings.TrimSpace(s))
	for i, str := range variantStrings {
		if str == lower {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid content type: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
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
