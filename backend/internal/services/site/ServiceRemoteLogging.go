package site

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
)

// logRemoteAction logs a remote plugin action to session and WebSocket.
func (s *Service) logRemoteAction(ref *remoteActionRef, input RemoteActionLogInput) {
	s.emitRemoteActionToSession(ref, input)
	logCtx := s.resolveRemoteActionLogContext(ref.SiteId, input.Details)
	s.emitRemoteActionToLogger(loggerEmitInput{
		Level:   input.Level,
		Message: input.Message,
		SiteId:  ref.SiteId,
		Action:  ref.Action,
		Step:    input.Step,
		Ctx:     logCtx,
	})
}

// emitRemoteActionToSession sends logs to session service and WebSocket.
func (s *Service) emitRemoteActionToSession(ref *remoteActionRef, input RemoteActionLogInput) {
	s.emitToSessionService(ref, input)
	s.emitToWSHub(ref, input)
}

// emitToSessionService logs the remote action to the session service.
func (s *Service) emitToSessionService(ref *remoteActionRef, input RemoteActionLogInput) {
	isUnavailable := s.sessionService == nil || ref.SessionId == ""

	if isUnavailable {
		return
	}

	s.sessionService.Log(session.LogInput{
		SessionId: ref.SessionId,
		Level:     input.Level,
		Step:      input.Step,
		Message:   input.Message,
		Details:   input.Details,
	})
}

// emitToWSHub broadcasts the remote action log via WebSocket.
func (s *Service) emitToWSHub(ref *remoteActionRef, input RemoteActionLogInput) {
	if s.wsHub == nil {
		return
	}

	s.wsHub.BroadcastRemotePluginLogWithSession(RemotePluginLogInput{
		SiteId:    ref.SiteId,
		Action:    ref.Action,
		SessionId: ref.SessionId,
		Level:     input.Level,
		Step:      input.Step,
		Message:   input.Message,
		Details:   input.Details,
	})
}

// remoteActionResolvedContext holds resolved context for log output.
type remoteActionResolvedContext struct {
	SiteName   string
	SiteUrl    string
	PluginSlug string
}

// resolveRemoteActionLogContext extracts and resolves log context from details JSON.
func (s *Service) resolveRemoteActionLogContext(siteId int64, details json.RawMessage) remoteActionResolvedContext {
	resolved := parseRemoteActionLogDetails(details)

	s.fillMissingSiteContext(siteId, &resolved)

	isSiteNameMissing := resolved.SiteName == ""
	if isSiteNameMissing {
		resolved.SiteName = fmt.Sprintf("site#%d", siteId)
	}

	return resolved
}

// parseRemoteActionLogDetails extracts context from JSON details.
func parseRemoteActionLogDetails(details json.RawMessage) remoteActionResolvedContext {
	var logCtx remoteActionLogContext

	hasDetails := len(details) > 0
	if hasDetails {
		_ = json.Unmarshal(details, &logCtx)
	}

	return remoteActionResolvedContext{
		SiteName:   logCtx.SiteName,
		SiteUrl:    logCtx.SiteUrl,
		PluginSlug: logCtx.PluginSlug,
	}
}

// fillMissingSiteContext loads site info from DB if name or URL is missing.
func (s *Service) fillMissingSiteContext(siteId int64, ctx *remoteActionResolvedContext) {
	hasName := ctx.SiteName != ""
	hasUrl := ctx.SiteUrl != ""
	isFullyResolved := hasName && hasUrl
	isInvalidSiteId := siteId <= 0
	isSkippable := isFullyResolved || isInvalidSiteId

	if isSkippable {

		return
	}

	s.applySiteContextFromDB(siteId, ctx)
}

// applySiteContextFromDB fetches the site and fills missing name/URL fields.
func (s *Service) applySiteContextFromDB(siteId int64, ctx *remoteActionResolvedContext) {
	siteResult := s.GetById(context.Background(), siteId)
	isUnavailable := !siteResult.IsSafe()

	if isUnavailable {
		return
	}

	site := siteResult.Value()
	applySiteFields(ctx, &site)
}

// applySiteFields copies missing name and URL from the site model.
func applySiteFields(ctx *remoteActionResolvedContext, site *models.Site) {
	isSiteNameMissing := ctx.SiteName == ""

	if isSiteNameMissing {
		ctx.SiteName = site.Name
	}

	isSiteUrlMissing := ctx.SiteUrl == ""

	if isSiteUrlMissing {
		ctx.SiteUrl = site.Url
	}
}

// loggerEmitInput bundles parameters for emitRemoteActionToLogger.
type loggerEmitInput struct {
	Level   string
	Message string
	SiteId  int64
	Action  string
	Step    string
	Ctx     remoteActionResolvedContext
}

// emitRemoteActionToLogger writes the log entry at the appropriate level.
func (s *Service) emitRemoteActionToLogger(input loggerEmitInput) {
	logFields := buildRemoteActionLogFields(input)

	isErrorLevel := input.Level == loglevel.Error.String()

	if isErrorLevel {
		s.log.Error(input.Message, logFields...)
	} else {
		s.log.Debug(input.Message, logFields...)
	}
}

// buildRemoteActionLogFields constructs the structured log fields.
func buildRemoteActionLogFields(input loggerEmitInput) []any {
	logFields := []any{"site", input.Ctx.SiteName}

	hasSiteUrl := input.Ctx.SiteUrl != ""
	if hasSiteUrl {
		logFields = append(logFields, "siteUrl", input.Ctx.SiteUrl)
	}

	logFields = append(logFields, "siteId", input.SiteId, "action", input.Action, "step", input.Step)

	hasPluginSlug := input.Ctx.PluginSlug != ""
	if hasPluginSlug {
		logFields = append(logFields, "pluginSlug", input.Ctx.PluginSlug)
	}

	return logFields
}

// endRemoteSession ends the session if service is available
func (s *Service) endRemoteSession(sessionId, status, errorMsg string) {
	hasSessionService := s.sessionService != nil
	hasSessionId := sessionId != ""
	isSessionEndable := hasSessionService && hasSessionId

	if isSessionEndable {
		s.sessionService.EndSession(sessionId, status, errorMsg)
	}
}
