// Package portutil provides utilities for resolving port conflicts before binding.
package portutil

import (
	"fmt"
	"net"
	"os/exec"
	"runtime"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// EnsurePortFree checks if the given port is in use and attempts to kill
// the occupying process. Returns nil if the port is free (or was freed).
func EnsurePortFree(port int) error {
	if !isPortInUse(port) {
		return nil
	}

	portStr := strconv.Itoa(port)

	pid, err := findPIDOnPort(port)
	if err != nil || pid == 0 {
		return apperror.Wrap(err, apperror.ErrInternal, "port is in use but could not identify the process").
			WithValue("port", portStr)
	}

	pidStr := strconv.Itoa(pid)

	if err := killProcess(pid); err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "port is in use but could not kill occupying process").
			WithValue("port", portStr).
			WithValue("pid", pidStr)
	}

	// Wait briefly for the OS to release the port
	for i := 0; i < 10; i++ {
		time.Sleep(200 * time.Millisecond)
		if !isPortInUse(port) {
			return nil
		}
	}

	return apperror.New(apperror.ErrInternal, "port still in use after killing process").
		WithValue("port", portStr).
		WithValue("pid", pidStr)
}

func isPortInUse(port int) bool {
	ln, err := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port))
	if err != nil {
		return true
	}
	ln.Close()
	return false
}

func findPIDOnPort(port int) (int, error) {
	switch runtime.GOOS {
	case "windows":
		out, err := exec.Command("cmd", "/c", fmt.Sprintf("netstat -ano | findstr :%d | findstr LISTENING", port)).CombinedOutput()
		if err != nil {
			return 0, apperror.Wrap(err, apperror.ErrInternal, "netstat command failed")
		}
		return parseNetstatWindows(string(out), port)
	default:
		out, err := exec.Command("lsof", "-ti", fmt.Sprintf("tcp:%d", port)).CombinedOutput()
		if err != nil {
			return 0, apperror.Wrap(err, apperror.ErrInternal, "lsof command failed")
		}
		return strconv.Atoi(strings.TrimSpace(string(out)))
	}
}

func parseNetstatWindows(output string, port int) (int, error) {
	needle := fmt.Sprintf(":%d", port)
	for _, line := range strings.Split(output, "\n") {
		line = strings.TrimSpace(line)
		if line == "" || !strings.Contains(line, needle) {
			continue
		}
		fields := strings.Fields(line)
		if len(fields) >= 5 {
			return strconv.Atoi(fields[len(fields)-1])
		}
	}
	return 0, apperror.New(apperror.ErrInternal, "no PID found in netstat output").
		WithValue("port", strconv.Itoa(port))
}

func killProcess(pid int) error {
	switch runtime.GOOS {
	case "windows":
		if err := exec.Command("taskkill", "/F", "/PID", strconv.Itoa(pid)).Run(); err != nil {
			return apperror.Wrap(err, apperror.ErrInternal, "taskkill failed").
				WithValue("pid", strconv.Itoa(pid))
		}
		return nil
	default:
		if err := exec.Command("kill", "-9", strconv.Itoa(pid)).Run(); err != nil {
			return apperror.Wrap(err, apperror.ErrInternal, "kill command failed").
				WithValue("pid", strconv.Itoa(pid))
		}
		return nil
	}
}
