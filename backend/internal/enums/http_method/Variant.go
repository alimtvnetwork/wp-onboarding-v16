package httpmethod

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents an HTTP method (GET, POST, PUT, DELETE, PATCH).
type Variant byte

const (
	Invalid Variant = iota
	Get
	Post
	Put
	Delete
	Patch
)

var variantLabels = [...]string{
	Invalid: "Invalid",
	Get:     "Get",
	Post:    "Post",
	Put:     "Put",
	Delete:  "Delete",
	Patch:   "Patch",
}

var variantValues = [...]string{
	Invalid: "INVALID",
	Get:     "GET",
	Post:    "POST",
	Put:     "PUT",
	Delete:  "DELETE",
	Patch:   "PATCH",
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

func (v Variant) IsValid() bool            { return v > Invalid && v < Variant(len(variantLabels)) }
func (v Variant) IsInvalid() bool           { return v == Invalid }
func (v Variant) IsDefined() bool           { return v != Invalid }
func (v Variant) IsUndefined() bool         { return v == Invalid }
func (v Variant) IsDefinedAndValid() bool   { return v.IsDefined() && v.IsValid() }
func (v Variant) IsGet() bool               { return v == Get }
func (v Variant) IsPost() bool              { return v == Post }
func (v Variant) IsPut() bool               { return v == Put }
func (v Variant) IsDelete() bool            { return v == Delete }
func (v Variant) IsPatch() bool             { return v == Patch }
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
	return []Variant{Get, Post, Put, Delete, Patch}
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
	for i, str := range variantValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid HTTP method: %q", s)
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
