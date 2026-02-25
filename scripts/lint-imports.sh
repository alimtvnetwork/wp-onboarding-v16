#!/usr/bin/env bash
# =============================================================================
# Go Import Grouping Lint — Enforce 3-group import blocks
#
# Validates that Go files with imports follow the standard grouping:
#   Group 1: stdlib      (no dots in path)
#   Group 2: third-party (has dots, not the module prefix)
#   Group 3: internal    (starts with the module prefix)
#
# Groups must be separated by blank lines and appear in order.
#
# Usage:
#   ./scripts/lint-imports.sh                  # scan default backend dir
#   ./scripts/lint-imports.sh --dir path/to/go # scan specific directory
#   ./scripts/lint-imports.sh --include-tests  # include _test.go files
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
MODULE_PREFIX=""

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)           SCAN_DIR="$2"; shift 2 ;;
    --include-tests) INCLUDE_TESTS=true; shift ;;
    -h|--help)
      head -19 "$0" | tail -16
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Detect module prefix from go.mod
# ---------------------------------------------------------------------------

detect_module() {
  local gomod="$SCAN_DIR/go.mod"
  if [[ -f "$gomod" ]]; then
    MODULE_PREFIX=$(head -1 "$gomod" | awk '{print $2}')
  fi
  if [[ -z "$MODULE_PREFIX" ]]; then
    echo -e "${YELLOW}⚠ Could not detect module prefix from go.mod${NC}"
    exit 0
  fi
}

# ---------------------------------------------------------------------------
# Classify an import path
# Returns: stdlib, third, internal
# ---------------------------------------------------------------------------

classify_import() {
  local path="$1"
  # Remove quotes
  path="${path%\"}"
  path="${path#\"}"

  if [[ "$path" == "$MODULE_PREFIX"* ]]; then
    echo "internal"
  elif [[ "$path" == *"."* ]]; then
    echo "third"
  else
    echo "stdlib"
  fi
}

# ---------------------------------------------------------------------------
# Check a single file's import block
# ---------------------------------------------------------------------------

check_file() {
  local file="$1"
  local in_import=false
  local current_group=""
  local prev_group=""
  local group_order=()
  local line_num=0
  local has_violation=false
  local blank_after_group=false

  while IFS= read -r line; do
    line_num=$((line_num + 1))

    # Detect import block start
    if [[ "$line" =~ ^import\ *\( ]]; then
      in_import=true
      group_order=()
      prev_group=""
      blank_after_group=false
      continue
    fi

    # Detect import block end
    if $in_import && [[ "$line" =~ ^\) ]]; then
      in_import=false
      # Validate group ordering
      validate_order "$file" "${group_order[@]}"
      continue
    fi

    if ! $in_import; then
      continue
    fi

    # Blank line = group separator
    local stripped="${line// /}"
    stripped="${stripped//$'\t'/}"
    if [[ -z "$stripped" ]]; then
      blank_after_group=true
      continue
    fi

    # Extract import path (handle aliased imports: alias "path")
    local import_path=""
    if [[ "$line" =~ \"([^\"]+)\" ]]; then
      import_path="${BASH_REMATCH[1]}"
    else
      continue
    fi

    current_group=$(classify_import "\"$import_path\"")

    # Check: if same group as prev but separated by blank line = split group
    if [[ -n "$prev_group" ]] && [[ "$current_group" == "$prev_group" ]] && $blank_after_group; then
      echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}Split ${current_group} group${NC} — merge into one block"
      VIOLATIONS=$((VIOLATIONS + 1))
    fi

    # Track group transitions
    if [[ "$current_group" != "$prev_group" ]]; then
      group_order+=("$current_group")
      # Check: new group must be separated by blank line (unless first group)
      if [[ -n "$prev_group" ]] && ! $blank_after_group; then
        echo -e "  ${RED}✗${NC} ${file}:${line_num}  ${YELLOW}Missing blank line${NC} before ${current_group} group"
        VIOLATIONS=$((VIOLATIONS + 1))
      fi
    fi

    prev_group="$current_group"
    blank_after_group=false
  done < "$file"
}

# ---------------------------------------------------------------------------
# Validate group order: must be stdlib → third → internal
# (any subset is fine, but order must be preserved)
# ---------------------------------------------------------------------------

validate_order() {
  local file="$1"
  shift
  local groups=("$@")

  # Deduplicate consecutive entries
  local deduped=()
  local prev=""
  for g in "${groups[@]}"; do
    if [[ "$g" != "$prev" ]]; then
      deduped+=("$g")
      prev="$g"
    fi
  done

  # Define expected order
  local order_map=( ["stdlib"]=1 ["third"]=2 ["internal"]=3 )
  local last_rank=0

  for g in "${deduped[@]}"; do
    local rank="${order_map[$g]}"
    if [[ "$rank" -lt "$last_rank" ]]; then
      echo -e "  ${RED}✗${NC} ${file}:  ${YELLOW}Wrong group order${NC} — expected stdlib → third-party → internal"
      VIOLATIONS=$((VIOLATIONS + 1))
      return
    fi
    last_rank="$rank"
  done
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

header "Go Import Grouping Lint (stdlib → third-party → internal)"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

detect_module

TOTAL_FILES=0

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))
  # Only check files that have multi-line import blocks
  if grep -q '^import (' "$file" 2>/dev/null; then
    check_file "$file"
  fi
done < <(eval "find $(build_find_args) | sort")

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
if [[ "$VIOLATIONS" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} Go files have correct import grouping${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} import grouping violation(s) found (${TOTAL_FILES} files scanned)${NC}"
  echo ""
  echo -e "${CYAN}Expected import order:${NC}"
  echo "  Group 1: stdlib       (\"fmt\", \"net/http\", ...)"
  echo "  Group 2: third-party  (\"github.com/...\", ...)"
  echo "  Group 3: internal     (\"${MODULE_PREFIX}/...\")"
  echo ""
  echo "  Separate groups with a single blank line."
  exit 1
fi
