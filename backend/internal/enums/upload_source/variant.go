package uploadsource

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents the source of a plugin upload.
type Variant byte

const (
	Invalid Variant = iota
	Script
	RestAPI
	AdminUI
	WPCLI
)

var variantStrings = [...]string{
	Invalid: "invalid",
	Script:  "upload_script",
	RestAPI: "rest_api",
	AdminUI: "admin_ui",
	WPCLI:   "wp_cli",
}

var variantLabels = [...]string{
	Invalid: "Invalid Upload Source",
	Script:  "Deployment Script",
	RestAPI: "REST API",
	AdminUI: "Admin UI",
	WPCLI:   "WP-CLI",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsScript() bool  { return v == Script }
func (v Variant) IsRestAPI() bool { return v == RestAPI }
func (v Variant) IsAdminUI() bool { return v == AdminUI }
func (v Variant) IsWPCLI() bool   { return v == WPCLI }
func (v Variant) IsInvalid() bool { return v == Invalid }

func All() []Variant {
	return []Variant{Script, RestAPI, AdminUI, WPCLI}
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	lower := strings.ToLower(strings.TrimSpace(s))
	for i, str := range variantStrings {
		if str == lower {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid upload source: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
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
