// Package wordpress — PowerShell command execution and result parsing.
package wordpress

import (
	"bytes"
	"encoding/json"
	"os/exec"
	"strings"
)

// executePowerShellCommand runs a PowerShell command and processes the output.
func executePowerShellCommand(args []string, onOutput func(line string)) (*PowerShellResult, error) {
	cmd := exec.Command("powershell.exe", args...)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	result := buildPsResult(cmd, &stdout, &stderr)
	parsePsJsonOutput(result)
	streamPsStderr(result, onOutput)
	finalizePsResult(result, err)

	return result, nil
}

// buildPsResult creates a PowerShellResult from command output.
func buildPsResult(cmd *exec.Cmd, stdout, stderr *bytes.Buffer) *PowerShellResult {
	result := &PowerShellResult{
		IsSuccess: false,
		ExitCode:  -1,
		Stdout:    stdout.String(),
		Stderr:    stderr.String(),
	}

	hasProcessState := cmd.ProcessState != nil
	if hasProcessState {
		result.ExitCode = cmd.ProcessState.ExitCode()
	}

	return result
}

// parsePsJsonOutput parses JSON from PowerShell stdout quiet mode.
func parsePsJsonOutput(result *PowerShellResult) {
	isStdoutEmpty := result.Stdout == ""

	if isStdoutEmpty {

		return
	}

	var jsonResult psJsonOutput
	err := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult)

	if err != nil {

		return
	}

	result.IsSuccess = jsonResult.Success
	result.Plugin = jsonResult.Plugin
	result.IsActivated = jsonResult.Activated
	result.ErrorMessage = jsonResult.Error
}

// streamPsStderr streams stderr lines to the output callback.
func streamPsStderr(result *PowerShellResult, onOutput func(line string)) {
	isCallbackMissing := onOutput == nil
	isStderrEmpty := result.Stderr == ""
	isSkippable := isCallbackMissing || isStderrEmpty

	if isSkippable {
		return
	}

	for _, line := range strings.Split(result.Stderr, "\n") {
		line = strings.TrimSpace(line)

		hasContent := line != ""
		if hasContent {
			onOutput("[PS] " + line)
		}
	}
}

// finalizePsResult sets final success/error state on the result.
func finalizePsResult(result *PowerShellResult, err error) {
	hasError := err != nil
	isErrorMessageEmpty := result.ErrorMessage == ""
	isUnreportedError := hasError && isErrorMessageEmpty

	if isUnreportedError {
		result.ErrorMessage = err.Error()
	}

	isZeroExit := result.ExitCode == 0
	isCleanResult := isZeroExit && isErrorMessageEmpty

	if isCleanResult {
		result.IsSuccess = true
	}
}
