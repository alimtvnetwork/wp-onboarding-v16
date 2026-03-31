// Typed response structs for the /logs/dedup-registry endpoint.
package wordpress

// DedupRegistryPhpResponse is the raw response from the PHP /logs/dedup-registry endpoint.
type DedupRegistryPhpResponse struct {
	Success       bool                  `json:"Success"`
	DedupRegistry DedupRegistryData     `json:"DedupRegistry"`
	Message       string                `json:"Message,omitempty"`
}

// DedupRegistryData holds dedup registry metadata and entries.
type DedupRegistryData struct {
	Exists        bool     `json:"Exists"`
	Version       *string  `json:"Version"`
	EntryCount    int      `json:"EntryCount"`
	FileSizeBytes int64    `json:"FileSizeBytes"`
	Entries       []string `json:"Entries"`
}

// DedupRegistryClearPhpResponse is the raw response from DELETE /logs/dedup-registry.
type DedupRegistryClearPhpResponse struct {
	Success            bool   `json:"Success"`
	Message            string `json:"Message"`
	PreviousEntryCount int    `json:"PreviousEntryCount"`
}

// DedupRegistryResult is the combined response sent to the React frontend.
// Contains results from both plugin namespaces probed in parallel.
type DedupRegistryResult struct {
	Plugins []PluginDedupRegistryData `json:"plugins"`
}

// PluginDedupRegistryData holds dedup registry info for a single plugin namespace.
type PluginDedupRegistryData struct {
	Namespace     string             `json:"namespace"`
	Label         string             `json:"label"`
	Available     bool               `json:"available"`
	DedupRegistry *DedupRegistryData `json:"dedupRegistry,omitempty"`
}

// DedupRegistryClearResult is the combined response for clearing dedup registries.
type DedupRegistryClearResult struct {
	Plugins []PluginDedupClearData `json:"plugins"`
}

// PluginDedupClearData holds clear results for a single plugin namespace.
type PluginDedupClearData struct {
	Namespace          string `json:"namespace"`
	Label              string `json:"label"`
	Cleared            bool   `json:"cleared"`
	PreviousEntryCount int    `json:"previousEntryCount"`
}
