# Deployment & CI/CD Guidelines

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This specification defines continuous integration, continuous deployment, and release management practices across PHP, TypeScript, and Python projects.

---

## 1. CI/CD Pipeline Architecture

### 1.1 Pipeline Stages

```
┌─────────┐   ┌─────────┐   ┌─────────┐   ┌─────────┐   ┌─────────┐
│  Build  │ → │  Test   │ → │ Analyze │ → │ Deploy  │ → │ Verify  │
└─────────┘   └─────────┘   └─────────┘   └─────────┘   └─────────┘
```

| Stage | Purpose | Failure Action |
|-------|---------|----------------|
| Build | Compile, bundle, install deps | Block pipeline |
| Test | Unit, integration, E2E tests | Block pipeline |
| Analyze | Lint, security scan, coverage | Block or warn |
| Deploy | Deploy to environment | Block pipeline |
| Verify | Smoke tests, health checks | Rollback |

### 1.2 Environment Progression

```
Feature Branch → develop → staging → production
     ↓              ↓          ↓          ↓
   Preview      Dev Env    Staging    Production
   (ephemeral)  (shared)   (prod-like) (live)
```

---

## 2. GitHub Actions Configuration

### 2.1 Main CI Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

env:
  NODE_VERSION: '20'
  PNPM_VERSION: '8'

jobs:
  # ============================================
  # Build & Lint
  # ============================================
  build:
    name: Build & Lint
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          
      - name: Setup pnpm
        uses: pnpm/action-setup@v2
        with:
          version: ${{ env.PNPM_VERSION }}
          
      - name: Get pnpm store directory
        id: pnpm-cache
        shell: bash
        run: echo "STORE_PATH=$(pnpm store path)" >> $GITHUB_OUTPUT
        
      - name: Cache pnpm dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.pnpm-cache.outputs.STORE_PATH }}
          key: ${{ runner.os }}-pnpm-${{ hashFiles('**/pnpm-lock.yaml') }}
          restore-keys: ${{ runner.os }}-pnpm-
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Lint
        run: pnpm lint
        
      - name: Type check
        run: pnpm type-check
        
      - name: Build
        run: pnpm build
        
      - name: Upload build artifacts
        uses: actions/upload-artifact@v4
        with:
          name: build
          path: dist/
          retention-days: 1

  # ============================================
  # Unit Tests
  # ============================================
  test-unit:
    name: Unit Tests
    runs-on: ubuntu-latest
    needs: build
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          
      - name: Setup pnpm
        uses: pnpm/action-setup@v2
        with:
          version: ${{ env.PNPM_VERSION }}
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Run unit tests
        run: pnpm test:unit --coverage
        
      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          files: coverage/lcov.info
          fail_ci_if_error: true
          token: ${{ secrets.CODECOV_TOKEN }}

  # ============================================
  # Integration Tests
  # ============================================
  test-integration:
    name: Integration Tests
    runs-on: ubuntu-latest
    needs: build
    
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: test
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
          
      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          
      - name: Setup pnpm
        uses: pnpm/action-setup@v2
        with:
          version: ${{ env.PNPM_VERSION }}
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Run migrations
        run: pnpm db:migrate
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/test
          
      - name: Run integration tests
        run: pnpm test:integration
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/test
          REDIS_URL: redis://localhost:6379

  # ============================================
  # Security Scan
  # ============================================
  security:
    name: Security Scan
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scan-ref: '.'
          severity: 'CRITICAL,HIGH'
          exit-code: '1'
          
      - name: Dependency audit
        run: pnpm audit --audit-level=high
