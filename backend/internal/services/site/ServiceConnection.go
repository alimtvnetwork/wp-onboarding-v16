// Package site — connection testing for WordPress sites
package site

import (
	"context"
	"fmt"

	connectionstatus "wp-plugin-publish/internal/enums/connectionstatustype"
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
	client := s.wpClientFactory(prepared.Site.Url, prepared.Site.Username, string(prepared.Password), callback)

	return s.executeConnectionTest(ctx, id, prepared.Site, client)
}

// broadcastStartProgress sends the initial connection test progress.
func (s *Service) broadcastStartProgress(id int64) {
	startProgress := ConnectionProgressInput{
		SiteID:  id,
		Step:    "start",
		Status:  stagestatus.Running.String(),
		Message: "Starting connection test...",
	}
	s.broadcastProgress(startProgress)
}

// buildProgressCallback creates a progress callback for the WordPress client.
func (s *Service) buildProgressCallback(id int64) func(wordpress.ProgressEvent) {
	return func(msg wordpress.ProgressEvent) {
		s.broadcastProgress(ConnectionProgressInput{
			SiteID:  id,
			Step:    "connecting",
			Status:  stagestatus.Running.String(),
			Message: msg.Message,
		})
	}
}

// connectionTestContext holds the site and decrypted password for a connection test.
type connectionTestContext struct {
	Site     *models.Site
	Password []byte
}

// prepareConnectionTest loads the site and decrypts credentials.
func (s *Service) prepareConnectionTest(ctx context.Context, id int64) (*connectionTestContext, *apperror.AppError) {
	site, siteErr := s.loadSiteWithProgress(ctx, id)
	if siteErr != nil {

		return nil, siteErr
	}

	password, decryptErr := s.decryptWithProgress(id, site.PasswordEncrypted)
	if decryptErr != nil {

		return nil, decryptErr
	}

	return &connectionTestContext{Site: site, Password: password}, nil
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
		SiteID:  id,
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
		SiteID:  id,
		Step:    connectionstep.FetchSite.String(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("Retrieved site: %s", siteName),
	}
	s.broadcastProgress(successProgress)
}

// decryptWithProgress decrypts a password with broadcast progress updates.
func (s *Service) decryptWithProgress(siteId int64, encrypted string) ([]byte, *apperror.AppError) {
	s.broadcastDecryptStart(siteId)

	password, err := decrypt(encrypted, s.encryptionKey)

	if err != nil {
		appErr := apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
		s.broadcastDecryptFailure(siteId, appErr)

		return nil, appErr
	}

	s.broadcastDecryptSuccess(siteId)

	return password, nil
}

// broadcastDecryptStart sends decrypt start progress.
func (s *Service) broadcastDecryptStart(siteId int64) {
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Running.String(),
		Message: "Decrypting credentials...",
	})
}

// broadcastDecryptFailure sends decrypt failure progress.
func (s *Service) broadcastDecryptFailure(siteId int64, appErr *apperror.AppError) {
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Failed.String(),
		Message: "Failed to decrypt credentials",
		Details: toJson(AppErrorDetail{Error: appErr.Error()}),
	})
}

// broadcastDecryptSuccess sends decrypt success progress.
func (s *Service) broadcastDecryptSuccess(siteId int64) {
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Completed.String(),
		Message: "Credentials decrypted",
	})
}

// connTestRef holds shared context for connection test handling.
type connTestRef struct {
	ID   int64
	Site *models.Site
}

// executeConnectionTest runs the connection test and processes the result.
func (s *Service) executeConnectionTest(ctx context.Context, id int64, site *models.Site, client *wordpress.Client) (*ConnectionResult, *apperror.AppError) {
	s.broadcastConnecting(id, site.Url)

	ref := connTestRef{ID: id, Site: site}
	connResult := client.TestConnection()
	if connResult.HasError() {

		return s.handleConnectionFailure(ctx, ref, connResult.AppError()), nil
	}

	return s.handleConnectionSuccess(ctx, ref, connResult.Value()), nil
}

// broadcastConnecting sends a "connecting" progress event.
func (s *Service) broadcastConnecting(id int64, siteUrl string) {
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  id,
		Step:    "connect",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Connecting to %s...", siteUrl),
	})
}

// handleConnectionFailure processes a failed connection test.
func (s *Service) handleConnectionFailure(ctx context.Context, ref connTestRef, err error) *ConnectionResult {
	result := &ConnectionResult{
		IsSuccess: false,
		Message:   err.Error(),
	}

	s.broadcastApiTestFailure(ref, err)
	s.updateConnectionStatus(ctx, ref.ID, connectionstatus.Disconnected.DBValue())
	s.broadcastCompleteStep(ref.ID, stagestatus.Failed.String(), "Connection test failed")
	return result
}

// broadcastApiTestFailure broadcasts the API test failure step.
func (s *Service) broadcastApiTestFailure(ref connTestRef, err error) {
	failDetails := toJson(ConnectionFailureDetails{
		Url:      ref.Site.Url,
		Username: ref.Site.Username,
	})
	failProgress := ConnectionProgressInput{
		SiteID:  ref.ID,
		Step:    connectionstep.ApiTest.String(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Connection failed: %s", err.Error()),
		Details: failDetails,
	}
	s.broadcastProgress(failProgress)
}

// handleConnectionSuccess processes a successful connection test.
func (s *Service) handleConnectionSuccess(ctx context.Context, ref connTestRef, connInfo *wordpress.ConnectionInfo) *ConnectionResult {
	result := buildSuccessResult(connInfo)
	s.broadcastApiTestSuccess(ref.ID, connInfo.WPVersion)
	s.updateConnectionStatus(ctx, ref.ID, connectionstatus.Connected.DBValue())
	s.broadcastCompleteStep(ref.ID, stagestatus.Completed.String(), "Connection test completed successfully")
	s.log.Info("Site connection tested", "id", ref.ID, "success", result.IsSuccess)
	return result
}

// buildSuccessResult constructs a success ConnectionResult.
func buildSuccessResult(connInfo *wordpress.ConnectionInfo) *ConnectionResult {
	return &ConnectionResult{
		IsSuccess:       true,
		WPVersion:       connInfo.WPVersion,
		PluginsEndpoint: true,
		Message:         "Connection successful",
	}
}

// broadcastApiTestSuccess broadcasts the API test success step.
func (s *Service) broadcastApiTestSuccess(id int64, wpVersion string) {
	successDetails := toJson(ConnectionSuccessDetails{WPVersion: wpVersion})
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  id,
		Step:    connectionstep.ApiTest.String(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("WordPress %s detected, REST API accessible", wpVersion),
		Details: successDetails,
	})
}

// broadcastCompleteStep broadcasts a completion step.
func (s *Service) broadcastCompleteStep(id int64, status, message string) {
	s.broadcastProgress(ConnectionProgressInput{
		SiteID:  id,
		Step:    connectionstep.Complete.String(),
		Status:  status,
		Message: message,
	})
}
