// Package site provides WordPress site management services
package site

import (
	"context"
	"database/sql"
	"fmt"
	"net/url"
	"strings"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// Config holds service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	EncryptionKey   string
	WPClientFactory func(url, user, pass string) *wordpress.Client
}

// Service provides site management operations
type Service struct {
	db              *database.DB
	log             *logger.Logger
	encryptionKey   []byte
	wpClientFactory func(url, user, pass string) *wordpress.Client
}

// New creates a new site service instance
func New(cfg Config) *Service {
	return &Service{
		db:              cfg.DB,
		log:             cfg.Logger,
		encryptionKey:   []byte(cfg.EncryptionKey),
		wpClientFactory: cfg.WPClientFactory,
	}
}

// List returns all registered sites
func (s *Service) List(ctx context.Context) ([]models.Site, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		ORDER BY Name ASC
	`

	rows, err := s.db.QueryContext(ctx, query)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list sites")
	}
	defer rows.Close()

	var sites []models.Site
	for rows.Next() {
		site, err := s.scanSite(rows)
		if err != nil {
			return nil, err
		}
		sites = append(sites, *site)
	}

	if err := rows.Err(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "error iterating sites")
	}

	if sites == nil {
		sites = []models.Site{} // Return empty slice instead of nil
	}

	return sites, nil
}

// GetByID returns a site by its ID
func (s *Service) GetByID(ctx context.Context, id int64) (*models.Site, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Id = ?
	`

	row := s.db.QueryRowContext(ctx, query, id)
	site, err := s.scanSiteRow(row)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, apperror.New(apperror.ErrNotFound, "site not found").
				WithContext("siteId", id)
		}
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site")
	}

	return site, nil
}

