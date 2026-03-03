package handlers

import (
	"net/http"

	"github.com/gorilla/mux"

	"riseup-licensing/internal/enums/auditaction"
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

	license, getErr := h.Licenses.GetByKey(key)
	if getErr != nil {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

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
	activeCount, countErr := h.Activations.CountActive(licenseId)
	if countErr != nil {
		errorResponse(w, http.StatusInternalServerError, "failed to check activations")

		return
	}

	isAtLimit := activeCount >= maxActivations

	if isAtLimit {
		errorResponse(w, http.StatusConflict, "activation limit reached")

		return
	}

	activation, actErr := h.Activations.Activate(services.ActivateInput{
		LicenseId: licenseId,
		Domain:    domain,
		IpAddress: r.RemoteAddr,
		UserAgent: r.UserAgent(),
	})
	if actErr != nil {
		errorResponse(w, http.StatusInternalServerError, "activation failed")

		return
	}

	h.logPublicAudit(r, &licenseId, auditaction.Activated, domain)
	jsonResponse(w, http.StatusOK, activation)
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

	license, getErr := h.Licenses.GetByKey(key)
	if getErr != nil {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	deactErr := h.Activations.Deactivate(license.Id, req.Domain)
	if deactErr != nil {
		errorResponse(w, http.StatusInternalServerError, "deactivation failed")

		return
	}

	h.logPublicAudit(r, &license.Id, auditaction.Deactivated, req.Domain)
	jsonResponse(w, http.StatusOK, map[string]string{"status": "deactivated"})
}

// Status handles GET /licenses/{key}/status.
func (h *PublicHandlers) Status(w http.ResponseWriter, r *http.Request) {
	key := mux.Vars(r)["key"]

	license, getErr := h.Licenses.GetByKey(key)
	if getErr != nil {
		errorResponse(w, http.StatusNotFound, "license not found")

		return
	}

	activations, listErr := h.Activations.ListByLicense(license.Id)
	if listErr != nil {
		errorResponse(w, http.StatusInternalServerError, "failed to list activations")

		return
	}

	jsonResponse(w, http.StatusOK, map[string]any{
		"license":     license,
		"activations": activations,
	})
}
