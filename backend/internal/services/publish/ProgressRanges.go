package publish

// ─── Normalized Progress Ranges ──────────────────────────────────────────────
//
// Each publish pipeline stage owns a fixed percentage band.
// Progress values MUST be monotonically increasing across stages.
//
// Pipeline order:
//   backup → remote_backup → cloud_upload → package → pre_backup → upload → activate → cleanup → complete
//
// ┌────────────────┬───────┬─────┐
// │ Stage          │ Start │ End │
// ├────────────────┼───────┼─────┤
// │ backup         │   5   │ 10  │
// │ remote_backup  │  12   │ 15  │
// │ cloud_upload   │  18   │ 25  │
// │ package        │  28   │ 38  │
// │ pre_backup     │  40   │ 48  │
// │ upload         │  50   │ 70  │
// │ activate       │  72   │ 85  │
// │ rollback       │  86   │ 90  │
// │ cleanup        │  92   │ 96  │
// │ complete       │ 100   │ 100 │
// └────────────────┴───────┴─────┘

// stageProgress defines start/end percentage for a pipeline stage.
type stageProgress struct {
	Start int
	End   int
}

// Centralized progress ranges for all pipeline stages.
var (
	progressBackup       = stageProgress{Start: 5, End: 10}
	progressRemoteBackup = stageProgress{Start: 12, End: 15}
	progressCloudUpload  = stageProgress{Start: 18, End: 25}
	progressPackage      = stageProgress{Start: 28, End: 38}
	progressPreBackup    = stageProgress{Start: 40, End: 48}
	progressUpload       = stageProgress{Start: 50, End: 70}
	progressActivate     = stageProgress{Start: 72, End: 85}
	progressRollback     = stageProgress{Start: 86, End: 90}
	progressCleanup      = stageProgress{Start: 92, End: 96}
	progressComplete     = 100
)

// lerp computes a value between Start and End based on fraction (0.0–1.0).
func (sp stageProgress) lerp(fraction float64) int {
	if fraction <= 0 {
		return sp.Start
	}
	if fraction >= 1 {
		return sp.End
	}
	return sp.Start + int(fraction*float64(sp.End-sp.Start))
}