// GetByURL returns a site by its URL
func (s *Service) GetByURL(ctx context.Context, siteURL string) (*models.Site, error) {
	normalizedURL := normalizeURL(siteURL)
	
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Url = ?
	`

	row := s.db.QueryRowContext(ctx, query, normalizedURL)
	site, err := s.scanSiteRow(row)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil // Not found is not an error for this method
		}
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site by URL")
	}

	return site, nil
}

// CreateInput holds the data needed to create a site
type CreateInput struct {
	Name     string
	URL      string
	Username string
	Password string
}

// Create adds a new WordPress site
func (s *Service) Create(ctx context.Context, input CreateInput) (*models.Site, error) {
	// Validate input
	if err := s.validateInput(input); err != nil {
		return nil, err
	}

	// Normalize URL
	normalizedURL := normalizeURL(input.URL)

	// Check if URL already exists
	existing, err := s.GetByURL(ctx, normalizedURL)
	if err != nil {
		return nil, err
	}
	if existing != nil {
		return nil, apperror.New(apperror.ErrValidation, "site with this URL already exists").
			WithContext("url", normalizedURL)
	}

	// Encrypt password
	encryptedPassword, err := encrypt([]byte(input.Password), s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
	}

	// Insert into database
	query := `
		INSERT INTO Sites (Name, Url, Username, PasswordEncrypted, ConnectionStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, 'unknown', datetime('now'), datetime('now'))
	`

	result, err := s.db.ExecContext(ctx, query, input.Name, normalizedURL, input.Username, encryptedPassword)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create site")
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get inserted site ID")
	}

	s.log.Info("Site created", "id", id, "name", input.Name, "url", normalizedURL)

	// Return the created site
	return s.GetByID(ctx, id)
}

// UpdateInput holds the data for updating a site
type UpdateInput struct {
	Name     *string
	URL      *string
	Username *string
	Password *string // Only updated if non-nil
}

// Update modifies an existing site
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) (*models.Site, error) {
	// Get existing site
	existing, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	// Build update query dynamically
	var updates []string
	var args []interface{}

	if input.Name != nil && *input.Name != "" {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}

	if input.URL != nil && *input.URL != "" {
		normalizedURL := normalizeURL(*input.URL)
		
		// Check if new URL conflicts with another site
		if normalizedURL != existing.URL {
			other, err := s.GetByURL(ctx, normalizedURL)
			if err != nil {
				return nil, err
			}
			if other != nil && other.ID != id {
				return nil, apperror.New(apperror.ErrValidation, "another site with this URL already exists")
			}
		}
		
		updates = append(updates, "Url = ?")
		args = append(args, normalizedURL)
	}

	if input.Username != nil && *input.Username != "" {
		updates = append(updates, "Username = ?")
		args = append(args, *input.Username)
	}

	if input.Password != nil && *input.Password != "" {
		encryptedPassword, err := encrypt([]byte(*input.Password), s.encryptionKey)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
		}
		updates = append(updates, "PasswordEncrypted = ?")
		args = append(args, encryptedPassword)
		
		// Reset connection status when password changes
		updates = append(updates, "ConnectionStatus = 'unknown'")
	}

	if len(updates) == 0 {
		return existing, nil // Nothing to update
	}

	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := fmt.Sprintf("UPDATE Sites SET %s WHERE Id = ?", strings.Join(updates, ", "))

	_, err = s.db.ExecContext(ctx, query, args...)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseUpdate, "failed to update site")
	}

	s.log.Info("Site updated", "id", id)

	return s.GetByID(ctx, id)
}

// Delete removes a site and its mappings (cascaded by FK)
func (s *Service) Delete(ctx context.Context, id int64) error {
	// Verify site exists
	_, err := s.GetByID(ctx, id)
	if err != nil {
		return err
	}

	query := "DELETE FROM Sites WHERE Id = ?"
	result, err := s.db.ExecContext(ctx, query, id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete site")
	}

	rowsAffected, _ := result.RowsAffected()
	if rowsAffected == 0 {
		return apperror.New(apperror.ErrNotFound, "site not found")
	}

	s.log.Info("Site deleted", "id", id)

	return nil
}

// TestConnection verifies the WordPress REST API is accessible
func (s *Service) TestConnection(ctx context.Context, id int64) (*ConnectionResult, error) {
	site, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	// Decrypt password
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	// Create WordPress client
	client := s.wpClientFactory(site.URL, site.Username, string(password))

	// Test connection
	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		
		// Update connection status
		s.updateConnectionStatus(ctx, id, "disconnected")
		
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	// Update connection status and last tested time
	s.updateConnectionStatus(ctx, id, "connected")

	s.log.Info("Site connection tested", "id", id, "success", result.Success)

	return result, nil
}

// TestConnectionWithCredentials tests a connection without saving
func (s *Service) TestConnectionWithCredentials(ctx context.Context, siteURL, username, password string) (*ConnectionResult, error) {
	normalizedURL := normalizeURL(siteURL)
	
	client := s.wpClientFactory(normalizedURL, username, password)

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	return result, nil
}

// GetDecryptedPassword returns the decrypted password for a site
func (s *Service) GetDecryptedPassword(ctx context.Context, id int64) (string, error) {
	site, err := s.GetByID(ctx, id)
	if err != nil {
		return "", err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	return string(password), nil
}

// updateConnectionStatus updates the connection status and last tested time
func (s *Service) updateConnectionStatus(ctx context.Context, id int64, status string) {
	query := `
		UPDATE Sites 
		SET ConnectionStatus = ?, LastTestedAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE Id = ?
	`
	_, err := s.db.ExecContext(ctx, query, status, id)
	if err != nil {
		s.log.Error("Failed to update connection status", "id", id, "error", err)
	}
}

// validateInput validates the create input
func (s *Service) validateInput(input CreateInput) error {
	if input.Name == "" {
		return apperror.New(apperror.ErrValidation, "name is required")
	}
	if input.URL == "" {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}
	if input.Username == "" {
		return apperror.New(apperror.ErrValidation, "username is required")
	}
	if input.Password == "" {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}

	// Validate URL format
	if _, err := url.Parse(input.URL); err != nil {
		return apperror.New(apperror.ErrValidation, "invalid URL format")
	}

	return nil
}

// scanSite scans a site from database rows
func (s *Service) scanSite(rows *sql.Rows) (*models.Site, error) {
	var site models.Site
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString

	err := rows.Scan(
		&site.ID,
		&site.Name,
		&site.URL,
		&site.Username,
		&site.PasswordEncrypted,
		&site.ConnectionStatus,
		&lastTestedAt,
		&lastSyncAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan site")
	}

	site.LastTestedAt = parseNullTime(lastTestedAt)
	site.LastSyncAt = parseNullTime(lastSyncAt)
	site.CreatedAt = parseTime(createdAt.String)
	site.UpdatedAt = parseTime(updatedAt.String)

	return &site, nil
}

// scanSiteRow scans a site from a single row
func (s *Service) scanSiteRow(row *sql.Row) (*models.Site, error) {
	var site models.Site
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&site.ID,
		&site.Name,
		&site.URL,
		&site.Username,
		&site.PasswordEncrypted,
		&site.ConnectionStatus,
		&lastTestedAt,
		&lastSyncAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, err
	}

	site.LastTestedAt = parseNullTime(lastTestedAt)
	site.LastSyncAt = parseNullTime(lastSyncAt)
	site.CreatedAt = parseTime(createdAt.String)
	site.UpdatedAt = parseTime(updatedAt.String)

	return &site, nil
}

// ConnectionResult represents the result of a connection test
type ConnectionResult struct {
	Success         bool   `json:"success"`
	WPVersion       string `json:"wpVersion,omitempty"`
	PluginsEndpoint bool   `json:"pluginsEndpoint"`
	Message         string `json:"message,omitempty"`
}

// normalizeURL normalizes a URL for consistent storage
func normalizeURL(rawURL string) string {
	rawURL = strings.TrimSpace(rawURL)
	rawURL = strings.TrimSuffix(rawURL, "/")
	
	// Ensure protocol
	if !strings.HasPrefix(rawURL, "http://") && !strings.HasPrefix(rawURL, "https://") {
		rawURL = "https://" + rawURL
	}
	
	return rawURL
}

// parseNullTime parses a nullable time string
func parseNullTime(ns sql.NullString) *time.Time {
	if !ns.Valid || ns.String == "" {
		return nil
	}
	t := parseTime(ns.String)
	return &t
}

// parseTime parses a time string from SQLite
func parseTime(s string) time.Time {
	if s == "" {
		return time.Time{}
	}
	t, err := time.Parse("2006-01-02 15:04:05", s)
	if err != nil {
		return time.Time{}
	}
	return t
}
