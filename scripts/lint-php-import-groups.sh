#!/usr/bin/env bash
# =============================================================================
# PHP Import Grouping Lint — Enforce use-statement ordering
#
# Checks that PHP `use` statements follow the convention:
#   Group 1: Global/built-in types (no backslash)   — Throwable, PDO, etc.
#   Group 2: Namespaced imports                      — Plugin\Enums\*, etc.
# Separated by exactly one blank line.
#
# Usage:
#   ./scripts/lint-php-import-groups.sh                     # default: wp-plugins/
#   ./scripts/lint-php-import-groups.sh --dir wp-plugins/qupload
#   ./scripts/lint-php-import-groups.sh --verbose
#
# Exit codes:
#   0 = clean
#   1 = violations found
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
YELLOW='\033[0;33m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
DIM='\033[2m'
NC='\033[0m'

SCAN_DIR="wp-plugins"
VERBOSE=false
VIOLATIONS=0
TOTAL_FILES=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)     SCAN_DIR="$2"; shift 2 ;;
    --verbose) VERBOSE=true; shift ;;
    -h|--help)
      head -17 "$0" | tail -14
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

# ---------------------------------------------------------------------------
# Analyze a single PHP file for import grouping
# ---------------------------------------------------------------------------

analyze_file() {
  local file="$1"

  awk -v file="$file" -v verbose="$VERBOSE" '
  BEGIN {
    in_block = 0
    last_global_line = 0
    first_ns_line = 0
    has_globals = 0
    has_ns = 0
    seen_ns = 0
    order_violation = 0
    order_line = 0
    order_import = ""
    blank_count = 0
    block_start = 0
  }

  /^[[:space:]]*use [A-Za-z]/ && /;[[:space:]]*$/ {
    if (!in_block) {
      in_block = 1
      block_start = NR
    }

    # Extract symbol between "use " and ";"
    line = $0
    gsub(/^[[:space:]]*use[[:space:]]+/, "", line)
    gsub(/;[[:space:]]*$/, "", line)

    is_global = (index(line, "\\") == 0)

    if (is_global) {
      has_globals = 1
      last_global_line = NR
      if (seen_ns && !order_violation) {
        order_violation = 1
        order_line = NR
        order_import = $0
      }
    } else {
      has_ns = 1
      seen_ns = 1
      if (!first_ns_line) first_ns_line = NR
    }
    next
  }

  in_block && /^[[:space:]]*$/ {
    next
  }

  in_block {
    in_block = 0
  }

  END {
    if (order_violation) {
      printf "ORDER|%s|%d|%s\n", file, order_line, order_import
    }

    if (has_globals && has_ns) {
      gap = first_ns_line - last_global_line - 1
      if (gap != 1) {
        printf "SEPARATOR|%s|%d|expected 1 blank line between groups (found %d)\n", file, first_ns_line, gap
      }
    }

    if (!order_violation && verbose == "true" && (has_globals || has_ns)) {
      printf "OK|%s|%d|imports grouped correctly\n", file, block_start
    }
  }
  ' "$file"
}

# ---------------------------------------------------------------------------
# Main scan
# ---------------------------------------------------------------------------

header "PHP Import Grouping Lint"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))
  result=$(analyze_file "$file")

  if [[ -n "$result" ]]; then
    while IFS='|' read -r kind filepath line_num detail; do
      case "$kind" in
        *ORDER*)
          echo -e "  ${RED}✗${NC} ${filepath}:${line_num}  ${YELLOW}order${NC}: global import after namespaced — ${detail}"
          VIOLATIONS=$((VIOLATIONS + 1))
          ;;
        *SEPARATOR*)
          echo -e "  ${RED}✗${NC} ${filepath}:${line_num}  ${YELLOW}separator${NC}: ${detail}"
          VIOLATIONS=$((VIOLATIONS + 1))
          ;;
        *OK*)
          if [[ "$VERBOSE" == "true" ]]; then
            echo -e "  ${DIM}✓ ${filepath}:${line_num}  ${detail}${NC}"
          fi
          ;;
      esac
    done <<< "$result"
  fi
done < <(find "$SCAN_DIR" -type f -name '*.php' \
  -not -path '*/vendor/*' \
  -not -path '*/.git/*' \
  -not -path '*/node_modules/*' \
  -not -path '*/plugins-onboard/*' \
  | sort)

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} PHP files have correctly grouped imports${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} import grouping violation(s) in ${TOTAL_FILES} files${NC}"
  echo ""
  echo -e "${CYAN}Convention:${NC}"
  echo "  Group 1: Global types     (Throwable, PDO, Exception, WP_REST_Request, ...)"
  echo "           ↕ blank line"
  echo "  Group 2: Namespaced       (Plugin\\Enums\\*, Plugin\\Helpers\\*, ...)"
  exit 1
fi
