package wordpress

// resolveNamespace probes the remote site for the active uploader namespace
// and returns it. Prefer Riseup Asia when available, otherwise fall back to
// QUpload so shared management endpoints keep working on sites where only
// QUpload is installed. If neither plugin can be confirmed, fall back to the
// primary Riseup namespace for deterministic error reporting.
func (c *Client) resolveNamespace() string {
	riseupResult := c.CheckRiseupAsiaAvailable()
	if !riseupResult.HasError() {
		riseup := riseupResult.Value()
		if riseup != nil && riseup.HasNamespace() {
			return riseup.Namespace
		}
	}

	quploadResult := c.CheckQUploadAvailable()
	if !quploadResult.HasError() {
		qupload := quploadResult.Value()
		if qupload != nil && qupload.HasNamespace() {
			return qupload.Namespace
		}
	}

	return RiseupAsiaNamespace
}

// ResolveNamespace returns the active uploader namespace for cross-package callers.
// Falls back to RiseupAsiaNamespace when probing fails.
func (c *Client) ResolveNamespace() string {
	return c.resolveNamespace()
}
