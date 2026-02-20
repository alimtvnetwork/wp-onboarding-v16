package apperror

// Is checks if the error matches a specific code.
func Is(err error, code string) bool {
	appErr, ok := err.(*AppError)
	if !ok {
		return false
	}

	return appErr.Code == code
}
