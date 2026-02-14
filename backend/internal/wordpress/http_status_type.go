package wordpress

// HttpStatusType represents HTTP status codes used in REST API responses.
type HttpStatusType int

const (
	// HttpStatusOk represents 200 OK.
	HttpStatusOk HttpStatusType = 200

	// HttpStatusCreated represents 201 Created.
	HttpStatusCreated HttpStatusType = 201

	// HttpStatusNoContent represents 204 No Content.
	HttpStatusNoContent HttpStatusType = 204

	// HttpStatusBadRequest represents 400 Bad Request.
	HttpStatusBadRequest HttpStatusType = 400

	// HttpStatusUnauthorized represents 401 Unauthorized.
	HttpStatusUnauthorized HttpStatusType = 401

	// HttpStatusForbidden represents 403 Forbidden.
	HttpStatusForbidden HttpStatusType = 403

	// HttpStatusNotFound represents 404 Not Found.
	HttpStatusNotFound HttpStatusType = 404

	// HttpStatusServerError represents 500 Internal Server Error.
	HttpStatusServerError HttpStatusType = 500
)

// IsEqual checks type-safe equality against another HttpStatusType.
func (h HttpStatusType) IsEqual(other HttpStatusType) bool {
	return h == other
}

// Int returns the raw integer value.
func (h HttpStatusType) Int() int {
	return int(h)
}

// IsSuccess returns true if the status represents a successful response (2xx).
func (h HttpStatusType) IsSuccess() bool {
	return h >= 200 && h < 300
}

// IsClientError returns true if the status represents a client error (4xx).
func (h HttpStatusType) IsClientError() bool {
	return h >= 400 && h < 500
}

// IsServerError returns true if the status represents a server error (5xx).
func (h HttpStatusType) IsServerError() bool {
	return h >= 500
}
