package wordpress

// resolveNamespace probes the remote site for the active uploader namespace
// and returns it. Falls back to RiseupAsiaNamespace if none is detected.
func (c *Client) resolveNamespace() string {
	result := c.CheckRiseupAsiaAvailable()

	if result.HasError() || result.Value().IsNamespaceMissing() {
		return RiseupAsiaNamespace
	}

	return result.Value().Namespace
}

// ResolveNamespace returns the active uploader namespace for cross-package callers.
// Falls back to RiseupAsiaNamespace when probing fails.
func (c *Client) ResolveNamespace() string {
	return c.resolveNamespace()
}
