package wordpress

// HeaderType represents HTTP header names used in WordPress API requests.
type HeaderType string

const (
	// HeaderAuthorization is the HTTP Authorization header.
	HeaderAuthorization HeaderType = "Authorization"

	// HeaderContentType is the HTTP Content-Type header.
	HeaderContentType HeaderType = "Content-Type"

	// HeaderUserAgent is the HTTP User-Agent header.
	HeaderUserAgent HeaderType = "User-Agent"

	// HeaderSourceMachine is a custom header identifying the source machine (hostname).
	HeaderSourceMachine HeaderType = "X-Riseup-Source-Machine"

	// HeaderUserAgentValue is the default User-Agent value for WordPress API requests.
	HeaderUserAgentValue HeaderType = "WP-Plugin-Publish/1.0"
)

// IsEqual checks type-safe equality against another HeaderType.
func (h HeaderType) IsEqual(other HeaderType) bool {
	return h == other
}

// String returns the raw string value.
func (h HeaderType) String() string {
	return string(h)
}
