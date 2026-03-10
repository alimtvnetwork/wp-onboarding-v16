package wordpress

import (
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// RemoteBackupResult holds the response from the remote plugin backup endpoint.
type RemoteBackupResult struct {
	Success  bool   `json:"success"`            // external key (Riseup Asia Uploader API)
	Message  string `json:"message,omitempty"`   // external key
	Filename string `json:"filename,omitempty"`  // external key
	Size     int64  `json:"size,omitempty"`      // external key
	Count    int    `json:"count,omitempty"`     // external key — backup count after creation
}

// CreateRemoteBackup triggers a remote plugin backup on the WordPress site.
func (c *Client) CreateRemoteBackup(slug string) apperror.Result[RemoteBackupResult] {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginBackup.String()

	return doApiCall[RemoteBackupResult](c, apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  operationtype.RemotePluginBackup,
		PluginSlug: normalizedSlug,
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
		ErrorCode:  apperror.ErrWPConnection,
	})
}
