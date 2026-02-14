package wordpress

// PluginStatusType represents WordPress plugin status values.
type PluginStatusType string

const (
	// PluginStatusActive is the active status for a plugin.
	PluginStatusActive PluginStatusType = "active"

	// PluginStatusInactive is the inactive status for a plugin.
	PluginStatusInactive PluginStatusType = "inactive"
)

// IsEqual checks type-safe equality against another PluginStatusType.
func (p PluginStatusType) IsEqual(other PluginStatusType) bool {
	return p == other
}

// String returns the raw string value.
func (p PluginStatusType) String() string {
	return string(p)
}

// IsActive returns true if the plugin status is Active.
func (p PluginStatusType) IsActive() bool {
	return p.IsEqual(PluginStatusActive)
}
