// Package wordpress — TestConnection steps 4–5: plugin access and write permission checks.
package wordpress

import (
	"encoding/json"
	"fmt"

	connectionstep "wp-plugin-publish/internal/enums/connectionsteptype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	poststatus "wp-plugin-publish/internal/enums/poststatustype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// testPluginAccess checks plugin management permissions (Step 4).
func (c *Client) testPluginAccess(result *ConnectionInfo) error {
	c.reportPluginAccessStart()

	pluginResp := c.fetchPluginAccessResponse()
	if pluginResp.HasError() {
		return c.reportPluginAccessRequestFailed(pluginResp.AppError())
	}

	resp := pluginResp.Value()

	return c.evaluatePluginAccess(resp.StatusCode, result)
}

// reportPluginAccessStart sends the plugin access check start event.
func (c *Client) reportPluginAccessStart() {
	c.progress(ProgressEvent{
		Step:    connectionstep.PluginAccessCheck.Value(),
		Status:  stagestatus.Running.String(),
		Message: "Checking plugin management access...",
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// fetchPluginAccessResponse sends the plugin list API call.
func (c *Client) fetchPluginAccessResponse() apperror.Result[APICallResponse] {
	pluginAccessInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  WPCorePlugins,
		Operation: "check plugin access",
	}

	return c.doAPICallWithStatus(pluginAccessInput)
}

// reportPluginAccessRequestFailed sends a plugin access request failure event.
func (c *Client) reportPluginAccessRequestFailed(err *apperror.AppError) error {
	c.progress(ProgressEvent{
		Step:    connectionstep.PluginAccessCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: fmt.Sprintf("Plugin endpoint request failed: %v", err),
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})

	return apperror.Wrap(err, apperror.ErrWPPluginList, "plugin endpoint not accessible").WithURL(c.baseURL)
}

// evaluatePluginAccess checks plugin access based on status code.
func (c *Client) evaluatePluginAccess(statusCode int, result *ConnectionInfo) error {
	isUnauthorized := statusCode == HttpStatusUnauthorized.Int() || statusCode == HttpStatusForbidden.Int()

	if isUnauthorized {
		return c.reportInsufficientPluginPermissions(result, statusCode)
	}

	c.reportPluginAccessByStatus(statusCode, result)

	return nil
}

// reportInsufficientPluginPermissions sends permission failure event.
func (c *Client) reportInsufficientPluginPermissions(result *ConnectionInfo, statusCode int) error {
	c.progress(ProgressEvent{
		Step:    connectionstep.PluginAccessCheck.Value(),
		Status:  stagestatus.Failed.String(),
		Message: "User cannot manage plugins - requires administrator role",
		Details: toProgress(UserRolesProgress{URL: c.baseURL, UserRoles: result.UserRoles}),
	})

	return apperror.New(apperror.ErrWPAuth, "insufficient permissions: user cannot manage plugins (requires administrator role)").
		WithURL(c.baseURL).
		WithStatusCode(statusCode)
}

// reportPluginAccessByStatus logs the plugin access check outcome.
func (c *Client) reportPluginAccessByStatus(statusCode int, result *ConnectionInfo) {
	if statusCode == HttpStatusOk.Int() {
		result.CanManagePlugins = true
		c.reportPluginAccessConfirmed()
	} else {
		c.reportPluginAccessWarning(statusCode)
	}
}

// reportPluginAccessConfirmed sends the plugin access confirmed event.
func (c *Client) reportPluginAccessConfirmed() {
	c.progress(ProgressEvent{
		Step:    connectionstep.PluginAccessCheck.Value(),
		Status:  stagestatus.Completed.String(),
		Message: "Plugin management access confirmed",
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// reportPluginAccessWarning sends the plugin access warning event.
func (c *Client) reportPluginAccessWarning(statusCode int) {
	c.progress(ProgressEvent{
		Step:    connectionstep.PluginAccessCheck.Value(),
		Status:  stagestatus.Warning.String(),
		Message: fmt.Sprintf("Plugin endpoint returned %d", statusCode),
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// testWritePermission creates and deletes a draft post to verify write access (Step 5).
func (c *Client) testWritePermission(result *ConnectionInfo) {
	c.reportWriteTestStart()

	writeResp := c.sendWriteTestPost()
	if writeResp.HasError() {
		c.reportWriteTestRequestFailed(writeResp.AppError())

		return
	}

	resp := writeResp.Value()
	c.evaluateWriteTestByStatus(resp.StatusCode, resp.Body, result)
}

// reportWriteTestStart sends the write test start event.
func (c *Client) reportWriteTestStart() {
	c.progress(ProgressEvent{
		Step:    connectionstep.WriteTest.Value(),
		Status:  stagestatus.Running.String(),
		Message: "Testing write permissions...",
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// sendWriteTestPost creates a draft post for write testing.
func (c *Client) sendWriteTestPost() apperror.Result[APICallResponse] {
	testPost := wpTestPost{
		Title:   "WP Plugin Publish Connection Test",
		Content: "This draft was created to test API write permissions. You can safely delete it.",
		Status:  poststatus.Draft.String(),
	}

	writeTestInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  WPCorePosts,
		Body:      testPost,
		Operation: "test write permissions",
	}

	return c.doAPICallWithStatus(writeTestInput)
}

// reportWriteTestRequestFailed sends a write test request failure event.
func (c *Client) reportWriteTestRequestFailed(err *apperror.AppError) {
	c.progress(ProgressEvent{
		Step:    connectionstep.WriteTest.Value(),
		Status:  stagestatus.Warning.String(),
		Message: "Could not test write permissions",
		Details: toProgress(URLErrorProgress{URL: c.baseURL, Error: err.Error()}),
	})
}

// evaluateWriteTestByStatus handles the write test response based on status code.
func (c *Client) evaluateWriteTestByStatus(statusCode int, body []byte, result *ConnectionInfo) {
	if statusCode == HttpStatusCreated.Int() {
		c.handleWriteTestCleanup(body, result)

		return
	}

	c.reportWriteTestNonCreated(statusCode)
}

// reportWriteTestNonCreated sends events for non-201 write test responses.
func (c *Client) reportWriteTestNonCreated(statusCode int) {
	isUnauthorized := statusCode == HttpStatusUnauthorized.Int() || statusCode == HttpStatusForbidden.Int()

	msg := fmt.Sprintf("Write test returned %d", statusCode)
	if isUnauthorized {
		msg = "User cannot create posts"
	}

	c.progress(ProgressEvent{
		Step:    connectionstep.WriteTest.Value(),
		Status:  stagestatus.Warning.String(),
		Message: msg,
		Details: toProgress(URLProgress{URL: c.baseURL}),
	})
}

// handleWriteTestCleanup cleans up the test post and reports success.
func (c *Client) handleWriteTestCleanup(body []byte, result *ConnectionInfo) {
	var createdPost wpCreatedPost
	if err := json.Unmarshal(body, &createdPost); err != nil || createdPost.Id <= 0 {
		return
	}

	c.deleteTestPost(createdPost.Id)

	result.CanWritePosts = true
	c.progress(ProgressEvent{
		Step:    connectionstep.WriteTest.Value(),
		Status:  stagestatus.Completed.String(),
		Message: "Write permissions verified (test post created and deleted)",
		Details: toProgress(WriteTestProgress{URL: c.baseURL, TestPostID: createdPost.Id}),
	})
}

// deleteTestPost removes the test draft post.
func (c *Client) deleteTestPost(postId int) {
	deleteInput := apiCallInput{
		Method:     httpmethod.Delete,
		Endpoint:   fmt.Sprintf(WPCorePostById+"?force=true", postId),
		Operation:  "delete test post",
		OkStatuses: []int{HttpStatusOk.Int(), HttpStatusNoContent.Int()},
	}
	c.doAPICallRaw(deleteInput)
}
