package handlers

import (
	"net/http"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/enums/licensestatustype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/services"
)

// updateLicenseRequest is the JSON body for license update.
type updateLicenseRequest struct {
	Status         *string `json:"status"`
	Type           *string `json:"type"`
	MaxActivations *int    `json:"maxActivations"`
	Notes          *string `json:"notes"`
}

// UpdateLicense handles PATCH /admin/licenses/{id}.
func (h *AdminHandlers) UpdateLicense(w http.ResponseWriter, r *http.Request) {
	id, parseErr := extractIdParam(r)
	if parseErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid license id")

		return
	}

	var req updateLicenseRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid request body")

		return
	}

	input := buildUpdateInput(req)

	updateResult := h.Licenses.Update(id, input)
	if updateResult.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to update license")

		return
	}

	license := updateResult.Value()

	h.logAudit(r, &license.Id, auditactiontype.Updated, "")
	jsonResponse(w, http.StatusOK, license)
}

// buildUpdateInput converts a request into an UpdateInput.
func buildUpdateInput(req updateLicenseRequest) services.UpdateInput {
	var input services.UpdateInput

	if req.Status != nil {
		status := licensestatus.Parse(*req.Status)
		input.Status = &status
	}

	if req.Type != nil {
		ltype := licensetype.Parse(*req.Type)
		input.Type = &ltype
	}

	input.MaxActivations = req.MaxActivations
	input.Notes = req.Notes

	return input
}

// DeleteLicense handles DELETE /admin/licenses/{id}.
func (h *AdminHandlers) DeleteLicense(w http.ResponseWriter, r *http.Request) {
	id, parseErr := extractIdParam(r)
	if parseErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid license id")

		return
	}

	deleteErr := h.Licenses.Delete(id)
	if deleteErr != nil {
		errorResponse(w, http.StatusInternalServerError, "failed to delete license")

		return
	}

	h.logAudit(r, &id, auditaction.Deleted, "")
	jsonResponse(w, http.StatusOK, statusBody{Status: "deleted"})
}
