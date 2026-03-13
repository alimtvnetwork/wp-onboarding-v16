// Package site — credential CRUD operations for multi-user per site
package site

import (
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/pkg/apperror"
)

// DB returns the underlying database connection.
func (s *Service) DB() *database.DB {
	return s.db
}

// CreateCredential creates a new credential for a site.
func (s *Service) CreateCredential(siteId int64, appName, username, password string) (*database.SiteCredential, *apperror.AppError) {
	encrypted, encErr := encrypt([]byte(password), s.encryptionKey)
	if encErr != nil {
		return nil, apperror.Wrap(encErr, apperror.ErrInternal, "failed to encrypt credential password")
	}

	input := database.SeedCredentialInput{
		SiteId:            siteId,
		AppName:           appName,
		Username:          username,
		PasswordEncrypted: encrypted,
		IsDefault:         false,
	}

	id, createErr := s.db.CreateSiteCredential(input)
	if createErr != nil {
		return nil, createErr
	}

	s.log.Info("Credential created", "credId", id, "siteId", siteId, "appName", appName)

	return s.getCredentialById(id)
}

// UpdateCredential updates an existing credential.
func (s *Service) UpdateCredential(credId int64, appName, username, password string) (*database.SiteCredential, *apperror.AppError) {
	encrypted, encErr := encrypt([]byte(password), s.encryptionKey)
	if encErr != nil {
		return nil, apperror.Wrap(encErr, apperror.ErrInternal, "failed to encrypt credential password")
	}

	updateErr := s.db.UpdateSiteCredential(credId, appName, username, encrypted)
	if updateErr != nil {
		return nil, updateErr
	}

	s.log.Info("Credential updated", "credId", credId)

	return s.getCredentialById(credId)
}

// getCredentialById fetches a single credential by ID.
func (s *Service) getCredentialById(credId int64) (*database.SiteCredential, *apperror.AppError) {
	row := s.db.QueryRow(`
		SELECT Id, SiteId, AppName, Username, PasswordEncrypted, IsDefault, ConnectionStatus, LastTestedAt, CreatedAt, UpdatedAt
		FROM SiteCredentials WHERE Id = ?
	`, credId)

	return database.ScanCredentialRowExported(row)
}
