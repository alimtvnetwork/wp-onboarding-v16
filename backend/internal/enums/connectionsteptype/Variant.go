package connectionsteptype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents connection test step identifiers.
type Variant byte

const (
	Invalid           Variant = iota
	DnsCheck
	RestApiCheck
	AuthCheck
	PluginAccessCheck
	WriteTest
	Complete
	FetchSite
	ApiTest
)

var variantLabels = [...]string{
	Invalid:           "Invalid",
	DnsCheck:          "DnsCheck",
	RestApiCheck:      "RestApiCheck",
	AuthCheck:         "AuthCheck",
	PluginAccessCheck: "PluginAccessCheck",
	WriteTest:         "WriteTest",
	Complete:          "Complete",
	FetchSite:         "FetchSite",
	ApiTest:           "ApiTest",
}

var variantValues = [...]string{
	Invalid:           "invalid",
	DnsCheck:          "dns_check",
	RestApiCheck:      "rest_api_check",
	AuthCheck:         "auth_check",
	PluginAccessCheck: "plugin_access_check",
	WriteTest:         "write_test",
	Complete:          "complete",
	FetchSite:         "fetch_site",
	ApiTest:           "api_test",
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

func All() []Variant {
	return []Variant{DnsCheck, RestApiCheck, AuthCheck, PluginAccessCheck, WriteTest, Complete, FetchSite, ApiTest}
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
		if str == trimmed {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid connection step: %q", s)
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
