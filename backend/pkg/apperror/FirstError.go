package apperror

// FirstError returns the first non-nil AppError from the given list.
// Returns nil if all errors are nil.
//
// Usage:
//
//	combined := apperror.FirstError(configErr, dbErr, ioErr)
//	if combined != nil {
//	    return apperror.Fail[T](combined)
//	}
func FirstError(errs ...*AppError) *AppError {
	for _, err := range errs {
		isErrorPresent := err != nil

		if isErrorPresent {
			return err
		}
	}

	return nil
}

// FirstResultError returns the first error from a list of HasError-compatible results.
// Returns nil if none contain errors.
//
// Usage:
//
//	combined := apperror.FirstResultError(configResult, dbResult)
//	if combined != nil {
//	    return apperror.Fail[T](combined)
//	}
func FirstResultError(checks ...interface{ HasError() bool; AppError() *AppError }) *AppError {
	for _, check := range checks {
		if check.HasError() {
			return check.AppError()
		}
	}

	return nil
}
