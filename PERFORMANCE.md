# PERFORMANCE.md — myWAP / MyMarhalah

Dokumentasi segala optimization prestasi yang telah dibuat. Fail ini jadi reference untuk team dev dan deployment.

---

## Index

1. [Ringkasan Optimization](#ringkasan-optimization)
2. [Checklist Production Deployment](#checklist-production-deployment)
3. [Coding Guidelines](#coding-guidelines)
4. [Monitoring](#monitoring)
5. [Server Tuning](#server-tuning)

---

## Ringkasan Optimization

### Fasa 1 — Kritikal (selesai)

| # | Task | Impak |
|---|------|-------|
| 1 | Cache `AppSetting` dengan `Cache::rememberForever` | JIMAT 2 query setiap page |
| 2 | Eager-load `user.organization` + `roles` dalam `HandleInertiaRequests` | JIMAT 2 query setiap page |
| 3 | Semua 4 notification email kini `ShouldQueue` | HTTP response tak block |
| 4 | Fix 10 N+1 query dalam 6 controllers | JIMAT puluhan query per page |
| 5 | `withCount()` ganti `->count()` dalam loop | JIMAT N query |
| 6 | GROUP BY ganti 12-24 SUM queries untuk carta bulanan | Dari 36 query ke 2 query |
| 7 | `JOIN + orderBy + first` ganti `get()->sortBy()->first()` | Load 1 row bukan semua |
| 8 | `ProcessAgeTransitions` +`with('roles')` + in-memory filter | JIMAT ribuan query batch |
| 9 | Migration 16 index pada 12 table | Full scan → index seek |
| 10 | `.htaccess` — Gzip + Cache-Control + security headers | Less bytes, faster repeat |

### Fasa 2 — Medium (selesai)

| # | Task | Impak |
|---|------|-------|
| 11 | Rate limiting pada 15 public routes | Elak abuse |
| 12 | `Cache::remember(60)` pada `FeeService::getAdminStats` | Dari 5 query ke 1 cached |
| 13 | `Cache::remember(60)` pada `FeeService::getDueCount` | Cached count |
| 14 | Fix `REGEXP member_no` — `member_no_sequence` INT column | Full table scan -> index lookup |
| 15 | `Cache::remember(30)` pada notification unread count | JIMAT 1 query setiap page |

### Fasa 3 — Cleanup (selesai)

| # | Task | Impak |
|---|------|-------|
| 16 | Quill editor (144KB) — lazy-load hanya di `ArticleManage.vue` | JIMAT 144KB di semua page |
| 17 | Code split vendor chunks (`vite.config.js`) | Parallel download, better caching |
| 18 | 11 dead blade files dipadam | Less build noise |
| 19 | `@tailwindcss/vite` v4 + `concurrently` dibuang | Less npm install time |
| 20 | `orangehill/iseed` + `laravel/tinker` → `require-dev` | Kurang autoload di production |
| 21 | Tailwind content scan — buang vendor pagination path | Faster build |

### Fasa 4 — Hardening (selesai)

| # | Task | Impak |
|---|------|-------|
| 22 | DB connection pool (`min=2`, `max=20`) untuk pgsql/mysql/mariadb | Elak connection exhaustion |
| 23 | Paginate `EventController::adminAttendance()` | Tak load semua events |
| 24 | Paginate `CategoryController::index()` | Tak load semua categories |
| 25 | `Cache::remember(300)` pada `isLifeMember`/`isExempted` | JIMAT query berulang per-user |

---

## Checklist Production Deployment

### Sebelum deploy

```
# 1. Run migration (index baru + member_no_sequence)
php artisan migrate --force

# 2. Build frontend (code split + Quill lazy)
npm ci && npm run build

# 3. Cache semua config
php artisan config:cache
php artisan route:cache
php artisan event:cache

# 4. Spatie permission cache
php artisan permission:cache-reset

# 5. Install tanpa dev dependencies
composer install --no-dev --optimize-autoloader
```

### .env production (wajib)

```env
APP_NAME="myWAP"
APP_ENV=production           # JANGAN local
APP_DEBUG=false              # JANGAN true — JIMAT overhead
APP_URL=https://mywap.my

# Database — guna Postgres (bukan SQLite)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mywap
DB_USERNAME=<user>
DB_PASSWORD=<pass>
DB_POOL_MIN=2
DB_POOL_MAX=20

# Redis untuk cache + session + queue
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<null atau pass>

# Email — guna Resend
MAIL_MAILER=resend

# Log jangan debug
LOG_LEVEL=error

# Session
SESSION_ENCRYPT=true
SESSION_LIFETIME=120

# Queue worker
# Start: php artisan queue:work redis --tries=3 --backoff=5
```

### PHP OPcache settings (`php.ini` production)

```ini
; WAJIB — skip PHP compilation setiap request
opcache.enable=1
opcache.validate_timestamps=0
opcache.max_accelerated_files=10000
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.file_cache=/tmp/opcache
```

### Web server

```
# Nginx — static file serving
location ~* \.(css|js|woff2|png|jpg|jpeg|gif|svg|ico)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Gzip
gzip on;
gzip_vary on;
gzip_types text/html text/css application/javascript application/json image/svg+xml;
```

---

## Coding Guidelines

### 1. Eager Loading — WAJIB

Setiap kali query model yang ada relationship, **SENTIASA guna `with()`**.

```php
// ❌ SALAH — N+1
$posts = NewsPost::all();
foreach ($posts as $post) {
    echo $post->author->name; // 1 query setiap post
}

// ✅ BETUL
$posts = NewsPost::with('author')->get();
foreach ($posts as $post) {
    echo $post->author->name; // dah load
}
```

### 2. Aggregate — guna withCount / GROUP BY

```php
// ❌ SALAH — N+1
$orgs = Organization::all();
$orgs->map(fn($o) => $o->members()->count());

// ✅ BETUL
$orgs = Organization::withCount('members')->get();
$orgs->map(fn($o) => $o->members_count);
```

```php
// ❌ SALAH — 12 SUM queries
collect(range(1, 12))->map(fn($m) => Payment::whereMonth('created_at', $m)->sum('amount'));

// ✅ BETUL — 1 GROUP BY query
Payment::selectRaw('MONTH(created_at) as month, SUM(amount) as total')
    ->groupBy('month')
    ->pluck('total', 'month');
```

### 3. Caching Pattern

```php
// Cache expensive queries
$stats = Cache::remember("fee_stats:{$orgId}:{$year}", 60, fn() => [
    'total' => User::count(),
    'paid' => User::whereHas('membershipFees', ...)->count(),
]);

// Bust cache bila data berubah
Cache::forget("fee_stats:{$orgId}:{$year}");
```

### 4. Index Rules

Bila buat migration baru, sentiasa index:
- Foreign keys
- Columns dalam WHERE / JOIN clause
- Compound index untuk query yang selalu filter kombinasi column

### 5. Queue Email

Semua notification WAJIB `implements ShouldQueue`. Jangan block HTTP response.

```php
class OtpEmail extends Notification implements ShouldQueue { ... }
```

### 6. Paginate

Sentiasa guna `paginate()` bukan `all()` / `get()` untuk list view.

### 7. Query dalam Loop — JANGAN

```php
// ❌ SALAH
foreach ($request->products as $item) {
    $product = Product::findOrFail($item['id']);
}

// ✅ BETUL — single query
$products = Product::whereIn('id', $productIds)->get()->keyBy('id');
```

---

## Monitoring

### Yang patut dipantau

| Metrik | Tool | Threshold |
|--------|------|-----------|
| Query count per request | Laravel Debugbar (dev) / Telescope (dev) | < 10 queries |
| Query time | Slow query log Postgres | < 100ms |
| N+1 detection | Laravel Debugbar / Telescope | 0 |
| Memory per request | `/proc` atau `htop` | < 64MB |
| Redis hit rate | `redis-cli INFO stats` | > 90% |
| Queue wait time | Horizon dashboard | < 5s |
| OPcache hit rate | `opcache_get_status()` | > 99% |

### Commands berguna

```bash
# Debug query (local je, jangan production)
php artisan tinker
DB::enableQueryLog();
// ... do something ...
DB::getQueryLog();

# Check cache
php artisan cache:clear          # clear semua
php artisan config:clear          # clear config cache
php artisan route:list            # list routes
php artisan optimize:clear        # clear semua cache

# Permission cache
php artisan permission:cache-reset

# Queue
php artisan queue:work redis --tries=3
php artisan queue:failed          # list failed jobs
php artisan queue:retry all       # retry failed
```

---

## Server Tuning

### Nginx + PHP-FPM pool

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
```

### Postgres tuning (`postgresql.conf`)

```ini
shared_buffers = 256MB            # 25% RAM
effective_cache_size = 1GB        # 50% RAM
work_mem = 16MB
maintenance_work_mem = 64MB
max_connections = 50              # match PHP-FPM + pool
```

### Redis tuning

```ini
maxmemory-policy allkeys-lru
maxmemory 256mb
save ""                           # disable RDB snapshot (jika AOF)
```

---

*Last updated: 2026-06-15*
*Versi: Fasa 1-4 selesai, 22 optimization*
