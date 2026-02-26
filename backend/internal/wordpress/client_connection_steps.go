// Package wordpress — TestConnection step helpers.
// Each step is extracted from TestConnection() to comply with the 15-line function body limit.
package wordpress

import (
	"encoding/json"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/enums/connection_step"
	"wp-plugin-publish/internal/enums/post_status"
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

// testDnsReachability checks if the WordPress site is reachable (Step 1).
func (c *Client) testDnsReachability(result *ConnectionInfo) error {
	c.progress(connectionstep.DnsCheck.Value(), stagestatus.Running.String(), fmt.Sprintf("Resolving %s...", c.baseURL), toProgress(URLProgress{URL: c.baseURL}))

	resp, err := c.httpClient.Get(c.baseURL)
	if err != nil {
		c.progress(connectionstep.DnsCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Cannot reach site: %v", err), toProgress(URLErrorProgress{URL: c.baseURL, Error: err.Error()}))
		return apperror.Wrap(err, apperror.ErrWPConnection, "cannot reach WordPress site").WithURL(c.baseURL)
	}
	resp.Body.Close()

	c.progress(connectionstep.DnsCheck.Value(), stagestatus.Completed.String(), "Site is reachable", toProgress(URLStatusProgress{URL: c.baseURL, Status: resp.StatusCode}))
	return nil
}

// testRestApiAvailability checks WordPress REST API availability (Step 2).
func (c *Client) testRestApiAvailability(result *ConnectionInfo) error {
	c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Running.String(), "Checking WordPress REST API...", toProgress(URLProgress{URL: c.baseURL}))

	resp, err := c.httpClient.Get(fmt.Sprintf("%s/wp-json/", c.baseURL))
	if err != nil {
		c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("REST API not accessible: %v", err), toProgress(URLProgress{URL: c.baseURL}))

		return apperror.Wrap(err, apperror.ErrWPAPIDisabled, "REST API not accessible").WithURL(c.baseURL)
	}
	defer resp.Body.Close()

	if err := c.validateRestApiStatus(resp, result); err != nil {
		return err
	}

	c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Completed.String(), "REST API is available", toProgress(SiteNameProgress{URL: c.baseURL, SiteName: result.SiteName}))

	return nil
}

// validateRestApiStatus checks the REST API response status and parses site info.
func (c *Client) validateRestApiStatus(resp *http.Response, result *ConnectionInfo) error {
	if resp.StatusCode == HttpStatusNotFound.Int() {
		c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Failed.String(), "REST API not found - is permalink structure set?", toProgress(URLProgress{URL: c.baseURL}))

		return apperror.New(apperror.ErrWPAPIDisabled, "WordPress REST API not found - ensure permalinks are enabled").WithURL(c.baseURL)
	}

	var rootInfo wpRootInfo
	if err := json.NewDecoder(resp.Body).Decode(&rootInfo); err == nil {
		result.SiteName = rootInfo.Name
		result.SiteDescription = rootInfo.Description
	}

	return nil
}

// testAuthentication verifies API credentials via users/me endpoint (Step 3).
func (c *Client) testAuthentication(result *ConnectionInfo) error {
	c.progress(connectionstep.AuthCheck.Value(), stagestatus.Running.String(), fmt.Sprintf("Authenticating as %s...", c.username), toProgress(AuthInitProgress{URL: c.baseURL, Username: c.username}))

	body, statusCode, err := c.doAPICallWithStatus(apiCallInput{
		Method: "GET", Endpoint: WPCoreUsersMe, Operation: "authenticate user",
	})
	if err != nil {
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Authentication request failed: %v", err), toProgress(URLProgress{URL: c.baseURL}))

		return apperror.Wrap(err, apperror.ErrWPAuth, "authentication request failed").
			WithURL(c.baseURL).
			WithUsername(c.username)
	}

	if authErr := c.checkAuthStatus(statusCode, body); authErr != nil {
		return authErr
	}

	c.parseUserInfoFromBytes(body, result)
	c.progress(connectionstep.AuthCheck.Value(), stagestatus.Completed.String(), fmt.Sprintf("Authenticated as %s (ID: %d)", result.UserDisplayName, result.UserId), toProgress(UserAuthProgress{URL: c.baseURL, UserID: result.UserId, Roles: result.UserRoles}))

	return nil
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

	if statusCode != HttpStatusOk.Int() {
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Unexpected response: %d", statusCode), toProgress(AuthBodyProgress{URL: c.baseURL, Body: string(body)}))

		return apperror.New(apperror.ErrWPConnection, "unexpected authentication response").
			WithURL(c.baseURL).
			WithStatusCode(statusCode).
			WithDetails(string(body))
	}

	return nil
}

