// Package handlers - Snapshot CRUD HTTP handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/wordpress"
)

// --- Snapshot Validation Helpers ---

// isSiteServiceMissing checks that the site service is available, responding with an error if not.
// Returns true if the service is missing (positive guard for failure).
func isSiteServiceMissing(w http.ResponseWriter) bool {
	isMissing :=
		Services == nil ||
		Services.SiteService == nil
	if isMissing {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", responsemessage.ServiceNotAvailable.String())

		return true
	}

	return false
}

// parseSiteIdOrFail extracts the site ID from URL params, responding with an error on failure.
func parseSiteIdOrFail(w http.ResponseWriter, r *http.Request) (int64, bool) {
	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", responsemessage.InvalidId.String())
		return 0, false
	}

	return siteId, true
}

// parseSnapshotIdOrFail extracts the snapshot ID from URL params, responding with an error on failure.
func parseSnapshotIdOrFail(w http.ResponseWriter, r *http.Request) (int64, bool) {
	snapshotId, err := getSnapshotIdParam(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", responsemessage.InvalidId.String())
		return 0, false
	}

	return snapshotId, true
}

// getSnapshotIdParam extracts the snapshot ID from URL parameters.
func getSnapshotIdParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)

	return strconv.ParseInt(vars["snapshotId"], 10, 64)
}

// --- Remote Snapshot CRUD Handlers ---

// GetRemoteSnapshots returns all snapshots from a remote WordPress site.
var GetRemoteSnapshots = handleSiteActionByID("E3020",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshots(ctx, siteId)
	},
)

// GetRemoteSnapshot returns a specific snapshot from a remote WordPress site.
func GetRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	snapshotId, ok := parseSnapshotIdOrFail(w, r)
	if !ok {
		return
	}

	snapshot, err := Services.SiteService.GetRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3021", err.Error())
		return
	}

	respondSuccess(w, snapshot)
}

// CreateRemoteSnapshot triggers a new snapshot on a remote WordPress site.
func CreateRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	var opts wordpress.SnapshotCreateOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.CreateRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3022", err.Error())
		return
	}

	respondCreated(w, result)
}

// DeleteRemoteSnapshot removes a snapshot from a remote WordPress site.
func DeleteRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	snapshotId, ok := parseSnapshotIdOrFail(w, r)
	if !ok {
		return
	}

	if err := Services.SiteService.DeleteRemoteSnapshot(r.Context(), siteId, snapshotId); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3023", err.Error())
		return
	}

	respondSuccess(w, SnapshotDeleteResponse{IsDeleted: true, SnapshotId: snapshotId})
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote WordPress site.
func RestoreRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	snapshotId, ok := parseSnapshotIdOrFail(w, r)
	if !ok {
		return
	}

	result, err := Services.SiteService.RestoreRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3024", err.Error())
		return
	}

	respondSuccess(w, result)
}
