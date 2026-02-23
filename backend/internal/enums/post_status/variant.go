package poststatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents WordPress post status values.
type Variant byte

const (
	Invalid Variant = iota
	Publish
	Draft
	Pending
)

var variantLabels = [...]string{
	Invalid: "Invalid",
	Publish: "Publish",
	Draft:   "Draft",
	Pending: "Pending",
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

func (v Variant) IsPublish() bool { return v == Publish }
func (v Variant) IsDraft() bool   { return v == Draft }
func (v Variant) IsPending() bool { return v == Pending }
func (v Variant) IsInvalid() bool { return v == Invalid }

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
	return []Variant{Publish, Draft, Pending}
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
	return Invalid, fmt.Errorf("invalid post status: %q", s)
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
