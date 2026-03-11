// Package auditactiontype defines audit log action enum values.
package auditactiontype

// Variant represents an audit log action.
type Variant string

const (
	Created     Variant = "created"
	Activated   Variant = "activated"
	Deactivated Variant = "deactivated"
	Validated   Variant = "validated"
	Expired     Variant = "expired"
	Revoked     Variant = "revoked"
	Updated     Variant = "updated"
	Deleted     Variant = "deleted"
)

// String returns the string representation.
func (v Variant) String() string { return string(v) }

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

// All returns all valid audit action variants.
func All() []Variant {
	return []Variant{
		Created, Activated, Deactivated, Validated,
		Expired, Revoked, Updated, Deleted,
	}
}

// Parse converts a string to a Variant. Returns empty Variant if not found.
func Parse(s string) Variant {
	for _, v := range All() {
		if string(v) == s {
			return v
		}
	}

	return ""
}
