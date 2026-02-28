package site

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// executeRemotePluginAction runs a remote plugin action with session logging
func (s *Service) executeRemotePluginAction(ctx context.Context, input remoteActionInput) *apperror.AppError {
	startTime := time.Now()

	ref, refErr := s.setupRemoteActionRef(ctx, input)

	if refErr != nil {

		return refErr
	}

	client, connectErr := s.connectForRemoteAction(ref)

	if connectErr != nil {

		return connectErr
	}

	ref.Client = client

	return s.runRemoteAction(ctx, remoteActionExecInput{
		Ref:       ref,
		StartTime: startTime,
		ExecFn:    input.ExecFn,
	})
}

// setupRemoteActionRef resolves the site, creates the ref, and starts session.
func (s *Service) setupRemoteActionRef(ctx context.Context, input remoteActionInput) (*remoteActionRef, *apperror.AppError) {
	site, siteErr := s.resolveRemoteSite(ctx, input.SiteId)

	if siteErr != nil {

		return nil, siteErr
	}

	ref := &remoteActionRef{
		SiteId:     input.SiteId,
		Action:     input.Action,
		PluginSlug: input.PluginSlug,
		Site:       &site,
	}

	ref.SessionId = s.initRemoteActionSession(ref)
	s.broadcastRemoteActionStarted(ref)

	return ref, nil
}

// remoteActionExecInput bundles parameters for runRemoteAction.
type remoteActionExecInput struct {
	Ref       *remoteActionRef
	StartTime time.Time
	ExecFn    func(*wordpress.Client) *apperror.AppError
}

// runRemoteAction executes the action and handles success/failure.
func (s *Service) runRemoteAction(ctx context.Context, input remoteActionExecInput) *apperror.AppError {
	s.logRemoteStageStart(input.Ref)

	appErr := input.ExecFn(input.Ref.Client)
	durationMs := time.Since(input.StartTime).Milliseconds()

	if appErr != nil {
		s.handleRemoteActionError(ctx, input.Ref, appErr, durationMs)

		return appErr
	}

	s.handleRemoteActionSuccess(ctx, input.Ref, durationMs)

	return nil
}

// initRemoteActionSession starts a session for the remote action and returns the session ID.
func (s *Service) initRemoteActionSession(ref *remoteActionRef) string {
	if s.sessionService == nil {

		return ""
	}

	sessionType := resolveRemoteSessionType(ref.Action)

	startInput := session.StartSessionInput{
		Type:       sessionType,
		PluginId:   0,
		SiteId:     ref.SiteId,
		PluginName: ref.PluginSlug,
		SiteName:   ref.Site.Name,
	}
	result := s.sessionService.StartSession(startInput)
	if result.HasError() {
		return ""
	}

	return result.Value()
}

// connectForRemoteAction decrypts credentials and creates a WordPress client.
func (s *Service) connectForRemoteAction(ref *remoteActionRef) (*wordpress.Client, *apperror.AppError) {
	s.logRemoteAction(ref, RemoteActionLogInput{
		Level: "info", Step: "decrypt", Message: "Decrypting site credentials...",
	})

	password, decryptErr := decrypt(ref.Site.PasswordEncrypted, s.encryptionKey)
	if decryptErr != nil {
		return nil, s.handleDecryptFailure(ref, decryptErr)
	}

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level: "info", Step: "connect",
		Message: fmt.Sprintf("Connecting to WordPress site: %s", ref.Site.Url),
	})

	return s.wpClientFactory(ref.Site.Url, ref.Site.Username, string(password), nil), nil
}

// handleDecryptFailure logs and returns the decryption error.
func (s *Service) handleDecryptFailure(ref *remoteActionRef, decryptErr error) *apperror.AppError {
	errMsg := "failed to decrypt password"
	appErr := apperror.Wrap(decryptErr, apperror.ErrInternal, errMsg)

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level: "error", Step: "decrypt", Message: errMsg,
		Details: session.ToJSON(AppErrorDetail{Error: appErr.Error()}),
	})
	s.endRemoteSession(ref.SessionId, "error", errMsg)

	return appErr
}

// logRemoteStageStart logs the stage start for the remote action execution.
func (s *Service) logRemoteStageStart(ref *remoteActionRef) {
	hasSessionService := s.sessionService != nil
	hasSessionId := ref.SessionId != ""

	if hasSessionService && hasSessionId {
		s.sessionService.LogStageStart(ref.SessionId, ref.Action)
	}

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level:   "info",
		Step:    ref.Action,
		Message: fmt.Sprintf("Executing %s action on plugin: %s", ref.Action, ref.PluginSlug),
		Details: session.ToJSON(RemoteActionExecDetails{
			TargetUrl:  ref.Site.Url,
			PluginSlug: ref.PluginSlug,
		}),
	})
}
