#!/usr/bin/env bash
# =============================================================================
# Go File Size Lint — Max 300 lines per .go file
#
# Scans all .go files (excluding vendor, generated, _test.go, and e2e)
# and reports any file exceeding 300 lines.
#
# Usage:
#   ./scripts/lint-file-size.sh                  # scan default backend dir
#   ./scripts/lint-file-size.sh --dir path/to/go # scan specific directory
#   ./scripts/lint-file-size.sh --max 250        # custom line limit
#   ./scripts/lint-file-size.sh --include-tests  # include _test.go files
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
NC='\033[0m'

MAX_LINES=300
SCAN_DIR="backend"
INCLUDE_TESTS=false
VIOLATIONS=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)         SCAN_DIR="$2"; shift 2 ;;
    --max)         MAX_LINES="$2"; shift 2 ;;
    --include-tests) INCLUDE_TESTS=true; shift ;;
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

violation() {
  local file="$1" lines="$2"
  echo -e "  ${RED}✗${NC} ${file}  ${YELLOW}${lines} lines${NC}  (max ${MAX_LINES})"
  VIOLATIONS=$((VIOLATIONS + 1))
}

# ---------------------------------------------------------------------------
# Build find command
# ---------------------------------------------------------------------------

build_find_args() {
  local args=( "$SCAN_DIR" -type f -name '*.go' )
  args+=( -not -path '*/vendor/*' )
  args+=( -not -path '*/_generated/*' )
  args+=( -not -path '*/e2e/*' )
  args+=( -not -path '*/.git/*' )

  if [[ "$INCLUDE_TESTS" == "false" ]]; then
    args+=( -not -name '*_test.go' )
  fi

  echo "${args[@]}"
}

# ---------------------------------------------------------------------------
# Main scan
# ---------------------------------------------------------------------------

header "Go File Size Lint (max ${MAX_LINES} lines)"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  echo "  Use --dir to specify the Go source directory"
  exit 0
fi

TOTAL_FILES=0
OVERSIZED_FILES=()

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))
  line_count=$(wc -l < "$file")

  if [[ "$line_count" -gt "$MAX_LINES" ]]; then
    violation "$file" "$line_count"
    OVERSIZED_FILES+=("$file")
  fi
done < <(eval "find $(build_find_args) | sort")

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} Go files are within ${MAX_LINES}-line limit${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} file(s) exceed ${MAX_LINES}-line limit (out of ${TOTAL_FILES} scanned)${NC}"
  echo ""
  echo -e "${CYAN}Splitting patterns:${NC}"
  echo "  entity.go             — struct + constructors"
  echo "  entity_crud.go        — DB operations"
  echo "  entity_validation.go  — validation logic"
  echo "  entity_helpers.go     — private utilities"
  exit 1
fi
