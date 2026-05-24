# 06 — Deep Dive Area Prioritas

> Analisa mendalam untuk 5 area prioritas dengan code snippet, masalah spesifik, contoh perbaikan, dan estimasi effort.
> Direview: 2026-05-24 | Reviewer: Antigravity AI Agent

---

## 1. Auth & Authorization — Deep Dive

### 1.1 Kode yang Ditemukan

**Proxy Pattern Login** (`app/Services/Auth/AuthService.php` baris 21-41):
```php
public function login(string $email, string $password, array $deviceInfo = []): array
{
    $user = User::query()->where('email', $email)->first();

    if ($user !== null && ! $user->is_active) {
        throw new AuthorizationException('Your account is inactive.');
    }

    $tokens = $this->issueToken([
        'grant_type' => 'password',
        'username' => $email,
        'password' => $password,
    ]);

    if ($user !== null && isset($deviceInfo['device_id'], $deviceInfo['platform'])) {
        $this->upsertDevice($user, $deviceInfo);
    }

    return $tokens;
}
```

**Super-admin Bypass** (`app/Providers/AppServiceProvider.php` baris 56):
```php
Gate::before(fn (?User $user, string $ability): ?bool => 
    ($user && $user->hasRole('super-admin')) ? true : null
);
```

**OTP Login** (`app/Services/Auth/AuthService.php` baris 49-60):
```php
public function issueTokenForUser(User $user): array
{
    $result = $user->createToken('otp-login');
    $expiresAt = $result->token->expires_at ?? now()->addHours(8);

    return [
        'access_token' => $result->accessToken,
        'refresh_token' => null,  // Personal Access Token tidak punya refresh token
        'token_type' => 'Bearer',
        'expires_in' => (int) now()->diffInSeconds($expiresAt),
    ];
}
```

### 1.2 Masalah Spesifik

#### ⚠️ Masalah 1: `MustVerifyEmail` Dinonaktifkan
- **File:** `app/Models/User.php` baris 5
- **Kode:** `// use Illuminate\Contracts\Auth\MustVerifyEmail;`
- **Dampak:** User bisa login dengan email yang belum diverifikasi — potensi spam account.
- **Estimasi Effort:** S (Small)

#### ⚠️ Masalah 2: OTP Login Tidak Punya Refresh Token
- **File:** `app/Services/Auth/AuthService.php` baris 49-60
- **Kode:** `'refresh_token' => null` — Personal Access Token tidak mendukung refresh.
- **Dampak:** User yang login via OTP harus re-login setiap 8 jam. Inkonsistensi pengalaman dengan login password yang mendapat refresh token 30 hari.
- **Estimasi Effort:** M (Medium) — perlu redesign OTP login flow agar menggunakan Password Grant atau custom grant.

#### ⚠️ Masalah 3: Race Condition pada Device Upsert
- **File:** `app/Services/Auth/AuthService.php` baris 105-121
- **Kode:** `UserDevice::query()->updateOrCreate(...)` — tanpa DB transaction.
- **Dampak:** Login concurrent dari device yang sama bisa menyebabkan duplicate insert sebelum unique constraint check.
- **Estimasi Effort:** S (Small)

#### 💡 Masalah 4: Tidak Ada Endpoint "Logout All Devices"
- **File:** `app/Services/Auth/AuthService.php` baris 75-100
- **Dampak:** User yang kehilangan device tidak bisa invalidasi semua session.
- **Estimasi Effort:** S (Small)

### 1.3 Contoh Perbaikan

**Perbaikan Masalah 1 — Aktifkan Email Verification:**
```php
// app/Models/User.php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements FilamentUser, OAuthenticatable, MustVerifyEmail
{
    // ...
}
```

**Perbaikan Masalah 4 — Logout All Devices:**
```php
// app/Services/Auth/AuthService.php — tambahkan method
public function logoutAllDevices(User $user): void
{
    // Revoke all access tokens
    $user->tokens()->update(['revoked' => true]);
    
    // Revoke all refresh tokens
    RefreshToken::query()
        ->whereIn('access_token_id', $user->tokens()->pluck('id'))
        ->update(['revoked' => true]);
    
    // Nullify all push tokens
    UserDevice::query()
        ->where('user_id', $user->getKey())
        ->update(['push_token' => null]);
}
```

---

## 2. Multi-tenancy — Deep Dive

### 2.1 Kode yang Ditemukan

**Tidak ada kode multi-tenancy yang ditemukan.** Seluruh data dalam project ini beroperasi dalam satu namespace global.

### 2.2 Masalah Spesifik

#### 🔥 Masalah Utama: Tidak Ada Arsitektur Multi-tenancy
- **Dampak:** Project tidak bisa digunakan sebagai SaaS tanpa refactoring signifikan.
- **Estimasi Effort:** XL (Extra Large) — arsitektur fundamental yang mempengaruhi hampir semua layer.

### 2.3 Rekomendasi Arsitektur

