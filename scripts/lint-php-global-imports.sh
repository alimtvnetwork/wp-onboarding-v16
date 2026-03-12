#!/usr/bin/env bash
# =============================================================================
# PHP Global Class Import Lint — Detect unimported global classes in namespaced files
#
# Scans namespaced PHP files for references to known global PHP/WordPress
# classes that are not imported via a `use` statement. Unimported globals
# resolve relative to the current namespace, causing fatal "Class not found".
#
# Usage:
#   ./scripts/lint-php-global-imports.sh                     # default: wp-plugins/
#   ./scripts/lint-php-global-imports.sh --dir wp-plugins/qupload
#   ./scripts/lint-php-global-imports.sh --verbose
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

SCAN_DIR="wp-plugins"
VERBOSE=false
VIOLATIONS=0
TOTAL_FILES=0

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir)     SCAN_DIR="$2"; shift 2 ;;
    --verbose) VERBOSE=true; shift ;;
    -h|--help)
      head -16 "$0" | tail -13
      exit 0
      ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Known global classes that must be explicitly imported in namespaced files.
# Covers PHP built-ins, SPL, and common WordPress classes.
# ---------------------------------------------------------------------------

GLOBAL_CLASSES=(
  # PHP core
  "SQLite3"
  "PDO"
  "PDOException"
  "PDOStatement"
  "DateTime"
  "DateTimeImmutable"
  "DateInterval"
  "DateTimeZone"
  "Exception"
  "RuntimeException"
  "InvalidArgumentException"
  "LogicException"
  "BadMethodCallException"
  "OverflowException"
  "UnexpectedValueException"
  "DomainException"
  "RangeException"
  "LengthException"
  "OutOfBoundsException"
  "OutOfRangeException"
  "TypeError"
  "ValueError"
  "Error"
  "Throwable"
  "JsonException"
  "ZipArchive"
  "SplFileInfo"
  "SplFileObject"
  "SplTempFileObject"
  "ArrayObject"
  "stdClass"
  "Closure"
  "Generator"
  "WeakReference"
  "Fiber"
  "CURLFile"
  "SimpleXMLElement"
  "DOMDocument"
  "DOMElement"
  "DOMNode"
  "ReflectionClass"
  "ReflectionMethod"
  "ReflectionFunction"
  "RecursiveDirectoryIterator"
  "RecursiveIteratorIterator"
  "DirectoryIterator"
  "FilesystemIterator"
  "GlobIterator"
  "RegexIterator"
  "SplPriorityQueue"
  "SplStack"
  "SplQueue"
  "Countable"
  "Iterator"
  "IteratorAggregate"
  "Serializable"
  "JsonSerializable"
  "Stringable"
  # WordPress
  "WP_Error"
  "WP_REST_Response"
  "WP_REST_Request"
  "WP_REST_Server"
  "WP_Query"
  "WP_User"
  "WP_Post"
  "WP_Term"
  "WP_Comment"
  "WP_HTTP_Response"
  "WP_Filesystem_Base"
  "WP_Filesystem_Direct"
  "wpdb"
  "WP_Widget"
  "WP_Customize_Control"
  "WP_CLI"
  "WP_Hook"
  "WP_Rewrite"
  "WP_Roles"
  "WP_Role"
  "WP_Admin_Bar"
  "Walker"
  "Walker_Nav_Menu"
)

# ---------------------------------------------------------------------------
# Build regex pattern from global classes list
# ---------------------------------------------------------------------------

build_class_pattern() {
  local pattern=""
  for cls in "${GLOBAL_CLASSES[@]}"; do
    if [[ -n "$pattern" ]]; then
      pattern="${pattern}|${cls}"
    else
      pattern="${cls}"
    fi
  done
  echo "$pattern"
}

CLASS_PATTERN=$(build_class_pattern)

# ---------------------------------------------------------------------------
# Analyze a single PHP file
# ---------------------------------------------------------------------------

