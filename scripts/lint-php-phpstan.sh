#!/usr/bin/env bash
# =============================================================================
# lint-php-phpstan.sh — Run PHPStan static analysis on wp-plugins
#
# Checks each plugin directory under wp-plugins/ for a phpstan.neon config.
# If found and PHPStan is available (vendor/bin or global), runs analysis.
#
# Exit codes:
#   0 = all checks pass (or skipped)
#   1 = PHPStan found errors
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WP_PLUGINS_DIR="$ROOT_DIR/wp-plugins"

FAILURES=0

if [[ ! -d "$WP_PLUGINS_DIR" ]]; then
  exit 0
fi

for plugin_dir in "$WP_PLUGINS_DIR"/*/; do
  config_file="$plugin_dir/phpstan.neon"

  if [[ ! -f "$config_file" ]]; then
    continue
  fi

  plugin_name=$(basename "$plugin_dir")

  # Resolve PHPStan binary
  phpstan_bin=""
  if [[ -x "$plugin_dir/vendor/bin/phpstan" ]]; then
    phpstan_bin="$plugin_dir/vendor/bin/phpstan"
  elif command -v phpstan &> /dev/null; then
    phpstan_bin="phpstan"
  else
    echo "  ⊘ $plugin_name: PHPStan not installed, skipping"
    continue
  fi

  output=$("$phpstan_bin" analyse --configuration "$config_file" --no-progress --error-format=raw 2>&1) || true
  exit_code=${PIPESTATUS[0]:-$?}

  if [[ $exit_code -ne 0 ]]; then
    echo "  ✗ $plugin_name: PHPStan errors found"
    echo "$output" | head -20
    FAILURES=$((FAILURES + 1))
  fi
done

if [[ "$FAILURES" -gt 0 ]]; then
  exit 1
fi

exit 0