Jika multi-tenancy dibutuhkan, ada dua pendekatan:

**Opsi A: Single Database + `tenant_id` (Lebih Sederhana)**
```php
// Migration: tambahkan tenant_id ke tabel yang perlu isolasi
Schema::table('categories', function (Blueprint $table) {
    $table->foreignId('tenant_id')->constrained()->index();
});

// Model: Global scope
class Category extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }
        });
    }
}
```

**Opsi B: Stancl/Tenancy Package (Lebih Lengkap)**
```bash
composer require stancl/tenancy
php artisan tenancy:install
```

**Rekomendasi:** Jika multi-tenancy memang target, mulai dengan Opsi A (single DB + tenant_id) karena lebih sederhana dan kompatibel dengan Filament. Stancl/Tenancy lebih cocok untuk kasus yang memerlukan database per-tenant.

---

## 3. API Versioning & Response — Deep Dive

### 3.1 Kode yang Ditemukan

**Response Wrapper** (`app/Support/ApiResponse.php` baris 12-39):
```php
public static function success(
    $data = null,
    string $message = 'OK',
    int $status = 200,
    array $meta = []
): JsonResponse {
    $payload = [
        'success' => true,
        'message' => $message,
    ];

    if ($data instanceof AnonymousResourceCollection && $data->resource instanceof AbstractPaginator) {
        $paginator = $data->resource;
        $payload['data'] = $data->resolve();
        $meta = ['pagination' => self::paginationMeta($paginator)] + $meta;
    } elseif ($data instanceof AbstractPaginator) {
        $payload['data'] = $data->items();
        $meta = ['pagination' => self::paginationMeta($data)] + $meta;
    } else {
        $payload['data'] = $data;
    }

    if (! empty($meta)) {
        $payload['meta'] = $meta;
    }

    return response()->json($payload, $status);
}
```

**Spatie QueryBuilder** (`app/Http/Controllers/Api/V1/CategoryController.php` baris 24-33):
```php
$categories = QueryBuilder::for(Category::class)
    ->allowedFilters(
        'name',
        'slug',
        AllowedFilter::exact('is_active'),
    )
    ->allowedSorts('name', 'slug', 'is_active', 'created_at', 'updated_at')
    ->defaultSort('name')
    ->paginate($perPage)
    ->appends($request->query());
```

### 3.2 Masalah Spesifik

#### 💡 Masalah 1: `$data` Parameter Tanpa Type Hint
- **File:** `app/Support/ApiResponse.php` baris 13
- **Kode:** `$data = null` — parameter tanpa type hint.
- **Dampak:** PHPStan level rendah mungkin tidak menangkap tipe yang salah.
- **Estimasi Effort:** S (Small)

#### 💡 Masalah 2: Tidak Ada Error Code Standar
- **File:** Seluruh codebase
- **Dampak:** Error response menggunakan `code` parameter optional (`ApiResponse::error()`) tapi tidak ada enum/constant untuk error codes. Flutter client harus parsing message string.
- **Estimasi Effort:** S (Small)

### 3.3 Contoh Perbaikan

**Perbaikan Masalah 2 — Error Code Enum:**
```php
// app/Support/Enums/ApiErrorCode.php
enum ApiErrorCode: string
{
    case AuthInvalidCredentials = 'AUTH_INVALID_CREDENTIALS';
    case AuthInactiveAccount = 'AUTH_INACTIVE_ACCOUNT';
    case AuthTokenExpired = 'AUTH_TOKEN_EXPIRED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';
    case MaintenanceMode = 'MAINTENANCE_MODE';
}

// Penggunaan:
return ApiResponse::error(
    'Invalid credentials.',
    401,
    code: ApiErrorCode::AuthInvalidCredentials->value
);
```

---

## 4. Filament Panel — Deep Dive

### 4.1 Kode yang Ditemukan

**Panel Provider** (`app/Providers/Filament/AdminPanelProvider.php` baris 24-63):
```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->brandName('Laravel Starter')
        ->brandLogo(asset('images/logo-light.svg'))
        ->brandLogoHeight('2.5rem')
        ->favicon(asset('favicon.ico'))
        ->colors(['primary' => Color::Indigo])
        ->databaseNotifications()
        ->discoverResources(app_path('Filament/Resources'), 'App\Filament\Resources')
        ->discoverPages(app_path('Filament/Pages'), 'App\Filament\Pages')
        ->pages([Dashboard::class])
        ->discoverWidgets(app_path('Filament/Widgets'), 'App\Filament\Widgets')
        ->widgets([StarterOverview::class, AccountWidget::class])
        ->middleware([...])
        ->authMiddleware([Authenticate::class]);
}
```

**Panel Access Control** (`app/Models/User.php` baris 43-46):
```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->is_active && $this->hasAnyRole(self::PANEL_ROLES);
}
```

### 4.2 Masalah Spesifik

