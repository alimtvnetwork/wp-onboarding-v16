package handlers

import (
	"net/http"

	"github.com/gorilla/mux"

	"riseup-licensing/internal/enums/auditaction"
	"riseup-licensing/internal/services"
)

// PublicHandlers holds dependencies for public license validation endpoints.
type PublicHandlers struct {
	Licenses    *services.LicenseService
	Activations *services.ActivationService
	Audit       *services.AuditService
}

// validateResponse is the JSON response for license validation.
type validateResponse struct {
	Valid      bool   `json:"valid"`
	Status     string `json:"status"`
	Product    string `json:"product"`
	Type       string `json:"type"`
	Activations int  `json:"activations"`
	MaxActivations int `json:"maxActivations"`
}

// Validate handles GET /licenses/{key}/validate.
func (h *PublicHandlers) Validate(w http.ResponseWriter, r *http.Request) {
	key := mux.Vars(r)["key"]

	license, getErr := h.Licenses.GetByKey(key)
	if getErr != nil {
		jsonResponse(w, http.StatusOK, validateResponse{Valid: false, Status: "not_found"})

		return
	}

	activeCount, countErr := h.Activations.CountActive(license.Id)
	if countErr != nil {
		errorResponse(w, http.StatusInternalServerError, "failed to check activations")

		return
	}

	h.logPublicAudit(r, &license.Id, auditaction.Validated, "")

	jsonResponse(w, http.StatusOK, validateResponse{
		Valid:          license.IsUsable(),
		Status:         license.Status.String(),
		Product:        license.Product.String(),
		Type:           license.Type.String(),
		Activations:    activeCount,
		MaxActivations: license.MaxActivations,
	})
}

// logPublicAudit is a convenience wrapper for public endpoint audit logging.
func (h *PublicHandlers) logPublicAudit(
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
