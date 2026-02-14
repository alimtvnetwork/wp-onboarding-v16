// Package handlers - Adapter helper functions for input conversion
package handlers

// getString extracts a string from a map by key
func getString(m map[string]any, key string) string {
	if v, ok := m[key].(string); ok {
		return v
	}
	return ""
}

// getBool extracts a bool from a map by key with a default value
func getBool(m map[string]any, key string, defaultVal bool) bool {
	if v, ok := m[key].(bool); ok {
		return v
	}
	return defaultVal
}

// getStringAny tries multiple keys and returns the first string found
func getStringAny(m map[string]any, keys ...string) string {
	for _, k := range keys {
		if v, ok := m[k].(string); ok {
			return v
		}
	}
	return ""
}

// getBoolAny tries multiple keys and returns the first bool found
func getBoolAny(m map[string]any, defaultVal bool, keys ...string) bool {
	for _, k := range keys {
		if v, ok := m[k].(bool); ok {
			return v
		}
	}
	return defaultVal
}

// getStringSliceAny tries multiple keys and returns the first string slice found
func getStringSliceAny(m map[string]any, keys ...string) []string {
	for _, k := range keys {
		if raw, ok := m[k]; ok {
			if ss, ok := raw.([]string); ok {
				return ss
			}
			if arr, ok := raw.([]any); ok {
				out := make([]string, 0, len(arr))
				for _, it := range arr {
					if s, ok := it.(string); ok {
						out = append(out, s)
					}
				}
				return out
			}
		}
	}
	return nil
}

// firstString tries multiple keys and returns the first string found with existence flag
func firstString(m map[string]any, keys ...string) (string, bool) {
	for _, k := range keys {
		if v, ok := m[k].(string); ok {
			return v, true
		}
	}
	return "", false
}

// firstBool tries multiple keys and returns the first bool found with existence flag
func firstBool(m map[string]any, keys ...string) (bool, bool) {
	for _, k := range keys {
		if v, ok := m[k].(bool); ok {
			return v, true
		}
	}
	return false, false
}

// firstStringSlice tries multiple keys and returns the first string slice found
func firstStringSlice(m map[string]any, keys ...string) ([]string, bool) {
	for _, k := range keys {
		if raw, ok := m[k]; ok {
			if ss, ok := raw.([]string); ok {
				return ss, true
			}
			if arr, ok := raw.([]any); ok {
				out := make([]string, 0, len(arr))
				for _, it := range arr {
					if s, ok := it.(string); ok {
						out = append(out, s)
					}
				}
				return out, true
			}
		}
	}
	return nil, false
}
