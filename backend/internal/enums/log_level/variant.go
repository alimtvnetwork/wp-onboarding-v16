package loglevel

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents logging severity levels.
type Variant byte

const (
	Invalid Variant = iota
	Debug
	Info
	Warn
	Error
)

var variantLabels = [...]string{
	Invalid: "Invalid",
	Debug:   "Debug",
	Info:    "Info",
	Warn:    "Warn",
	Error:   "Error",
}

func (v Variant) String() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Label() string { return v.String() }

// Lower returns the lowercase representation used in log files and broadcast payloads.
func (v Variant) Lower() string { return strings.ToLower(v.String()) }

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsDebug() bool   { return v == Debug }
func (v Variant) IsInfo() bool    { return v == Info }
func (v Variant) IsWarn() bool    { return v == Warn }
func (v Variant) IsError() bool   { return v == Error }
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
	return []Variant{Debug, Info, Warn, Error}
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
	return Invalid, fmt.Errorf("invalid log level: %q", s)
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
