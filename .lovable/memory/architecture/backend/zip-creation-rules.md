 # ZIP Creation Rules
 
 ## Critical Pattern: Finalize Before Return
 
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
 
 This matches the PowerShell `upload-plugin.ps1` behavior and WordPress expectations.
 
 ## References
 
 - Error resolution: `spec/error-resolution/02-zip-finalization-before-return.md`
 - Working reference: `wp-plugins/scripts/upload-plugin.ps1`