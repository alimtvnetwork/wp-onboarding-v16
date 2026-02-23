package healthstatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents site health status values.
type Variant byte

const (
	Invalid  Variant = iota
	Healthy
	Degraded
	Down
	Unknown
)

var variantLabels = [...]string{
	Invalid:  "Invalid",
	Healthy:  "Healthy",
	Degraded: "Degraded",
	Down:     "Down",
	Unknown:  "Unknown",
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

// DBValue returns the lowercase value used in database storage and JSON responses.
func (v Variant) DBValue() string {
	return strings.ToLower(v.String())
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsHealthy() bool  { return v == Healthy }
func (v Variant) IsDegraded() bool { return v == Degraded }
func (v Variant) IsDown() bool     { return v == Down }
func (v Variant) IsUnknown() bool  { return v == Unknown }
func (v Variant) IsInvalid() bool  { return v == Invalid }

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
	return []Variant{Healthy, Degraded, Down, Unknown}
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
	return Invalid, fmt.Errorf("invalid health status: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)
	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.DBValue())
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
