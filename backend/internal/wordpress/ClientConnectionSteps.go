// Package wordpress — TestConnection step helpers (Steps 1–3).
// Each step is extracted from TestConnection() to comply with the 15-line function body limit.
package wordpress

import (
	"encoding/json"
	"fmt"
	"net/http"

	connectionstep "wp-plugin-publish/internal/enums/connectionsteptype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// TestConnection runs the full five-step connection test sequence:
// 1. REST API probe   2. Auth check   3. Parse user info   4. Plugin access   5. Write test
func (c *Client) TestConnection() apperror.Result[*ConnectionInfo] {
	result := &ConnectionInfo{
		Url: c.baseUrl,
	}

	appErr := c.runPreAuthSteps(result)
	if appErr != nil {

		return apperror.Fail[*ConnectionInfo](appErr)
	}

	return c.runPostAuthSteps(result)
}

// runPreAuthSteps executes REST API probe and authentication.
func (c *Client) runPreAuthSteps(result *ConnectionInfo) *apperror.AppError {
	appErr := c.probeRestAPI(result)
	if appErr != nil {
		return appErr
	}

	return c.authenticateAndParseUser(result)
}

// runPostAuthSteps executes plugin access check and write permission test.
func (c *Client) runPostAuthSteps(result *ConnectionInfo) apperror.Result[*ConnectionInfo] {
	appErr := c.testPluginAccess(result)
	if appErr != nil {

		return apperror.Fail[*ConnectionInfo](appErr)
	}

	c.testWritePermission(result)

	return apperror.Ok(result)
}

// probeRestAPI checks WordPress REST API availability (Step 1).
func (c *Client) probeRestAPI(result *ConnectionInfo) *apperror.AppError {
	c.reportProbeStart()

	resp, err := c.httpClient.Get(BuildWpProbeUrl(c.baseUrl))
	if err != nil {

		return c.reportProbeFailure(err)
	}
	defer resp.Body.Close()

	appErr := c.validateRestApiStatus(resp, result)
	if appErr != nil {

		return appErr
	}

	c.reportProbeSuccess(result)

	return nil
}

// reportProbeStart sends the DNS/API check start event.
func (c *Client) reportProbeStart() {
	probeStart := ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Running.String(),
		Message: "Checking WordPress REST API availability...",
		Details: toProgress(UrlProgress{Url: c.baseUrl}),
	}
	c.progress(probeStart)
}

// reportProbeFailure sends a probe failure event and returns an error.
func (c *Client) reportProbeFailure(err error) *apperror.AppError {
	failEvent := ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("REST API not accessible: %v", err),
		Details: toProgress(UrlProgress{Url: c.baseUrl}),
	}
	c.progress(failEvent)

	return apperror.Wrap(err, apperror.ErrWPAPIDisabled, "REST API not accessible").WithURL(c.baseUrl)
}

// reportProbeSuccess sends the probe success event.
func (c *Client) reportProbeSuccess(result *ConnectionInfo) {
	successEvent := ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Completed.String(),
		Message: "REST API is available",
		Details: toProgress(SiteNameProgress{Url: c.baseUrl, SiteName: result.SiteName}),
	}
	c.progress(successEvent)
}

// validateRestApiStatus checks the REST API response status and parses site info.
func (c *Client) validateRestApiStatus(resp *http.Response, result *ConnectionInfo) *apperror.AppError {
	isMissing := resp.StatusCode == HttpStatusNotFound.Int()

	if isMissing {
		return c.reportRestApiNotFound()
	}

	var rootInfo wpRootInfo
	decodeErr := json.NewDecoder(resp.Body).Decode(&rootInfo)
	isDecoded := decodeErr == nil

	if isDecoded {
		result.SiteName = rootInfo.Name
		result.SiteDescription = rootInfo.Description
	}

	return nil
}

// reportRestApiNotFound sends a not-found event and returns an error.
func (c *Client) reportRestApiNotFound() *apperror.AppError {
	notFoundEvent := ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: "REST API not found - is permalink structure set?",
		Details: toProgress(UrlProgress{Url: c.baseUrl}),
	}
	c.progress(notFoundEvent)

	return apperror.New(apperror.ErrWPAPIDisabled, "WordPress REST API not found - ensure permalinks are enabled").WithURL(c.baseUrl)
}

