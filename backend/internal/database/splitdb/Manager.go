// Package splitdb provides hierarchical SQLite database management
package splitdb

import (
	"database/sql"
	"fmt"
	"os"
	"regexp"
	"strings"
	"sync"
	"time"

	_ "github.com/mattn/go-sqlite3"

	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// DBManager manages a hierarchical split database structure
type DBManager struct {
	rootDB   *sql.DB
	dataDir  string
	openDBs  map[string]*sql.DB
	mu       sync.RWMutex
	log      *logger.Logger
	maxOpen  int
	maxIdle  int
	connLife time.Duration
}

// Project represents a project in the split database
type Project struct {
	Id          string
	Slug        string
	DisplayName string
	Path        string
	Status      string
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

// Database represents a child database record
type Database struct {
	Id           string
	ProjectId    string
	Type         string
	EntityId     string
	Path         string
	SizeBytes    int64
	RecordCount  int64
	Status       string
	CreatedAt    time.Time
	UpdatedAt    time.Time
	LastAccessed *time.Time
}

// DatabaseStats holds statistics for a database
type DatabaseStats struct {
	Id          string
	DatabaseId  string
	RecordedAt  time.Time
	SizeBytes   int64
	RecordCount int64
	QueryCount  int64
	AvgQueryMs  float64
}

// Config holds DBManager configuration
type Config struct {
	DataDir  string
	Logger   *logger.Logger
	MaxOpen  int
	MaxIdle  int
	ConnLife time.Duration
}

// NewDBManager creates a new split database manager
func NewDBManager(cfg Config) (*DBManager, error) {
	if cfg.MaxOpen == 0 {
		cfg.MaxOpen = 50
	}
	if cfg.MaxIdle == 0 {
		cfg.MaxIdle = 2
	}
	if cfg.ConnLife == 0 {
		cfg.ConnLife = time.Hour
	}

	err := os.MkdirAll(cfg.DataDir, 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to create data dir").
			WithPath(cfg.DataDir)
	}

	rootPath, err := pathutil.Join(cfg.DataDir, "root.db")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to resolve root db path")
	}
	rootDB, err := sql.Open("sqlite3", rootPath+"?_foreign_keys=on&_journal_mode=WAL")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to open root db").
			WithPath(rootPath)
	}

	err = configureDB(rootDB)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to configure root db").
			WithPath(rootPath)
	}

	manager := &DBManager{
		rootDB:   rootDB,
		dataDir:  cfg.DataDir,
		openDBs:  make(map[string]*sql.DB),
		log:      cfg.Logger,
		maxOpen:  cfg.MaxOpen,
		maxIdle:  cfg.MaxIdle,
		connLife: cfg.ConnLife,
	}

	err = manager.initRootSchema()
	if err != nil {

		return nil, err
	}

	return manager, nil
}

// configureDB sets up SQLite for optimal concurrent access
func configureDB(db *sql.DB) error {
	pragmas := []string{
		"PRAGMA journal_mode=WAL",
		"PRAGMA busy_timeout=5000",
		"PRAGMA foreign_keys=ON",
		"PRAGMA synchronous=NORMAL",
	}

	for _, pragma := range pragmas {
		_, err := db.Exec(pragma)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to execute pragma").
				WithDetails(pragma)
		}
	}

	return nil
}

// GenerateSlug converts a name to a URL-safe slug
func GenerateSlug(name string) string {
	slug := strings.ToLower(name)
	slug = regexp.MustCompile(`[^a-z0-9]+`).ReplaceAllString(slug, "-")
	slug = strings.Trim(slug, "-")
	return slug
}

// generateId generates a unique ID
func generateId() string {
	return fmt.Sprintf("%d", time.Now().UnixNano())
}
