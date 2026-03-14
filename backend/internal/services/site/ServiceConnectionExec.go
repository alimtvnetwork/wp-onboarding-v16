// Package site — connection test execution and broadcast helpers
package site

import (
	"context"
	"fmt"

	connectionstatus "wp-plugin-publish/internal/enums/connectionstatustype"
	connectionstep "wp-plugin-publish/internal/enums/connectionsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// decryptWithProgress decrypts a password with broadcast progress updates.
func (s *Service) decryptWithProgress(siteId int64, encrypted []byte) ([]byte, *apperror.AppError) {
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
	decryptStart := ConnectionProgressInput{
		SiteId:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Running.String(),
		Message: "Decrypting credentials...",
	}
	s.broadcastProgress(decryptStart)
}

// broadcastDecryptFailure sends decrypt failure progress.
func (s *Service) broadcastDecryptFailure(siteId int64, appErr *apperror.AppError) {
	decryptFail := ConnectionProgressInput{
		SiteId:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Failed.String(),
		Message: "Failed to decrypt credentials",
		Details: toJson(AppErrorDetail{Error: appErr.Error()}),
	}
	s.broadcastProgress(decryptFail)
}

// broadcastDecryptSuccess sends decrypt success progress.
func (s *Service) broadcastDecryptSuccess(siteId int64) {
	decryptSuccess := ConnectionProgressInput{
		SiteId:  siteId,
		Step:    "decrypt",
		Status:  stagestatus.Completed.String(),
		Message: "Credentials decrypted",
	}
	s.broadcastProgress(decryptSuccess)
}

// connTestRef holds shared context for connection test handling.
type connTestRef struct {
	Id   int64
	Site *models.Site
}

// executeConnectionTest runs the connection test and processes the result.
func (s *Service) executeConnectionTest(ctx context.Context, id int64, site *models.Site, client *wordpress.Client) (*ConnectionResult, *apperror.AppError) {
	s.broadcastConnecting(id, site.Url)

	ref := connTestRef{Id: id, Site: site}
	connResult := client.TestConnection()
	if connResult.HasError() {

		return s.handleConnectionFailure(ctx, ref, connResult.AppError()), nil
	}

	return s.handleConnectionSuccess(ctx, ref, connResult.Value()), nil
}

// broadcastConnecting sends a "connecting" progress event.
func (s *Service) broadcastConnecting(id int64, siteUrl string) {
	connectingProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    "connect",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Connecting to %s...", siteUrl),
	}
	s.broadcastProgress(connectingProgress)
}

// handleConnectionFailure processes a failed connection test.
func (s *Service) handleConnectionFailure(ctx context.Context, ref connTestRef, err error) *ConnectionResult {
	result := &ConnectionResult{
		IsSuccess: false,
		Message:   err.Error(),
	}

	s.broadcastApiTestFailure(ref, err)
	s.updateConnectionStatus(ctx, ref.Id, connectionstatus.Disconnected.DBValue())
	s.broadcastCompleteStep(ref.Id, stagestatus.Failed.String(), "Connection test failed")

	return result
}

// broadcastApiTestFailure broadcasts the API test failure step.
func (s *Service) broadcastApiTestFailure(ref connTestRef, err error) {
	failDetails := toJson(ConnectionFailureDetails{
		Url:      ref.Site.Url,
		Username: ref.Site.Username,
	})
	failProgress := ConnectionProgressInput{
		SiteId:  ref.Id,
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
	s.broadcastApiTestSuccess(ref.Id, connInfo.WPVersion)
	s.updateConnectionStatus(ctx, ref.Id, connectionstatus.Connected.DBValue())
	s.broadcastCompleteStep(ref.Id, stagestatus.Completed.String(), "Connection test completed successfully")
	s.log.Info("Site connection tested", "id", ref.Id, "success", result.IsSuccess)

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
	apiSuccessProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    connectionstep.ApiTest.String(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("WordPress %s detected, REST API accessible", wpVersion),
		Details: successDetails,
	}
	s.broadcastProgress(apiSuccessProgress)
}

// broadcastCompleteStep broadcasts a completion step.
func (s *Service) broadcastCompleteStep(id int64, status, message string) {
	completeProgress := ConnectionProgressInput{
		SiteId:  id,
		Step:    connectionstep.Complete.String(),
		Status:  status,
		Message: message,
	}
	s.broadcastProgress(completeProgress)
}
