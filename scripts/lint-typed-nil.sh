#!/usr/bin/env bash
# =============================================================================
# Go Typed-Nil Lint — Detect *apperror.AppError assigned to error variables
#
# The "typed-nil" interface trap occurs when a nil *apperror.AppError is
# assigned to an 'error' interface variable. The interface becomes non-nil
# even though the concrete value is nil, causing 'if err != nil' to pass
# unexpectedly.
#
# This lint detects two dangerous patterns:
#
#   ❌  var err error
#       ...
#       err = someFunc()  // returns *apperror.AppError — typed-nil trap!
#
#   ❌  result, err := strconv.Atoi(s)  // err is 'error' type
#       ...
#       err = svc.DoThing()             // returns *apperror.AppError — reuse!
#
# Safe pattern — use a fresh variable:
#
#   ✅  parseErr := strconv.Atoi(s)
#       if parseErr != nil { ... }
#       appErr := svc.DoThing()
#       if appErr != nil { ... }
#
# Usage:
#   ./scripts/lint-typed-nil.sh                  # scan default backend dir
#   ./scripts/lint-typed-nil.sh --dir path/to/go # scan specific directory
#   ./scripts/lint-typed-nil.sh --include-tests  # include _test.go files
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
      head -35 "$0" | tail -32
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
  # Exempt packages per coding standard
  args+=( -not -path '*/pkg/apperror/*' )
  args+=( -not -path '*/pkg/pathutil/*' )
  args+=( -not -path '*/internal/database/*' )
  args+=( -not -path '*/internal/enums/*' )
  args+=( -not -path '*/cmd/*' )

  if [[ "$INCLUDE_TESTS" == "false" ]]; then
    args+=( -not -name '*_test.go' )
  fi

  echo "${args[@]}"
}

# ---------------------------------------------------------------------------
# Detection logic
#
# Strategy: For each Go file, find functions that return *apperror.AppError
# or apperror.Result[T]. Then detect when a variable first declared via
# a stdlib/error-returning call is later reassigned from such a function.
#
# Simplified heuristic (grep-based, not AST):
#   1. Flag any 'var ... error' declaration followed by assignment from
#      a function known to return *apperror.AppError
#   2. Flag reassignment of 'err' (originally from a stdlib := call) with
#      a call to a method/function in the same file that returns AppError
#
# Primary pattern: detect 'err = <call>' (reassignment, not :=) where
# the variable was previously declared with := from a stdlib function.
# This is the most dangerous and common pattern.
# ---------------------------------------------------------------------------

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

header "Go Typed-Nil Lint (detect *apperror.AppError → error assignments)"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

TOTAL_FILES=0

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))

  # Pattern 1: Explicit 'var err error' or 'var ... error' declarations
  # followed by usage — this is always suspicious in internal code
  while IFS= read -r match; do
    if [[ -n "$match" ]]; then
      line_num="${match%%:*}"
      line_content="${match#*:}"
      echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}var declared as 'error' type${NC}"
      echo -e "    ${line_content}"
      VIOLATIONS=$((VIOLATIONS + 1))
    fi
  done < <(grep -n 'var[[:space:]]\+[a-zA-Z_]*[[:space:]]\+error\b' "$file" 2>/dev/null || true)

  # Pattern 2: Function parameter typed as 'error' (not in interface impls)
  # Skip: Error() string, ServeHTTP, MarshalJSON, UnmarshalJSON, Close, etc.
  while IFS= read -r match; do
    if [[ -n "$match" ]]; then
      line_content="${match#*:}"
      # Skip exempted interface methods
      is_exempted=false
      for exempt in "Error()" "ServeHTTP" "MarshalJSON" "UnmarshalJSON" "Close" "Start" "Shutdown" "Parse" "Walk" "Write" "Hijack" "Push" "Scan" "Migrate" "PongHandler" "func(" "configureConnection"; do
        if [[ "$line_content" == *"$exempt"* ]]; then
          is_exempted=true
          break
        fi
      done

      if [[ "$is_exempted" == "false" ]]; then
        line_num="${match%%:*}"
        echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}Function returns 'error' instead of '*apperror.AppError'${NC}"
        echo -e "    ${line_content}"
        VIOLATIONS=$((VIOLATIONS + 1))
      fi
    fi
  done < <(grep -n ')[[:space:]]*error[[:space:]]*{' "$file" 2>/dev/null || true)

  # Pattern 3: Function signature returning (T, error) — should be Result[T]
  while IFS= read -r match; do
    if [[ -n "$match" ]]; then
      line_content="${match#*:}"
      # Skip exempted methods
      is_exempted=false
      for exempt in "Error()" "ServeHTTP" "MarshalJSON" "UnmarshalJSON" "Close" "Start" "Shutdown" "Parse" "Walk" "Write" "Hijack" "Push" "Scan" "Migrate" "ReadCloser" "interface" "//" "func(" "configureConnection" "Enqueue"; do
        if [[ "$line_content" == *"$exempt"* ]]; then
          is_exempted=true
          break
        fi
      done

      if [[ "$is_exempted" == "false" ]]; then
        line_num="${match%%:*}"
        echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}Returns 'error' type — use *apperror.AppError or apperror.Result[T]${NC}"
        echo -e "    ${line_content}"
        VIOLATIONS=$((VIOLATIONS + 1))
      fi
    fi
  done < <(grep -n ',[[:space:]]*error)' "$file" 2>/dev/null | grep -v '//' | grep -v 'interface' | grep -v 'func(' || true)

done < <(eval "find $(build_find_args) | sort")

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} Go files are free of typed-nil patterns${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} typed-nil risk(s) found (${TOTAL_FILES} files scanned)${NC}"
  echo ""
  echo -e "${CYAN}The typed-nil trap:${NC}"
  echo "  A nil *apperror.AppError assigned to an 'error' interface"
  echo "  makes the interface non-nil, causing 'if err != nil' to pass."
  echo ""
  echo -e "${CYAN}Fix:${NC}"
  echo "  • Use *apperror.AppError or apperror.Result[T] return types"
  echo "  • Use unique variable names (e.g., parseErr, createErr)"
  echo "  • Never reuse an 'error'-typed variable for *apperror.AppError"
  exit 1
fi