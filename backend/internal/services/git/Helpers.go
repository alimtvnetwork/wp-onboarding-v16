// Package git — low-level command execution and output parsing helpers.
package git

import (
	"bytes"
	"context"
	"os/exec"
	"regexp"
	"strconv"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// requireGitRepo returns an AppError if the path is not a git repository.
func requireGitRepo(path string) *apperror.AppError {
	gitDir, err := pathutil.Join(path, ".git")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to resolve git directory path")
	}

	isGitMissing := pathutil.IsDirMissing(gitDir)

	if isGitMissing {
		return apperror.New(apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	return nil
}

// runGitCommand executes a git command in the specified directory
func (s *Service) runGitCommand(dir string, args ...string) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(s.timeout)*time.Second)
	defer cancel()

	cmd := exec.CommandContext(ctx, "git", args...)
	cmd.Dir = dir

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()
	if err != nil {
		return stderr.String(), apperror.Wrap(err, apperror.ErrGitCommand, stderr.String())
	}

	return stdout.String(), nil
}

// parseGitPullOutput extracts statistics from git pull output
func parseGitPullOutput(output string, result *PullResult) {
	re := regexp.MustCompile(`(\d+) files? changed(?:, (\d+) insertions?\(\+\))?(?:, (\d+) deletions?\(-\))?`)
	matches := re.FindStringSubmatch(output)
	hasFileChanges := len(matches) >= 2

	if hasFileChanges {
		parsed, parseErr := strconv.Atoi(matches[1])
		if parseErr == nil {
			result.FilesChanged = parsed
		}

		hasInsertions := len(matches) >= 3

		if hasInsertions {
			parsed, parseErr := strconv.Atoi(matches[2])
			if parseErr == nil {
				result.Insertions = parsed
			}
		}

		hasDeletions := len(matches) >= 4

		if hasDeletions {
			parsed, parseErr := strconv.Atoi(matches[3])
			if parseErr == nil {
				result.Deletions = parsed
			}
		}
	}
}
