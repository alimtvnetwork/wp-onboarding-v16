#!/usr/bin/env bash
# lint-json-tags.sh — Detects redundant JSON tags on Go structs.
# A JSON tag is "redundant" if it uses camelCase (or matches the field name)
# and does NOT have an "// external key" comment on the same line.
#
# Allowed tags:
#   `json:",omitempty"`
#   `json:"-"`
#   `json:"someKey"` with "// external key" comment
#
# Usage: bash scripts/lint-json-tags.sh [dir]
#   dir defaults to backend/

set -euo pipefail

DIR="${1:-backend}"
EXIT_CODE=0
COUNT=0

# Find all .go files, skip test files and vendor
while IFS= read -r file; do
  line_num=0
  while IFS= read -r line; do
    line_num=$((line_num + 1))

    # Skip lines with "// external key"
    if echo "$line" | grep -q '// external key'; then
      continue
    fi

    # Skip json:"-" (field exclusion)
    if echo "$line" | grep -qE '`json:"-"`'; then
      continue
    fi

    # Skip json:",omitempty" (omitempty-only, no rename)
    # Match: `json:",omitempty"` — no field name before the comma
    if echo "$line" | grep -qE '`json:",omitempty"`'; then
      continue
    fi

    # Skip lines without json tags
    if ! echo "$line" | grep -qE '`json:"[^"]*"`'; then
      continue
    fi

    # Extract the json tag value
    tag=$(echo "$line" | sed -n 's/.*`json:"\([^"]*\)".*/\1/p')
    if [ -z "$tag" ]; then
      continue
    fi

    # Strip ,omitempty suffix for comparison
    base_tag="${tag%,omitempty}"

    # If base_tag is empty, it was just ",omitempty" — already handled above
    if [ -z "$base_tag" ]; then
      continue
    fi

    # This line has a json:"fieldName" tag without "// external key" — flag it
    echo "  $file:$line_num: redundant tag \`json:\"$tag\"\` (missing '// external key' comment)"
    COUNT=$((COUNT + 1))
    EXIT_CODE=1

  done < "$file"
done < <(find "$DIR" -name '*.go' ! -name '*_test.go' ! -path '*/vendor/*' -type f)

if [ "$EXIT_CODE" -eq 0 ]; then
  echo "✅ No redundant JSON tags found"
else
  echo ""
  echo "❌ Found $COUNT redundant JSON tag(s). Either:"
  echo "   1) Remove the tag (let Go default to PascalCase), or"
  echo "   2) Add '// external key (source)' comment if parsing external JSON"
  exit 1
fi
