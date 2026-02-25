package connectionstatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents site connection status values.
type Variant byte

const (
	Invalid      Variant = iota
	Connected
	Disconnected
)

var variantLabels = [...]string{
	Invalid:      "Invalid",
	Connected:    "Connected",
	Disconnected: "Disconnected",
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

// DBValue returns the lowercase value used in database storage.
func (v Variant) DBValue() string {
	return strings.ToLower(v.String())
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsConnected() bool    { return v == Connected }
func (v Variant) IsDisconnected() bool { return v == Disconnected }
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

func All() []Variant {
	return []Variant{Connected, Disconnected}
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
	return Invalid, fmt.Errorf("invalid connection status: %q", s)
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
