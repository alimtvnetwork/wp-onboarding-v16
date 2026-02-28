package apperror

// Is checks if the error matches a specific code.
func Is(err error, code ErrorCode) bool {
	appErr, ok := err.(*AppError)
	if !ok {
		return false
	}

	return appErr.Code == code
}

// Extract returns the *AppError from an error, or nil if not an AppError.
func Extract(err error) *AppError {
	appErr, ok := err.(*AppError)
	if !ok {
		return nil
	}

	return appErr
}

// Recover extracts an *AppError from a panic value (used with recover()).
// Returns nil if the panic value is not an *AppError.
func Recover(panicValue any) *AppError {
	isPanicMissing := panicValue == nil

	if isPanicMissing {
		return nil
	}

	appErr, ok := panicValue.(*AppError)
	if !ok {
		return nil
	}

	return appErr
}
