// Package auditaction defines audit log action enum values.
package auditaction

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

// All returns all valid audit action variants.
func All() []Variant {
	return []Variant{
		Created, Activated, Deactivated, Validated,
		Expired, Revoked, Updated, Deleted,
	}
}