// authenticateAndParseUser checks authentication (Step 2) and parses user info (Step 3).
func (c *Client) authenticateAndParseUser(result *ConnectionInfo) *apperror.AppError {
	c.reportAuthStart()

	authResp := c.fetchAuthResponse()
	if authResp.HasError() {

		return c.reportAuthRequestFailed(authResp.AppError())
	}

	resp := authResp.Value()
	authErr := c.checkAuthStatus(resp.StatusCode, resp.Body)
	if authErr != nil {

		return authErr
	}

	c.parseUserInfoFromBytes(resp.Body, result)
	c.reportAuthSuccess(result)

	return nil
}

// reportAuthStart sends the auth check start event.
func (c *Client) reportAuthStart() {
	authStartEvent := ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Authenticating as %s...", c.username),
		Details: toProgress(AuthInitProgress{Url: c.baseUrl, Username: c.username}),
	}
	c.progress(authStartEvent)
}

// fetchAuthResponse sends the authentication API call.
func (c *Client) fetchAuthResponse() apperror.Result[ApiCallResponse] {
	authInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  WPCoreUsersMe,
		Operation: "authenticate user",
	}

	return c.doApiCallWithStatus(authInput)
}

// reportAuthRequestFailed sends an auth request failure event.
func (c *Client) reportAuthRequestFailed(err *apperror.AppError) *apperror.AppError {
	authFailEvent := ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Authentication request failed: %v", err),
		Details: toProgress(UrlProgress{Url: c.baseUrl}),
	}
	c.progress(authFailEvent)

	return apperror.Wrap(err, apperror.ErrWPAuth, "authentication request failed").
		WithURL(c.baseUrl).
		WithUsername(c.username)
}

// reportAuthSuccess sends the auth success event.
func (c *Client) reportAuthSuccess(result *ConnectionInfo) {
	authProgress := UserAuthProgress{
		Url:    c.baseUrl,
		UserId: result.UserId,
		Roles:  result.UserRoles,
	}

	authSuccessEvent := ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("Authenticated as %s (ID: %d)", result.UserDisplayName, result.UserId),
		Details: toProgress(authProgress),
	}
	c.progress(authSuccessEvent)
}

// checkAuthStatus validates the authentication response status code.
func (c *Client) checkAuthStatus(statusCode int, body []byte) *apperror.AppError {
	isUnauthorized := statusCode == HttpStatusUnauthorized.Int()

	if isUnauthorized {

		return c.reportAuthFailure("Invalid username or application password",
			apperror.New(apperror.ErrWPAuth, "authentication failed: invalid username or application password").
				WithURL(c.baseUrl).
				WithUsername(c.username))
	}

	isForbidden := statusCode == HttpStatusForbidden.Int()

	if isForbidden {

		return c.reportAuthFailure("Access forbidden - user lacks permissions",
			apperror.New(apperror.ErrWPAuth, "authentication failed: user lacks required permissions").
				WithURL(c.baseUrl).
				WithStatusCode(statusCode))
	}

	return c.checkUnexpectedAuthStatus(statusCode, body)
}

// checkUnexpectedAuthStatus handles non-standard auth response codes.
func (c *Client) checkUnexpectedAuthStatus(statusCode int, body []byte) *apperror.AppError {
	isOk := statusCode == HttpStatusOk.Int()

	if isOk {

		return nil
	}

	unexpectedEvent := ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Unexpected response: %d", statusCode),
		Details: toProgress(AuthBodyProgress{Url: c.baseUrl, Body: string(body)}),
	}
	c.progress(unexpectedEvent)

	return apperror.New(apperror.ErrWPConnection, "unexpected authentication response").
		WithURL(c.baseUrl).
		WithStatusCode(statusCode).
		WithDetails(string(body))
}

// reportAuthFailure logs an auth failure and returns the error.
func (c *Client) reportAuthFailure(message string, err *apperror.AppError) *apperror.AppError {
	authFailureEvent := ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: message,
		Details: toProgress(UrlProgress{Url: c.baseUrl}),
	}
	c.progress(authFailureEvent)

	return err
}

// parseUserInfoFromBytes decodes the users/me response bytes into the ConnectionInfo result.
func (c *Client) parseUserInfoFromBytes(body []byte, result *ConnectionInfo) {
	var userInfo wpUserInfo
	decodeErr := json.Unmarshal(body, &userInfo)
	isDecoded := decodeErr == nil

	if isDecoded {
		result.UserId = userInfo.Id
		result.UserDisplayName = userInfo.Name
		result.UserRoles = userInfo.Roles
		result.CanManagePlugins = userInfo.Capabilities["activate_plugins"] || userInfo.Capabilities["install_plugins"]
	}
}
