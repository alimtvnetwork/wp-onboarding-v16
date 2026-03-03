// Package router provides HTTP route registration for the licensing server.
package router

import (
	"database/sql"
	"net/http"
	"time"

	"github.com/gorilla/mux"

	"riseup-licensing/internal/handlers"
	"riseup-licensing/internal/middleware"
	"riseup-licensing/internal/services"
	"riseup-licensing/pkg/ratelimit"
)

// Config holds router dependencies.
type Config struct {
	DB         *sql.DB
	HMACSecret string
	AdminToken string
	RateLimit  int
}

// New creates a new HTTP router with all licensing endpoints registered.
func New(cfg Config) http.Handler {
	r := mux.NewRouter()
	r.Use(jsonContentType)

	api := r.PathPrefix("/api/v1").Subrouter()
	api.HandleFunc("/health", healthHandler).Methods("GET")

	svcGroup := buildServices(cfg.DB)
	registerPublicRoutes(api, cfg, svcGroup)
	registerAdminRoutes(api, cfg, svcGroup)

	return r
}

// serviceGroup bundles all service instances.
type serviceGroup struct {
	Licenses    *services.LicenseService
	Activations *services.ActivationService
	Audit       *services.AuditService
}

// buildServices creates all service instances from the database.
func buildServices(db *sql.DB) serviceGroup {

	return serviceGroup{
		Licenses:    services.NewLicenseService(db),
		Activations: services.NewActivationService(db),
		Audit:       services.NewAuditService(db),
	}
}

// registerPublicRoutes adds HMAC-protected public license endpoints.
func registerPublicRoutes(
	api *mux.Router,
	cfg Config,
	svc serviceGroup,
) {
	pub := api.PathPrefix("/licenses").Subrouter()

	rate := resolveRateLimit(cfg.RateLimit)
	limiter := ratelimit.New(rate, time.Minute)

	pub.Use(middleware.RateLimit(limiter))
	pub.Use(middleware.HMACAuth(cfg.HMACSecret))

	h := &handlers.PublicHandlers{
		Licenses:    svc.Licenses,
		Activations: svc.Activations,
		Audit:       svc.Audit,
	}

	pub.HandleFunc("/{key}/validate", h.Validate).Methods("GET")
	pub.HandleFunc("/{key}/activate", h.Activate).Methods("POST")
	pub.HandleFunc("/{key}/deactivate", h.Deactivate).Methods("POST")
	pub.HandleFunc("/{key}/status", h.Status).Methods("GET")
}

// registerAdminRoutes adds Bearer-token-protected admin endpoints.
func registerAdminRoutes(
	api *mux.Router,
	cfg Config,
	svc serviceGroup,
) {
	admin := api.PathPrefix("/admin").Subrouter()
	admin.Use(middleware.AdminAuth(cfg.AdminToken))

	h := &handlers.AdminHandlers{
		Licenses: svc.Licenses,
		Audit:    svc.Audit,
	}

	admin.HandleFunc("/licenses", h.CreateLicense).Methods("POST")
	admin.HandleFunc("/licenses", h.ListLicenses).Methods("GET")
	admin.HandleFunc("/licenses/{id:[0-9]+}", h.GetLicense).Methods("GET")
	admin.HandleFunc("/licenses/{id:[0-9]+}", h.UpdateLicense).Methods("PATCH")
	admin.HandleFunc("/licenses/{id:[0-9]+}", h.DeleteLicense).Methods("DELETE")
}

// resolveRateLimit returns the configured rate or a sensible default.
func resolveRateLimit(rate int) int {
	isInvalid := rate <= 0

	if isInvalid {

		return 60
	}

	return rate
}

// jsonContentType sets the Content-Type header for all responses.
func jsonContentType(next http.Handler) http.Handler {

	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		next.ServeHTTP(w, r)
	})
}

// healthHandler returns a simple health check response.
func healthHandler(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte(`{"status":"ok","service":"licensing"}`)) //nolint:errcheck
}
