 # Issue Fixed: ZIP Finalization Race Condition
 
 ## Problem
 
 ZIP files created for plugin uploads were incomplete because `defer` was used to close the `zip.Writer`. The central directory (required for valid ZIP) was written *after* the function returned, causing "Could not find plugin file after extraction" errors.
 
 ## Solution
 
 1. Remove `defer` for `zipWriter.Close()` and `zipFile.Close()`
 2. Explicitly close writer FIRST, then file, BEFORE returning the path
 3. Verify file exists and has non-zero size before returning
 4. Clean up partial files on any error
 
 ## Key Pattern
 
 ```go
 // Close in correct order BEFORE return
 if err := zipWriter.Close(); err != nil {
     zipFile.Close()
     os.Remove(path)
     return "", err
 }
 if err := zipFile.Close(); err != nil {
     os.Remove(path)
     return "", err
 }
 
 // Verify
 if info, err := os.Stat(path); err != nil || info.Size() == 0 {
     return "", errors.New("invalid zip")
 }
 
 return path, nil
 ```
 
 ## Related: Temp File Preservation
 
 Temp ZIP files are now ALWAYS preserved on publish failure for debugging. The cleanup logic tracks `publishFailed` and only deletes on success (unless `keepZipFiles` is enabled).
 
 ## Files Changed
 
 - `backend/internal/services/publish/service.go`
 
 ## Date Fixed
 
 2026-02-05