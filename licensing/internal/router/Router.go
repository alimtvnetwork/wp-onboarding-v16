// Package router provides HTTP route registration for the licensing server.
package router

import (
	"database/sql"
	"net/http"

	"github.com/gorilla/mux"
)

// Config holds router dependencies.
type Config struct {
	DB         *sql.DB
	HMACSecret string
	AdminToken string
}

// New creates a new HTTP router with all licensing endpoints registered.
func New(cfg Config) http.Handler {
	r := mux.NewRouter()

	api := r.PathPrefix("/api/v1").Subrouter()

	// Health check
	api.HandleFunc("/health", healthHandler).Methods("GET")

	// Public endpoints (HMAC-signed) — to be implemented
	// api.HandleFunc("/licenses/{key}/validate", ...).Methods("GET")
	// api.HandleFunc("/licenses/{key}/activate", ...).Methods("POST")
	// api.HandleFunc("/licenses/{key}/deactivate", ...).Methods("POST")
	// api.HandleFunc("/licenses/{key}/status", ...).Methods("GET")

	// Admin endpoints (Bearer token) — to be implemented
	// admin := api.PathPrefix("/admin").Subrouter()
	// admin.Use(adminAuthMiddleware(cfg.AdminToken))
	// admin.HandleFunc("/licenses", ...).Methods("POST")
	// admin.HandleFunc("/licenses", ...).Methods("GET")
	// admin.HandleFunc("/licenses/{id}", ...).Methods("PATCH")
	// admin.HandleFunc("/licenses/{id}", ...).Methods("DELETE")
	// admin.HandleFunc("/audit", ...).Methods("GET")

	return r
}

// healthHandler returns a simple health check response.
func healthHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	w.Write([]byte(`{"status":"ok","service":"licensing"}`)) //nolint:errcheck
}
