package handlers

import (
	"net/http"

	"github.com/gorilla/mux"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/services"
)

// activateRequest is the JSON body for domain activation.
type activateRequest struct {
	Domain string `json:"domain"`
}

// Activate handles POST /licenses/{key}/activate.
func (h *PublicHandlers) Activate(w http.ResponseWriter, r *http.Request) {
	key := mux.Vars(r)["key"]

	var req activateRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil || req.Domain == "" {
		errorResponse(w, http.StatusBadRequest, "domain is required")

		return
	}

	getResult := h.Licenses.GetByKey(key)
	if getResult.HasError() {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	license := getResult.Value()
	isLicenseUnusable := !license.IsUsable()

	if isLicenseUnusable {
		errorResponse(w, http.StatusForbidden, "license is not active")

		return
	}

	h.executeActivation(w, r, license.Id, license.MaxActivations, req.Domain)
}

// executeActivation checks the limit and creates the activation.
func (h *PublicHandlers) executeActivation(
	w http.ResponseWriter,
	r *http.Request,
	licenseId int64,
	maxActivations int,
	domain string,
) {
	countResult := h.Activations.CountActive(licenseId)
	if countResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to check activations")

		return
	}

	isAtLimit := countResult.Value() >= maxActivations

	if isAtLimit {
		errorResponse(w, http.StatusConflict, "activation limit reached")

		return
	}

	actResult := h.Activations.Activate(services.ActivateInput{
		LicenseId: licenseId,
		Domain:    domain,
		IpAddress: r.RemoteAddr,
		UserAgent: r.UserAgent(),
	})
	if actResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "activation failed")

		return
	}

	h.logPublicAudit(r, &licenseId, auditactiontype.Activated, domain)
	jsonResponse(w, http.StatusOK, actResult.Value())
}

// Deactivate handles POST /licenses/{key}/deactivate.
func (h *PublicHandlers) Deactivate(w http.ResponseWriter, r *http.Request) {
	key := mux.Vars(r)["key"]

	var req activateRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil || req.Domain == "" {
		errorResponse(w, http.StatusBadRequest, "domain is required")

		return
	}

	getResult := h.Licenses.GetByKey(key)
	if getResult.HasError() {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	license := getResult.Value()

	deactErr := h.Activations.Deactivate(license.Id, req.Domain)
	if deactErr != nil {
		errorResponse(w, http.StatusInternalServerError, "deactivation failed")

		return
	}

	h.logPublicAudit(r, &license.Id, auditactiontype.Deactivated, req.Domain)
	jsonResponse(w, http.StatusOK, statusBody{Status: "deactivated"})
}

// Status handles GET /licenses/{key}/status.
func (h *PublicHandlers) Status(w http.ResponseWriter, r *http.Request) {
	key := mux.Vars(r)["key"]

	getResult := h.Licenses.GetByKey(key)
	if getResult.HasError() {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	license := getResult.Value()

	listResult := h.Activations.ListByLicense(license.Id)
	if listResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to list activations")

		return
	}

	resp := licenseStatusResponse{
		License:     license,
		Activations: listResult.Value(),
	}

	jsonResponse(w, http.StatusOK, resp)
}
