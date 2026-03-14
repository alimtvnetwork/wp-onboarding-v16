// Package site — connection testing for WordPress sites
package site

import (
	"context"
	"fmt"

	connectionstep "wp-plugin-publish/internal/enums/connectionsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ConnectionResult represents the result of a connection test
type ConnectionResult struct {
	IsSuccess       bool
	WPVersion       string `json:",omitempty"`
	PluginsEndpoint bool
	Message         string `json:",omitempty"`
}

// TestConnection verifies the WordPress REST API is accessible
func (s *Service) TestConnection(ctx context.Context, id int64) (*ConnectionResult, *apperror.AppError) {
	s.broadcastStartProgress(id)

	prepared, prepErr := s.prepareConnectionTest(ctx, id)
	if prepErr != nil {

		return nil, prepErr
	}

	callback := s.buildProgressCallback(id)
	client := s.wpClientFactory(prepared.Site.Url, prepared.Username, string(prepared.Password), callback)

	s.log.Info("Connection test using credential",
		"siteId", id,
		"username", prepared.Username,
		"source", prepared.CredentialSource,
	)

	return s.executeConnectionTest(ctx, id, prepared.Site, client)
}

// broadcastStartProgress sends the initial connection test progress.
func (s *Service) broadcastStartProgress(id int64) {
	startProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    "start",
		Status:  stagestatus.Running.String(),
		Message: "Starting connection test...",
	}
	s.broadcastProgress(startProgress)
}

// buildProgressCallback creates a progress callback for the WordPress client.
func (s *Service) buildProgressCallback(id int64) func(wordpress.ProgressEvent) {
	return func(msg wordpress.ProgressEvent) {
		connectProgress := ConnectionProgressInput{
			SiteId:  id,
			Step:    "connecting",
			Status:  stagestatus.Running.String(),
			Message: msg.Message,
		}
		s.broadcastProgress(connectProgress)
	}
}

// connectionTestContext holds the site and decrypted password for a connection test.
type connectionTestContext struct {
	Site             *models.Site
	Username         string
	Password         []byte
	CredentialSource string // "SiteCredentials" or "legacy"
}

// prepareConnectionTest loads the site and resolves credentials from the default SiteCredential,
// falling back to the legacy Sites.Username/PasswordEncrypted fields.
func (s *Service) prepareConnectionTest(ctx context.Context, id int64) (*connectionTestContext, *apperror.AppError) {
	site, siteErr := s.loadSiteWithProgress(ctx, id)
	if siteErr != nil {

		return nil, siteErr
	}

	// Try default credential from SiteCredentials table first
	defaultCred, credErr := s.db.GetDefaultCredential(id)
	hasDefaultCredential := credErr == nil && defaultCred != nil

	if hasDefaultCredential {
		s.log.Info("Using default credential from SiteCredentials",
			"siteId", id,
			"credId", defaultCred.Id,
			"appName", defaultCred.AppName,
			"username", defaultCred.Username,
		)

		password, decryptErr := s.decryptWithProgress(id, defaultCred.PasswordEncrypted)
		if decryptErr != nil {

			return nil, decryptErr
		}

		return &connectionTestContext{
			Site:             site,
			Username:         defaultCred.Username,
			Password:         password,
			CredentialSource: "SiteCredentials",
		}, nil
	}

	// Fall back to legacy site-level credentials
	s.log.Warn("No default credential found in SiteCredentials, falling back to legacy site fields",
		"siteId", id,
	)

	password, decryptErr := s.decryptWithProgress(id, site.PasswordEncrypted)
	if decryptErr != nil {

		return nil, decryptErr
	}

	return &connectionTestContext{
		Site:             site,
		Username:         site.Username,
		Password:         password,
		CredentialSource: "legacy",
	}, nil
}

// loadSiteWithProgress loads a site and broadcasts progress updates.
func (s *Service) loadSiteWithProgress(ctx context.Context, id int64) (*models.Site, *apperror.AppError) {
	siteResult := s.GetById(ctx, id)
	if siteResult.HasError() {
		s.broadcastSiteLoadFailure(id, siteResult.AppError())

		return nil, siteResult.AppError()
	}

	site := siteResult.Value()
	s.broadcastSiteLoadSuccess(id, site.Name)

	return &site, nil
}

// broadcastSiteLoadFailure sends a site load failure progress event.
func (s *Service) broadcastSiteLoadFailure(id int64, appErr *apperror.AppError) {
	failProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    connectionstep.FetchSite.String(),
		Status:  stagestatus.Failed.String(),
		Message: "Failed to retrieve site info",
		Details: toJson(AppErrorDetail{Error: appErr.Error()}),
	}
	s.broadcastProgress(failProgress)
}

// broadcastSiteLoadSuccess sends a site load success progress event.
func (s *Service) broadcastSiteLoadSuccess(id int64, siteName string) {
	successProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    connectionstep.FetchSite.String(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("Retrieved site: %s", siteName),
	}
	s.broadcastProgress(successProgress)
}
