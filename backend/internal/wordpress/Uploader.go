// Package wordpress provides uploader capabilities using the Rise Up Uploader API.
package wordpress

import (
	"encoding/json"
	"fmt"
	"path/filepath"

	action "wp-plugin-publish/internal/enums/actiontype"
	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/pkg/apperror"
)

// Note: RiseUpUploaderNamespace is defined in constants.go

// UploaderStatus represents the /status endpoint response.
// Supports both legacy flat format and envelope Results[0] format.
type UploaderStatus struct {
	// Legacy flat fields
	Status           string            `json:"status"`            // external key (Riseup Asia Uploader API)
	Message          string            `json:"message"`           // external key
	Version          string            `json:"version"`           // external key
	WordPressVersion string            `json:"wordpress_version"` // external key
	PHPVersion       string            `json:"php_version"`       // external key
	Endpoints        map[string]string `json:"endpoints,omitempty"` // external key
	// Envelope PascalCase fields (populated when parsing from envelope Results)
	EnvVersion  string `json:"Version,omitempty"`  // external key (envelope format)
	EnvPlugin   string `json:"Plugin,omitempty"`   // external key
	EnvSlug     string `json:"Slug,omitempty"`     // external key
	EnvWp       string `json:"Wp,omitempty"`       // external key
	EnvPhp      string `json:"Php,omitempty"`      // external key
	EnvIsActive bool   `json:"IsActive,omitempty"` // external key
}

// UploaderUploadResult represents the /upload endpoint response.
type UploaderUploadResult struct {
	Success       bool   `json:"success"`                    // external key (Riseup Asia Uploader API)
	Message       string `json:"message"`                    // external key
	Plugin        string `json:"plugin,omitempty"`           // external key
	Activated     bool   `json:"activated"`                  // external key
	PluginDetails *struct {
		Name        string `json:"name"`        // external key
		Version     string `json:"version"`     // external key
		Author      string `json:"author"`      // external key
		Description string `json:"description"` // external key
	} `json:"plugin_details,omitempty"` // external key
	ActivationError string `json:"activation_error,omitempty"` // external key
}

// IsDefined returns true if the result is not nil (nil-safe).
func (r *UploaderUploadResult) IsDefined() bool { return r != nil }

// IsSuccessful returns true if the upload succeeded (nil-safe).
func (r *UploaderUploadResult) IsSuccessful() bool { return r != nil && r.Success }

// IsFailed returns true if the result is nil or the upload failed (nil-safe).
func (r *UploaderUploadResult) IsFailed() bool { return r == nil || !r.Success }

// IsActivated returns true if the plugin was activated after upload (nil-safe).
func (r *UploaderUploadResult) IsActivated() bool { return r != nil && r.Activated }

// IsDeactivated returns true if the plugin was not activated (nil-safe).
func (r *UploaderUploadResult) IsDeactivated() bool { return r == nil || !r.Activated }

// UploaderPluginInfo represents plugin info from the list endpoint.
type UploaderPluginInfo struct {
	Slug        string `json:"slug"`        // external key (Riseup Asia Uploader API)
	File        string `json:"file"`        // external key
	Name        string `json:"name"`        // external key
	Version     string `json:"version"`     // external key
	Author      string `json:"author"`      // external key
	Description string `json:"description"` // external key
	Active      bool   `json:"active"`      // external key
}

// UploaderFileInfo represents file info from the files endpoint.
type UploaderFileInfo struct {
	Path     string `json:"path"`     // external key (Riseup Asia Uploader API)
	Size     int64  `json:"size"`     // external key
	Modified string `json:"modified"` // external key
	Hash     string `json:"hash"`     // external key
}

// uploaderNamespaces defines the namespace probe order: newest first, then legacy.
var uploaderNamespaces = []string{
	RiseupAsiaNamespace,
	RiseUpUploaderNamespace,
	PluginUploaderNamespace,
}

// UploaderAvailability holds the result of checking if the uploader plugin is available.
type UploaderAvailability struct {
	Available bool
	Namespace string
}

// IsDefined returns true if the availability result is not nil.
func (a *UploaderAvailability) IsDefined() bool { return a != nil }

// IsAvailable returns true if the uploader is present and available (nil-safe).
func (a *UploaderAvailability) IsAvailable() bool { return a != nil && a.Available }

// IsUnavailable returns true if the uploader is nil or not available (nil-safe).
func (a *UploaderAvailability) IsUnavailable() bool { return a == nil || !a.Available }

// HasNamespace returns true if the availability result has a resolved namespace (nil-safe).
func (a *UploaderAvailability) HasNamespace() bool { return a != nil && a.Namespace != "" }

// IsNamespaceMissing returns true if no namespace was resolved (nil-safe).
func (a *UploaderAvailability) IsNamespaceMissing() bool { return a == nil || a.Namespace == "" }

