package wordpress

// resolveNamespace probes the remote site for the active uploader namespace
// and returns it. Falls back to RiseupAsiaNamespace if none is detected.
func (c *Client) resolveNamespace() string {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		return RiseupAsiaNamespace
	}
	return namespace
}
