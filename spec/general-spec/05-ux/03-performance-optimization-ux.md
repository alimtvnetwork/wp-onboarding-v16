# Performance Optimization Guidelines

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines performance optimization patterns for web applications, covering frontend rendering, network efficiency, and backend optimization.

---

## 1. Performance Budgets

### 1.1 Core Web Vitals Targets

| Metric | Good | Needs Improvement | Poor |
|--------|------|-------------------|------|
| **LCP** (Largest Contentful Paint) | ≤2.5s | ≤4.0s | >4.0s |
| **INP** (Interaction to Next Paint) | ≤200ms | ≤500ms | >500ms |
| **CLS** (Cumulative Layout Shift) | ≤0.1 | ≤0.25 | >0.25 |

### 1.2 Resource Budgets

```
JavaScript Budget:
- Initial bundle: <150KB gzipped
- Per-route chunk: <50KB gzipped
- Total JS: <500KB gzipped

CSS Budget:
- Critical CSS: <14KB (inline)
- Total CSS: <100KB gzipped

Image Budget:
- Hero images: <200KB
- Thumbnails: <30KB
- Icons: <5KB (prefer SVG)

Font Budget:
- Max 2 font families
- Max 4 font weights total
- Prefer variable fonts
```

---

## 2. JavaScript Optimization

### 2.1 Code Splitting

**React with React Router**
```typescript
import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';

// Lazy load route components
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Settings = lazy(() => import('./pages/Settings'));
const Reports = lazy(() => import('./pages/Reports'));

function App() {
  return (
    <Suspense fallback={<PageSkeleton />}>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/settings" element={<Settings />} />
        <Route path="/reports" element={<Reports />} />
      </Routes>
    </Suspense>
  );
}
```

### 2.2 Dynamic Imports

```typescript
// Import heavy libraries on demand
async function exportToPDF(data: ReportData) {
  const { jsPDF } = await import('jspdf');
  const doc = new jsPDF();
  // Generate PDF...
}

// Component-level dynamic import
function ChartSection({ data }: ChartProps) {
  const [Chart, setChart] = useState<ComponentType | null>(null);
  
  useEffect(() => {
    import('./Chart').then((mod) => setChart(() => mod.default));
  }, []);
  
  if (!Chart) return <ChartSkeleton />;
  return <Chart data={data} />;
}
```

### 2.3 Tree Shaking

```typescript
// ✓ CORRECT: Named imports (tree-shakeable)
import { format, parseISO } from 'date-fns';
import { Button, Input } from '@/components/ui';

// ✗ WRONG: Default imports (imports entire library)
import _ from 'lodash';
import * as dateFns from 'date-fns';

// ✓ CORRECT: Cherry-pick lodash
import debounce from 'lodash/debounce';
import throttle from 'lodash/throttle';
```

---

## 3. React Performance

### 3.1 Memoization

```typescript
import { memo, useMemo, useCallback } from 'react';

// Memoize expensive components
const ExpensiveList = memo(function ExpensiveList({ 
  items, 
  onSelect 
}: ListProps) {
  return (
    <ul>
      {items.map((item) => (
        <li key={item.id} onClick={() => onSelect(item)}>
          {item.name}
        </li>
      ))}
    </ul>
  );
});

// Memoize expensive calculations
function Dashboard({ orders }: DashboardProps) {
  const stats = useMemo(() => {
    return {
      total: orders.reduce((sum, o) => sum + o.amount, 0),
      average: orders.length > 0 
        ? orders.reduce((sum, o) => sum + o.amount, 0) / orders.length 
        : 0,
      topProducts: calculateTopProducts(orders),
    };
  }, [orders]);
  
  return <StatsDisplay stats={stats} />;
}

// Memoize callbacks passed to children
function Parent() {
  const [count, setCount] = useState(0);
  
  const handleIncrement = useCallback(() => {
    setCount((c) => c + 1);
  }, []);
  
  return <MemoizedChild onIncrement={handleIncrement} />;
}
```

