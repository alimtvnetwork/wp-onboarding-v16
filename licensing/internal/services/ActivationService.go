package services

import (
	"database/sql"
	"fmt"
	"time"

	"riseup-licensing/internal/models"
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
func (s *ActivationService) Activate(input ActivateInput) (*models.Activation, error) {
	existing, findErr := s.findExisting(input.LicenseId, input.Domain)
	if findErr != nil {

		return nil, findErr
	}

	isReactivation := existing != nil

	if isReactivation {

		return s.reactivate(existing.Id, input)
	}

	return s.createNew(input)
}

// findExisting checks if an activation already exists for a license+domain pair.
func (s *ActivationService) findExisting(licenseId int64, domain string) (*models.Activation, error) {
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

		return nil, fmt.Errorf("find activation: %w", scanErr)
	}

	return &a, nil
}

// reactivate updates an existing deactivated activation.
func (s *ActivationService) reactivate(
	id int64,
	input ActivateInput,
) (*models.Activation, error) {
	query := `UPDATE activations SET deactivated_at = NULL, ip_address = ?, user_agent = ?, activated_at = ?
		WHERE id = ?`

	now := time.Now()

	_, execErr := s.db.Exec(query, input.IpAddress, input.UserAgent, now, id)
	if execErr != nil {

		return nil, fmt.Errorf("reactivate: %w", execErr)
	}

	return s.findExisting(input.LicenseId, input.Domain)
}

// createNew inserts a brand-new activation record.
func (s *ActivationService) createNew(input ActivateInput) (*models.Activation, error) {
	query := `INSERT INTO activations (license_id, domain, ip_address, user_agent) VALUES (?, ?, ?, ?)`

	_, execErr := s.db.Exec(query, input.LicenseId, input.Domain, input.IpAddress, input.UserAgent)
	if execErr != nil {

		return nil, fmt.Errorf("insert activation: %w", execErr)
	}

	return s.findExisting(input.LicenseId, input.Domain)
}

// Deactivate marks an activation as deactivated by license ID and domain.
func (s *ActivationService) Deactivate(licenseId int64, domain string) error {
	query := `UPDATE activations SET deactivated_at = ? WHERE license_id = ? AND domain = ? AND deactivated_at IS NULL`

	_, execErr := s.db.Exec(query, time.Now(), licenseId, domain)
	if execErr != nil {

		return fmt.Errorf("deactivate: %w", execErr)
	}

	return nil
}

// CountActive returns the number of active (non-deactivated) activations for a license.
func (s *ActivationService) CountActive(licenseId int64) (int, error) {
	query := `SELECT COUNT(*) FROM activations WHERE license_id = ? AND deactivated_at IS NULL`

	var count int

	scanErr := s.db.QueryRow(query, licenseId).Scan(&count)
	if scanErr != nil {

		return 0, fmt.Errorf("count active: %w", scanErr)
	}

	return count, nil
}

// ListByLicense returns all activations for a license.
func (s *ActivationService) ListByLicense(licenseId int64) ([]models.Activation, error) {
	query := `SELECT id, license_id, domain, ip_address, user_agent, activated_at, deactivated_at
		FROM activations WHERE license_id = ? ORDER BY activated_at DESC`

	rows, queryErr := s.db.Query(query, licenseId)
	if queryErr != nil {

		return nil, fmt.Errorf("query activations: %w", queryErr)
	}
	defer rows.Close()

	return s.scanAll(rows)
}

// scanAll scans multiple activation rows.
func (s *ActivationService) scanAll(rows interface{ Next() bool; Scan(...any) error; Err() error }) ([]models.Activation, error) {
	var activations []models.Activation

	for rows.Next() {
		var a models.Activation

		scanErr := rows.Scan(
			&a.Id, &a.LicenseId, &a.Domain, &a.IpAddress, &a.UserAgent, &a.ActivatedAt, &a.DeactivatedAt,
		)
		if scanErr != nil {

			return nil, fmt.Errorf("scan activation: %w", scanErr)
		}

		activations = append(activations, a)
	}

	if rows.Err() != nil {

		return nil, fmt.Errorf("iterate activation rows: %w", rows.Err())
	}

	return activations, nil
}
