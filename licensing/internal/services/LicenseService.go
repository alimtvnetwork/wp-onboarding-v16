// Package services provides core business logic for the licensing server.
package services

import (
	"database/sql"
	"fmt"
	"time"

	"riseup-licensing/internal/enums/licensestatus"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
	"riseup-licensing/internal/models"
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
func (s *LicenseService) Create(input CreateInput) (*models.License, error) {
	query := `INSERT INTO licenses (key, email, product, type, status, max_activations, notes, expires_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)`

	result, execErr := s.db.Exec(
		query,
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

		return nil, fmt.Errorf("insert license: %w", execErr)
	}

	id, idErr := result.LastInsertId()
	if idErr != nil {

		return nil, fmt.Errorf("get inserted id: %w", idErr)
	}

	return s.GetById(id)
}

// GetById retrieves a license by its database ID.
func (s *LicenseService) GetById(id int64) (*models.License, error) {
	query := `SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
		FROM licenses WHERE id = ?`

	return s.scanOne(s.db.QueryRow(query, id))
}

// GetByKey retrieves a license by its license key string.
func (s *LicenseService) GetByKey(key string) (*models.License, error) {
	query := `SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
		FROM licenses WHERE key = ?`

	return s.scanOne(s.db.QueryRow(query, key))
}

// List returns all licenses, ordered by creation date descending.
func (s *LicenseService) List() ([]models.License, error) {
	query := `SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
		FROM licenses ORDER BY created_at DESC`

	rows, queryErr := s.db.Query(query)
	if queryErr != nil {

		return nil, fmt.Errorf("query licenses: %w", queryErr)
	}
	defer rows.Close()

	return s.scanAll(rows)
}

// scanOne scans a single license row.
func (s *LicenseService) scanOne(row *sql.Row) (*models.License, error) {
	var l models.License
	var product, ltype, status string

	scanErr := row.Scan(
		&l.Id, &l.Key, &l.Email, &product, &ltype, &status,
		&l.MaxActivations, &l.Notes, &l.CreatedAt, &l.ExpiresAt, &l.UpdatedAt,
	)
	if scanErr != nil {

		return nil, fmt.Errorf("scan license: %w", scanErr)
	}

	l.Product = producttype.Parse(product)
	l.Type = licensetype.Parse(ltype)
	l.Status = licensestatus.Parse(status)

	return &l, nil
}
