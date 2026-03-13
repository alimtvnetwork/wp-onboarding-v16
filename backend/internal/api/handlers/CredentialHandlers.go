package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/pkg/apperror"
)

// CredentialCreateInput represents the request body for creating a credential.
type CredentialCreateInput struct {
	AppName  string
	Username string
	Password string
}

// CredentialUpdateInput represents the request body for updating a credential.
type CredentialUpdateInput struct {
	AppName  string
	Username string
	Password string
}

// CredentialResponse is the safe JSON response for a credential (no password).
type CredentialResponse struct {
	Id               int64  `json:"id"`
	SiteId           int64  `json:"siteId"`
	AppName          string `json:"appName"`
	Username         string `json:"username"`
	IsDefault        bool   `json:"isDefault"`
	ConnectionStatus string `json:"connectionStatus"`
	LastTestedAt     string `json:"lastTestedAt,omitempty"`
	CreatedAt        string `json:"createdAt"`
	UpdatedAt        string `json:"updatedAt"`
}

// GetSiteCredentialsList returns all credentials for a site (no passwords).
func GetSiteCredentialsList(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	creds, appErr := Services.SiteService.ListCredentials(r.Context(), id)
	if appErr != nil {
		respondBadRequest(w, apperror.ErrDatabaseQuery, appErr.Error())

		return
	}

	respondSuccess(w, toCredentialResponses(creds))
}

// CreateSiteCredential adds a new credential to a site.
func CreateSiteCredential(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input CredentialCreateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	msg := validateCredentialInput(input)
	hasError := msg != ""

	if hasError {
		respondBadRequest(w, apperror.ErrValidation, msg)

		return
	}

	cred, appErr := Services.SiteService.CreateCredential(r.Context(), id, input)
	if appErr != nil {
		respondBadRequest(w, apperror.ErrDatabaseInsert, appErr.Error())

		return
	}

	respondCreated(w, toCredentialResponse(cred))
}

// UpdateSiteCredential updates an existing credential.
func UpdateSiteCredential(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	_, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	credId, credOk := parseId(w, r, "credId")
	if !credOk {
		return
	}

	var input CredentialUpdateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	cred, appErr := Services.SiteService.UpdateCredential(r.Context(), credId, input)
	if appErr != nil {
		respondBadRequest(w, apperror.ErrDatabaseUpdate, appErr.Error())

		return
	}

	respondSuccess(w, toCredentialResponse(cred))
}

// DeleteSiteCredential removes a credential.
func DeleteSiteCredentialHandler(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	credId, ok := parseId(w, r, "credId")
	if !ok {
		return
	}

	appErr := Services.SiteService.DeleteCredential(r.Context(), credId)
	if appErr != nil {
		respondBadRequest(w, apperror.ErrDatabaseDelete, appErr.Error())

		return
	}

	respondSuccess(w, map[string]bool{"deleted": true})
}

// SetDefaultSiteCredential sets a credential as default.
func SetDefaultSiteCredential(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	siteId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	credId, credOk := parseId(w, r, "credId")
	if !credOk {
		return
	}

	appErr := Services.SiteService.SetDefaultCredential(r.Context(), siteId, credId)
	if appErr != nil {
		respondBadRequest(w, apperror.ErrDatabaseUpdate, appErr.Error())

		return
	}

	respondSuccess(w, map[string]bool{"updated": true})
}

// validateCredentialInput validates create credential input.
func validateCredentialInput(input CredentialCreateInput) string {
	isAppNameEmpty := input.AppName == ""

	if isAppNameEmpty {
		return "App name is required"
	}

	isUsernameEmpty := input.Username == ""

	if isUsernameEmpty {
		return "Username is required"
	}

	isPasswordEmpty := input.Password == ""

	if isPasswordEmpty {
		return "Password is required"
	}

	return ""
}

// toCredentialResponse converts a database credential to a safe response.
func toCredentialResponse(cred *database.SiteCredential) CredentialResponse {
	lastTested := ""
	if cred.LastTestedAt != nil {
		lastTested = cred.LastTestedAt.Format("2006-01-02 15:04:05")
	}

	return CredentialResponse{
		Id:               cred.Id,
		SiteId:           cred.SiteId,
		AppName:          cred.AppName,
		Username:         cred.Username,
		IsDefault:        cred.IsDefault,
		ConnectionStatus: cred.ConnectionStatus,
		LastTestedAt:     lastTested,
		CreatedAt:        cred.CreatedAt.Format("2006-01-02 15:04:05"),
		UpdatedAt:        cred.UpdatedAt.Format("2006-01-02 15:04:05"),
	}
}

// toCredentialResponses converts a slice of credentials to responses.
func toCredentialResponses(creds []database.SiteCredential) []CredentialResponse {
	responses := make([]CredentialResponse, 0, len(creds))

	for i := range creds {
		responses = append(responses, toCredentialResponse(&creds[i]))
	}

	return responses
}

// SiteServiceInterface credential methods (added to existing interface)
// ListCredentials, CreateCredential, UpdateCredential, DeleteCredential, SetDefaultCredential
// are defined in AdapterSite.go as part of the interface.

// Ensure the handler functions satisfy the pattern — they call SiteServiceInterface methods.
// The adapter will delegate to site.Service which uses the database layer.
var _ = func() {
	// compile-time reference check
	var s SiteServiceInterface
	_, _ = s.ListCredentials(context.Background(), 0)
}
