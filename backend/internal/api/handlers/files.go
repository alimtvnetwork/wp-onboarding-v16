// Package handlers provides file content handlers
package handlers

import (
	"crypto/md5"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"os"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// FileContentRequest is the request body for file content endpoints
type FileContentRequest struct {
	Path string `json:"path"`
}

// GetLocalFileContent returns the content of a local file in a plugin
func GetLocalFileContent(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	vars := mux.Vars(r)
	idStr := vars["id"]
	pluginID, err := strconv.ParseInt(idStr, 10, 64)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", wordpress.ResponseMessageInvalidId.String())
		return
	}

	// Parse request body
	var req FileContentRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidRequestBody.String())
		return
	}

	if req.Path == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "File path is required")
		return
	}

	// Get plugin to find its path — returns *models.Plugin with Path field
	pluginData, err := Services.PluginService.GetByID(r.Context(), pluginID)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E2001", wordpress.ResponseMessagePluginNotFound.String())
		return
	}

	pluginPath := pluginData.Path
	filePath, err := pathutil.Join(pluginPath, req.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrInternal, "failed to resolve file path: "+err.Error())
		return
	}

	content, err := readFileContent(filePath)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E2002", "File not found: "+err.Error())
		return
	}

	respondSuccess(w, FileContentResponse{
		Path:    req.Path,
		Content: content,
	})
}

// GetFileDiff returns both local and remote content for a file
func GetFileDiff(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil || Services.PublishService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	vars := mux.Vars(r)
	pluginIDStr := vars["id"]
	siteIDStr := vars["siteId"]
	
	pluginID, err := strconv.ParseInt(pluginIDStr, 10, 64)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", wordpress.ResponseMessageInvalidId.String())
		return
	}
	
	siteID, err := strconv.ParseInt(siteIDStr, 10, 64)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", wordpress.ResponseMessageInvalidId.String())
		return
	}

	// Parse request body
	var req FileContentRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidRequestBody.String())
		return
	}

	if req.Path == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "File path is required")
		return
	}

	// Get file diff via publish service
	result, err := Services.PublishService.GetFileDiff(r.Context(), pluginID, siteID, req.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E5001", "Failed to get file diff: "+err.Error())
		return
	}

	respondSuccess(w, result)
}

func readFileContent(filePath string) (string, error) {
	file, err := os.Open(filePath)
	if err != nil {
		return "", err
	}
	defer file.Close()

	content, err := io.ReadAll(file)
	if err != nil {
		return "", err
	}

	return string(content), nil
}

func calculateMD5(filePath string) (string, error) {
	file, err := os.Open(filePath)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}
