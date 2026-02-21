package stagestatus

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents pipeline stage lifecycle status values.
type Variant byte

const (
	Invalid   Variant = iota
	Pending
	Started
	Running
	Completed
	Failed
	Skipped
)

var variantLabels = [...]string{
	Invalid:   "Invalid",
	Pending:   "Pending",
	Started:   "Started",
	Running:   "Running",
	Completed: "Completed",
	Failed:    "Failed",
	Skipped:   "Skipped",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) Label() string { return v.String() }

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsPending() bool   { return v == Pending }
func (v Variant) IsStarted() bool   { return v == Started }
func (v Variant) IsRunning() bool   { return v == Running }
func (v Variant) IsCompleted() bool { return v == Completed }
func (v Variant) IsFailed() bool    { return v == Failed }
func (v Variant) IsSkipped() bool   { return v == Skipped }
func (v Variant) IsInvalid() bool   { return v == Invalid }

// IsTerminal returns true if the stage has reached a final state.
func (v Variant) IsTerminal() bool { return v == Completed || v == Failed || v == Skipped }

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
	return []Variant{Pending, Started, Running, Completed, Failed, Skipped}
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
	return Invalid, fmt.Errorf("invalid stage status: %q", s)
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