analyze_file() {
  local file="$1"

  awk -v file="$file" -v class_pattern="$CLASS_PATTERN" -v verbose="$VERBOSE" '
  BEGIN {
    has_namespace = 0
    violation_count = 0

    # Parse class_pattern into an array
    n = split(class_pattern, class_list, "|")
    for (i = 1; i <= n; i++) {
      global_classes[class_list[i]] = 1
      imported[class_list[i]] = 0
    }
  }

  # Detect namespace declaration (file is namespaced)
  /^[[:space:]]*namespace[[:space:]]+[A-Za-z]/ {
    has_namespace = 1
  }

  # Track use imports — "use ClassName;" (global) or extract last segment
  /^[[:space:]]*use[[:space:]]+[A-Za-z]/ && /;[[:space:]]*$/ {
    line = $0
    gsub(/^[[:space:]]*use[[:space:]]+/, "", line)
    gsub(/;[[:space:]]*$/, "", line)

    # Handle aliased imports: "use Foo\Bar as Baz;"
    gsub(/[[:space:]]+as[[:space:]]+.*$/, "", line)

    # For bare class (no backslash) — direct global import
    if (index(line, "\\") == 0) {
      if (line in global_classes) {
        imported[line] = 1
      }
    }
  }

  END {
    if (!has_namespace) exit

    # Second pass: scan for usage of global classes not imported
    # We need to re-read the file for usage detection
  }
  ' "$file"

  # Two-pass approach: first collect imports, then scan for usage
  awk -v file="$file" -v class_pattern="$CLASS_PATTERN" -v verbose="$VERBOSE" '
  BEGIN {
    has_namespace = 0
    n = split(class_pattern, class_list, "|")
    for (i = 1; i <= n; i++) {
      global_classes[class_list[i]] = 1
      imported[class_list[i]] = 0
    }
    violation_count = 0
    # Track which classes we have already reported
  }

  # Detect namespace
  /^[[:space:]]*namespace[[:space:]]+[A-Za-z]/ {
    has_namespace = 1
  }

  # Track use imports
  /^[[:space:]]*use[[:space:]]+[A-Za-z]/ && /;[[:space:]]*$/ {
    line = $0
    gsub(/^[[:space:]]*use[[:space:]]+/, "", line)
    gsub(/;[[:space:]]*$/, "", line)
    gsub(/[[:space:]]+as[[:space:]]+.*$/, "", line)

    # Bare global import
    if (index(line, "\\") == 0) {
      if (line in global_classes) {
        imported[line] = 1
      }
    }
  }

  # Collect all lines for second pass
  { all_lines[NR] = $0 }

  END {
    if (!has_namespace) {
      if (verbose == "true") {
        printf "SKIP|%s|0|not namespaced\n", file
      }
      exit
    }

    # Scan for usage of unimported global classes
    for (linenum = 1; linenum <= NR; linenum++) {
      line = all_lines[linenum]

      # Skip use statements themselves, comments, namespace lines
      if (line ~ /^[[:space:]]*use[[:space:]]/) continue
      if (line ~ /^[[:space:]]*namespace[[:space:]]/) continue
      if (line ~ /^[[:space:]]*\/\//) continue
      if (line ~ /^[[:space:]]*\*/) continue
      if (line ~ /^[[:space:]]*\/\*/) continue

      # Check each global class
      for (i = 1; i <= n; i++) {
        cls = class_list[i]
        if (imported[cls]) continue
        if (reported[cls]) continue

        # Match patterns: new ClassName, ClassName::, ClassName $, catch (ClassName,
        # extends ClassName, implements ClassName, instanceof ClassName,
        # : ClassName (return type), ClassName| (union type)
        # But NOT inside strings or as substring of another identifier

        # Build word-boundary-ish patterns
        # new ClassName
        if (line ~ "new[[:space:]]+" cls "([^A-Za-z0-9_]|$)") {
          printf "VIOLATION|%s|%d|%s|new %s\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # ClassName:: (static call)
        if (line ~ "(^|[^A-Za-z0-9_\\\\])" cls "::") {
          printf "VIOLATION|%s|%d|%s|%s:: static reference\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # catch (ClassName
        if (line ~ "catch[[:space:]]*\\([[:space:]]*" cls "([^A-Za-z0-9_]|$)") {
          printf "VIOLATION|%s|%d|%s|catch (%s)\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # class_exists(ClassName::class)
        if (line ~ cls "::class") {
          printf "VIOLATION|%s|%d|%s|%s::class reference\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # instanceof ClassName
        if (line ~ "instanceof[[:space:]]+" cls "([^A-Za-z0-9_]|$)") {
          printf "VIOLATION|%s|%d|%s|instanceof %s\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # extends ClassName or implements ClassName
        if (line ~ "(extends|implements)[[:space:]]+" cls "([^A-Za-z0-9_]|$)") {
          printf "VIOLATION|%s|%d|%s|extends/implements %s\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # Type hints: : ClassName (return type) or (ClassName $ (param type)
        if (line ~ ":[[:space:]]*\\??" cls "([^A-Za-z0-9_]|$)") {
          printf "VIOLATION|%s|%d|%s|type hint %s\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
        # Parameter type hint: (ClassName $
        if (line ~ "[(,][[:space:]]*\\??" cls "[[:space:]]+\\$") {
          printf "VIOLATION|%s|%d|%s|param type %s\n", file, linenum, cls, cls
          reported[cls] = 1
          violation_count++
          continue
        }
      }
    }

    if (violation_count == 0 && verbose == "true") {
      printf "OK|%s|0|all global classes properly imported\n", file
    }
  }
  ' "$file"
}

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

header() {
  echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

# ---------------------------------------------------------------------------
# Main scan
# ---------------------------------------------------------------------------

header "PHP Global Class Import Lint"

if [[ ! -d "$SCAN_DIR" ]]; then
  echo -e "${YELLOW}⚠ Directory not found: ${SCAN_DIR}${NC}"
  exit 0
fi

while IFS= read -r file; do
  TOTAL_FILES=$((TOTAL_FILES + 1))
  result=$(analyze_file "$file")

  if [[ -n "$result" ]]; then
    while IFS='|' read -r kind filepath line_num class_name detail; do
      case "$kind" in
        *VIOLATION*)
          echo -e "  ${RED}✗${NC} ${filepath}:${line_num}  ${YELLOW}${class_name}${NC} — ${detail}"
          echo -e "    ${DIM}Fix: add  use ${class_name};  to imports${NC}"
          VIOLATIONS=$((VIOLATIONS + 1))
          ;;
        *OK*)
          if [[ "$VERBOSE" == "true" ]]; then
            echo -e "  ${DIM}✓ ${filepath}  ${detail}${NC}"
          fi
          ;;
        *SKIP*)
          if [[ "$VERBOSE" == "true" ]]; then
            echo -e "  ${DIM}– ${filepath}  ${detail}${NC}"
          fi
          ;;
      esac
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
  echo -e "${GREEN}✓ All ${TOTAL_FILES} namespaced PHP files have proper global class imports${NC}"
  exit 0
else
  echo -e "${RED}✗ ${VIOLATIONS} unimported global class(es) in ${TOTAL_FILES} files${NC}"
  echo ""
  echo -e "${CYAN}Why this matters:${NC}"
  echo "  In namespaced PHP files, bare class names resolve relative to the"
  echo "  current namespace. Without an explicit 'use' import, PHP looks for"
  echo "  e.g. YourNamespace\\SQLite3 instead of the global \\SQLite3."
  echo ""
  echo -e "${CYAN}Fix:${NC}"
  echo "  Add 'use ClassName;' at the top of the file with other imports."
  exit 1
fi