```

### 2.2 Deployment Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]
  workflow_dispatch:
    inputs:
      environment:
        description: 'Deployment environment'
        required: true
        type: choice
        options:
          - staging
          - production

jobs:
  # ============================================
  # Deploy to Staging
  # ============================================
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' || github.event.inputs.environment == 'staging'
    environment:
      name: staging
      url: https://staging.example.com
      
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Build for staging
        run: pnpm build
        env:
          VITE_API_URL: ${{ vars.STAGING_API_URL }}
          VITE_ENVIRONMENT: staging
          
      - name: Deploy to staging
        run: |
          # Example: Deploy to Vercel
          npx vercel deploy --prod --token=${{ secrets.VERCEL_TOKEN }}
        env:
          VERCEL_ORG_ID: ${{ secrets.VERCEL_ORG_ID }}
          VERCEL_PROJECT_ID: ${{ secrets.VERCEL_PROJECT_ID }}
          
      - name: Run smoke tests
        run: pnpm test:smoke
        env:
          TEST_URL: https://staging.example.com

  # ============================================
  # Deploy to Production
  # ============================================
  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: deploy-staging
    if: github.event.inputs.environment == 'production'
    environment:
      name: production
      url: https://www.example.com
      
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Build for production
        run: pnpm build
        env:
          VITE_API_URL: ${{ vars.PRODUCTION_API_URL }}
          VITE_ENVIRONMENT: production
          
      - name: Deploy to production
        run: |
          npx vercel deploy --prod --token=${{ secrets.VERCEL_TOKEN }}
        env:
          VERCEL_ORG_ID: ${{ secrets.VERCEL_ORG_ID }}
          VERCEL_PROJECT_ID: ${{ secrets.VERCEL_PROJECT_ID_PROD }}
          
      - name: Run smoke tests
        run: pnpm test:smoke
        env:
          TEST_URL: https://www.example.com
          
      - name: Notify on success
        uses: slackapi/slack-github-action@v1
        with:
          payload: |
            {
              "text": "✅ Production deployment successful",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Production Deployment*\nVersion: ${{ github.sha }}\nDeployed by: ${{ github.actor }}"
                  }
                }
              ]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

---

## 3. Environment Management

### 3.1 Environment Configuration

| Environment | Purpose | Data | Access |
|-------------|---------|------|--------|
| Local | Development | Seed/mock | Individual |
| Preview | PR testing | Seed | PR author + reviewers |
| Development | Integration | Shared test | Team |
| Staging | Pre-production | Production copy | Team + QA |
| Production | Live | Real | Restricted |

### 3.2 Environment Variables

```bash
# .env.example - Template for developers
# Copy to .env.local and fill in values

# ===========================================
# Required - Application will not start without these
# ===========================================
DATABASE_URL=postgresql://user:pass@localhost:5432/myapp
REDIS_URL=redis://localhost:6379

# ===========================================
# Optional - Defaults provided
# ===========================================
LOG_LEVEL=info
PORT=3000

# ===========================================
# Secrets - Never commit actual values
# ===========================================
JWT_SECRET=replace-with-secure-secret
API_KEY=replace-with-api-key
```

### 3.3 Secret Management

```yaml
# GitHub Environments configuration
# Settings > Environments > [environment]

environments:
  staging:
    secrets:
      - DATABASE_URL
      - REDIS_URL
      - JWT_SECRET
    variables:
      - API_URL: https://api.staging.example.com
      - LOG_LEVEL: debug
      
  production:
    secrets:
      - DATABASE_URL
      - REDIS_URL
      - JWT_SECRET
    variables:
      - API_URL: https://api.example.com
      - LOG_LEVEL: warn
    protection_rules:
      - required_reviewers: 2
      - wait_timer: 5  # minutes
```

---

## 4. Database Migrations

### 4.1 Migration Pipeline

```yaml
# .github/workflows/migrate.yml
name: Database Migration

on:
  workflow_dispatch:
    inputs:
      environment:
        description: 'Target environment'
        required: true
        type: choice
        options:
          - staging
          - production
      action:
        description: 'Migration action'
        required: true
        type: choice
        options:
          - migrate
          - rollback
          - status

jobs:
  migrate:
    name: Run Migration
    runs-on: ubuntu-latest
    environment: ${{ github.event.inputs.environment }}
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Run migration
        run: |
          case "${{ github.event.inputs.action }}" in
            migrate)
              pnpm db:migrate
              ;;
            rollback)
              pnpm db:rollback
              ;;
            status)
              pnpm db:status
              ;;
          esac
        env:
          DATABASE_URL: ${{ secrets.DATABASE_URL }}
