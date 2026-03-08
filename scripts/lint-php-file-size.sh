#!/usr/bin/env bash
# =============================================================================
# lint-php-file-size.sh — Enforce PHP file size limit (≤500 lines).
#
# Usage:
#   bash scripts/lint-php-file-size.sh                  # default: wp-plugins/
#   bash scripts/lint-php-file-size.sh --dir wp-plugins/riseup-asia-uploader
#   bash scripts/lint-php-file-size.sh --limit 400      # custom limit
#
# Exit codes:
#   0 = all files pass
#   1 = one or more files exceed the limit
# =============================================================================

set -euo pipefail

LIMIT=500
SEARCH_DIR="wp-plugins"

# Parse arguments.
while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)   SEARCH_DIR="$2"; shift 2 ;;
    --limit) LIMIT="$2"; shift 2 ;;
    *)       shift ;;
  esac
done

# Skip directories that are exempt from the limit.
EXCLUDE_DIRS=("vendor" "node_modules" ".git")

EXCLUDE_ARGS=()
for dir in "${EXCLUDE_DIRS[@]}"; do
  EXCLUDE_ARGS+=(-not -path "*/${dir}/*")
done

VIOLATIONS=0

while IFS= read -r file; do
  lines=$(wc -l < "$file")
  if [[ "$lines" -gt "$LIMIT" ]]; then
    echo "  FAIL: ${file} (${lines} lines, limit ${LIMIT})"
    VIOLATIONS=$((VIOLATIONS + 1))
  fi
done < <(find "$SEARCH_DIR" -name '*.php' "${EXCLUDE_ARGS[@]}" -type f 2>/dev/null)

if [[ "$VIOLATIONS" -gt 0 ]]; then
  echo "  ${VIOLATIONS} PHP file(s) exceed ${LIMIT}-line limit"
  exit 1
fi

exit 0
