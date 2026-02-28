// Package site — credential-based connection testing (no saved site)
package site

import (
	"fmt"

	"wp-plugin-publish/pkg/apperror"

	connectionstep "wp-plugin-publish/internal/enums/connectionsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/wordpress"
)

// TestConnectionWithCredentials tests a connection without saving
func (s *Service) TestConnectionWithCredentials(siteUrl, username, password string) (*ConnectionResult, *apperror.AppError) {
	normalizedUrl := normalizeUrl(siteUrl)

	s.broadcastCredentialsStart(normalizedUrl, siteUrl)

	callback := s.buildCredentialsProgressCallback()
	client := s.wpClientFactory(normalizedUrl, username, password, callback)

	return s.executeCredentialsTest(client, normalizedUrl, username)
}

// broadcastCredentialsStart sends start and normalize progress for credential tests.
func (s *Service) broadcastCredentialsStart(normalizedUrl, originalUrl string) {
	startProgress := ConnectionProgressInput{
		SiteId:  0,
		Step:    "start",
		Status:  stagestatus.Running.String(),
		Message: "Testing connection with provided credentials...",
	}
	s.broadcastProgress(startProgress)
	normalizeDetails := toJson(UrlNormalizeDetails{
		OriginalUrl:   originalUrl,
		NormalizedUrl: normalizedUrl,
	})
	normalizeProgress := ConnectionProgressInput{
		SiteId:  0,
		Step:    "normalize",
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("Normalized URL: %s", normalizedUrl),
		Details: normalizeDetails,
	}
	s.broadcastProgress(normalizeProgress)
}

// buildCredentialsProgressCallback creates a progress callback for credential tests.
func (s *Service) buildCredentialsProgressCallback() func(wordpress.ProgressEvent) {
	return func(event wordpress.ProgressEvent) {
		credProgress := ConnectionProgressInput{
			SiteId:  0,
			Step:    event.Step,
			Status:  event.Status,
			Message: event.Message,
			Details: event.Details,
		}
		s.broadcastProgress(credProgress)
	}
}

// executeCredentialsTest runs the connection test with provided credentials.
func (s *Service) executeCredentialsTest(client *wordpress.Client, normalizedUrl, username string) (*ConnectionResult, *apperror.AppError) {
	connResult := client.TestConnection()
	if connResult.HasError() {

		return s.buildCredentialsFailure(normalizedUrl, username, connResult.AppError()), nil
	}

	return s.buildCredentialsSuccess(connResult.Value()), nil
}

// buildCredentialsFailure handles a failed credentials test.
func (s *Service) buildCredentialsFailure(normalizedUrl, username string, appErr *apperror.AppError) *ConnectionResult {
	result := &ConnectionResult{
		IsSuccess: false,
		Message:   appErr.Error(),
	}

	failDetails := toJson(ConnectionFailureDetails{
		Url:      normalizedUrl,
		Username: username,
	})
	credFailProgress := ConnectionProgressInput{
		SiteId:  0,
		Step:    connectionstep.ApiTest.String(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Connection failed: %s", appErr.Error()),
		Details: failDetails,
	}
	s.broadcastProgress(credFailProgress)
	s.broadcastCompleteStep(0, stagestatus.Failed.String(), "Connection test failed")
	return result
}

// buildCredentialsSuccess handles a successful credentials test.
func (s *Service) buildCredentialsSuccess(connInfo *wordpress.ConnectionInfo) *ConnectionResult {
	result := buildSuccessResult(connInfo)

	successDetails := toJson(ConnectionSuccessDetails{WPVersion: connInfo.WPVersion})
	credSuccessProgress := ConnectionProgressInput{
		SiteId:  0,
		Step:    connectionstep.ApiTest.String(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("WordPress %s detected", connInfo.WPVersion),
		Details: successDetails,
	}
	s.broadcastProgress(credSuccessProgress)
	s.broadcastCompleteStep(0, stagestatus.Completed.String(), "Connection test completed successfully")
	return result
}
