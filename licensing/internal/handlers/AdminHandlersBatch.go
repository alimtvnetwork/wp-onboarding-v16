package handlers

import (
	"encoding/csv"
	"fmt"
	"net/http"
	"strconv"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/models"
)

// batchIdsRequest is the JSON body for batch operations.
type batchIdsRequest struct {
	Ids  []int64 `json:"ids"`
	Days int     `json:"days,omitempty"`
}

// batchResultResponse is the JSON response for batch operations.
type batchResultResponse struct {
	Affected int `json:"affected"`
}

// BatchRevoke handles POST /admin/licenses/batch/revoke.
func (h *AdminHandlers) BatchRevoke(w http.ResponseWriter, r *http.Request) {
	var req batchIdsRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid request body")

		return
	}

	isEmpty := len(req.Ids) == 0

	if isEmpty {
		errorResponse(w, http.StatusBadRequest, "ids are required")

		return
	}

	result := h.Licenses.BatchRevoke(req.Ids)
	if result.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to batch revoke")

		return
	}

	for _, id := range req.Ids {
		idCopy := id
		h.logAudit(r, &idCopy, auditactiontype.Revoked, "")
	}

	jsonResponse(w, http.StatusOK, batchResultResponse{Affected: result.Value()})
}

// BatchExtend handles POST /admin/licenses/batch/extend.
func (h *AdminHandlers) BatchExtend(w http.ResponseWriter, r *http.Request) {
	var req batchIdsRequest

	decodeErr := decodeJSON(r, &req)
	if decodeErr != nil {
		errorResponse(w, http.StatusBadRequest, "invalid request body")

		return
	}

	isEmpty := len(req.Ids) == 0

	if isEmpty {
		errorResponse(w, http.StatusBadRequest, "ids are required")

		return
	}

	days := req.Days
	isDefaultDays := days <= 0

	if isDefaultDays {
		days = 30
	}

	result := h.Licenses.BatchExtend(req.Ids, days)
	if result.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to batch extend")

		return
	}

	for _, id := range req.Ids {
		idCopy := id
		h.logAudit(r, &idCopy, auditactiontype.Updated, "")
	}

	jsonResponse(w, http.StatusOK, batchResultResponse{Affected: result.Value()})
}

// ExportCSV handles GET /admin/licenses/export.
func (h *AdminHandlers) ExportCSV(w http.ResponseWriter, r *http.Request) {
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

	w.Header().Set("Content-Type", "text/csv")
	w.Header().Set("Content-Disposition", "attachment; filename=licenses-export.csv")

	writer := csv.NewWriter(w)

	header := []string{"ID", "Key", "Email", "Product", "Type", "Status", "MaxActivations", "Created", "Expires"}
	writer.Write(header) //nolint:errcheck

	for _, l := range licenses {
		expiresAt := ""
		hasExpiry := l.ExpiresAt != nil

		if hasExpiry {
			expiresAt = l.ExpiresAt.Format("2006-01-02T15:04:05Z")
		}

		row := []string{
			strconv.FormatInt(l.Id, 10),
			l.Key,
			l.Email,
			l.Product.String(),
			l.Type.String(),
			l.Status.String(),
			fmt.Sprintf("%d", l.MaxActivations),
			l.CreatedAt.Format("2006-01-02T15:04:05Z"),
			expiresAt,
		}
		writer.Write(row) //nolint:errcheck
	}

	writer.Flush()
}

// GetStats handles GET /admin/licenses/stats.
func (h *AdminHandlers) GetStats(w http.ResponseWriter, r *http.Request) {
	result := h.Licenses.Stats()
	if result.HasError() {
		errorResponse(w, http.StatusInternalServerError, "failed to get stats")

		return
	}

	jsonResponse(w, http.StatusOK, result.Value())
}
