# 27. Performance Targets

## Overview
Performance requirements, benchmarks, and optimization strategies for the frontend application.

---

## 27.1 Core Web Vitals Targets

| Metric | Target | Poor | Measurement |
|--------|--------|------|-------------|
| **LCP** (Largest Contentful Paint) | < 2.5s | > 4.0s | Time to largest content element |
| **FID** (First Input Delay) | < 100ms | > 300ms | Time to first interaction |
| **CLS** (Cumulative Layout Shift) | < 0.1 | > 0.25 | Visual stability score |
| **TTFB** (Time to First Byte) | < 600ms | > 1.8s | Server response time |
| **FCP** (First Contentful Paint) | < 1.8s | > 3.0s | Time to first content |
| **TTI** (Time to Interactive) | < 3.8s | > 7.3s | Time to fully interactive |

### Page-Specific Targets

| Page | LCP Target | TTI Target | Notes |
|------|------------|------------|-------|
| Landing Page | < 2.0s | < 3.0s | Critical first impression |
| Login Page | < 1.5s | < 2.5s | Simple form |
| Dashboard | < 2.5s | < 4.0s | Data-heavy |
| Section View | < 2.0s | < 3.5s | Markdown rendering |
| Extension Form | < 1.5s | < 2.5s | Simple form |

---

## 27.2 API Response Time SLAs

### Target Response Times

| Endpoint Category | p50 | p95 | p99 | Timeout |
|-------------------|-----|-----|-----|---------|
| Authentication | 150ms | 300ms | 500ms | 5s |
| Read (GET) | 100ms | 200ms | 400ms | 10s |
| Write (POST/PUT) | 200ms | 400ms | 800ms | 15s |
| File Upload | 500ms | 2s | 5s | 30s |
| Complex Queries | 300ms | 600ms | 1s | 20s |

### Specific Endpoints

| Endpoint | Target (p95) | Max Payload |
|----------|--------------|-------------|
| `POST /login` | 300ms | 1KB |
| `GET /exams/{slug}` | 200ms | 50KB |
| `GET /exams/{slug}/content` | 300ms | 500KB |
| `POST /log-event` | 100ms | 4KB |
| `POST /participants/{id}/extensions` | 500ms | 5MB (files) |
| `GET /participants/{id}/progress` | 150ms | 10KB |

---

## 27.3 Bundle Size Budgets

### JavaScript Bundles

| Bundle | Max Size (gzip) | Max Size (raw) |
|--------|-----------------|----------------|
| **Total App** | 200KB | 800KB |
| Main bundle | 100KB | 400KB |
| Vendor bundle | 80KB | 350KB |
| Per-route chunk | 30KB | 120KB |

### Critical Path

| Resource | Max Size | Load Priority |
|----------|----------|---------------|
| Critical CSS | 14KB | Inline |
| Above-fold JS | 50KB | Preload |
| Web fonts | 100KB | Preload |
| Hero image | 100KB | High |

### Dependency Budgets

| Category | Max Size (gzip) |
|----------|-----------------|
| React + ReactDOM | 45KB |
| Router | 15KB |
| State management | 10KB |
| UI component library | 30KB |
| Utilities (date-fns, etc.) | 20KB |
| Other dependencies | 30KB |

---

## 27.4 Asset Optimization

### Images

| Context | Format | Max Size | Dimensions |
|---------|--------|----------|------------|
| Hero images | WebP + fallback | 100KB | 1920×1080 max |
| Card thumbnails | WebP | 30KB | 400×300 max |
| Avatars | WebP | 10KB | 200×200 max |
| Icons | SVG | 2KB each | Vector |
| Logos | SVG | 5KB | Vector |

### Image Loading Strategy
```html
<!-- Hero/LCP image: eager load -->
<img src="hero.webp" loading="eager" fetchpriority="high" />

<!-- Below fold: lazy load -->
<img src="card.webp" loading="lazy" />

<!-- Responsive images -->
<picture>
  <source srcset="hero-lg.webp" media="(min-width: 1024px)" />
  <source srcset="hero-md.webp" media="(min-width: 768px)" />
  <img src="hero-sm.webp" alt="..." />
</picture>
```

### Font Loading
```css
/* Preload critical fonts */
<link rel="preload" href="font.woff2" as="font" type="font/woff2" crossorigin>

/* Use font-display: swap */
@font-face {
  font-family: 'CustomFont';
  src: url('font.woff2') format('woff2');
  font-display: swap;
}
```

---

## 27.5 Database Query Limits

### Query Performance Targets

| Query Type | p50 | p95 | p99 |
|------------|-----|-----|-----|
| Simple lookup (by ID) | 5ms | 20ms | 50ms |
| Indexed search | 20ms | 50ms | 100ms |
| List with pagination | 30ms | 80ms | 150ms |
| Complex joins | 50ms | 150ms | 300ms |
| Aggregations | 100ms | 300ms | 500ms |

### Query Count Limits

| Page Load | Max Queries | Target Queries |
|-----------|-------------|----------------|
| Dashboard | 10 | 5 |
| Section View | 8 | 4 |
| Admin List | 15 | 8 |
| Report Page | 20 | 12 |

### N+1 Prevention
- Always use eager loading for relationships
- Batch queries where possible
- Use query result caching

