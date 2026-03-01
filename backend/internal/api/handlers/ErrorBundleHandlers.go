// Package handlers provides error bundle ZIP download handler
package handlers

import (
	"archive/zip"
	"encoding/json"
	"io"
	"log"
	"net/http"
	"os"
	"time"

	"wp-plugin-publish/internal/constants/logfile"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/ziputil"
)

// errorBundleInput holds resolved paths and flags for writing the error bundle ZIP.
type errorBundleInput struct {
	LogFile     string
	ErrorFile   string
	LogExists   bool
	ErrorExists bool
	Report      string
}

// DownloadErrorBundle creates and serves a ZIP bundle of error logs
func DownloadErrorBundle(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"
	report := extractReportFromBody(r)

	logFile := dataDir + "/log.txt"
	errorFile := dataDir + "/error.log.txt"

	logExists := fileExists(logFile)
	errorExists := fileExists(errorFile)

	hasLogFiles :=
		logExists ||
			errorExists
	if !hasLogFiles {
		respondError(w, wordpress.HttpStatusNotFound, "E9001", "No error log files found")

		return
	}

	input := errorBundleInput{
		LogFile: logFile, ErrorFile: errorFile,
		LogExists: logExists, ErrorExists: errorExists,
		Report: report,
	}
	writeErrorBundleZip(w, input)
}

// extractReportFromBody reads the optional report field from a POST body.
func extractReportFromBody(r *http.Request) string {
	isReadOnly := r.Method != http.MethodPost

	if isReadOnly {
		return ""
	}

	var payload struct {
		Report string `json:"report"` // external key (frontend request body)
	}

	bodyBytes, err := io.ReadAll(io.LimitReader(r.Body, 2*1024*1024))
	if err != nil {
		log.Printf("[WARN] Failed to read report body: %v", err)

		return ""
	}

	hasBody := len(bodyBytes) > 0
	if hasBody {
		unmarshalErr := json.Unmarshal(bodyBytes, &payload)

		if unmarshalErr != nil {
			log.Printf("[WARN] Failed to unmarshal report body: %v", unmarshalErr)
		}
	}

	return payload.Report
}

// writeErrorBundleZip writes the ZIP response with log files and manifest.
func writeErrorBundleZip(w http.ResponseWriter, input errorBundleInput) {
	w.Header().Set("Content-Type", "application/zip")
	w.Header().Set("Content-Disposition", "attachment; filename=error-bundle-"+time.Now().Format("20060102-150405")+".zip")

	zipWriter := zip.NewWriter(w)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	addBundleLogFiles(zipWriter, input)
	addBundleReport(zipWriter, input.Report)
	addBundleManifest(zipWriter, input)
}

// addBundleLogFiles adds the log and error files to the ZIP.
func addBundleLogFiles(zipWriter *zip.Writer, input errorBundleInput) {
	if input.LogExists {
		addErr := addFileToZip(zipWriter, input.LogFile, logfile.AllLog)

		if addErr != nil {
			log.Printf("[WARN] Failed to add log file to bundle: path=%s error=%v", input.LogFile, addErr)
		}
	}

	if input.ErrorExists {
		addErr := addFileToZip(zipWriter, input.ErrorFile, logfile.ErrorLog)

		if addErr != nil {
			log.Printf("[WARN] Failed to add error file to bundle: path=%s error=%v", input.ErrorFile, addErr)
		}
	}
}

// addBundleReport adds the user report to the ZIP if present.
func addBundleReport(zipWriter *zip.Writer, report string) {
	isReportEmpty := report == ""

	if isReportEmpty {

		return
	}

	reportWriter, err := zipWriter.Create(logfile.Report)
	if err != nil {
		log.Printf("[WARN] Failed to create report entry in bundle: %v", err)

		return
	}

	_, writeErr := io.WriteString(reportWriter, report)

	if writeErr != nil {
		log.Printf("[WARN] Failed to write report to bundle: %v", writeErr)
	}
}

// addBundleManifest writes the manifest.json into the ZIP.
func addBundleManifest(zipWriter *zip.Writer, input errorBundleInput) {
	manifest := buildManifestFiles(input)

	manifestWriter, err := zipWriter.Create(logfile.Manifest)
	if err != nil {
		log.Printf("[WARN] Failed to create manifest entry in bundle: %v", err)

		return
	}

	encodeErr := json.NewEncoder(manifestWriter).Encode(manifest)

	if encodeErr != nil {
		log.Printf("[WARN] Failed to encode manifest in bundle: %v", encodeErr)
	}
}

// bundleManifest is the manifest structure written to the ZIP.
type bundleManifest struct {
	GeneratedAt string   `json:"generatedAt"` // external key (export manifest JSON file)
	Files       []string `json:"files"`       // external key
}

// buildManifestFiles constructs the manifest with the list of included files.
func buildManifestFiles(input errorBundleInput) bundleManifest {
	files := []string{}
	if input.LogExists {
		files = append(files, logfile.AllLog)
	}

	if input.ErrorExists {
		files = append(files, logfile.ErrorLog)
	}

	hasReport := input.Report != ""
	if hasReport {
		files = append(files, logfile.Report)
	}

	return bundleManifest{GeneratedAt: time.Now().Format(time.RFC3339), Files: files}
}

// addFileToZip adds a file from disk into the ZIP archive.
func addFileToZip(zipWriter *zip.Writer, srcPath string, destName string) *apperror.AppError {
	file, err := os.Open(srcPath)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrFileOpen, "failed to open file for zip").WithPath(srcPath)
	}
	defer file.Close()

	writer, err := zipWriter.Create(destName)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrZipCreate, "failed to create zip entry").WithPath(destName)
	}

	_, err = io.Copy(writer, file)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrZipWrite, "failed to copy file into zip").WithPath(srcPath)
	}

	return nil
}
