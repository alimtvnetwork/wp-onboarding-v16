package main

import (
	"fmt"
	"log"
	"net/http"
	"os"

	"riseup-licensing/internal/config"
	"riseup-licensing/internal/database"
	"riseup-licensing/internal/router"
)

func main() {
	cfg := config.Load()

	db, dbErr := database.Open(cfg.DBPath)
	if dbErr != nil {
		log.Fatalf("Failed to open database: %v", dbErr)
	}
	defer db.Close()

	migrateErr := database.Migrate(db)
	if migrateErr != nil {
		log.Fatalf("Failed to run migrations: %v", migrateErr)
	}

	r := router.New(router.Config{
		DB:         db,
		HMACSecret: cfg.HMACSecret,
		AdminToken: cfg.AdminToken,
		RateLimit:  cfg.RateLimit,
	})

	addr := fmt.Sprintf(":%d", cfg.Port)
	log.Printf("Licensing server starting on %s", addr)
	log.Printf("Database: %s", cfg.DBPath)

	serverErr := http.ListenAndServe(addr, r)
	if serverErr != nil {
		fmt.Fprintf(os.Stderr, "Server error: %v\n", serverErr)
		os.Exit(1)
	}
}
