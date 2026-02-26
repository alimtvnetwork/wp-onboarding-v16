package publishtype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents the type of a publish operation.
type Variant byte

const (
	Invalid  Variant = iota
	Full
	Selected
)

var variantLabels = [...]string{
	Invalid:  "Invalid",
	Full:     "Full",
	Selected: "Selected",
}

// dbValues maps each variant to its lowercase DB/wire representation.
var dbValues = [...]string{
	Invalid:  "invalid",
	Full:     "full",
	Selected: "selected",
}

func (v Variant) String() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Label() string { return v.String() }

// Value returns the lowercase wire/DB representation (e.g. "full", "selected").
func (v Variant) Value() string {
	if v.IsInvalid() {
		return dbValues[Invalid]
	}
	return dbValues[v]
}

func (v Variant) IsValid() bool            { return v > Invalid && v < Variant(len(variantLabels)) }
func (v Variant) IsInvalid() bool           { return v == Invalid }
func (v Variant) IsDefined() bool           { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool   { return v.IsDefined() && v.IsValid() }
func (v Variant) IsFull() bool              { return v == Full }
func (v Variant) IsSelected() bool          { return v == Selected }
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
	return []Variant{Full, Selected}
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantLabels) {
		return Invalid
	}
	return Variant(i)
}

// Parse accepts both PascalCase labels ("Full") and lowercase DB values ("full").
func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)
	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	for i, str := range dbValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid publish type: %q", s)
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
