# Skipped: Kubernetes Deployment

**Status:** ⏸️ Skipped  
**Complexity:** High  
**Updated:** 2026-01-29  

---

## Why Skipped

For simplicity, we are not using Kubernetes deployment. The application runs locally as a simple Go binary — no containers, orchestration, or cloud infrastructure required.

---

## What This Would Include

If implemented, Kubernetes deployment would provide:

### Components Required

| Component | Purpose | Complexity |
|-----------|---------|------------|
| Docker | Container runtime | Medium |
| Kubernetes | Container orchestration | High |
| Helm | Package management | Medium |
| Ingress Controller | Traffic routing | Medium |
| PersistentVolumeClaim | Database storage | Medium |

### Dockerfile

```dockerfile
# Would require containerization
FROM golang:1.22-alpine AS builder
WORKDIR /app
COPY . .
RUN CGO_ENABLED=1 go build -o gsearch ./main.go

FROM alpine:3.19
RUN apk add --no-cache ca-certificates sqlite
COPY --from=builder /app/gsearch /usr/local/bin/
ENTRYPOINT ["gsearch"]
```

### Kubernetes Manifests

```yaml
# Would require deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: gsearch
spec:
  replicas: 2
  template:
    spec:
      containers:
        - name: gsearch
          image: gsearch:1.2.0
          ports:
            - containerPort: 8080
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
          resources:
            requests:
              memory: "256Mi"
              cpu: "250m"
```

### Helm Chart Structure

```
gsearch-chart/
├── Chart.yaml
├── values.yaml
├── templates/
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── configmap.yaml
│   └── secret.yaml
```

---

## Simple Alternative (What We Use Instead)

```bash
# Build locally
go build -o gsearch ./main.go

# Run directly
./gsearch search "query"

# Or install to PATH
sudo cp gsearch /usr/local/bin/

# Run as background process (if needed)
nohup ./gsearch daemon &
```

---

## Revisit Criteria

Consider implementing if:
- Deploying to cloud infrastructure (AWS, GCP, Azure)
- Horizontal scaling across multiple nodes needed
- High availability requirements (99.9%+ uptime)
- Blue-green or canary deployments required

---

## Cross-References

- [Deployment Guide](../05-features/22-golang-search-cli/17-deployment-guide.md) — Simple local deployment
- [Skipped Features Overview](./00-overview.md) — Why features are skipped