```

### 4.2 Safe Migration Practices

```typescript
// migrations/20260126000000_add_user_preferences.ts

import { Kysely, sql } from 'kysely';

export async function up(db: Kysely<unknown>): Promise<void> {
  // 1. Add column with default (non-blocking)
  await db.schema
    .alterTable('users')
    .addColumn('preferences', 'jsonb', (col) => 
      col.defaultTo(sql`'{}'::jsonb`).notNull()
    )
    .execute();
    
  // 2. Create index concurrently (non-blocking on PostgreSQL)
  await sql`
    CREATE INDEX CONCURRENTLY IF NOT EXISTS 
    idx_users_preferences_theme 
    ON users ((preferences->>'theme'))
  `.execute(db);
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await sql`DROP INDEX IF EXISTS idx_users_preferences_theme`.execute(db);
  
  await db.schema
    .alterTable('users')
    .dropColumn('preferences')
    .execute();
}
```

---

## 5. Deployment Strategies

### 5.1 Blue-Green Deployment

```
                    Load Balancer
                         │
              ┌──────────┴──────────┐
              │                     │
         ┌────▼────┐           ┌────▼────┐
         │  Blue   │           │  Green  │
         │ (live)  │           │ (idle)  │
         └─────────┘           └─────────┘
              │                     │
         ┌────▼────┐           ┌────▼────┐
         │   DB    │◄──────────│   DB    │
         │ (shared)│           │         │
         └─────────┘           └─────────┘

Deployment:
1. Deploy new version to Green
2. Run smoke tests on Green
3. Switch traffic to Green
4. Monitor for issues
5. Blue becomes new idle
```

### 5.2 Canary Deployment

```yaml
# Example: Kubernetes canary with Argo Rollouts
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
  name: app-rollout
spec:
  replicas: 10
  strategy:
    canary:
      steps:
        # 10% traffic to canary
        - setWeight: 10
        - pause: { duration: 5m }
        
        # Check metrics
        - analysis:
            templates:
              - templateName: success-rate
            args:
              - name: service-name
                value: my-app
                
        # 50% traffic
        - setWeight: 50
        - pause: { duration: 10m }
        
        # Full rollout
        - setWeight: 100
```

### 5.3 Rolling Deployment

```yaml
# Kubernetes rolling update
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app
spec:
  replicas: 5
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1        # Max pods above desired
      maxUnavailable: 0  # Zero downtime
  template:
    spec:
      containers:
        - name: app
          image: app:v2.0.0
          readinessProbe:
            httpGet:
              path: /health
              port: 3000
            initialDelaySeconds: 5
            periodSeconds: 5
```

---

## 6. Health Checks & Monitoring

### 6.1 Health Check Endpoints

```typescript
// src/health/health.controller.ts

interface HealthStatus {
  status: 'healthy' | 'degraded' | 'unhealthy';
  timestamp: string;
  version: string;
  checks: Record<string, CheckResult>;
}

interface CheckResult {
  status: 'pass' | 'fail';
  latency_ms?: number;
  message?: string;
}

