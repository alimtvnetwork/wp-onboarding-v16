#!/usr/bin/env bash
# =============================================================================
# GE-1 Enforcement Lint — Generic Enforce Pattern (spec/12-generic-enforce/)
#
# Flags violations of the zero-loose-types policy in non-framework code:
#   - Record<string, unknown>  (TS)
#   - : any / as any           (TS)
#   - catch (err: any)         (TS — must use unknown + narrowing)
#   - map[string]interface{}   (Go)
#   - interface{}              (Go)
#   - Dictionary<string, object> (C#)
#   - dynamic                  (C#)
#   - serde_json::Value        (Rust, outside parse boundaries)
#   - Box<dyn Any>             (Rust)
#
# Usage:
#   ./scripts/lint-ge.sh              # scan all languages
#   ./scripts/lint-ge.sh --ts         # TypeScript only
#   ./scripts/lint-ge.sh --go         # Go only
#   ./scripts/lint-ge.sh --cs         # C# only
#   ./scripts/lint-ge.sh --rs         # Rust only
#   ./scripts/lint-ge.sh --fix-guide  # print remediation guidance
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
NC='\033[0m' # No Color

VIOLATIONS=0

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

violation() {
  local file="$1" line="$2" match="$3" rule="$4"
  echo -e "  ${RED}✗${NC} ${file}:${line}  ${YELLOW}${match}${NC}  (${rule})"
  VIOLATIONS=$((VIOLATIONS + 1))
}

# Scan with grep, skip comments/test files/node_modules
# $1 = pattern, $2 = file glob, $3 = rule label, $4.. = extra grep args
scan() {
  local pattern="$1" glob="$2" rule="$3"
  shift 3

  # Find matching files, excluding test/spec files and node_modules
  local files
  files=$(find . \
    -name "$glob" \
    ! -path "*/node_modules/*" \
    ! -path "*/.git/*" \
    ! -path "*/dist/*" \
    ! -path "*/build/*" \
    ! -path "*vendor*" \
    ! -name "*.test.*" \
    ! -name "*.spec.*" \
    ! -path "*/test/*" \
    ! -path "*/__tests__/*" \
    2>/dev/null || true)

  [ -z "$files" ] && return

  while IFS= read -r file; do
    # Skip framework/utility files (GE-5 exception)
    case "$file" in
      */lib/retry.*|*/lib/cache.*|*/lib/api/envelope.*|*/utils/retry.*) continue ;;
    esac

    while IFS=: read -r lineno content; do
      # Skip comments
      case "$content" in
        *"//"*"$pattern"*) 
          # Check if pattern appears BEFORE the comment marker
          local before_comment="${content%%//*}"
          echo "$before_comment" | grep -qE "$pattern" "$@" 2>/dev/null && violation "$file" "$lineno" "$(echo "$content" | xargs)" "$rule"
          continue
          ;;
        *"/*"*|*"*"*|*"#"*) continue ;;
      esac

      violation "$file" "$lineno" "$(echo "$content" | xargs)" "$rule"
    done < <(grep -nE "$pattern" "$file" "$@" 2>/dev/null || true)
  done <<< "$files"
}

# ---------------------------------------------------------------------------
# TypeScript checks
# ---------------------------------------------------------------------------

lint_ts() {
  header "TypeScript — GE-2 Zero Loose Types"

  # Record<string, unknown> — always a violation (GE-2)
  scan 'Record<string,\s*unknown>' "*.ts" "GE-2: Use named domain type"
  scan 'Record<string,\s*unknown>' "*.tsx" "GE-2: Use named domain type"

  # : any (type annotation)
  scan ':\s*any\b' "*.ts" "GE-2: Replace with concrete type or generic T"
  scan ':\s*any\b' "*.tsx" "GE-2: Replace with concrete type or generic T"

  # as any (type cast)
  scan 'as\s+any\b' "*.ts" "GE-2: Use proper type assertion"
  scan 'as\s+any\b' "*.tsx" "GE-2: Use proper type assertion"

  # catch (err: any) — must use unknown
  scan 'catch\s*\(\s*\w+\s*:\s*any' "*.ts" "GE-2: Use catch (err: unknown) + instanceof"
  scan 'catch\s*\(\s*\w+\s*:\s*any' "*.tsx" "GE-2: Use catch (err: unknown) + instanceof"
}

# ---------------------------------------------------------------------------
# Go checks
# ---------------------------------------------------------------------------

lint_go() {
  header "Go — GE-2 Zero Loose Types"

  # map[string]interface{}
  scan 'map\[string\]interface\{\}' "*.go" "GE-2: Use named struct or typed map"

  # interface{} as parameter or field type
  scan '\binterface\{\}' "*.go" "GE-2: Use concrete type, generic T, or any (Go 1.18+)"

  # map[string]any (Go 1.18+ equivalent)
  scan 'map\[string\]any\b' "*.go" "GE-2: Use named struct or typed map"
}

# ---------------------------------------------------------------------------
# C# checks
# ---------------------------------------------------------------------------

lint_cs() {
  header "C# — GE-2 Zero Loose Types"

  # Dictionary<string, object>
  scan 'Dictionary<string,\s*object>' "*.cs" "GE-2: Use named domain type"

  # dynamic keyword
  scan '\bdynamic\b' "*.cs" "GE-2: Use concrete type"
}

# ---------------------------------------------------------------------------
# Rust checks
# ---------------------------------------------------------------------------

lint_rs() {
  header "Rust — GE-2 Zero Loose Types"

  # serde_json::Value in struct fields / function sigs (not parse boundaries)
  scan 'serde_json::Value' "*.rs" "GE-2: Deserialize to concrete struct"

  # Box<dyn Any>
  scan 'Box<dyn\s+Any>' "*.rs" "GE-2: Use concrete type or generic T"
}

# ---------------------------------------------------------------------------
# Fix guide
# ---------------------------------------------------------------------------

print_fix_guide() {
  cat <<'EOF'

━━━ GE-1 Remediation Guide ━━━

For each violation:

1. IDENTIFY what the data actually represents
   - Read the surrounding code to understand the domain meaning
   - Check what fields are actually accessed on the object

2. CREATE a named interface/struct with known fields
   - Place it in the nearest shared types file (e.g., src/lib/api/types.ts)
   - Use optional fields (?) for properties that may not always exist
   - Add an index signature [key: string]: unknown ONLY if truly extensible

3. REPLACE the raw generic with the named type
   - Update the type annotation at the declaration site
   - Update all consumers (props, function params, return types)

4. For catch blocks:
   - Change: catch (err: any) { err.message }
   - To:     catch (err: unknown) { if (err instanceof Error) { err.message } }

Example:
  BEFORE: context: Record<string, unknown>
  AFTER:  context: ErrorDiagnosticContext   // defined with known fields

EOF
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

case "${1:-all}" in
  --ts)        lint_ts ;;
  --go)        lint_go ;;
  --cs)        lint_cs ;;
  --rs)        lint_rs ;;
  --fix-guide) print_fix_guide; exit 0 ;;
  all|*)       lint_ts; lint_go; lint_cs; lint_rs ;;
esac

echo ""
if [ "$VIOLATIONS" -eq 0 ]; then
  echo -e "${GREEN}✓ No GE-2 violations found. Codebase is clean.${NC}"
  exit 0
else
  echo -e "${RED}✗ Found ${VIOLATIONS} GE-2 violation(s). Run with --fix-guide for remediation steps.${NC}"
  exit 1
fi