---

## 27.6 Caching Strategy

### Browser Caching

| Resource Type | Cache-Control | Max-Age |
|---------------|---------------|---------|
| HTML pages | `no-cache` | 0 |
| JS/CSS (hashed) | `public, immutable` | 1 year |
| Images (hashed) | `public, immutable` | 1 year |
| Fonts | `public` | 1 year |
| API responses | `private, no-store` | 0 |

### Application Caching

| Data Type | Cache Location | TTL | Invalidation |
|-----------|----------------|-----|--------------|
| User session | Memory | Session | Logout |
| Exam content | Memory | 5 min | On update |
| Progress data | Memory | 1 min | On action |
| Static config | localStorage | 1 hour | Version change |

### Service Worker (PWA)
```javascript
// Cache static assets
const STATIC_CACHE = 'static-v1';
const STATIC_ASSETS = [
  '/offline.html',
  '/css/critical.css',
  '/js/main.js',
];

// Network-first for API
self.addEventListener('fetch', (event) => {
  if (event.request.url.includes('/api/')) {
    event.respondWith(networkFirst(event.request));
  } else {
    event.respondWith(cacheFirst(event.request));
  }
});
```

---

## 27.7 Runtime Performance

### JavaScript Execution

| Metric | Target | Measurement |
|--------|--------|-------------|
| Long tasks | < 50ms | No tasks blocking main thread |
| Script evaluation | < 500ms | Initial JS parse/compile |
| Hydration | < 1s | React hydration time |
| Re-renders | < 16ms | Stay within frame budget |

### Memory Limits

| Metric | Warning | Critical |
|--------|---------|----------|
| JS heap | > 50MB | > 100MB |
| DOM nodes | > 1,500 | > 3,000 |
| Event listeners | > 500 | > 1,000 |

### Animation Performance
- Target: 60fps (16.67ms per frame)
- Use CSS transforms over layout properties
- Avoid layout thrashing
- Use `will-change` sparingly

---

## 27.8 Network Performance

### Connection Handling

| Scenario | Strategy |
|----------|----------|
| Slow 3G | Show skeleton loaders, reduce image quality |
| Offline | Show cached content, queue actions |
| Reconnect | Sync queued actions, refresh stale data |

### Prefetching Strategy
```html
<!-- Prefetch likely navigation targets -->
<link rel="prefetch" href="/dashboard" />

<!-- Preconnect to API domain -->
<link rel="preconnect" href="https://api.example.com" />

<!-- DNS prefetch for third parties -->
<link rel="dns-prefetch" href="https://fonts.googleapis.com" />
```

### Request Optimization
- Batch API requests where possible
- Use HTTP/2 multiplexing
- Compress request payloads
- Debounce frequent updates (300ms)

---

## 27.9 Monitoring & Alerts

### Real User Monitoring (RUM)

| Metric | Alert Threshold | Page |
|--------|-----------------|------|
| LCP p75 | > 3s | All |
| FID p75 | > 150ms | All |
| CLS p75 | > 0.15 | All |
| Error rate | > 1% | All |
| Bounce rate | > 50% | Landing |

### Synthetic Monitoring

| Test | Frequency | Locations |
|------|-----------|-----------|
| Homepage load | 5 min | 3 regions |
| Login flow | 15 min | 2 regions |
| Dashboard load | 15 min | 2 regions |

### Performance Budget Enforcement
```javascript
// Lighthouse CI budget
{
  "budgets": [
    {
      "path": "/*",
      "resourceSizes": [
        { "resourceType": "script", "budget": 200 },
        { "resourceType": "total", "budget": 500 }
      ],
      "resourceCounts": [
        { "resourceType": "third-party", "budget": 5 }
      ]
    }
  ]
}
```

---

## 27.10 Performance Testing

### Automated Tests

| Tool | Purpose | Frequency |
|------|---------|-----------|
| Lighthouse CI | Performance scores | Every PR |
| WebPageTest | Detailed metrics | Daily |
| k6/Artillery | Load testing | Weekly |
| Chrome DevTools | Profiling | Development |

### Load Testing Scenarios

| Scenario | Users | Duration | Target |
|----------|-------|----------|--------|
| Baseline | 10 | 5 min | < 200ms p95 |
| Normal load | 100 | 15 min | < 300ms p95 |
| Peak load | 500 | 10 min | < 500ms p95 |
| Stress test | 1000 | 5 min | No errors |

### Performance Regression Detection
- Compare against baseline
- Alert on > 10% regression
- Block deployment on > 25% regression

---

## Acceptance Criteria

- [ ] LCP < 2.5s on all pages
- [ ] FID < 100ms on all pages
- [ ] CLS < 0.1 on all pages
- [ ] Total JS bundle < 200KB gzipped
- [ ] API responses < 200ms p95
- [ ] No JavaScript long tasks > 50ms
- [ ] Performance monitoring in place
- [ ] Load tests pass at 500 concurrent users

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Loading States | [20-loading-states](20-loading-states.md) |
| Error Handling | [16-error-handling](16-error-handling.md) |
| Backend Testing | [41-testing-requirements](../../01-admin-backend/split-spec/41-testing-requirements.md) |

---

*This concludes the frontend specification split.*
