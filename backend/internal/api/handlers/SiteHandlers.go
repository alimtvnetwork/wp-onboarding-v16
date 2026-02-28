// Package handlers provides site-related HTTP request handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// SiteCreateInput represents the request body for creating a site
type SiteCreateInput struct {
	Name     string
	Url      string
	Username string
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            string `json:",omitempty"`
	ApplicationPassword string `json:",omitempty"`
}

// SiteUpdateInput represents the request body for updating a site
type SiteUpdateInput struct {
	Name     *string `json:",omitempty"`
	Url      *string `json:",omitempty"`
	Username *string `json:",omitempty"`
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            *string `json:",omitempty"`
	ApplicationPassword *string `json:",omitempty"`
}

// GetSites returns all registered WordPress sites
var GetSites = handleListNilSafe(
	siteService,
	apperror.ErrDatabaseConnect,
	func(ctx context.Context) (any, error) {
		return Services.SiteService.List(ctx)
	},
)

// CreateSite creates a new WordPress site connection
func CreateSite(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	var input SiteCreateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	normalizeCreateSitePassword(&input)

	if msg := validateCreateSiteInput(input); msg != "" {
		respondBadRequest(w, apperror.ErrValidation, msg)

		return
	}

	createSiteOrFail(w, r, input)
}

// createSiteOrFail persists the site and writes the response.
func createSiteOrFail(w http.ResponseWriter, r *http.Request, input SiteCreateInput) {
	site, err := Services.SiteService.Create(r.Context(), input)
	if err != nil {
		respondBadRequest(w, apperror.ErrDatabaseInsert, err.Error())

		return
	}

	respondCreated(w, site)
}

// normalizeCreateSitePassword maps applicationPassword to password if needed.
func normalizeCreateSitePassword(input *SiteCreateInput) {
	if input.Password == "" && input.ApplicationPassword != "" {
		input.Password = input.ApplicationPassword
	}
}

// validateCreateSiteInput returns an error message if any required field is missing.
func validateCreateSiteInput(input SiteCreateInput) string {
	if input.Name == "" {
		return "Name is required"
	}

	if input.Url == "" {
		return "URL is required"
	}

	return validateCreateSiteCredentials(input)
}

// validateCreateSiteCredentials checks the credential fields of SiteCreateInput.
func validateCreateSiteCredentials(input SiteCreateInput) string {
	if input.Username == "" {
		return "Username is required"
	}

	if input.Password == "" {
		return "Application password is required"
	}

	return ""
}

// GetSite returns a specific site by ID
var GetSite = handleActionByID(
	handlerIDConfig{
		GetService:  siteService,
		ServiceName: "Site service",
		ParamName:   "id",
		ErrCode:     apperror.ErrNotFound,
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetById(ctx, id)
	},
)

// UpdateSite updates an existing site
func UpdateSite(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}

	var input SiteUpdateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	normalizeUpdateSitePassword(&input)
	updateSiteOrFail(w, r, id, input)
}

// updateSiteOrFail persists the site update and writes the response.
func updateSiteOrFail(w http.ResponseWriter, r *http.Request, id int64, input SiteUpdateInput) {
	site, err := Services.SiteService.Update(r.Context(), id, input)
	if err != nil {
		respondBadRequest(w, apperror.ErrDatabaseUpdate, err.Error())

		return
	}

	respondSuccess(w, site)
}

// normalizeUpdateSitePassword maps applicationPassword to password if needed.
func normalizeUpdateSitePassword(input *SiteUpdateInput) {
	if input.Password == nil && input.ApplicationPassword != nil {
		input.Password = input.ApplicationPassword
	}
}

// DeleteSite removes a site
var DeleteSite = handleDeleteByID(
	handlerIDConfig{
		GetService:  siteService,
		ServiceName: "Site service",
		ParamName:   "id",
		ErrCode:     apperror.ErrDatabaseDelete,
	},
	func(ctx context.Context, id int64) error {
		return Services.SiteService.Delete(ctx, id)
	},
)

// TestSiteConnection tests the WordPress REST API connection
var TestSiteConnection = handleActionByID(
	handlerIDConfig{
		GetService:  siteService,
		ServiceName: "Site service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPConnection,
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.TestConnection(ctx, id)
	},
)

// credentialsInput is the JSON body for TestSiteCredentials.
type credentialsInput struct {
	Url      string
	Username string
	Password string
}

// TestSiteCredentials tests credentials without saving (for pre-create validation)
func TestSiteCredentials(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	var input credentialsInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	testCredentialsOrFail(w, r, input)
}

// testCredentialsOrFail executes the credentials test and writes the response.
func testCredentialsOrFail(w http.ResponseWriter, r *http.Request, input credentialsInput) {
	result, err := Services.SiteService.TestConnectionWithCredentials(r.Context(), input.Url, input.Username, input.Password)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrWPConnection, err.Error())

		return
	}

	respondSuccess(w, result)
}

// GetSiteCredentials returns decrypted credentials for API Explorer
var GetSiteCredentials = handleActionByID(
	handlerIDConfig{
		GetService:  siteService,
		ServiceName: "Site service",
		ParamName:   "id",
		ErrCode:     apperror.ErrDatabaseMigrate,
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetCredentials(ctx, id)
	},
)
