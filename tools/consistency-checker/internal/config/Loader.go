// Package config loads and validates rules.json configuration.
package config

import (
	"encoding/json"
	"os"

	"consistency-checker/pkg/apperror"
)

// Config is the top-level configuration.
type Config struct {
	GlobalExclude []string   `json:"global_exclude"`
	Rules         []RuleSpec `json:"rules"`
}

// RuleSpec defines a single rule from rules.json.
type RuleSpec struct {
	ID        string            `json:"id"`
	Name      string            `json:"name"`
	IsEnabled bool              `json:"enabled"`
	Severity  string            `json:"severity"`
	Languages []string          `json:"languages"`
	Params    map[string]any    `json:"params"`
	Exclude   []string          `json:"exclude"`
	Reference string            `json:"reference"`
}

// Load reads and parses a rules.json config file.
func Load(path string) apperror.Result[Config] {
	data, err := os.ReadFile(path)
	if err != nil {
		return apperror.Fail[Config](apperror.Wrap(err, apperror.ErrConfig, "failed to read config").WithPath(path))
	}

	return parse(data)
}

// parse unmarshals JSON bytes into Config.
func parse(data []byte) apperror.Result[Config] {
	var cfg Config
	if err := json.Unmarshal(data, &cfg); err != nil {
		return apperror.Fail[Config](apperror.Wrap(err, apperror.ErrConfig, "invalid config JSON"))
	}

	return apperror.Ok(cfg)
}

// EnabledRules returns only rules where IsEnabled is true.
func (c *Config) EnabledRules() []RuleSpec {
	enabled := make([]RuleSpec, 0, len(c.Rules))
	for _, r := range c.Rules {
		if r.IsEnabled {
			enabled = append(enabled, r)
		}
	}
	return enabled
}

// ParamInt extracts an int parameter with a default.
func (r *RuleSpec) ParamInt(key string, defaultVal int) int {
	v, ok := r.Params[key]
	if !ok {
		return defaultVal
	}

	return coerceToInt(v, defaultVal)
}

// coerceToInt converts a JSON number to int.
func coerceToInt(v any, defaultVal int) int {
	switch n := v.(type) {
	case float64:
		return int(n)
	case int:
		return n
	default:
		return defaultVal
	}
}

// ParamString extracts a string parameter with a default.
func (r *RuleSpec) ParamString(key, defaultVal string) string {
	v, ok := r.Params[key]
	if !ok {
		return defaultVal
	}
	if s, ok := v.(string); ok {
		return s
	}
	return defaultVal
}
