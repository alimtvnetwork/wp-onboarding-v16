package teststatustype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents E2E test result and run status values.
type Variant byte

const (
	Invalid Variant = iota
	Running
	Passed
	Failed
	Skipped
	Aborted
)

var variantLabels = [...]string{
	Invalid: "Invalid",
	Running: "Running",
	Passed:  "Passed",
	Failed:  "Failed",
	Skipped: "Skipped",
	Aborted: "Aborted",
}

func (v Variant) String() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Label() string { return v.String() }

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsRunning() bool { return v == Running }
func (v Variant) IsPassed() bool  { return v == Passed }
func (v Variant) IsFailed() bool  { return v == Failed }
func (v Variant) IsSkipped() bool { return v == Skipped }
func (v Variant) IsAborted() bool { return v == Aborted }
func (v Variant) IsInvalid() bool         { return v == Invalid }
func (v Variant) IsDefined() bool         { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool { return v.IsDefined() && v.IsValid() }

// IsTerminal returns true if the test has reached a final state.
func (v Variant) IsTerminal() bool { return v == Passed || v == Failed || v == Skipped || v == Aborted }

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
	return []Variant{Running, Passed, Failed, Skipped, Aborted}
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
	return Invalid, fmt.Errorf("invalid test status: %q", s)
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
