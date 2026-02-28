// Package sync — FileChange builder helpers for compareFiles.
package sync

import (
	changetype "wp-plugin-publish/internal/enums/changetype"
	syncdirection "wp-plugin-publish/internal/enums/syncdirectiontype"
	"wp-plugin-publish/internal/models"
)

// buildModifiedChange creates a FileChange for a modified file, or nil if hashes match.
func buildModifiedChange(path string, local, remote FileEntry) *models.FileChange {
	if local.Hash == remote.Hash {
		return nil
	}

	localMod := local.ModifiedAt
	remoteMod := remote.ModifiedAt
	direction := syncdirection.LocalNewer.Value()
	if remoteMod.After(localMod) {
		direction = syncdirection.RemoteNewer.Value()
	}

	return &models.FileChange{
		FilePath:         path,
		ChangeType:       changetype.Modified.Value(),
		LocalHash:        local.Hash,
		RemoteHash:       remote.Hash,
		LocalModifiedAt:  &localMod,
		RemoteModifiedAt: &remoteMod,
		LocalSize:        local.Size,
		RemoteSize:       remote.Size,
		Direction:        direction,
	}
}

// buildAddedChange creates a FileChange for a locally-added file.
func buildAddedChange(path string, local FileEntry) models.FileChange {
	localMod := local.ModifiedAt
	return models.FileChange{
		FilePath:        path,
		ChangeType:      changetype.Added.Value(),
		LocalHash:       local.Hash,
		LocalModifiedAt: &localMod,
		LocalSize:       local.Size,
		Direction:       syncdirection.LocalOnly.Value(),
	}
}

// buildDeletedChange creates a FileChange for a remotely-only file (deleted locally).
func buildDeletedChange(path string, remote FileEntry) models.FileChange {
	remoteMod := remote.ModifiedAt
	return models.FileChange{
		FilePath:         path,
		ChangeType:       changetype.Deleted.Value(),
		RemoteHash:       remote.Hash,
		RemoteModifiedAt: &remoteMod,
		RemoteSize:       remote.Size,
		Direction:        syncdirection.RemoteOnly.Value(),
	}
}
