// Package main — consistency-checker CLI entry point.
package main

import (
	"flag"
	"fmt"
	"os"

	_ "github.com/mattn/go-sqlite3"

	"consistency-checker/internal/config"
	"consistency-checker/internal/database"
	"consistency-checker/internal/engine"
	"consistency-checker/internal/report"
	"consistency-checker/internal/rules"
	"consistency-checker/internal/scanner"
)

// cliFlags holds parsed command-line arguments.
type cliFlags struct {
	Dir      string
	Config   string
	DBPath   string
	Format   string
	IsDryRun bool
}

func main() {
	flags := parseFlags()
	cfg := loadConfig(flags.Config)
	files := scanFiles(cfg, flags.Dir)

	result := runEngine(cfg, files)
	summary := report.BuildSummary(result.Findings, result.FilesCount)

	printOutput(flags.Format, result.Findings, summary)

	persistResults(flags, summary, result)
	exitWithCode(summary)
}

// printOutput renders findings in the requested format.
func printOutput(format string, findings []engine.Finding, summary report.Summary) {
	if format == "json" {
		report.PrintJSON(findings, summary)
		return
	}

	report.PrintFindings(findings)
	report.PrintSummary(summary)
}

// parseFlags reads CLI arguments.
func parseFlags() cliFlags {
	var flags cliFlags
	flag.StringVar(&flags.Dir, "dir", ".", "Directory to scan")
	flag.StringVar(&flags.Config, "config", "config/rules.json", "Path to rules.json")
	flag.StringVar(&flags.DBPath, "db", "data/findings.db", "Path to SQLite database")
	flag.StringVar(&flags.Format, "format", "text", "Output format: text or json")
	flag.BoolVar(&flags.IsDryRun, "dry-run", false, "Print findings without persisting")
	flag.Parse()
	return flags
}

// loadConfig reads and validates the config file.
func loadConfig(path string) config.Config {
	result := config.Load(path)
	if result.HasError() {
		fmt.Fprintf(os.Stderr, "Error: %s\n", result.AppError())
		os.Exit(2)
	}
	return result.Value()
}

// scanFiles discovers files in the target directory.
func scanFiles(cfg config.Config, dir string) []scanner.ScannedFile {
	scanResult := scanner.Scan(scanner.ScanInput{
		Directory:     dir,
		GlobalExclude: cfg.GlobalExclude,
	})
	if scanResult.HasError() {
		fmt.Fprintf(os.Stderr, "Error: %s\n", scanResult.AppError())
		os.Exit(2)
	}
	return scanResult.Value()
}

// runEngine creates the engine, registers rules, and executes.
func runEngine(cfg config.Config, files []scanner.ScannedFile) engine.RunResult {
	eng := engine.New()
	rules.RegisterAll(eng)

	return eng.Run(engine.RunInput{
		Files:        files,
		EnabledRules: cfg.EnabledRules(),
	})
}

// persistResults saves findings to SQLite unless dry-run.
func persistResults(flags cliFlags, summary report.Summary, result engine.RunResult) {
	if flags.IsDryRun {
		return
	}

	dbResult := database.Open(flags.DBPath)
	if dbResult.HasError() {
		fmt.Fprintf(os.Stderr, "Warning: %s\n", dbResult.AppError())
		return
	}
	db := dbResult.Value()
	defer db.Close()

	saveRun(db, flags, summary, result)
}

// saveRun persists the run and its findings.
func saveRun(db *database.DB, flags cliFlags, summary report.Summary, result engine.RunResult) {
	runResult := db.StartRun(flags.Dir, flags.Config)
	if runResult.HasError() {
		return
	}
	runId := runResult.Value()

	for _, f := range result.Findings {
		db.InsertFinding(runId, toDBFinding(f))
	}

	db.CompleteRun(database.Run{
		Id: runId, TotalFiles: summary.TotalFiles,
		TotalFindings: summary.TotalFindings,
		ErrorCount:    summary.ErrorCount,
		WarningCount:  summary.WarningCount,
		InfoCount:     summary.InfoCount,
		ExitCode:      exitCode(summary),
	})
}

// toDBFinding converts an engine Finding to a database Finding.
func toDBFinding(f engine.Finding) database.Finding {
	return database.Finding{
		RuleId: f.RuleID, RuleName: f.RuleName,
		Severity: f.Severity, FilePath: f.FilePath,
		Line: f.Line, EndLine: f.EndLine,
		Message: f.Message, Suggestion: f.Suggestion,
		Reference: f.Reference, Context: f.Context,
	}
}

// exitCode returns 1 if errors exist, 0 otherwise.
func exitCode(s report.Summary) int {
	if s.ErrorCount > 0 {
		return 1
	}
	return 0
}

// exitWithCode exits the process with appropriate code.
func exitWithCode(s report.Summary) {
	os.Exit(exitCode(s))
}