export async function healthCheck(): Promise<HealthStatus> {
  const checks: Record<string, CheckResult> = {};
  
  // Database check
  const dbStart = Date.now();
  try {
    await db.raw('SELECT 1');
    checks.database = {
      status: 'pass',
      latency_ms: Date.now() - dbStart,
    };
  } catch (error) {
    checks.database = {
      status: 'fail',
      message: error.message,
    };
  }
  
  // Redis check
  const redisStart = Date.now();
  try {
    await redis.ping();
    checks.redis = {
      status: 'pass',
      latency_ms: Date.now() - redisStart,
    };
  } catch (error) {
    checks.redis = {
      status: 'fail',
      message: error.message,
    };
  }
  
  // Determine overall status
  const hasFailure = Object.values(checks).some(c => c.status === 'fail');
  
  return {
    status: hasFailure ? 'unhealthy' : 'healthy',
    timestamp: new Date().toISOString(),
    version: process.env.APP_VERSION || 'unknown',
    checks,
  };
}
```

### 6.2 Deployment Verification

```yaml
# Post-deployment smoke tests
smoke-tests:
  runs-on: ubuntu-latest
  steps:
    - name: Wait for deployment
      run: sleep 30
      
    - name: Health check
      run: |
        response=$(curl -s -o /dev/null -w "%{http_code}" ${{ env.DEPLOY_URL }}/health)
        if [ "$response" != "200" ]; then
          echo "Health check failed with status $response"
          exit 1
        fi
        
    - name: Critical path test
      run: |
        # Test login flow
        curl -f ${{ env.DEPLOY_URL }}/api/auth/status
        
        # Test main page loads
        curl -f ${{ env.DEPLOY_URL }}/ | grep -q "<!DOCTYPE html>"
        
    - name: Performance check
      run: |
        # Ensure response time < 2 seconds
        time=$(curl -s -o /dev/null -w "%{time_total}" ${{ env.DEPLOY_URL }}/)
        if (( $(echo "$time > 2.0" | bc -l) )); then
          echo "Response time ${time}s exceeds 2s threshold"
          exit 1
        fi
```

---

## 7. Rollback Procedures

### 7.1 Automated Rollback

```yaml
# .github/workflows/deploy.yml (partial)
deploy:
  steps:
    - name: Deploy
      id: deploy
      run: |
        # Store current version for rollback
        echo "PREVIOUS_VERSION=$(get-current-version)" >> $GITHUB_OUTPUT
        deploy-new-version
        
    - name: Verify deployment
      id: verify
      continue-on-error: true
      run: |
        pnpm test:smoke
        
    - name: Rollback on failure
      if: steps.verify.outcome == 'failure'
      run: |
        echo "Deployment verification failed, rolling back..."
        deploy-version ${{ steps.deploy.outputs.PREVIOUS_VERSION }}
        
    - name: Fail pipeline
      if: steps.verify.outcome == 'failure'
      run: exit 1
```

### 7.2 Manual Rollback Runbook

```markdown
# Rollback Runbook

## Prerequisites
- [ ] Incident declared and communicated
- [ ] Previous stable version identified
- [ ] Database migration compatibility verified

## Rollback Steps

### 1. Pause Deployments
```bash
# Disable auto-deploy
gh workflow disable deploy.yml
```

### 2. Rollback Application
```bash
# Option A: Revert to previous deployment
vercel rollback

# Option B: Deploy specific version
git checkout v1.2.3
pnpm build
vercel deploy --prod
```

### 3. Verify Rollback
```bash
# Check health
curl https://api.example.com/health

# Run smoke tests
pnpm test:smoke --url=https://api.example.com
```

### 4. Database Rollback (if needed)
```bash
# CAUTION: Data loss possible
pnpm db:rollback --steps=1
```

### 5. Post-Rollback
- [ ] Verify all critical paths working
- [ ] Notify stakeholders
- [ ] Create incident report
- [ ] Re-enable deployments after fix
```

---

## 8. CI/CD Best Practices

### 8.1 Do's

- ✅ Keep pipelines fast (< 10 minutes for PR checks)
- ✅ Cache dependencies aggressively
- ✅ Run tests in parallel
- ✅ Use environment protection rules
- ✅ Implement automated rollbacks
- ✅ Monitor deployment metrics

### 8.2 Don'ts

- ❌ Store secrets in code or logs
- ❌ Deploy without automated tests
- ❌ Skip staging for "small changes"
- ❌ Ignore failing tests
- ❌ Deploy on Fridays (unless necessary)
- ❌ Make database changes without backup

### 8.3 Pipeline Performance Tips

```yaml
# Parallelize independent jobs
jobs:
  lint:
    runs-on: ubuntu-latest
  test:
    runs-on: ubuntu-latest
  security:
    runs-on: ubuntu-latest
    
  # Only deploy if all pass
  deploy:
    needs: [lint, test, security]
    
# Use matrix for multiple configurations
test:
  strategy:
    matrix:
      node: [18, 20, 22]
      os: [ubuntu-latest, windows-latest]
    fail-fast: false  # Continue other matrix jobs on failure
```
