package handlers

import (
	"net/http"
	"strconv"

	"github.com/gorilla/mux"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
	"riseup-licensing/internal/models"
	"riseup-licensing/internal/services"
)

// AdminHandlers holds dependencies for admin license CRUD endpoints.
type AdminHandlers struct {
	Licenses *services.LicenseService
	Audit    *services.AuditService
}

// createLicenseRequest is the JSON body for license creation.
type createLicenseRequest struct {
	Email          string `json:"email"`
	Product        string `json:"product"`
	Type           string `json:"type"`
	MaxActivations int    `json:"maxActivations"`
	Notes          string `json:"notes"`
}

// CreateLicense handles POST /admin/licenses.
func (h *AdminHandlers) CreateLicense(w http.ResponseWriter, r *http.Request) {
	var req createLicenseRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid request body")

		return
	}

	isEmailMissing := req.Email == ""

	if isEmailMissing {
		errorResponse(w, http.StatusBadRequest, "email is required")

		return
	}

	h.executeCreate(w, r, req)
}

// executeCreate generates a key and persists the license.
func (h *AdminHandlers) executeCreate(
	w http.ResponseWriter,
	r *http.Request,
	req createLicenseRequest,
) {
	keyResult := services.GenerateKey()
	if keyResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to generate key")

		return
	}

	input := buildCreateInput(keyResult.Value(), req)

	createResult := h.Licenses.Create(input)
	if createResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to create license")

		return
	}

	license := createResult.Value()

	h.logAudit(r, &license.Id, auditaction.Created, "")
	jsonResponse(w, http.StatusCreated, license)
}

// buildCreateInput converts a request into a CreateInput.
func buildCreateInput(key string, req createLicenseRequest) services.CreateInput {
	maxActivations := req.MaxActivations
	isDefaultMax := maxActivations <= 0

	if isDefaultMax {
		maxActivations = 1
	}

	return services.CreateInput{
		Key:            key,
		Email:          req.Email,
		Product:        producttype.Parse(req.Product),
		Type:           licensetype.Parse(req.Type),
		MaxActivations: maxActivations,
		Notes:          req.Notes,
	}
}

// ListLicenses handles GET /admin/licenses.
func (h *AdminHandlers) ListLicenses(w http.ResponseWriter, r *http.Request) {
	listResult := h.Licenses.List()
	if listResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to list licenses")

		return
	}

	licenses := listResult.Value()
	isNilList := licenses == nil

	if isNilList {
		licenses = []models.License{}
	}

	jsonResponse(w, http.StatusOK, licenses)
}

// GetLicense handles GET /admin/licenses/{id}.
func (h *AdminHandlers) GetLicense(w http.ResponseWriter, r *http.Request) {
	id, parseErr := extractIdParam(r)
	if parseErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid license id")

		return
	}

	getResult := h.Licenses.GetById(id)
	if getResult.HasError() {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	jsonResponse(w, http.StatusOK, getResult.Value())
}

// extractIdParam reads the {id} path variable as int64.
func extractIdParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)

	return strconv.ParseInt(vars["id"], 10, 64)
}

// ListAuditLogs handles GET /admin/audit.
func (h *AdminHandlers) ListAuditLogs(w http.ResponseWriter, r *http.Request) {
	filter := services.ListFilter{}

	actionParam := r.URL.Query().Get("action")
	hasAction := actionParam != ""

	if hasAction {
		action := auditaction.Variant(actionParam)
		filter.Action = &action
	}

	licenseParam := r.URL.Query().Get("license_id")
	hasLicense := licenseParam != ""

	if hasLicense {
		id, parseErr := strconv.ParseInt(licenseParam, 10, 64)
		if parseErr != nil {
			errorResponse(w, http.StatusBadRequest, "invalid license_id")

			return
		}

		filter.LicenseId = &id
	}

	listResult := h.Audit.List(filter)
	if listResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to list audit logs")

		return
	}

	logs := listResult.Value()
	isNilList := logs == nil

	if isNilList {
		logs = []models.AuditLog{}
	}

	jsonResponse(w, http.StatusOK, logs)
}

// logAudit is a convenience wrapper for audit logging.
func (h *AdminHandlers) logAudit(
	r *http.Request,
	licenseId *int64,
	action auditaction.Variant,
	domain string,
) {
	h.Audit.Log(services.LogInput{
		LicenseId: licenseId,
		Action:    action,
		Domain:    domain,
		IpAddress: r.RemoteAddr,
	}) //nolint:errcheck
}
