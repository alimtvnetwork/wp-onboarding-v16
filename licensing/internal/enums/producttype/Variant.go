// Package producttype defines product identifier enum values.
package producttype

// Variant represents a product identifier.
type Variant string

const (
	RiseupUploader Variant = "riseup-uploader"
)

// String returns the string representation.
func (v Variant) String() string { return string(v) }

// IsRiseupUploader returns true for the Riseup Uploader product.
func (v Variant) IsRiseupUploader() bool { return v == RiseupUploader }

// All returns all valid product type variants.
func All() []Variant {
	return []Variant{RiseupUploader}
}

// Parse converts a string to a Variant. Returns RiseupUploader as zero-value fallback.
func Parse(s string) Variant {
	for _, v := range All() {
		if string(v) == s {
			return v
		}
	}

	return RiseupUploader
}