### 3.2 Virtualization

```typescript
import { useVirtualizer } from '@tanstack/react-virtual';

function VirtualList({ items }: { items: Item[] }) {
  const parentRef = useRef<HTMLDivElement>(null);
  
  const virtualizer = useVirtualizer({
    count: items.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => 50,
    overscan: 5,
  });
  
  return (
    <div ref={parentRef} className="h-[400px] overflow-auto">
      <div
        style={{
          height: `${virtualizer.getTotalSize()}px`,
          position: 'relative',
        }}
      >
        {virtualizer.getVirtualItems().map((virtualItem) => (
          <div
            key={virtualItem.key}
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              height: `${virtualItem.size}px`,
              transform: `translateY(${virtualItem.start}px)`,
            }}
          >
            <ItemRow item={items[virtualItem.index]} />
          </div>
        ))}
      </div>
    </div>
  );
}
```

### 3.3 State Management

```typescript
// ✓ CORRECT: Colocate state near usage
function ProductList() {
  const [filter, setFilter] = useState('');
  // Filter state is local to this component
  
  return (
    <div>
      <FilterInput value={filter} onChange={setFilter} />
      <Products filter={filter} />
    </div>
  );
}

// ✓ CORRECT: Split context to prevent rerenders
const UserContext = createContext<User | null>(null);
const UserActionsContext = createContext<UserActions | null>(null);

function UserProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  
  const actions = useMemo(() => ({
    login: (credentials: Credentials) => { /* ... */ },
    logout: () => setUser(null),
  }), []);
  
  return (
    <UserContext.Provider value={user}>
      <UserActionsContext.Provider value={actions}>
        {children}
      </UserActionsContext.Provider>
    </UserContext.Provider>
  );
}

// Components can subscribe to just what they need
function LoginButton() {
  const actions = useContext(UserActionsContext);
  // Won't rerender when user changes
}
```

---

## 4. Network Optimization

### 4.1 Data Fetching

```typescript
import { useQuery, useQueryClient } from '@tanstack/react-query';

// Prefetch on hover
function ProductLink({ productId }: { productId: string }) {
  const queryClient = useQueryClient();
  
  const prefetch = () => {
    queryClient.prefetchQuery({
      queryKey: ['product', productId],
      queryFn: () => fetchProduct(productId),
      staleTime: 60000,
    });
  };
  
  return (
    <Link 
      to={`/products/${productId}`}
      onMouseEnter={prefetch}
      onFocus={prefetch}
    >
      View Product
    </Link>
  );
}

// Parallel queries
function Dashboard() {
  const [
    { data: user },
    { data: orders },
    { data: stats },
  ] = useQueries({
    queries: [
      { queryKey: ['user'], queryFn: fetchUser },
      { queryKey: ['orders'], queryFn: fetchOrders },
      { queryKey: ['stats'], queryFn: fetchStats },
    ],
  });
  
  return <DashboardUI user={user} orders={orders} stats={stats} />;
}

// Infinite scroll with cursor pagination
function InfiniteList() {
  const {
    data,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery({
    queryKey: ['items'],
    queryFn: ({ pageParam }) => fetchItems({ cursor: pageParam }),
    getNextPageParam: (lastPage) => lastPage.nextCursor,
    initialPageParam: undefined,
  });
  
  return (
    <div>
      {data?.pages.map((page) =>
        page.items.map((item) => <Item key={item.id} item={item} />)
      )}
      
      {hasNextPage && (
        <button 
          onClick={() => fetchNextPage()}
          disabled={isFetchingNextPage}
        >
          {isFetchingNextPage ? 'Loading...' : 'Load More'}
        </button>
      )}
    </div>
  );
}
```

### 4.2 Request Optimization