// reportAuthFailure logs an auth failure and returns the error.
func (c *Client) reportAuthFailure(message string, err *apperror.AppError) error {
	c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), message, toProgress(URLProgress{URL: c.baseURL}))

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

// testPluginAccess checks plugin management permissions (Step 4).
func (c *Client) testPluginAccess(result *ConnectionInfo) error {
	c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Running.String(), "Checking plugin management access...", toProgress(URLProgress{URL: c.baseURL}))

	_, statusCode, err := c.doAPICallWithStatus(apiCallInput{
		Method: "GET", Endpoint: WPCorePlugins, Operation: "check plugin access",
	})
	if err != nil {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Plugin endpoint request failed: %v", err), toProgress(URLProgress{URL: c.baseURL}))

		return apperror.Wrap(err, apperror.ErrWPPluginList, "plugin endpoint not accessible").WithURL(c.baseURL)
	}

	if statusCode == HttpStatusUnauthorized.Int() || statusCode == HttpStatusForbidden.Int() {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Failed.String(), "User cannot manage plugins - requires administrator role", toProgress(UserRolesProgress{URL: c.baseURL, UserRoles: result.UserRoles}))

		return apperror.New(apperror.ErrWPAuth, "insufficient permissions: user cannot manage plugins (requires administrator role)").
			WithURL(c.baseURL).
			WithStatusCode(statusCode)
	}

	c.reportPluginAccessByStatus(statusCode, result)

	return nil
}

// reportPluginAccessByStatus logs the plugin access check outcome.
func (c *Client) reportPluginAccessByStatus(statusCode int, result *ConnectionInfo) {
	if statusCode == HttpStatusOk.Int() {
		result.CanManagePlugins = true
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Completed.String(), "Plugin management access confirmed", toProgress(URLProgress{URL: c.baseURL}))
	} else {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Warning.String(), fmt.Sprintf("Plugin endpoint returned %d", statusCode), toProgress(URLProgress{URL: c.baseURL}))
	}
}

// testWritePermissions creates and deletes a draft post to verify write access (Step 5).
func (c *Client) testWritePermissions(result *ConnectionInfo) {
	c.progress(connectionstep.WriteTest.Value(), stagestatus.Running.String(), "Testing write permissions...", toProgress(URLProgress{URL: c.baseURL}))

	testPost := wpTestPost{
		Title:   "WP Plugin Publish Connection Test",
		Content: "This draft was created to test API write permissions. You can safely delete it.",
		Status:  poststatus.Draft.String(),
	}

	body, statusCode, err := c.doAPICallWithStatus(apiCallInput{
		Method: "POST", Endpoint: WPCorePosts, Body: testPost, Operation: "test write permissions",
	})
	if err != nil {
		c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), "Could not test write permissions", toProgress(URLErrorProgress{URL: c.baseURL, Error: err.Error()}))

		return
	}

	c.evaluateWriteTestByStatus(statusCode, body, result)
}

// evaluateWriteTestByStatus handles the write test response based on status code.
func (c *Client) evaluateWriteTestByStatus(statusCode int, body []byte, result *ConnectionInfo) {
	if statusCode == HttpStatusCreated.Int() {
		c.handleWriteTestCleanup(body, result)
	} else if statusCode == HttpStatusUnauthorized.Int() || statusCode == HttpStatusForbidden.Int() {
		c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), "User cannot create posts", toProgress(URLProgress{URL: c.baseURL}))
	} else {
		c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), fmt.Sprintf("Write test returned %d", statusCode), toProgress(URLProgress{URL: c.baseURL}))
	}
}

// handleWriteTestCleanup cleans up the test post and reports success.
func (c *Client) handleWriteTestCleanup(body []byte, result *ConnectionInfo) {
	var createdPost wpCreatedPost
	if err := json.Unmarshal(body, &createdPost); err != nil || createdPost.Id <= 0 {
		return
	}

	c.doAPICallRaw(apiCallInput{
		Method:     "DELETE",
		Endpoint:   fmt.Sprintf(WPCorePostById+"?force=true", createdPost.Id),
		Operation:  "delete test post",
		OkStatuses: []int{HttpStatusOk.Int(), HttpStatusNoContent.Int()},
	})

	result.CanWritePosts = true
	c.progress(connectionstep.WriteTest.Value(), stagestatus.Completed.String(), "Write permissions verified (test post created and deleted)", toProgress(WriteTestProgress{URL: c.baseURL, TestPostID: createdPost.Id}))
}
