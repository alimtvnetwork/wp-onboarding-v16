#!/usr/bin/env bash
# =============================================================================
# Pre-commit Hook — Runs all Go lint checks before allowing a commit.
#
# Install:
#   ln -sf ../../scripts/pre-commit.sh .git/hooks/pre-commit
#
# Or run manually:
#   bash scripts/pre-commit.sh
#
# Exit codes:
#   0 = all checks pass
#   1 = one or more checks failed
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FAILURES=0
CHECKS=0

run_check() {
  local label="$1"
  shift
  CHECKS=$((CHECKS + 1))

  if "$@" > /dev/null 2>&1; then
    echo -e "  ${GREEN}✓${NC} ${label}"
  else
    echo -e "  ${RED}✗${NC} ${label}"
    FAILURES=$((FAILURES + 1))
  fi
}

echo -e "\n${CYAN}━━━ Pre-commit Quality Gates ━━━${NC}\n"

# ── Backend checks ──────────────────────────────────────────────────────────

if [[ -d "backend" ]]; then
  echo -e "${CYAN}Backend:${NC}"
  run_check "File size (≤300 lines)"         bash "$SCRIPT_DIR/lint-file-size.sh"
  run_check "Function size (≤15 lines)"      bash "$SCRIPT_DIR/lint-func-size.sh"
  run_check "Negative naming"                bash "$SCRIPT_DIR/lint-negative.sh"
  run_check "Import grouping"                bash "$SCRIPT_DIR/lint-imports.sh"
  run_check "Generic enforce (GE)"           bash "$SCRIPT_DIR/lint-ge.sh" --go
  run_check "JSON tags"                      bash "$SCRIPT_DIR/lint-json-tags.sh"
  run_check "Inline if"                      bash "$SCRIPT_DIR/lint-inline-if.sh"
  run_check "Typed-nil (error ← AppError)"   bash "$SCRIPT_DIR/lint-typed-nil.sh"
  run_check "go vet"                         bash -c "cd backend && go vet ./..."
  echo ""
fi

# ── Licensing checks ───────────────────────────────────────────────────────

if [[ -d "licensing" ]]; then
  echo -e "${CYAN}Licensing:${NC}"
  run_check "File size (≤300 lines)"         bash "$SCRIPT_DIR/lint-file-size.sh" --dir licensing
  run_check "Function size (≤15 lines)"      bash "$SCRIPT_DIR/lint-func-size.sh" --dir licensing
  run_check "Negative naming"                bash "$SCRIPT_DIR/lint-negative.sh" --dir licensing
  run_check "Import grouping"                bash "$SCRIPT_DIR/lint-imports.sh" --dir licensing
  run_check "JSON tags"                      bash "$SCRIPT_DIR/lint-json-tags.sh" licensing
  run_check "Inline if"                      bash "$SCRIPT_DIR/lint-inline-if.sh" --dir licensing
  run_check "Typed-nil (error ← AppError)"   bash "$SCRIPT_DIR/lint-typed-nil.sh" --dir licensing
  run_check "go vet"                         bash -c "cd licensing && go vet ./..."
  echo ""
fi

# ── Tools checks ───────────────────────────────────────────────────────────

if [[ -d "tools/consistency-checker" ]]; then
  echo -e "${CYAN}Consistency Checker:${NC}"
  run_check "File size (≤300 lines)"         bash "$SCRIPT_DIR/lint-file-size.sh" --dir tools/consistency-checker
  run_check "Function size (≤15 lines)"      bash "$SCRIPT_DIR/lint-func-size.sh" --dir tools/consistency-checker
  echo ""
fi

# ── PHP checks ─────────────────────────────────────────────────────────────

if [[ -d "wp-plugins" ]]; then
  echo -e "${CYAN}PHP (wp-plugins):${NC}"
  run_check "File size (≤500 lines)"         bash "$SCRIPT_DIR/lint-php-file-size.sh"
  echo ""
fi

# ── Summary ────────────────────────────────────────────────────────────────

if [[ "$FAILURES" -eq 0 ]]; then
  echo -e "${GREEN}✓ All ${CHECKS} checks passed${NC}\n"
  exit 0
else
  echo -e "${RED}✗ ${FAILURES}/${CHECKS} check(s) failed — commit aborted${NC}\n"
  exit 1
fi