```typescript
// Debounce search input
function SearchInput() {
  const [query, setQuery] = useState('');
  const debouncedQuery = useDebouncedValue(query, 300);
  
  const { data, isLoading } = useQuery({
    queryKey: ['search', debouncedQuery],
    queryFn: () => search(debouncedQuery),
    enabled: debouncedQuery.length >= 2,
  });
  
  return (
    <div>
      <input 
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Search..."
      />
      {isLoading && <Spinner />}
      <SearchResults results={data} />
    </div>
  );
}

// Batch multiple requests
async function batchedFetch<T>(
  ids: string[],
  fetchFn: (ids: string[]) => Promise<T[]>,
  batchSize: number = 50
): Promise<T[]> {
  const batches = chunk(ids, batchSize);
  const results = await Promise.all(
    batches.map((batch) => fetchFn(batch))
  );
  return results.flat();
}
```

---

## 5. Image Optimization

### 5.1 Responsive Images

```tsx
function ResponsiveImage({ src, alt, sizes }: ImageProps) {
  // Generate srcset for different sizes
  const srcSet = [320, 640, 960, 1280, 1920]
    .map((w) => `${src}?w=${w} ${w}w`)
    .join(', ');
  
  return (
    <img
      src={`${src}?w=960`}
      srcSet={srcSet}
      sizes={sizes || '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw'}
      alt={alt}
      loading="lazy"
      decoding="async"
    />
  );
}

// Picture element for art direction
function HeroImage() {
  return (
    <picture>
      <source
        media="(min-width: 1024px)"
        srcSet="/hero-desktop.webp"
        type="image/webp"
      />
      <source
        media="(min-width: 640px)"
        srcSet="/hero-tablet.webp"
        type="image/webp"
      />
      <source
        srcSet="/hero-mobile.webp"
        type="image/webp"
      />
      <img
        src="/hero-mobile.jpg"
        alt="Hero image"
        loading="eager"
        fetchPriority="high"
      />
    </picture>
  );
}
```

### 5.2 Lazy Loading

```tsx
// Native lazy loading
<img src="/photo.jpg" loading="lazy" alt="..." />

// Intersection Observer for more control
function LazyImage({ src, alt, placeholder }: LazyImageProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [isInView, setIsInView] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);
  
  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsInView(true);
          observer.disconnect();
        }
      },
      { rootMargin: '200px' }
    );
    
    if (imgRef.current) {
      observer.observe(imgRef.current);
    }
    
    return () => observer.disconnect();
  }, []);
  
  return (
    <div className="relative">
      {!isLoaded && (
        <div className="absolute inset-0 bg-gray-200 animate-pulse" />
      )}
      <img
        ref={imgRef}
        src={isInView ? src : placeholder}
        alt={alt}
        onLoad={() => setIsLoaded(true)}
        className={isLoaded ? 'opacity-100' : 'opacity-0'}
      />
    </div>
  );
}
```

---

## 6. CSS Optimization

### 6.1 Critical CSS

```html
<!-- Inline critical CSS for above-the-fold content -->
<head>
  <style>
    /* Critical CSS - minimal styles for initial render */
    :root { --primary: #3b82f6; }
    body { margin: 0; font-family: system-ui; }
    .header { height: 64px; background: white; }
    .hero { min-height: 400px; }
    /* ... */
  </style>
  
  <!-- Defer non-critical CSS -->
  <link rel="preload" href="/styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="/styles.css"></noscript>
</head>
```

### 6.2 CSS Performance

```css
/* ✓ CORRECT: Efficient selectors */
.button { }
.nav-item { }
[data-state="open"] { }

/* ✗ AVOID: Expensive selectors */
div > ul > li > a { }  /* Deep nesting */
*:not(.active) { }     /* Universal with negation */
[class*="btn-"] { }    /* Substring matching */

/* Use contain for isolated components */
.card {
  contain: layout style paint;
}

/* Use will-change sparingly */
.animated-element {
  will-change: transform;
}

/* Remove will-change after animation */
.animated-element.animation-complete {
  will-change: auto;
}
```

