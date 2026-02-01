.PHONY: build run test lint clean dev

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

# Run linter
lint:
	golangci-lint run

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

# Generate swagger docs (if using)
docs:
	swag init -g cmd/server/main.go

# Build for all platforms
build-all:
	GOOS=darwin GOARCH=amd64 go build -o bin/wp-plugin-publish-darwin-amd64 ./cmd/server
	GOOS=darwin GOARCH=arm64 go build -o bin/wp-plugin-publish-darwin-arm64 ./cmd/server
	GOOS=linux GOARCH=amd64 go build -o bin/wp-plugin-publish-linux-amd64 ./cmd/server
	GOOS=windows GOARCH=amd64 go build -o bin/wp-plugin-publish-windows-amd64.exe ./cmd/server
