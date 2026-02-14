package wordpress

// ContentTypeValue represents HTTP Content-Type values.
type ContentTypeValue string

const (
	// ContentTypeJSON is the JSON content type.
	ContentTypeJSON ContentTypeValue = "application/json"

	// ContentTypeMultipart is the multipart form-data content type.
	ContentTypeMultipart ContentTypeValue = "multipart/form-data"

	// ContentTypeFormURLEncoded is the URL-encoded form content type.
	ContentTypeFormURLEncoded ContentTypeValue = "application/x-www-form-urlencoded"
)

// IsEqual checks type-safe equality against another ContentTypeValue.
func (c ContentTypeValue) IsEqual(other ContentTypeValue) bool {
	return c == other
}

// String returns the raw string value.
func (c ContentTypeValue) String() string {
	return string(c)
}

// IsJSON returns true if the content type is JSON.
func (c ContentTypeValue) IsJSON() bool {
	return c.IsEqual(ContentTypeJSON)
}

// IsMultipart returns true if the content type is multipart form-data.
func (c ContentTypeValue) IsMultipart() bool {
	return c.IsEqual(ContentTypeMultipart)
}