### 6.3 Font Optimization

```css
/* Font display swap */
@font-face {
  font-family: 'Custom Font';
  src: url('/fonts/custom.woff2') format('woff2');
  font-weight: 400;
  font-style: normal;
  font-display: swap;
}

/* Variable font for multiple weights */
@font-face {
  font-family: 'Variable Font';
  src: url('/fonts/variable.woff2') format('woff2-variations');
  font-weight: 100 900;
  font-display: swap;
}
```

```html
<!-- Preload critical fonts -->
<link 
  rel="preload" 
  href="/fonts/custom.woff2" 
  as="font" 
  type="font/woff2" 
  crossorigin
>
```

---

## 7. Backend Optimization

### 7.1 Database Query Optimization

```typescript
// ✓ CORRECT: Select only needed columns
const users = await db
  .select('id', 'name', 'email')
  .from('users')
  .where('status', 'active');

// ✗ WRONG: Select all columns
const users = await db.select('*').from('users');

// ✓ CORRECT: Batch queries
const [users, orders, stats] = await Promise.all([
  db.select('*').from('users').where('id', userId),
  db.select('*').from('orders').where('user_id', userId),
  db.select(db.raw('COUNT(*) as count')).from('orders').where('user_id', userId),
]);

// ✓ CORRECT: Use indexes
// CREATE INDEX idx_orders_user_status ON orders(user_id, status);
const orders = await db
  .select('*')
  .from('orders')
  .where('user_id', userId)
  .where('status', 'pending')
  .orderBy('created_at', 'desc')
  .limit(10);
```

### 7.2 N+1 Query Prevention

```typescript
// ✗ WRONG: N+1 queries
async function getOrdersWithProducts(userId: string) {
  const orders = await db.select('*').from('orders').where('user_id', userId);
  
  // This creates N additional queries!
  for (const order of orders) {
    order.products = await db.select('*').from('products').where('order_id', order.id);
  }
  
  return orders;
}

// ✓ CORRECT: Eager loading / JOIN
async function getOrdersWithProducts(userId: string) {
  const orders = await db
    .select('orders.*', 'products.name as product_name')
    .from('orders')
    .leftJoin('order_items', 'orders.id', 'order_items.order_id')
    .leftJoin('products', 'order_items.product_id', 'products.id')
    .where('orders.user_id', userId);
  
  return groupByOrder(orders);
}

// ✓ CORRECT: Batch loading
async function getOrdersWithProducts(userId: string) {
  const orders = await db.select('*').from('orders').where('user_id', userId);
  const orderIds = orders.map(o => o.id);
  
  // One query for all products
  const products = await db
    .select('*')
    .from('order_items')
    .join('products', 'order_items.product_id', 'products.id')
    .whereIn('order_items.order_id', orderIds);
  
  // Group products by order
  const productsByOrder = groupBy(products, 'order_id');
  
  return orders.map(order => ({
    ...order,
    products: productsByOrder[order.id] || [],
  }));
}
```

### 7.3 Response Compression

```typescript
// Express middleware
import compression from 'compression';

app.use(compression({
  filter: (req, res) => {
    if (req.headers['x-no-compression']) {
      return false;
    }
    return compression.filter(req, res);
  },
  level: 6, // Balance between speed and compression
  threshold: 1024, // Only compress responses > 1KB
}));
```

---

## 8. Caching Strategies

### 8.1 HTTP Caching Headers

```typescript
// Static assets (immutable)
app.use('/static', express.static('public', {
  maxAge: '1y',
  immutable: true,
}));

// API responses
app.get('/api/products', async (req, res) => {
  res.set({
    'Cache-Control': 'public, max-age=300, s-maxage=600, stale-while-revalidate=86400',
    'ETag': generateETag(products),
  });
  res.json(products);
});

// Private user data
app.get('/api/profile', auth, async (req, res) => {
  res.set({
    'Cache-Control': 'private, no-cache, must-revalidate',
  });
  res.json(profile);
});
```

