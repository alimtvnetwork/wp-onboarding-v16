// Package wordpress — TestConnection step helpers (Steps 1–3).
// Each step is extracted from TestConnection() to comply with the 15-line function body limit.
package wordpress

import (
	"encoding/json"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/enums/connection_step"
	"wp-plugin-publish/internal/enums/http_method"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/pkg/apperror"
)

// wpRootInfo is the typed struct for parsing the WordPress REST API root response.
type wpRootInfo struct {
	Name        string `json:"name"`        // external key (WordPress REST API)
	Description string `json:"description"` // external key
}

// wpUserInfo is the typed struct for parsing the users/me response.
type wpUserInfo struct {
	Id           int             `json:"id"`           // external key (WordPress REST API)
	Name         string          `json:"name"`         // external key
	Slug         string          `json:"slug"`         // external key
	Roles        []string        `json:"roles"`        // external key
	Capabilities map[string]bool `json:"capabilities"` // external key
}

// wpCreatedPost is the typed struct for parsing a created post response.
type wpCreatedPost struct {
	Id int `json:"id"` // external key (WordPress REST API)
}

// wpTestPost is the typed struct for creating a test draft post.
type wpTestPost struct {
	Title   string `json:"title"`   // external key (WordPress REST API)
	Content string `json:"content"` // external key
	Status  string `json:"status"`  // external key
}

// TestConnection runs the full five-step connection test sequence:
// 1. REST API probe   2. Auth check   3. Parse user info   4. Plugin access   5. Write test
func (c *Client) TestConnection() (*ConnectionInfo, error) {
	result := &ConnectionInfo{
		URL: c.baseURL,
	}

	if err := c.probeRestAPI(result); err != nil {
		return result, err
	}

	if err := c.authenticateAndParseUser(result); err != nil {
		return result, err
	}

	if err := c.testPluginAccess(result); err != nil {
		return result, err
	}

	c.testWritePermission(result)

	return result, nil
}

// probeRestAPI checks WordPress REST API availability (Step 1).
func (c *Client) probeRestAPI(result *ConnectionInfo) error {
	c.reportProbeStart()

	resp, err := c.httpClient.Get(fmt.Sprintf("%s/wp-json/", c.baseURL))
	if err != nil {
		return c.reportProbeFailure(err)
	}
	defer resp.Body.Close()

	if err := c.validateRestApiStatus(resp, result); err != nil {
		return err
	}

	c.reportProbeSuccess(result)

	return nil
}

// reportProbeStart sends the DNS/API check start event.
func (c *Client) reportProbeStart() {
	c.progress(ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Running.String(),
		Message: "Checking WordPress REST API availability...",
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// reportProbeFailure sends a probe failure event and returns an error.
func (c *Client) reportProbeFailure(err error) error {
	c.progress(ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("REST API not accessible: %v", err),
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})

	return apperror.Wrap(err, apperror.ErrWPAPIDisabled, "REST API not accessible").WithURL(c.baseURL)
}

// reportProbeSuccess sends the probe success event.
func (c *Client) reportProbeSuccess(result *ConnectionInfo) {
	c.progress(ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Completed.String(),
		Message: "REST API is available",
		Details: toProgress(SiteNameProgress{URL: c.baseURL, SiteName: result.SiteName}),
	})
}

// validateRestApiStatus checks the REST API response status and parses site info.
func (c *Client) validateRestApiStatus(resp *http.Response, result *ConnectionInfo) error {
	if resp.StatusCode == HttpStatusNotFound.Int() {
		return c.reportRestApiNotFound()
	}

	var rootInfo wpRootInfo
	if err := json.NewDecoder(resp.Body).Decode(&rootInfo); err == nil {
		result.SiteName = rootInfo.Name
		result.SiteDescription = rootInfo.Description
	}

	return nil
}

// reportRestApiNotFound sends a not-found event and returns an error.
func (c *Client) reportRestApiNotFound() error {
	c.progress(ProgressEvent{
		Step:    connectionstep.DnsCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: "REST API not found - is permalink structure set?",
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})

	return apperror.New(apperror.ErrWPAPIDisabled, "WordPress REST API not found - ensure permalinks are enabled").WithURL(c.baseURL)
}

