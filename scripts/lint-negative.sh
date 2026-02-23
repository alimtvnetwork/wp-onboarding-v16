#!/usr/bin/env bash
# =============================================================================
# Go Negative Naming Lint — Flag IsNot*, HasNo* function declarations
#
# Scans all .go files and reports function declarations with negative naming
# patterns that violate the Positive Logic & Boolean Standards.
#
# Usage:
#   ./scripts/lint-negative.sh                  # scan default backend dir
#   ./scripts/lint-negative.sh --dir path/to/go # scan specific directory
#   ./scripts/lint-negative.sh --include-tests  # include _test.go files
#   ./scripts/lint-negative.sh --verbose        # show scanned file count
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
VERBOSE=false
VIOLATIONS=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)           SCAN_DIR="$2"; shift 2 ;;
    --include-tests) INCLUDE_TESTS=true; shift ;;
    --verbose)       VERBOSE=true; shift ;;
    -h|--help)
      head -15 "$0" | tail -12
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Negative naming patterns to detect
# ---------------------------------------------------------------------------

# Function declarations: func IsNot*, func HasNo*, func (x Type) IsNot*, etc.
# Exempt: enum variant checkers where the variant name is negative
#   e.g., IsNotFound() for a NotFound variant is acceptable

FUNC_PATTERN='func[[:space:]]+(\([^)]+\)[[:space:]]+)?(IsNot[A-Z]|HasNo[A-Z])[a-zA-Z]*\('

# Known exempt patterns (enum variant checkers)
EXEMPT_PATTERNS=(
  "IsNotFound"   # snapshot_error.NotFound variant
)

is_exempt() {
  local func_name="$1"
  for exempt in "${EXEMPT_PATTERNS[@]}"; do
    if [[ "$func_name" == *"$exempt"* ]]; then
      return 0
    fi
  done

  return 1
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

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

header "Go Negative Naming Lint"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  echo "  Use --dir to specify the Go source directory"
  exit 0
fi

TOTAL_FILES=0

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))

  while IFS= read -r match; do
    # Skip empty matches
    [[ -z "$match" ]] && continue

    # Check exemptions
    if is_exempt "$match"; then
      continue
    fi

    echo -e "  ${RED}✗${NC} ${file}: ${YELLOW}${match}${NC}"
    VIOLATIONS=$((VIOLATIONS + 1))
  done < <(grep -nE "$FUNC_PATTERN" "$file" 2>/dev/null || true)
done < <(eval "find $(build_find_args) | sort")

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VERBOSE" == "true" ]]; then
  echo -e "  Scanned ${TOTAL_FILES} files"
fi

if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ No negative naming violations found${NC}"
  echo ""
  echo -e "${CYAN}Rules enforced:${NC}"
  echo "  • No func IsNot*() declarations"
  echo "  • No func HasNo*() declarations"
  echo "  • Enum variant checkers (e.g., IsNotFound for NotFound) are exempt"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} negative naming violation(s) found${NC}"
  echo ""
  echo -e "${CYAN}Fix by using positive counterparts:${NC}"
  echo "  IsNotValid()     → IsInvalid()"
  echo "  IsNotReady()     → IsPending()"
  echo "  HasNoPermission() → IsRestricted()"
  exit 1
fi
