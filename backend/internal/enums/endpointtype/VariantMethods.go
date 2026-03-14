package endpointtype

import (
	"encoding/json"
	"fmt"
	"strings"
)

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

// IsSnapshot checks if this endpoint belongs to the snapshot domain.
func (v Variant) IsSnapshot() bool {
	return strings.HasPrefix(v.Value(), "/snapshots/")
}

// IsPlugin checks if this endpoint belongs to the plugin domain.
func (v Variant) IsPlugin() bool {
	return strings.HasPrefix(v.Value(), "/plugins/")
}

// IsAgent checks if this endpoint belongs to the agent domain.
func (v Variant) IsAgent() bool {
	return strings.HasPrefix(v.Value(), "/agents")
}

// IsUser checks if this endpoint belongs to the user management domain.
func (v Variant) IsUser() bool {
	return strings.HasPrefix(v.Value(), "/users")
}

func All() []Variant {
	all := make([]Variant, 0, len(variantLabels)-1)

	for i := 1; i < len(variantLabels); i++ {
		all = append(all, Variant(i))
	}

	return all
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

	for i, str := range variantValues {
		if str == trimmed {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid endpoint: %q", s)
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
