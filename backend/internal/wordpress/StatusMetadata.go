package wordpress

import (
	"encoding/json"
	"fmt"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// StatusMetadata is the normalized metadata extracted from a plugin /status endpoint.
type StatusMetadata struct {
	Version       string
	WpVersion     string
	PhpVersion    string
	PluginName    string
	ApiNamespace  string
	ServerTime    string
	DbAvailable   string
	RemoteSiteUrl string
	Message       string
}

// GetStatusMetadataByNamespace fetches and normalizes the status payload for a specific namespace.
func (c *Client) GetStatusMetadataByNamespace(namespace string) apperror.Result[*StatusMetadata] {
	endpoint := BuildNamespacedEndpoint(namespace, ep.Status)

	rawResult := c.doApiCallRaw(ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetUploaderStatus,
		ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*StatusMetadata](rawResult.AppError())
	}

	metadata, err := parseStatusMetadata(rawResult.Value())
	if err != nil {
		return apperror.FailWrap[*StatusMetadata](err, apperror.ErrInternal, "decode plugin status metadata")
	}

	if metadata.ApiNamespace == "" {
		metadata.ApiNamespace = namespace
	}

	return apperror.Ok(metadata)
}

func parseStatusMetadata(data []byte) (*StatusMetadata, error) {
	var body any
	if err := json.Unmarshal(data, &body); err != nil {
		return nil, err
	}

	payload := getStatusPayload(body)
	metadata := &StatusMetadata{
		Version:       getStatusString(payload, "Version", "version"),
		WpVersion:     getStatusString(payload, "Wp", "WpVersion", "wp", "wpVersion", "wordpress_version"),
		PhpVersion:    getStatusString(payload, "Php", "PhpVersion", "php", "phpVersion", "php_version"),
		PluginName:    getStatusString(payload, "Plugin", "plugin"),
		ApiNamespace:  getStatusString(payload, "Api", "ApiNamespace", "api"),
		ServerTime:    getStatusString(payload, "ServerTime", "serverTime", "Timestamp", "timestamp"),
		DbAvailable:   getStatusString(payload, "DbAvailable", "dbAvailable"),
		RemoteSiteUrl: getStatusString(payload, "SiteUrl", "siteUrl"),
		Message:       getStatusString(payload, "Message", "message"),
	}

	if metadata.Version == "" {
		metadata.Version = getStatusString(body, "Version", "version")
	}

	return metadata, nil
}

func getStatusPayload(body any) any {
	obj, ok := body.(map[string]any)
	if !ok {
		return body
	}

	if results, exists := obj["Results"]; exists {
		switch value := results.(type) {
		case []any:
			if len(value) > 0 {
				return value[0]
			}
		default:
			return value
		}
	}

	if result, exists := obj["Result"]; exists {
		return result
	}

	return body
}

func getStatusString(obj any, keys ...string) string {
	mapped, ok := obj.(map[string]any)
	if !ok {
		return ""
	}

	for _, key := range keys {
		if value, exists := mapped[key]; exists {
			formatted := formatStatusValue(value)
			if formatted != "" {
				return formatted
			}
		}
	}

	return ""
}

func formatStatusValue(value any) string {
	switch typed := value.(type) {
	case nil:
		return ""
	case string:
		return typed
	case bool:
		if typed {
			return "True"
		}
		return "False"
	default:
		return fmt.Sprint(typed)
	}
}
