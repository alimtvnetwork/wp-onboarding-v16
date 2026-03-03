package main

import (
	"fmt"
	"os"
	"os/exec"
	"strings"
)

// runFunc is set by package-mode bootstrap files (MainApp.go).
// When this file is run alone (go run cmd/server/main.go), runFunc stays nil.
var runFunc func()

func main() {
	if runFunc != nil {
		runFunc()
		return
	}

	candidates := []string{"./cmd/server", "./backend/cmd/server", "."}
	var lastErr error

	for _, target := range candidates {
		if !packageTargetExists(target) {
			continue
		}

		cmd := exec.Command("go", "run", target)
		cmd.Stdout = os.Stdout
		cmd.Stderr = os.Stderr
		cmd.Stdin = os.Stdin

		runErr := cmd.Run()
		if runErr == nil {
			return
		}
		lastErr = runErr
	}

	fmt.Fprintf(os.Stderr, "Failed to start server. Run one of: `cd backend && go run ./cmd/server` or `go run ./backend/cmd/server` (last error: %v)\n", lastErr)
	os.Exit(1)
}

func packageTargetExists(target string) bool {
	if target == "." {
		return true
	}

	rel := strings.TrimPrefix(target, "./")
	_, statErr := os.Stat(rel)
	return statErr == nil
}
