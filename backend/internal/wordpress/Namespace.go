package wordpress

// resolveNamespace probes the remote site for the active uploader namespace
// and returns it. Falls back to RiseupAsiaNamespace if none is detected.
func (c *Client) resolveNamespace() string {
	result, _ := c.CheckRiseupAsiaAvailable()

	if result.IsNamespaceMissing() {
		return RiseupAsiaNamespace
	}

	return result.Namespace
}
