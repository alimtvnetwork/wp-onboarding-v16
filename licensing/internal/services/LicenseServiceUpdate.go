package services

import (
	"fmt"

	"riseup-licensing/internal/enums/licensestatus"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/models"
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
) (*models.License, error) {
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
) (*models.License, error) {
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

		return nil, fmt.Errorf("update license: %w", execErr)
	}

	return s.GetById(id)
}

// Delete removes a license by ID.
func (s *LicenseService) Delete(id int64) error {
	_, execErr := s.db.Exec("DELETE FROM licenses WHERE id = ?", id)
	if execErr != nil {

		return fmt.Errorf("delete license: %w", execErr)
	}

	return nil
}

// scanAll scans multiple license rows.
func (s *LicenseService) scanAll(rows interface{ Next() bool; Scan(...any) error; Err() error }) ([]models.License, error) {
	var licenses []models.License

	for rows.Next() {
		l, scanErr := s.scanRow(rows)
		if scanErr != nil {

			return nil, scanErr
		}

		licenses = append(licenses, *l)
	}

	if rows.Err() != nil {

		return nil, fmt.Errorf("iterate license rows: %w", rows.Err())
	}

	return licenses, nil
}

// scanRow scans a single row from an iterator into a License.
func (s *LicenseService) scanRow(row interface{ Scan(...any) error }) (*models.License, error) {
	var l models.License
	var product, ltype, status string

	scanErr := row.Scan(
		&l.Id, &l.Key, &l.Email, &product, &ltype, &status,
		&l.MaxActivations, &l.Notes, &l.CreatedAt, &l.ExpiresAt, &l.UpdatedAt,
	)
	if scanErr != nil {

		return nil, fmt.Errorf("scan license row: %w", scanErr)
	}

	l.Product = producttype.Parse(product)
	l.Type = licensetype.Parse(ltype)
	l.Status = licensestatus.Parse(status)

	return &l, nil
}
