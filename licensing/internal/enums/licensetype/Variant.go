// Package licensetype defines license tier enum values.
package licensetype

// Variant represents a license tier.
type Variant string

const (
	Standard     Variant = "standard"
	Professional Variant = "professional"
	Enterprise   Variant = "enterprise"
)

// String returns the string representation.
func (v Variant) String() string { return string(v) }

// IsStandard returns true for the standard tier.
func (v Variant) IsStandard() bool { return v == Standard }

// IsProfessional returns true for the professional tier.
func (v Variant) IsProfessional() bool { return v == Professional }

// IsEnterprise returns true for the enterprise tier.
func (v Variant) IsEnterprise() bool { return v == Enterprise }

// All returns all valid license type variants.
func All() []Variant {
	return []Variant{Standard, Professional, Enterprise}
}

// Parse converts a string to a Variant. Returns Standard as zero-value fallback.
func Parse(s string) Variant {
	for _, v := range All() {
		if string(v) == s {
			return v
		}
	}

	return Standard
}
