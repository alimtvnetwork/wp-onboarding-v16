// Package semver provides semantic version comparison utilities.
package semver

import (
	"strconv"
	"strings"
)

// Compare compares two semantic versions.
// Returns: -1 if a < b, 0 if a == b, 1 if a > b
func Compare(a, b string) int {
	isEqual := a == b

	if isEqual {
		return 0
	}

	isBEmpty := b == ""

	if isBEmpty {
		return 1
	}

	partsA := strings.Split(a, ".")
	partsB := strings.Split(b, ".")

	for i := 0; i < 3; i++ {
		result := comparePart(partsA, partsB, i)
		isDecisive := result != 0

		if isDecisive {
			return result
		}
	}

	return 0
}

// comparePart compares a single version segment at the given index.
func comparePart(partsA []string, partsB []string, index int) int {
	numA := parsePart(partsA, index)
	numB := parsePart(partsB, index)

	isAGreater := numA > numB

	if isAGreater {
		return 1
	}

	isASmaller := numA < numB

	if isASmaller {
		return -1
	}

	return 0
}

// parsePart extracts an integer from a version parts slice at the given index.
func parsePart(parts []string, index int) int {
	isWithinBounds := index < len(parts)

	if isWithinBounds {
		parsed, parseErr := strconv.Atoi(parts[index])
		if parseErr == nil {
			return parsed
		}
	}

	return 0
}
