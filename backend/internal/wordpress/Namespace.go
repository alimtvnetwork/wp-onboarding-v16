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
