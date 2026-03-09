// Package services provides core business logic for the licensing server.
package services

import (
	"database/sql"
	"time"

	"riseup-licensing/internal/enums/licensestatus"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
	"riseup-licensing/internal/models"
	"riseup-licensing/pkg/apperror"
)

// LicenseService manages license CRUD operations against the database.
type LicenseService struct {
	db *sql.DB
}

// NewLicenseService creates a new LicenseService.
func NewLicenseService(db *sql.DB) *LicenseService {
	return &LicenseService{db: db}
}

// CreateInput holds parameters for creating a new license.
type CreateInput struct {
	Key            string
	Email          string
	Product        producttype.Variant
	Type           licensetype.Variant
	MaxActivations int
	Notes          string
	ExpiresAt      *time.Time
}

// Create inserts a new license into the database.
func (s *LicenseService) Create(input CreateInput) apperror.Result[*models.License] {
	result, execErr := s.db.Exec(
		licenseInsertSql,
		input.Key,
		input.Email,
		input.Product.String(),
		input.Type.String(),
		licensestatus.Active.String(),
		input.MaxActivations,
		input.Notes,
		input.ExpiresAt,
	)
	if execErr != nil {

		return apperror.FailWrap[*models.License](execErr, apperror.ErrDatabaseInsert, "insert license")
	}

	id, idErr := result.LastInsertId()
	if idErr != nil {

		return apperror.FailWrap[*models.License](idErr, apperror.ErrDatabaseQuery, "get inserted id")
	}

	return s.GetById(id)
}

// GetById retrieves a license by its database ID.
func (s *LicenseService) GetById(id int64) apperror.Result[*models.License] {
	return s.scanOne(s.db.QueryRow(licenseSelectByIdSql, id))
}

// GetByKey retrieves a license by its license key string.
func (s *LicenseService) GetByKey(key string) apperror.Result[*models.License] {
	query := `SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
		FROM licenses WHERE key = ?`

	return s.scanOne(s.db.QueryRow(query, key))
}

// List returns all licenses, ordered by creation date descending.
func (s *LicenseService) List() apperror.Result[[]models.License] {
	query := `SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
		FROM licenses ORDER BY created_at DESC`

	rows, queryErr := s.db.Query(query)
	if queryErr != nil {

		return apperror.FailWrap[[]models.License](queryErr, apperror.ErrDatabaseQuery, "query licenses")
	}
	defer rows.Close()

	return s.scanAll(rows)
}

// scanOne scans a single license row.
func (s *LicenseService) scanOne(row *sql.Row) apperror.Result[*models.License] {
	var l models.License
	var product, ltype, status string

	scanErr := row.Scan(
		&l.Id, &l.Key, &l.Email, &product, &ltype, &status,
		&l.MaxActivations, &l.Notes, &l.CreatedAt, &l.ExpiresAt, &l.UpdatedAt,
	)
	if scanErr != nil {

		return apperror.FailWrap[*models.License](scanErr, apperror.ErrDatabaseScan, "scan license")
	}

	l.Product = producttype.Parse(product)
	l.Type = licensetype.Parse(ltype)
	l.Status = licensestatus.Parse(status)

	return apperror.Ok(&l)
}
