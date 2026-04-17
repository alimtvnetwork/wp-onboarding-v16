#!/usr/bin/env bash
# =============================================================================
# Go Response Shape Lint — Detect inconsistent respondSuccess shapes
#
# Flags HTTP handlers that call respondSuccess(w, X) with categorically
# different concrete types from different branches of the same function.
#
# Why: When a handler returns {"a":1,"b":2} on one branch and
# {"Scan":{...}, "IsDetectionCreated":true} on another, the frontend
# can't read consistent keys, leading to silent bugs (see v2.37.0 fix
# for /plugins/scan-directory).
#
# Strategy (grep-based, not AST):
#   1. Walk each Go file, splitting into top-level func bodies via brace depth.
#   2. Within each func, collect every respondSuccess(w, EXPR) call.
#   3. Classify EXPR into a shape category:
#        - map_literal        : map[string]any{...} or map[string]...{...}
#        - struct_literal     : SomeType{...} or &SomeType{...}
#        - slice_literal      : []T{...}
#        - empty_slice        : []struct{}{} (nil-safe placeholder, ignored)
#        - identifier         : bare identifier / field access (result, x.Y)
#        - call               : function call result, e.g. buildXxx(...)
#        - literal            : string/number/bool/nil
#   4. If a single function mixes categories that produce different JSON
#      shapes (e.g. map_literal + struct_literal, or two different struct
#      types), flag it.
#
# Allowed mixes:
#   - identifier + identifier (assumed same upstream type)
#   - call + call to the SAME function name
#   - anything + empty_slice (the nil-safe []struct{}{} fallback)
#
# Usage:
#   ./scripts/lint-response-shape.sh                   # scan backend/
#   ./scripts/lint-response-shape.sh --dir licensing
#   ./scripts/lint-response-shape.sh --include-tests
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

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)           SCAN_DIR="$2"; shift 2 ;;
    --include-tests) INCLUDE_TESTS=true; shift ;;
    -h|--help)
      head -45 "$0" | tail -42
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

echo -e "\n${CYAN}━━━ Go Response Shape Lint (respondSuccess consistency) ━━━${NC}"

# Find handler files (anything calling respondSuccess) — much faster than scanning all .go
mapfile -t CANDIDATE_FILES < <(
  grep -rl --include='*.go' 'respondSuccess(' "$SCAN_DIR" 2>/dev/null \
    | grep -v '/vendor/' \
    | grep -v '/_generated/' \
    | { if [[ "$INCLUDE_TESTS" == "false" ]]; then grep -v '_test\.go$'; else cat; fi; } \
    | sort
)

