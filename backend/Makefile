.PHONY: build run test lint clean dev validate-openapi

# Build the application
build:
	go build -o bin/wp-plugin-publish ./cmd/server

# Run the application
run:
	go run ./cmd/server

# Run tests
test:
	go test -v ./...

# Run tests with coverage
coverage:
	go test -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out -o coverage.html

# Run golangci-lint
lint:
	golangci-lint run

# Run all custom lint scripts
lint-all: lint-file-size lint-func-size lint-negative lint-imports lint-ge lint-json-tags lint-inline-if lint-typed-nil
	@echo "✅ All lint checks passed"

# Individual lint targets
lint-file-size:
	bash ../scripts/lint-file-size.sh

lint-func-size:
	bash ../scripts/lint-func-size.sh

lint-negative:
	bash ../scripts/lint-negative.sh

lint-imports:
	bash ../scripts/lint-imports.sh

lint-ge:
	bash ../scripts/lint-ge.sh --go

lint-json-tags:
	bash ../scripts/lint-json-tags.sh

lint-inline-if:
	bash ../scripts/lint-inline-if.sh

lint-typed-nil:
	bash ../scripts/lint-typed-nil.sh

# Clean build artifacts
clean:
	rm -rf bin/
	rm -f coverage.out coverage.html

# Development mode with hot reload (requires air)
dev:
	air

# Initialize the database
init-db:
	mkdir -p data backups .temp
	go run ./cmd/server -init

# Format code
fmt:
	go fmt ./...

# Validate OpenAPI spec (CI-friendly, uses npx so no global install needed)
validate-openapi:
	@echo "Validating OpenAPI spec..."
	@npx --yes @redocly/cli lint api/openapi.json --skip-rule no-unused-components && echo "✅ OpenAPI spec is valid" || (echo "❌ OpenAPI spec validation failed" && exit 1)

# Generate swagger docs (if using)
docs:
	swag init -g cmd/server/main.go

# Build for all platforms
build-all:
	GOOS=darwin GOARCH=amd64 go build -o bin/wp-plugin-publish-darwin-amd64 ./cmd/server
	GOOS=darwin GOARCH=arm64 go build -o bin/wp-plugin-publish-darwin-arm64 ./cmd/server
	GOOS=linux GOARCH=amd64 go build -o bin/wp-plugin-publish-linux-amd64 ./cmd/server
	GOOS=windows GOARCH=amd64 go build -o bin/wp-plugin-publish-windows-amd64.exe ./cmd/server