// authenticateAndParseUser checks authentication (Step 2) and parses user info (Step 3).
func (c *Client) authenticateAndParseUser(result *ConnectionInfo) error {
	c.reportAuthStart()

	authResp := c.fetchAuthResponse()
	if authResp.HasError() {
		return c.reportAuthRequestFailed(authResp.AppError())
	}

	resp := authResp.Value()
	if authErr := c.checkAuthStatus(resp.StatusCode, resp.Body); authErr != nil {
		return authErr
	}

	c.parseUserInfoFromBytes(resp.Body, result)
	c.reportAuthSuccess(result)

	return nil
}

// reportAuthStart sends the auth check start event.
func (c *Client) reportAuthStart() {
	c.progress(ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Authenticating as %s...", c.username),
		Details: toProgress(AuthInitProgress{URL: c.baseURL, Username: c.username}),
	})
}

// fetchAuthResponse sends the authentication API call.
func (c *Client) fetchAuthResponse() apperror.Result[APICallResponse] {
	authInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  WPCoreUsersMe,
		Operation: "authenticate user",
	}

	return c.doAPICallWithStatus(authInput)
}

// reportAuthRequestFailed sends an auth request failure event.
func (c *Client) reportAuthRequestFailed(err *apperror.AppError) error {
	c.progress(ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Authentication request failed: %v", err),
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})

	return apperror.Wrap(err, apperror.ErrWPAuth, "authentication request failed").
		WithURL(c.baseURL).
		WithUsername(c.username)
}

// reportAuthSuccess sends the auth success event.
func (c *Client) reportAuthSuccess(result *ConnectionInfo) {
	c.progress(ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Completed.String(),
		Message: fmt.Sprintf("Authenticated as %s (ID: %d)", result.UserDisplayName, result.UserId),
		Details: toProgress(UserAuthProgress{URL: c.baseURL, UserID: result.UserId, Roles: result.UserRoles}),
	})
}

// checkAuthStatus validates the authentication response status code.
func (c *Client) checkAuthStatus(statusCode int, body []byte) error {
	if statusCode == HttpStatusUnauthorized.Int() {
		return c.reportAuthFailure("Invalid username or application password",
			apperror.New(apperror.ErrWPAuth, "authentication failed: invalid username or application password").
				WithURL(c.baseURL).
				WithUsername(c.username))
	}

	if statusCode == HttpStatusForbidden.Int() {
		return c.reportAuthFailure("Access forbidden - user lacks permissions",
			apperror.New(apperror.ErrWPAuth, "authentication failed: user lacks required permissions").
				WithURL(c.baseURL).
				WithStatusCode(statusCode))
	}

	return c.checkUnexpectedAuthStatus(statusCode, body)
}

// checkUnexpectedAuthStatus handles non-standard auth response codes.
func (c *Client) checkUnexpectedAuthStatus(statusCode int, body []byte) error {
	if statusCode == HttpStatusOk.Int() {
		return nil
	}

	c.progress(ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Unexpected response: %d", statusCode),
		Details: toProgress(AuthBodyProgress{URL: c.baseURL, Body: string(body)}),
	})

	return apperror.New(apperror.ErrWPConnection, "unexpected authentication response").
		WithURL(c.baseURL).
		WithStatusCode(statusCode).
		WithDetails(string(body))
}

// reportAuthFailure logs an auth failure and returns the error.
func (c *Client) reportAuthFailure(message string, err *apperror.AppError) error {
	c.progress(ProgressEvent{
		Step:    connectionstep.AuthCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: message,
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})

	return err
}

// parseUserInfoFromBytes decodes the users/me response bytes into the ConnectionInfo result.
func (c *Client) parseUserInfoFromBytes(body []byte, result *ConnectionInfo) {
	var userInfo wpUserInfo
	if err := json.Unmarshal(body, &userInfo); err == nil {
		result.UserId = userInfo.Id
		result.UserDisplayName = userInfo.Name
		result.UserRoles = userInfo.Roles
		result.CanManagePlugins = userInfo.Capabilities["activate_plugins"] || userInfo.Capabilities["install_plugins"]
	}
}
