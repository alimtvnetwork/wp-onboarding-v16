package sync

import (
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
)

// compareFiles compares local files with remote files and returns differences
func (s *serviceImpl) compareFiles(local []plugin.FileInfo, remote []wordpress.RemoteFile) []FileChange {
	var changes []FileChange

	// Build map of remote files by path
	remoteMap := make(map[string]wordpress.RemoteFile)
	for _, f := range remote {
		remoteMap[f.Path] = f
	}

	// Check local files against remote
	localPaths := make(map[string]bool)
	for _, lf := range local {
		if lf.IsDirectory {
			continue
		}
		localPaths[lf.Path] = true

		if rf, exists := remoteMap[lf.Path]; exists {
			// File exists on both - check if modified
			if lf.Hash != rf.Hash {
				changes = append(changes, FileChange{
					Path:        lf.Path,
					ChangeType:  "modified",
					LocalHash:   lf.Hash,
					RemoteHash:  rf.Hash,
					LocalSize:   lf.Size,
					RemoteSize:  rf.Size,
					LocalMTime:  lf.ModifiedAt,
					RemoteMTime: rf.ModifiedAt,
				})
			}
		} else {
			// File only exists locally - needs to be added
			changes = append(changes, FileChange{
				Path:       lf.Path,
				ChangeType: "added",
				LocalHash:  lf.Hash,
				LocalSize:  lf.Size,
				LocalMTime: lf.ModifiedAt,
			})
		}
	}

	// Check for deleted files (exist on remote but not local)
	for _, rf := range remote {
		if !localPaths[rf.Path] {
			changes = append(changes, FileChange{
				Path:        rf.Path,
				ChangeType:  "deleted",
				RemoteHash:  rf.Hash,
				RemoteSize:  rf.Size,
				RemoteMTime: rf.ModifiedAt,
			})
		}
	}

	return changes
}