#### ⚠️ Masalah 1: Tidak Ada Per-Resource Permission Enforcement
- **File:** Semua file di `app/Filament/Resources/`
- **Dampak:** Setelah user masuk panel (via `canAccessPanel()`), mereka bisa mengakses semua resource. Role `staff` yang seharusnya hanya bisa akses Category, bisa melihat Users dan Roles di sidebar.
- **Estimasi Effort:** M (Medium)

#### 💡 Masalah 2: Tidak Ada Dark Logo Variant
- **File:** `AdminPanelProvider.php` baris 32
- **Kode:** `->brandLogo(asset('images/logo-light.svg'))` — hanya logo light.
- **Dampak:** Jika dark mode diaktifkan, logo mungkin tidak terlihat.
- **Estimasi Effort:** S (Small)

#### 💡 Masalah 3: Tidak Ada Filament Global Search
- **File:** `AdminPanelProvider.php`
- **Dampak:** User admin tidak bisa mencari across resources.
- **Estimasi Effort:** S (Small)

### 4.3 Contoh Perbaikan

**Perbaikan Masalah 1 — Resource Permission (Manual tanpa Shield):**
```php
// app/Filament/Resources/Categories/CategoryResource.php
use App\Models\User;

class CategoryResource extends Resource
{
    // ...

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('categories.viewAny') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('categories.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('categories.update') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('categories.delete') ?? false;
    }
}
```

**Atau install Filament Shield:**
```bash
composer require bezhansalleh/filament-shield
php artisan shield:install
php artisan shield:generate --all
```

---

## 5. Testing Setup — Deep Dive

### 5.1 Kode yang Ditemukan

**PHPUnit Config** (`phpunit.xml` baris 20-35):
```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

**Test Base** (`tests/TestCase.php`):
```php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
```

### 5.2 Masalah Spesifik

#### 🔥 Masalah 1: SQLite In-Memory vs PostgreSQL Production
- **File:** `phpunit.xml` baris 26-28
- **Kode:** `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- **Dampak:** SQLite tidak mendukung semua fitur PostgreSQL:
  - JSONB column behavior berbeda
  - UUID/ULID generation behavior berbeda
  - Enum column handling berbeda
  - `ILIKE` vs `LIKE` case sensitivity berbeda
  - PostgreSQL-specific constraints tidak divalidasi
- Test bisa hijau di SQLite tapi gagal di PostgreSQL production.
- **Estimasi Effort:** S (Small) — hanya ubah `phpunit.xml`

#### ⚠️ Masalah 2: Tidak Ada Unit Test untuk Service
- **File:** `tests/Unit/Services/` — hanya `.gitkeep`
- **Dampak:** `AuthService`, `OtpService`, `PushNotificationService` tidak ditest secara isolasi. Bug dalam logika bisnis hanya terdeteksi via feature test (yang lebih lambat dan kurang presisi).
- **Estimasi Effort:** M (Medium)

#### ⚠️ Masalah 3: Tidak Ada CI Pipeline
- **File:** Tidak ada `.github/workflows/`
- **Dampak:** Quality gate (Pint, PHPStan, PHPUnit) hanya berjalan manual. Developer bisa push kode yang gagal test.
- **Estimasi Effort:** S (Small)

#### 💡 Masalah 4: Test Coverage Tidak Diukur
- **File:** `phpunit.xml` — tidak ada `<coverage>` configuration
- **Dampak:** Tidak bisa mengukur dan melacak test coverage secara objektif.
- **Estimasi Effort:** S (Small)

### 5.3 Contoh Perbaikan

**Perbaikan Masalah 1 — Gunakan PostgreSQL untuk Test:**
```xml
<!-- phpunit.xml — hapus SQLite override, gunakan .env.testing -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="DB_DATABASE" value="laravel_starter_test"/>
    <!-- sisanya tetap -->
</php>
```

**Perbaikan Masalah 3 — GitHub Actions CI:**
```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: laravel_starter_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: secret
        ports: ['5432:5432']
        options: --health-cmd pg_isready
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction
      - run: vendor/bin/pint --test
      - run: vendor/bin/phpstan analyse --memory-limit=1G
      - run: php artisan test
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_DATABASE: laravel_starter_test
          DB_USERNAME: postgres
          DB_PASSWORD: secret
```

---

## Ringkasan Estimasi Effort per Area

| Area | Temuan Kritis | Temuan Penting | Temuan Minor | Total Effort |
|------|---------------|----------------|--------------|--------------|
| Auth & Authorization | 1 | 2 | 1 | M-L |
| Multi-tenancy | 1 (fundamental) | 0 | 0 | XL |
| API Response | 0 | 0 | 2 | S |
| Filament Panel | 0 | 1 | 2 | M |
| Testing Setup | 1 | 2 | 1 | M |
| **TOTAL** | **3** | **5** | **6** | **L-XL** |

> **Keterangan Effort:** S = <2 jam, M = 2-8 jam, L = 1-2 hari, XL = >3 hari
