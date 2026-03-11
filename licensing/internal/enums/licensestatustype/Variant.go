// Package licensestatustype defines license status enum values.
package licensestatustype

// Variant represents a license status.
type Variant string

const (
	Active    Variant = "active"
	Expired   Variant = "expired"
	Suspended Variant = "suspended"
	Revoked   Variant = "revoked"
)

// String returns the string representation.
func (v Variant) String() string { return string(v) }

// IsActive returns true if the license is active.
func (v Variant) IsActive() bool { return v == Active }

// IsExpired returns true if the license has expired.
func (v Variant) IsExpired() bool { return v == Expired }

// IsSuspended returns true if the license is suspended.
func (v Variant) IsSuspended() bool { return v == Suspended }

// IsRevoked returns true if the license is revoked.
func (v Variant) IsRevoked() bool { return v == Revoked }

// IsUsable returns true if the license can be used (only Active).
func (v Variant) IsUsable() bool { return v == Active }

// IsDefined returns true if the variant is a known value.
func (v Variant) IsDefined() bool {
	for _, valid := range All() {
		if v == valid {
			return true
		}
	}

	return false
}

// IsDefinedAndValid returns true if the variant is both defined and non-empty.
func (v Variant) IsDefinedAndValid() bool {
	isNonEmpty := v != ""

	return isNonEmpty && v.IsDefined()
}

// All returns all valid status variants.
func All() []Variant {
	return []Variant{Active, Expired, Suspended, Revoked}
}

// Parse converts a string to a Variant. Returns Active as zero-value fallback.
func Parse(s string) Variant {
	for _, v := range All() {
		if string(v) == s {
			return v
		}
	}

	return Active
}
