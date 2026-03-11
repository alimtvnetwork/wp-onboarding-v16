package services

import (
	"riseup-licensing/internal/enums/licensestatustype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/models"
	"riseup-licensing/pkg/apperror"
)

// UpdateInput holds parameters for updating an existing license.
type UpdateInput struct {
	Status         *licensestatus.Variant
	Type           *licensetype.Variant
	MaxActivations *int
	Notes          *string
}

// Update modifies an existing license by ID.
func (s *LicenseService) Update(
	id int64,
	input UpdateInput,
) apperror.Result[*models.License] {
	setClauses, args := buildUpdateClauses(input)

	isNothingToUpdate := len(setClauses) == 0
	if isNothingToUpdate {

		return s.GetById(id)
	}

	return s.executeUpdate(id, setClauses, args)
}

// buildUpdateClauses constructs SET clauses and args from UpdateInput.
func buildUpdateClauses(input UpdateInput) ([]string, []any) {
	var setClauses []string
	var args []any

	if input.Status != nil {
		setClauses = append(setClauses, "status = ?")
		args = append(args, input.Status.String())
	}

	if input.Type != nil {
		setClauses = append(setClauses, "type = ?")
		args = append(args, input.Type.String())
	}

	if input.MaxActivations != nil {
		setClauses = append(setClauses, "max_activations = ?")
		args = append(args, *input.MaxActivations)
	}

	if input.Notes != nil {
		setClauses = append(setClauses, "notes = ?")
		args = append(args, *input.Notes)
	}

	return setClauses, args
}

// executeUpdate runs the UPDATE query and returns the refreshed license.
func (s *LicenseService) executeUpdate(
	id int64,
	setClauses []string,
	args []any,
) apperror.Result[*models.License] {
	query := "UPDATE licenses SET "

	for i, clause := range setClauses {
		isFirstClause := i == 0

		if isFirstClause {
			query += clause
		} else {
			query += ", " + clause
		}
	}

	query += ", updated_at = CURRENT_TIMESTAMP WHERE id = ?"
	args = append(args, id)

	_, execErr := s.db.Exec(query, args...)
	if execErr != nil {

		return apperror.FailWrap[*models.License](execErr, apperror.ErrDatabaseUpdate, "update license")
	}

	return s.GetById(id)
}

// Delete removes a license by ID.
func (s *LicenseService) Delete(id int64) *apperror.AppError {
	_, execErr := s.db.Exec(licenseDeleteSql, id)
	if execErr != nil {

		return apperror.Wrap(execErr, apperror.ErrDatabaseDelete, "delete license")
	}

	return nil
}

