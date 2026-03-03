#!/usr/bin/env bash
# =============================================================================
# Install Git Hooks — Symlinks pre-commit hook into .git/hooks/
#
# Usage:
#   bash scripts/install-hooks.sh
# =============================================================================

set -euo pipefail

HOOK_DIR=".git/hooks"
HOOK_TARGET="$HOOK_DIR/pre-commit"
SCRIPT_SOURCE="../../scripts/pre-commit.sh"

if [[ ! -d "$HOOK_DIR" ]]; then
  echo "❌ .git/hooks not found — are you in the repo root?"
  exit 1
fi

ln -sf "$SCRIPT_SOURCE" "$HOOK_TARGET"
chmod +x "$HOOK_TARGET"

echo "✅ Pre-commit hook installed → $HOOK_TARGET"
echo "   To uninstall: rm $HOOK_TARGET"