// CheckRiseupAsiaAvailable checks if the Riseup Asia Uploader plugin is installed.
// It tries namespaces in priority order (newest first) and returns the first match.
func (c *Client) CheckRiseupAsiaAvailable() apperror.Result[*UploaderAvailability] {
	for _, ns := range uploaderNamespaces {
		endpoint := "/" + ns + ep.Status.String()
		callResp := c.doApiCallWithStatus(apiCallInput{
			Method: httpmethod.Get, Endpoint: endpoint, Operation: "check uploader namespace",
		})
		if callResp.HasError() {
			return apperror.Fail[*UploaderAvailability](callResp.AppError())
		}

		resp := callResp.Value()
		isOkStatus := resp.StatusCode == HttpStatusOk.Int()
		isUnauthorized := resp.StatusCode == HttpStatusUnauthorized.Int()
		isForbidden := resp.StatusCode == HttpStatusForbidden.Int()
		isAvailable := isOkStatus || isUnauthorized || isForbidden

		if isAvailable {
			return apperror.Ok(&UploaderAvailability{Available: true, Namespace: ns})
		}
	}

	return apperror.Ok(&UploaderAvailability{Available: false})
}

// CheckRiseUpUploaderAvailable is deprecated, use CheckRiseupAsiaAvailable.
func (c *Client) CheckRiseUpUploaderAvailable() apperror.Result[*UploaderAvailability] {
	return c.CheckRiseupAsiaAvailable()
}

// CheckUploaderHelperAvailable is deprecated, use CheckRiseupAsiaAvailable.
func (c *Client) CheckUploaderHelperAvailable() apperror.Result[*UploaderAvailability] {
	return c.CheckRiseupAsiaAvailable()
}

// GetUploaderStatus gets the Rise Up Uploader status.
func (c *Client) GetUploaderStatus() apperror.Result[*UploaderStatus] {
	namespace := c.resolveNamespace()
	endpoint := BuildNamespacedEndpoint(namespace, ep.Status)

	rawResult := c.doApiCallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "get uploader status", ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*UploaderStatus](rawResult.AppError())
	}

	return parseUploaderStatus(rawResult.Value())
}

// parseUploaderStatus parses envelope or legacy flat format from raw response bytes.
func parseUploaderStatus(data []byte) apperror.Result[*UploaderStatus] {
	status, ok := UnwrapSingleResult[UploaderStatus](data)

	if ok {
		normalizeUploaderEnvelopeFields(status)

		return apperror.Ok(status)
	}

	var legacyStatus UploaderStatus
	err := json.Unmarshal(data, &legacyStatus)

	if err != nil {
		return apperror.FailWrap[*UploaderStatus](err, apperror.ErrInternal, "decode status response")
	}

	return apperror.Ok(&legacyStatus)
}

// normalizeUploaderEnvelopeFields copies envelope PascalCase fields to legacy fields.
func normalizeUploaderEnvelopeFields(status *UploaderStatus) {
	isVersionMissing    := status.Version == ""
	hasEnvVersion       := status.EnvVersion != ""

	if isVersionMissing && hasEnvVersion {
		status.Version = status.EnvVersion
	}

	isWpVersionMissing  := status.WordPressVersion == ""
	hasEnvWp            := status.EnvWp != ""

	if isWpVersionMissing && hasEnvWp {
		status.WordPressVersion = status.EnvWp
	}

	isPhpVersionMissing := status.PHPVersion == ""
	hasEnvPhp           := status.EnvPhp != ""

	if isPhpVersionMissing && hasEnvPhp {
		status.PHPVersion = status.EnvPhp
	}
}

// UploadInput bundles parameters for UploadPluginViaUploader.
type UploadInput struct {
	ZipPath      string
	Slug         string
	IsActivate   bool
	UploadSource uploadsource.Variant
}

// UploadPluginViaUploader uploads a plugin ZIP via the Rise Up Uploader.
// Uses multipart/form-data for efficiency (no base64 overhead, streamed upload).
func (c *Client) UploadPluginViaUploader(input UploadInput) apperror.Result[*UploaderUploadResult] {
	uc, err := c.prepareUploadContext(input.ZipPath, input.Slug)
	if err != nil {
		return apperror.Fail[*UploaderUploadResult](err)
	}
	defer uc.ZipFile.Close()

	c.reportUploadInitProgress(uc)

	mp, mpErr := buildMultipartBody(uc, input.IsActivate, input.UploadSource)
	if mpErr != nil {
		return apperror.Fail[*UploaderUploadResult](mpErr)
	}

	c.reportMultipartBodyReady(uc, input, mp)

	return c.executeUploadHTTP(uc, mp.Body, mp.ContentType)
}

// reportUploadInitProgress logs the upload initialization progress.
func (c *Client) reportUploadInitProgress(uc *uploadContext) {
	c.progress(ProgressEvent{
		Step: action.Upload.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Uploading %s (%d bytes) via multipart to %s", filepath.Base(uc.AbsZipPath), uc.ZipSize, uc.UploadURL),
		Details: toProgress(UploadInitProgress{
			ZipSize: uc.ZipSize, ZipPath: uc.AbsZipPath, Namespace: uc.Namespace, Endpoint: uc.UploadEndpoint, URL: uc.UploadURL, Method: "multipart/form-data",
		}),
	})
}

// reportMultipartBodyReady logs that the multipart body is ready.
func (c *Client) reportMultipartBodyReady(uc *uploadContext, input UploadInput, mp *multipartResult) {
	c.progress(ProgressEvent{
		Step: action.Upload.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Multipart body ready: slug=%s, activate=%v, zipSize=%d bytes, bodySize=%d bytes", uc.Slug, input.IsActivate, uc.ZipSize, mp.Body.Len()),
		Details: toProgress(UploadBodyProgress{
			Slug: uc.Slug, IsActivate: input.IsActivate, ZipSize: uc.ZipSize, BodySize: mp.Body.Len(),
		}),
	})
}