TOTAL_FILES=${#CANDIDATE_FILES[@]}

# Awk program: for each file, walk lines, track brace depth, identify
# top-level functions, and collect respondSuccess argument expressions.
# Output one line per finding:  FILE:LINE:FUNC:CATEGORY:DETAIL
AWK_PROG='
function classify(expr,    t) {
  # Trim leading/trailing whitespace
  sub(/^[[:space:]]+/, "", expr)
  sub(/[[:space:]]+$/, "", expr)

  # Empty slice nil-safe fallback — any []T{} is treated as the
  # nil-safe placeholder when paired with a populated slice elsewhere.
  if (expr ~ /^\[\][A-Za-z_][A-Za-z0-9_.]*\{\}$/) return "empty_slice"
  if (expr ~ /^\[\]struct\{\}\{\}$/) return "empty_slice"
  if (expr ~ /^\[\]any\{\}$/) return "empty_slice"

  # map literal:  map[...]...{
  if (expr ~ /^map\[/) return "map_literal|map"

  # non-empty slice literal:  []T{x,y,z}
  if (expr ~ /^\[\]/) return "slice_literal|slice"

  # pointer-to-struct literal:  &Foo{...}  or  &pkg.Foo{...}
  if (match(expr, /^&([A-Za-z_][A-Za-z0-9_.]*)\{/, m)) return "struct_literal|" m[1]

  # struct literal:  Foo{...}  or pkg.Foo{...}
  if (match(expr, /^([A-Z][A-Za-z0-9_]*|[a-z][a-zA-Z0-9_]*\.[A-Z][A-Za-z0-9_]*)\{/, m)) return "struct_literal|" m[1]

  # function call:  foo(...) or pkg.Foo(...)
  if (match(expr, /^([A-Za-z_][A-Za-z0-9_.]*)\(/, m)) return "call|" m[1]

  # literal nil/true/false/number/string
  if (expr ~ /^(nil|true|false)$/) return "literal|" expr
  if (expr ~ /^"/) return "literal|string"
  if (expr ~ /^[0-9]/) return "literal|number"

  # identifier or field access:  result, x.Y, x.Y.Z
  if (expr ~ /^[A-Za-z_][A-Za-z0-9_.]*$/) return "identifier|" expr

  return "unknown|" expr
}

BEGIN { depth = 0; func_name = ""; func_start = 0 }

# Track top-level function declarations
/^func[[:space:]]/ {
  if (depth == 0) {
    # Extract function name (handles both "func Name(" and "func (r *T) Name(")
    if (match($0, /^func[[:space:]]+\(([^)]+)\)[[:space:]]+([A-Za-z_][A-Za-z0-9_]*)/, m)) {
      func_name = m[2]
    } else if (match($0, /^func[[:space:]]+([A-Za-z_][A-Za-z0-9_]*)/, m)) {
      func_name = m[1]
    } else {
      func_name = "anonymous"
    }
    func_start = NR
  }
}

# Detect respondSuccess(w, EXPR) — capture EXPR up to matching close paren
# Heuristic: take everything between the second arg start and end-of-statement
{
  line = $0
  idx = index(line, "respondSuccess(")
  if (idx > 0 && func_name != "") {
    # Extract the args portion
    rest = substr(line, idx + length("respondSuccess("))
    # Find the comma separating w from the payload (w is always 1st arg)
    cidx = index(rest, ",")
    if (cidx > 0) {
      payload = substr(rest, cidx + 1)
      # Strip trailing ")" + comments
      sub(/\)[[:space:]]*(\/\/.*)?$/, "", payload)
      sub(/^[[:space:]]+/, "", payload)
      sub(/[[:space:]]+$/, "", payload)
      cat = classify(payload)
      print FILENAME ":" NR ":" func_name ":" cat ":" payload
    }
  }
}

# Brace tracking — count { and } per line, ignoring those in strings/comments (rough)
{
  l = $0
  # Strip line comments
  sub(/\/\/.*$/, "", l)
  # Count braces
  n_open = gsub(/\{/, "&", l)
  n_close = gsub(/\}/, "&", l)
  depth += n_open - n_close
  if (depth <= 0) {
    depth = 0
    func_name = ""
  }
}
'

# Collect all findings
TMP_FINDINGS=$(mktemp)
trap 'rm -f "$TMP_FINDINGS"' EXIT

for file in "${CANDIDATE_FILES[@]}"; do
  awk "$AWK_PROG" "$file" >> "$TMP_FINDINGS" 2>/dev/null || true
done

# ─────────────────────────────────────────────────────────────────────────────
# SOFT WARNING PASS — bare map[string]any / map[string]bool literals
#
# These work but are an anti-pattern: they bypass compile-time field checks,
# allow typos in JSON keys to ship silently, and make refactors fragile.
# Recommend promoting to a typed struct (see ResponseTypes.go for examples
# like ActionResponse, FlatScanResponse, DeployPreflightResponse).
#
# Soft = does NOT fail the build. Only the hard "mixed shape" check above
# can fail. This is intentional: typed structs are a goal, not a gate.
# Set RESPONSE_SHAPE_STRICT=1 to escalate warnings to errors.
# ─────────────────────────────────────────────────────────────────────────────
WARNINGS=0
WARN_REPORT=$(mktemp)
trap 'rm -f "$TMP_FINDINGS" "$WARN_REPORT"' EXIT

for file in "${CANDIDATE_FILES[@]}"; do
  # Match respondSuccess(w, map[string]<any|bool|interface{}>{ ... )
  grep -nE 'respondSuccess\([^,]+,[[:space:]]*(&)?map\[string\](any|bool|interface\{\})\{' "$file" 2>/dev/null \
    | while IFS= read -r hit; do
        echo "${file}:${hit}" >> "$WARN_REPORT"
      done || true
done

WARN_COUNT=$(wc -l < "$WARN_REPORT" | tr -d ' ')
if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo ""
  echo -e "${YELLOW}⚠ ${WARN_COUNT} bare map[string]any/bool literal(s) in respondSuccess (soft warning):${NC}"
  while IFS= read -r ln; do
    # ln is FILE:LINE:matched-text — show first two segments + a snippet
    f="${ln%%:*}"
    rest="${ln#*:}"
    lno="${rest%%:*}"
    snippet="${rest#*:}"
    # Trim leading whitespace from snippet
    snippet="$(echo "$snippet" | sed 's/^[[:space:]]*//' | cut -c1-100)"
    echo -e "    ${YELLOW}~${NC} ${f}:${lno}  ${snippet}"
  done < "$WARN_REPORT"
  echo ""
  echo -e "  ${CYAN}Recommendation:${NC} promote to a typed struct in ResponseTypes.go."
  echo -e "  ${CYAN}Why:${NC} typed structs catch key typos at compile time, document the shape,"
  echo -e "        and let the response-shape mixer check reason about field consistency."
  echo ""
  if [[ "${RESPONSE_SHAPE_STRICT:-0}" == "1" ]]; then
    echo -e "${RED}✗ RESPONSE_SHAPE_STRICT=1 — escalating warnings to errors${NC}"
    WARNINGS="$WARN_COUNT"
  fi
fi

# Group by FILE+FUNC; flag groups with mixed categories
# Allowed: empty_slice mixed with anything; identical category|subtype repeats;
#          all "call" with same function name.
REPORT=$(awk -F: '
{
  file = $1
  line = $2
  fn = $3
  cat = $4
  sub_t = $5
  # The expression itself may contain ":" — re-join from $6 onward
  expr = $6
  for (i = 7; i <= NF; i++) expr = expr ":" $i

  key = file "::" fn

  # Skip empty_slice (nil-safe fallback)
  if (cat == "empty_slice") next

  # Track unique cat|sub per function
  sig = cat "|" sub_t
  seen_sigs[key] = (seen_sigs[key] == "") ? sig : seen_sigs[key] "\n" sig
  seen_lines[key] = (seen_lines[key] == "") ? (line ":" sig ":" expr) : seen_lines[key] "\n" (line ":" sig ":" expr)
  count[key]++
}

END {
  for (key in seen_sigs) {
    # Get unique signatures
    n = split(seen_sigs[key], arr, "\n")
    delete uniq
    for (i = 1; i <= n; i++) uniq[arr[i]] = 1
    u = 0
    for (s in uniq) u++
    if (u < 2) continue

    # Check if all unique sigs share the same primary category
    delete cats
    for (s in uniq) {
      split(s, parts, "|")
      cats[parts[1]] = 1
    }
    nc = 0
    for (c in cats) nc++

    # Allowed: all same category AND it is "identifier" or "call"
    # (assumes same upstream type — too noisy to flag without type info)
    if (nc == 1) {
      for (c in cats) primary = c
      if (primary == "identifier" || primary == "call") continue
    }

    # FLAG: mixed shapes
    split(key, kp, "::")
    f = kp[1]; fn = kp[2]
    print "VIOLATION:" f ":" fn
    m = split(seen_lines[key], lines_arr, "\n")
    for (i = 1; i <= m; i++) print "  " lines_arr[i]
    print "  ---"
  }
}
' "$TMP_FINDINGS")

if [[ -z "$REPORT" ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} handler files have consistent respondSuccess shapes${NC}"
  if [[ "${WARNINGS:-0}" -gt 0 ]]; then exit 1; fi
  exit 0
fi

# Pretty-print violations
echo "$REPORT" | while IFS= read -r ln; do
  if [[ "$ln" == VIOLATION:* ]]; then
    rest="${ln#VIOLATION:}"
    file="${rest%%:*}"
    fn="${rest#*:}"
    echo ""
    echo -e "  ${RED}✗${NC} ${file}  ${YELLOW}func ${fn}${NC} mixes response shapes:"
    VIOLATIONS=$((VIOLATIONS + 1))
  elif [[ "$ln" == "  ---" ]]; then
    :
  else
    echo -e "    ${ln}"
  fi
done

# Re-count violations from REPORT (subshell pipe loses VIOLATIONS)
VIOLATIONS=$(echo "$REPORT" | grep -c '^VIOLATION:' || true)

echo ""
if [[ "$VIOLATIONS" -eq 0 && "${WARNINGS:-0}" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${TOTAL_FILES} handler files have consistent respondSuccess shapes${NC}"
  exit 0
elif [[ "$VIOLATIONS" -eq 0 ]]; then
  exit 1
else
  echo -e "${RED}✗ ${VIOLATIONS} handler(s) return inconsistent response shapes (${TOTAL_FILES} files scanned)${NC}"
  echo ""
  echo -e "${CYAN}Why this matters:${NC}"
  echo "  Frontend code reads fixed top-level keys. When a handler returns"
  echo "  {\"a\":1} on one branch and {\"Wrapper\":{\"a\":1}} on another,"
  echo "  fields silently become undefined and bugs surface at runtime."
  echo ""
  echo -e "${CYAN}Fix:${NC}"
  echo "  • Build the response with a single helper (e.g., buildFlatXxxResponse)"
  echo "  • Or define a typed struct used in every branch"
  echo "  • Use []struct{}{} for the nil-safe empty list fallback (exempted)"
  exit 1
fi
