 # ZIP Creation Rules

 ## Compression: Use Default Level (Speed-Optimized)

 All ZIP creation across the project uses `DefaultCompression` (level 6) for the best speed/size tradeoff:

 ### Go (backend)

 Use the shared `ziputil` package to register `flate.DefaultCompression` (level 6):

 ```go
 import "wp-plugin-publish/pkg/ziputil"

 zipWriter := zip.NewWriter(file)
 ziputil.RegisterBestCompression(zipWriter)
 ```

 This is applied in: `publish/service.go`, `site/service.go`, `backup/service.go`, `splitdb/export.go`, `error_settings_handlers.go`.

 ### PowerShell (scripts)

 Use `[System.IO.Compression.ZipFile]::CreateFromDirectory()` with `SmallestSize` level — **NOT** `Compress-Archive`:

 ```powershell
 Add-Type -AssemblyName System.IO.Compression.FileSystem
 [System.IO.Compression.ZipFile]::CreateFromDirectory(
     $sourceDir, $destZip,
     [System.IO.Compression.CompressionLevel]::SmallestSize,
     $true  # includeBaseDirectory
 )
 ```

 `SmallestSize` yields ~20-40% smaller files than `Compress-Archive -CompressionLevel Optimal`.

 ### PHP (WordPress)

 PHP's `ZipArchive` handles all standard deflate levels natively — no changes needed on the extraction side.

 ## Critical Pattern: Finalize Before Return (Go)

 When creating ZIP files that will be immediately used (uploaded, read, etc.), **NEVER** use `defer` for closing the `zip.Writer` or file handle.

 ### Why

 `zip.Writer.Close()` writes the **central directory** to the ZIP file. This is required for the archive to be valid. With `defer`, this happens *after* the function returns, creating a race condition.

 ### Correct Pattern

 ```go
 zipFile, err := os.Create(path)
 if err != nil {
     return "", err
 }
 // NO defer zipFile.Close()

 zipWriter := zip.NewWriter(zipFile)
 ziputil.RegisterBestCompression(zipWriter)
 // NO defer zipWriter.Close()

 // ... write files ...

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

 // Verify file is valid
 if info, err := os.Stat(path); err != nil || info.Size() == 0 {
     os.Remove(path)
     return "", errors.New("invalid zip")
 }

 return path, nil
 ```

 ### Close Order Matters

 1. `zipWriter.Close()` - writes central directory to the underlying file
 2. `zipFile.Close()` - flushes and closes the OS file handle

 Reversing this order will result in a corrupted ZIP.

 ## Temp File Preservation

 Temp ZIP files must be **preserved on failure** for debugging:

 - Track `publishFailed` flag throughout the operation
 - In cleanup `defer`, only delete if `!publishFailed && !keepZipFiles`
 - Log the preserved path so users can inspect the file

 ## ZIP Structure for WordPress Plugins

 The root folder inside the ZIP must use the **slug** (lowercase, hyphenated), not the display name:

 - ✅ `category-generator/category-generator.php`
 - ❌ `Category Generator/category-generator.php`

 This matches the PowerShell script behavior and WordPress expectations.

 ## References

 - Error resolution: `spec/07-error-manage/03-error-resolution/02-zip-finalization-before-return.md`
 - Compression helper: `backend/pkg/ziputil/ziputil.go`
 - Working reference: `wp-plugins/scripts/upload-plugin.ps1`