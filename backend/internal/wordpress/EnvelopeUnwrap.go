// EnvelopeUnwrap provides helpers to extract data from PHP envelope responses
// that would otherwise be double-wrapped when the Go handler adds its own envelope.
//
// See: spec/02-app-issues/34-double-envelope-wrapping-health-logs.md
package wordpress

// UnwrapPhpEnvelope extracts the inner data from a PHP envelope response.
// If the map contains a "Results" key (indicating a PHP envelope), it returns
// the first result for single-item responses or the full Results slice for lists.
// If the map is not an envelope, it is returned unchanged.
func UnwrapPhpEnvelope(data map[string]any) any {
	results, hasResults := data["Results"]
	if !hasResults {
		return data
	}

	attrs, hasAttrs := data["Attributes"]
	if !hasAttrs {
		return data
	}

	attrsMap, isMap := attrs.(map[string]any)
	if !isMap {
		return data
	}

	isSingle, _ := attrsMap["IsSingle"].(bool)
	resultSlice, isSlice := results.([]any)

	if !isSlice {
		return data
	}

	if isSingle && len(resultSlice) > 0 {
		return resultSlice[0]
	}

	return resultSlice
}
