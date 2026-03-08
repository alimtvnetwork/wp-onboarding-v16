#!/usr/bin/env bash
# =============================================================================
# PHP Function/Method Size Lint — Max N lines per function body
#
# Scans PHP files and counts non-blank, non-comment lines inside each
# function/method body. Reports any exceeding the limit.
#
# Usage:
#   ./scripts/lint-php-func-size.sh                     # default: wp-plugins/
#   ./scripts/lint-php-func-size.sh --dir wp-plugins/qupload
#   ./scripts/lint-php-func-size.sh --max 30            # custom line limit
#   ./scripts/lint-php-func-size.sh --verbose           # show all functions
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

MAX_LINES=20
SCAN_DIR="wp-plugins"
VERBOSE=false
VIOLATIONS=0
TOTAL_FUNCS=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)     SCAN_DIR="$2"; shift 2 ;;
    --max)     MAX_LINES="$2"; shift 2 ;;
    --verbose) VERBOSE=true; shift ;;
    -h|--help)
      head -16 "$0" | tail -13
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
  local file="$1" func_name="$2" start_line="$3" body_lines="$4"
  echo -e "  ${RED}✗${NC} ${file}:${start_line}  ${YELLOW}${func_name}${NC}  ${body_lines} lines (max ${MAX_LINES})"
  VIOLATIONS=$((VIOLATIONS + 1))
}

# ---------------------------------------------------------------------------
# PHP function body line counter (AWK)
#
# Strategy:
#   1. Find lines with "function " keyword — record name and start line
#   2. Track brace depth to find function boundaries
#   3. Count non-blank, non-comment lines within the body
#   4. Report functions exceeding MAX_LINES
# ---------------------------------------------------------------------------

analyze_file() {
  local file="$1"

  awk -v max="$MAX_LINES" -v file="$file" -v verbose="$VERBOSE" '
  BEGIN {
    in_func = 0
    depth = 0
    body_lines = 0
    func_name = ""
    func_start = 0
    in_block_comment = 0
  }

  # Track block comments
  /\/\*/ && !/\*\// { in_block_comment = 1 }
  /\*\// { in_block_comment = 0; next }
  in_block_comment { next }

  # Detect function declaration (PHP: public/private/protected/static function, or bare function)
  /function\s+[A-Za-z_]/ {
    if (in_func == 0) {
      func_line = $0
      # Extract function name
      if (match(func_line, /function\s+([A-Za-z_][A-Za-z0-9_]*)/, arr)) {
        func_name = arr[1]
      } else if (match(func_line, /function\s+([A-Za-z_][A-Za-z0-9_]*)/)) {
        temp = substr(func_line, RSTART, RLENGTH)
        sub(/function\s+/, "", temp)
        func_name = temp
      } else {
        func_name = "<anonymous>"
      }
      func_start = NR
    }
  }

  # Track brace depth
  {
    line = $0
    opens = gsub(/{/, "{", line)
    closes = gsub(/}/, "}", line)

    if (in_func == 0 && opens > 0 && func_start > 0) {
      # Function body starts
      in_func = 1
      depth = opens - closes
      body_lines = 0
      next
    }

    if (in_func) {
      depth += (opens - closes)

      if (depth <= 0) {
        # Function body ends
        if (body_lines > max) {
          printf "  VIOLATION|%s|%s|%d|%d\n", file, func_name, func_start, body_lines
        } else if (verbose == "true") {
          printf "  OK|%s|%s|%d|%d\n", file, func_name, func_start, body_lines
        }
        in_func = 0
        func_start = 0
        func_name = ""
        body_lines = 0
        depth = 0
        next
      }

      # Count non-blank, non-comment-only lines
      stripped = $0
      gsub(/^[[:space:]]+/, "", stripped)
      gsub(/[[:space:]]+$/, "", stripped)
      if (stripped != "" && !match(stripped, /^\/\//) && !match(stripped, /^#/) && !match(stripped, /^\*/)) {
        body_lines++
      }
    }
  }
  ' "$file"
}

# ---------------------------------------------------------------------------
# Main scan
# ---------------------------------------------------------------------------

header "PHP Function Size Lint (max ${MAX_LINES} lines per body)"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  echo "  Use --dir to specify the PHP source directory"
  exit 0
fi

TOTAL_FILES=0

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))
  result=$(analyze_file "$file")

  if [[ -n "$result" ]]; then
    while IFS='|' read -r prefix filepath func_name start_line body_lines; do
      TOTAL_FUNCS=$((TOTAL_FUNCS + 1))
      if [[ "$prefix" == *"VIOLATION"* ]]; then
        violation "$filepath" "$func_name" "$start_line" "$body_lines"
      elif [[ "$VERBOSE" == "true" ]]; then
        echo -e "  ${DIM}✓ ${filepath}:${start_line}  ${func_name}  ${body_lines} lines${NC}"
      fi
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
  echo -e "${GREEN}✓ All ${TOTAL_FUNCS} functions in ${TOTAL_FILES} files are within ${MAX_LINES}-line limit${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} function(s) exceed ${MAX_LINES}-line limit (${TOTAL_FUNCS} scanned across ${TOTAL_FILES} files)${NC}"
  echo ""
  echo -e "${CYAN}Refactoring patterns:${NC}"
  echo "  Long method          → extract to helper methods"
  echo "  Complex conditions   → named boolean variables"
  echo "  Switch/case blocks   → strategy pattern or lookup"
  echo "  Long HTML rendering  → extract to template partials"
  exit 1
fi
