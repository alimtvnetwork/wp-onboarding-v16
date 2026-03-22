// Typed response structs for the site health summary endpoint.
// Matches the PHP plugin's SiteHealthSummaryTrait output and frontend's SiteHealthSummaryResponse.
package wordpress

// HealthSummaryData is the typed response from the PHP site-health-summary endpoint.
type HealthSummaryData struct {
	System          HealthSystem       `json:"system"`
	Plugins         HealthPlugins      `json:"plugins"`
	Integrations    HealthIntegrations `json:"integrations"`
	Users           HealthUsers        `json:"users"`
	Database        HealthDatabase     `json:"database"`
	PluginOutdated  bool               `json:"pluginOutdated,omitempty"`
	OutdatedMessage string             `json:"outdatedMessage,omitempty"`
}

// BuildOutdatedHealthSummary returns a HealthSummaryData indicating the remote plugin is outdated.
func BuildOutdatedHealthSummary() *HealthSummaryData {
	return &HealthSummaryData{
		PluginOutdated:  true,
		OutdatedMessage: "The remote plugin needs to be updated to v2.31.0+ to support health summary.",
	}
}

// BuildHealthSummaryFromStatus builds a partial HealthSummaryData from the /status endpoint metadata.
// Used as fallback when /site-health-summary is not available but /status works.
func BuildHealthSummaryFromStatus(meta *StatusMetadata) *HealthSummaryData {
	dbAvail := meta.DbAvailable == "True" || meta.DbAvailable == "true" || meta.DbAvailable == "1"

	return &HealthSummaryData{
		System: HealthSystem{
			PhpVersion: meta.PhpVersion,
			WpVersion:  meta.WpVersion,
		},
		Database: HealthDatabase{},
		PluginOutdated:  !dbAvail && meta.DbAvailable != "",
		OutdatedMessage: "",
	}
}

// HealthSystem holds PHP/WP system information.
type HealthSystem struct {
	PhpVersion       string `json:"phpVersion"`
	WpVersion        string `json:"wpVersion"`
	MemoryLimit      string `json:"memoryLimit"`
	MemoryUsage      string `json:"memoryUsage"`
	MemoryPeak       string `json:"memoryPeak"`
	UploadMaxFilesize string `json:"uploadMaxFilesize"`
	PostMaxSize      string `json:"postMaxSize"`
	MaxExecutionTime int    `json:"maxExecutionTime"`
	DiskFree         string `json:"diskFree"`
	DiskTotal        string `json:"diskTotal"`
	DiskFreeBytes    int64  `json:"diskFreeBytes"`
	DiskTotalBytes   int64  `json:"diskTotalBytes"`
	ServerSoftware   string `json:"serverSoftware"`
	SslEnabled       bool   `json:"sslEnabled"`
	IsMultisite      bool   `json:"isMultisite"`
	Timezone         string `json:"timezone"`
	WpDebug          bool   `json:"wpDebug"`
	WpDebugLog       bool   `json:"wpDebugLog"`
}

// HealthPlugins holds plugin count information.
type HealthPlugins struct {
	Total    int `json:"total"`
	Active   int `json:"active"`
	Inactive int `json:"inactive"`
}

// HealthIntegrations holds third-party integration status.
type HealthIntegrations struct {
	WpReset     HealthWpReset     `json:"wpReset"`
	UpdraftPlus HealthUpdraftPlus `json:"updraftPlus"`
}

// HealthWpReset holds WP Reset plugin status.
type HealthWpReset struct {
	Available bool `json:"available"`
	IsPro     bool `json:"isPro"`
	Snapshots int  `json:"snapshots"`
}

// HealthUpdraftPlus holds UpdraftPlus plugin status.
type HealthUpdraftPlus struct {
	Available bool `json:"available"`
	Backups   int  `json:"backups"`
}

// HealthUsers holds user count information.
type HealthUsers struct {
	Total  int            `json:"total"`
	ByRole map[string]int `json:"byRole"`
}

// HealthDatabase holds database information.
type HealthDatabase struct {
	TableCount int    `json:"tableCount"`
	TotalSize  string `json:"totalSize"`
	TotalBytes int64  `json:"totalBytes"`
	Prefix     string `json:"prefix"`
}

// SiteSettingsData is the typed response from the PHP site-settings GET endpoint.
type SiteSettingsData struct {
	SearchEngineVisible bool   `json:"searchEngineVisible"`
	WpDebug             bool   `json:"wpDebug"`
	WpDebugLog          bool   `json:"wpDebugLog"`
	WpDebugDisplay      bool   `json:"wpDebugDisplay"`
	UploadMaxFilesize   string `json:"uploadMaxFilesize"`
	PostMaxSize         string `json:"postMaxSize"`
	MemoryLimit         string `json:"memoryLimit"`
	MaxExecutionTime    int    `json:"maxExecutionTime"`
	MaxInputVars        int    `json:"maxInputVars"`
	WpConfigWritable    bool   `json:"wpConfigWritable"`
	HtaccessWritable    bool   `json:"htaccessWritable"`
	PhpVersion          string `json:"phpVersion"`
	WpVersion           string `json:"wpVersion"`
	SiteUrl             string `json:"siteUrl"`
	HomeUrl             string `json:"homeUrl"`
	IsMultisite         bool   `json:"isMultisite"`
	Timezone            string `json:"timezone"`
	ActiveTheme         string `json:"activeTheme"`
	ServerSoftware      string `json:"serverSoftware"`
	PluginOutdated      bool   `json:"pluginOutdated,omitempty"`
	OutdatedMessage     string `json:"outdatedMessage,omitempty"`
}

// BuildOutdatedSiteSettings returns a graceful fallback when the remote plugin lacks the site-settings endpoint.
func BuildOutdatedSiteSettings() *SiteSettingsData {
	return &SiteSettingsData{
		PluginOutdated:  true,
		OutdatedMessage: "Remote plugin is outdated — the /site-settings endpoint is not available. Please update the plugin using Deploy Uploader.",
	}
}

// SiteSettingsUpdateResult is the typed response from the PHP site-settings PUT endpoint.
type SiteSettingsUpdateResult struct {
	IsSuccess bool              `json:"success"`
	Updated   map[string]any    `json:"updated"` // justified: dynamic key-value pairs from PHP
	Settings  SiteSettingsData  `json:"settings"`
	Warnings  []string          `json:"warnings,omitempty"`
}
