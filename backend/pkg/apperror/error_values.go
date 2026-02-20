package apperror

// WithValue adds a single key-value pair to the error context.
func (e *AppError) WithValue(key, value string) *AppError {
	if e.Values == nil {
		e.Values = make(map[string]string)
	}

	e.Values[key] = value

	return e
}

// WithValues merges multiple key-value pairs into the error context.
func (e *AppError) WithValues(values map[string]string) *AppError {
	if e.Values == nil {
		e.Values = make(map[string]string)
	}

	for k, v := range values {
		e.Values[k] = v
	}

	return e
}