### 8.2 Service Worker Caching

```typescript
// sw.js
const CACHE_NAME = 'app-v1';
const STATIC_ASSETS = [
  '/',
  '/styles.css',
  '/app.js',
  '/offline.html',
];

// Cache static assets on install
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
});

// Network-first for API, cache-first for static
self.addEventListener('fetch', (event) => {
  const { request } = event;
  
  if (request.url.includes('/api/')) {
    // Network first, fallback to cache
    event.respondWith(
      fetch(request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        })
        .catch(() => caches.match(request))
    );
  } else {
    // Cache first, fallback to network
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request))
    );
  }
});
```

---

## 9. Monitoring and Metrics

### 9.1 Performance Monitoring

```typescript
// Web Vitals reporting
import { onCLS, onINP, onLCP } from 'web-vitals';

function sendToAnalytics(metric: Metric) {
  const body = JSON.stringify({
    name: metric.name,
    value: metric.value,
    id: metric.id,
    page: window.location.pathname,
  });
  
  // Use sendBeacon for reliability
  if (navigator.sendBeacon) {
    navigator.sendBeacon('/api/vitals', body);
  } else {
    fetch('/api/vitals', { body, method: 'POST', keepalive: true });
  }
}

onCLS(sendToAnalytics);
onINP(sendToAnalytics);
onLCP(sendToAnalytics);
```

### 9.2 Performance Budgets in CI

```javascript
// lighthouse.config.js
module.exports = {
  ci: {
    collect: {
      url: ['http://localhost:3000/', 'http://localhost:3000/dashboard'],
      numberOfRuns: 3,
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9 }],
        'largest-contentful-paint': ['error', { maxNumericValue: 2500 }],
        'interactive': ['error', { maxNumericValue: 3500 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],
        'total-byte-weight': ['error', { maxNumericValue: 500000 }],
      },
    },
  },
};
```

---

## 10. Quick Wins Checklist

### 10.1 Immediate Improvements

```
□ Enable gzip/brotli compression
□ Add appropriate cache headers
□ Lazy load images below fold
□ Preload critical resources
□ Remove unused CSS/JS
□ Optimize and compress images
□ Use modern image formats (WebP, AVIF)
□ Minimize third-party scripts
□ Defer non-critical JavaScript
□ Inline critical CSS
```

### 10.2 Preload Hints

```html
<!-- Preload critical resources -->
<link rel="preload" href="/fonts/main.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/critical.css" as="style">
<link rel="preload" href="/hero.webp" as="image">

<!-- Prefetch next page -->
<link rel="prefetch" href="/dashboard">

<!-- DNS prefetch for third parties -->
<link rel="dns-prefetch" href="https://api.analytics.com">

<!-- Preconnect for critical origins -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

---

## Performance Checklist

| Category | Requirement | Target |
|----------|-------------|--------|
| LCP | Largest Contentful Paint | ≤2.5s |
| INP | Interaction to Next Paint | ≤200ms |
| CLS | Cumulative Layout Shift | ≤0.1 |
| Bundle | Initial JS bundle | <150KB gzip |
| Images | Lazy load, responsive, modern formats | Required |
| Fonts | Preload, font-display: swap | Required |
| Caching | HTTP cache headers | Required |
| Compression | Gzip/Brotli | Required |
| Monitoring | Web Vitals tracking | Required |

---

## Cross-References

- [02-caching-patterns-advanced.md](../04-advanced/02-caching-patterns-advanced.md) - Caching strategies
- [03-database-conventions-advanced.md](../04-advanced/03-database-conventions-advanced.md) - Query optimization
- [02-accessibility-standards-ux.md](./02-accessibility-standards-ux.md) - Accessible loading states
