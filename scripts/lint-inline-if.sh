#!/usr/bin/env bash
# =============================================================================
# Go Inline If Lint — Prohibit 'if err := ...; err != nil' patterns
#
# All error assignments must be on their own line, then checked separately:
#
#   ❌  if err := doThing(); err != nil {
#   ✅  err := doThing()
#       if err != nil {
#
# Also catches:  if _, err := ...; err != nil
#
# Usage:
#   ./scripts/lint-inline-if.sh                  # scan default backend dir
#   ./scripts/lint-inline-if.sh --dir path/to/go # scan specific directory
#   ./scripts/lint-inline-if.sh --include-tests  # include _test.go files
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

SCAN_DIR="backend"
INCLUDE_TESTS=false
VIOLATIONS=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)           SCAN_DIR="$2"; shift 2 ;;
    --include-tests) INCLUDE_TESTS=true; shift ;;
    -h|--help)
      head -20 "$0" | tail -17
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

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

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

header "Go Inline If Lint (prohibit 'if err := ...; err != nil')"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

TOTAL_FILES=0

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))

  line_num=0
  while IFS= read -r line; do
    line_num=$((line_num + 1))

    # Match: if err := ...; err != nil  OR  if _, err := ...; err != nil
    if [[ "$line" =~ ^[[:space:]]*if[[:space:]]+(.*:=.*;) ]]; then
      echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}Inline if-init${NC}"
      echo -e "    ${line}"
      VIOLATIONS=$((VIOLATIONS + 1))
    fi
  done < "$file"

done < <(eval "find $(build_find_args) | sort")

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} Go files are free of inline if-init patterns${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} inline if-init violation(s) found (${TOTAL_FILES} files scanned)${NC}"
  echo ""
  echo -e "${CYAN}Required pattern:${NC}"
  echo "  err := doThing()"
  echo "  if err != nil {"
  echo "      ..."
  echo "  }"
  echo ""
  echo -e "${CYAN}Prohibited:${NC}"
  echo "  if err := doThing(); err != nil {"
  echo "  if _, err := doThing(); err != nil {"
  exit 1
fi
