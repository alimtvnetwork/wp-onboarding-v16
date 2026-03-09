package services

import (
	"database/sql"
	"time"

	"riseup-licensing/internal/models"
	"riseup-licensing/pkg/apperror"
)

// ActivationService manages domain activation operations.
type ActivationService struct {
	db *sql.DB
}

// NewActivationService creates a new ActivationService.
func NewActivationService(db *sql.DB) *ActivationService {
	return &ActivationService{db: db}
}

// ActivateInput holds parameters for activating a license on a domain.
type ActivateInput struct {
	LicenseId int64
	Domain    string
	IpAddress string
	UserAgent string
}

// Activate creates or reactivates a domain activation for a license.
func (s *ActivationService) Activate(input ActivateInput) apperror.Result[*models.Activation] {
	existing, findErr := s.findExisting(input.LicenseId, input.Domain)
	if findErr != nil {

		return apperror.Fail[*models.Activation](findErr)
	}

	isReactivation := existing != nil

	if isReactivation {

		return s.reactivate(existing.Id, input)
	}

	return s.createNew(input)
}

// findExisting checks if an activation already exists for a license+domain pair.
func (s *ActivationService) findExisting(licenseId int64, domain string) (*models.Activation, *apperror.AppError) {
	query := `SELECT id, license_id, domain, ip_address, user_agent, activated_at, deactivated_at
		FROM activations WHERE license_id = ? AND domain = ?`

	var a models.Activation

	scanErr := s.db.QueryRow(query, licenseId, domain).Scan(
		&a.Id, &a.LicenseId, &a.Domain, &a.IpAddress, &a.UserAgent, &a.ActivatedAt, &a.DeactivatedAt,
	)

	isNotFound := scanErr == sql.ErrNoRows
	if isNotFound {

		return nil, nil
	}

	if scanErr != nil {

		return nil, apperror.Wrap(scanErr, apperror.ErrDatabaseScan, "find activation")
	}

	return &a, nil
}

// reactivate updates an existing deactivated activation.
func (s *ActivationService) reactivate(
	id int64,
	input ActivateInput,
) apperror.Result[*models.Activation] {
	query := `UPDATE activations SET deactivated_at = NULL, ip_address = ?, user_agent = ?, activated_at = ?
		WHERE id = ?`

	now := time.Now()

	_, execErr := s.db.Exec(query, input.IpAddress, input.UserAgent, now, id)
	if execErr != nil {

		return apperror.FailWrap[*models.Activation](execErr, apperror.ErrDatabaseUpdate, "reactivate")
	}

	existing, findErr := s.findExisting(input.LicenseId, input.Domain)
	if findErr != nil {

		return apperror.Fail[*models.Activation](findErr)
	}

	return apperror.Ok(existing)
}

// createNew inserts a brand-new activation record.
func (s *ActivationService) createNew(input ActivateInput) apperror.Result[*models.Activation] {
	query := `INSERT INTO activations (license_id, domain, ip_address, user_agent) VALUES (?, ?, ?, ?)`

	_, execErr := s.db.Exec(query, input.LicenseId, input.Domain, input.IpAddress, input.UserAgent)
	if execErr != nil {

		return apperror.FailWrap[*models.Activation](execErr, apperror.ErrDatabaseInsert, "insert activation")
	}

	existing, findErr := s.findExisting(input.LicenseId, input.Domain)
	if findErr != nil {

		return apperror.Fail[*models.Activation](findErr)
	}

	return apperror.Ok(existing)
}

// Deactivate marks an activation as deactivated by license ID and domain.
func (s *ActivationService) Deactivate(licenseId int64, domain string) *apperror.AppError {
	query := `UPDATE activations SET deactivated_at = ? WHERE license_id = ? AND domain = ? AND deactivated_at IS NULL`

	_, execErr := s.db.Exec(query, time.Now(), licenseId, domain)
	if execErr != nil {

		return apperror.Wrap(execErr, apperror.ErrDatabaseUpdate, "deactivate")
	}

	return nil
}

// CountActive returns the number of active (non-deactivated) activations for a license.
func (s *ActivationService) CountActive(licenseId int64) apperror.Result[int] {
	query := `SELECT COUNT(*) FROM activations WHERE license_id = ? AND deactivated_at IS NULL`

	var count int

	scanErr := s.db.QueryRow(query, licenseId).Scan(&count)
	if scanErr != nil {

		return apperror.FailWrap[int](scanErr, apperror.ErrDatabaseScan, "count active")
	}

	return apperror.Ok(count)
}

// ListByLicense returns all activations for a license.
func (s *ActivationService) ListByLicense(licenseId int64) apperror.Result[[]models.Activation] {
	query := `SELECT id, license_id, domain, ip_address, user_agent, activated_at, deactivated_at
		FROM activations WHERE license_id = ? ORDER BY activated_at DESC`

	rows, queryErr := s.db.Query(query, licenseId)
	if queryErr != nil {

		return apperror.FailWrap[[]models.Activation](queryErr, apperror.ErrDatabaseQuery, "query activations")
	}
	defer rows.Close()

	return s.scanAll(rows)
}

// scanAll scans multiple activation rows.
func (s *ActivationService) scanAll(rows interface{ Next() bool; Scan(...any) error; Err() error }) apperror.Result[[]models.Activation] {
	var activations []models.Activation

	for rows.Next() {
		var a models.Activation

		scanErr := rows.Scan(
			&a.Id, &a.LicenseId, &a.Domain, &a.IpAddress, &a.UserAgent, &a.ActivatedAt, &a.DeactivatedAt,
		)
		if scanErr != nil {

			return apperror.FailWrap[[]models.Activation](scanErr, apperror.ErrDatabaseScan, "scan activation")
		}

		activations = append(activations, a)
	}

	if rows.Err() != nil {

		return apperror.FailWrap[[]models.Activation](rows.Err(), apperror.ErrDatabaseQuery, "iterate activation rows")
	}

	return apperror.Ok(activations)
}
