# 03 — Laravel Best Practice

> Audit mendalam per area prioritas terhadap implementasi best practice Laravel modern.
> Direview: 2026-05-24 | Reviewer: Antigravity AI Agent

---

## A. Authentication & Authorization (PRIORITAS TINGGI)

### ✅ Laravel Passport dikonfigurasi dengan benar
- **Status:** ✅ Ada
- **Temuan:** Passport v13 digunakan sebagai OAuth2 provider untuk API:
  - `Passport::enablePasswordGrant()` — enabled di `AppServiceProvider::boot()` baris 47
  - Token lifetime: access 8 jam, refresh 30 hari (`AppServiceProvider.php` baris 48-49)
  - Password client ID/secret via `.env` (`PASSPORT_PASSWORD_CLIENT_ID`, `PASSPORT_PASSWORD_CLIENT_SECRET`)
  - Proxy pattern: `AuthService` mengirim request internal ke `/oauth/token` — tidak mengekspos endpoint OAuth mentah
- **File:** `app/Providers/AppServiceProvider.php` baris 47-49, `app/Services/Auth/AuthService.php`

### ✅ Token expiry dikonfigurasi
- **Status:** ✅ Ada
- **Temuan:** Access token 8 jam, refresh token 30 hari — konfigurasi wajar untuk mobile app.
- **File:** `app/Providers/AppServiceProvider.php` baris 48-49

### ✅ Implementasi Refresh Token
- **Status:** ✅ Ada
- **Temuan:** Refresh token didukung via Password Grant flow:
  - Endpoint: `POST /api/v1/auth/refresh`
  - `AuthService::refresh()` mengirim `grant_type=refresh_token` ke internal endpoint
  - Pada logout, refresh token juga di-revoke (`AuthService::logout()` baris 86-89)
- **File:** `app/Services/Auth/AuthService.php` baris 67-73

### ✅ Sistem Role & Permission (Spatie)
- **Status:** ✅ Ada
- **Temuan:** Spatie Laravel Permission v7 dengan konfigurasi multi-guard:
  - 3 Role: `super-admin`, `admin`, `staff`
  - 15 Permission: `{users,roles,categories}.{viewAny,view,create,update,delete}`
  - Guard: `web` — shared antara session dan API (karena sama-sama pakai provider `users`)
  - Permission naming: `resource.ability` pattern
- **File:** `database/seeders/RolePermissionSeeder.php`

### ✅ Permission di-cache
- **Status:** ✅ Ada
- **Temuan:** `RolePermissionSeeder` memanggil `app(PermissionRegistrar::class)->forgetCachedPermissions()` di awal seeder (baris 27). Spatie otomatis meng-cache permission setelah query pertama.
- **File:** `config/permission.php`

### ✅ Gate dan Policy digunakan konsisten
- **Status:** ✅ Ada
- **Temuan:**
  - 3 Policy: `UserPolicy`, `RolePolicy`, `CategoryPolicy`
  - `Gate::before` untuk super-admin bypass (`AppServiceProvider.php` baris 56)
  - `Gate::policy(Role::class, RolePolicy::class)` — explicit registration karena Spatie Role bukan model App (baris 53)
  - Controller menggunakan `$this->authorize()` untuk authorization check (`CategoryController` baris 20, 47, 61)
- **File:** `app/Policies/`, `app/Providers/AppServiceProvider.php`

### ✅ Pemisahan auth web vs auth API
- **Status:** ✅ Ada
- **Temuan:**
  - **API:** `auth:api` guard (Passport, stateless, Bearer token)
  - **Web/Back-office:** `auth` guard (session, cookie, CSRF — Filament)
  - Kedua guard share `users` provider, sehingga RBAC bekerja identik
  - `User::canAccessPanel()` membatasi akses Filament hanya untuk role panel
- **File:** `app/Models/User.php` baris 43-46, `routes/api.php` baris 30, 41

### ⚠️ Email verification, password reset, 2FA
- **Status:** ⚠️ Sebagian
- **Temuan:**
  - ❌ **Email verification:** `MustVerifyEmail` di-comment (`User.php` baris 5). User bisa login tanpa verifikasi email.
  - ❌ **Password reset:** Tidak ada endpoint password reset via API. Hanya ada `changePassword` (memerlukan login).
  - ❌ **2FA:** Tidak ada implementasi two-factor authentication.
- 💡 **Rekomendasi:** Minimal uncomment `MustVerifyEmail` dan tambahkan middleware `verified`. Password reset dan 2FA bisa di-sprint berikutnya.

### Skor Sub-area: 8/10

---

## B. Multi-tenancy (PRIORITAS TINGGI)

### ❌ Tidak ada implementasi Multi-tenancy
- **Status:** ❌ Tidak Ada
- **Temuan:**
  - Tidak ada package multi-tenancy terpasang (Stancl/Tenancy tidak ada di `composer.json`)
  - Tidak ada kolom `tenant_id` di migration manapun
  - Tidak ada global scope untuk isolasi data
  - Tidak ada tenant onboarding flow
  - Filament panel tidak dikonfigurasi untuk multi-tenancy
  - Tidak ada database per-tenant atau shared database approach
  - Tidak ada proteksi cross-tenant data leakage
- **Implikasi:** Jika target use case memang SaaS/Multi-tenant, ini adalah gap yang signifikan. Seluruh data saat ini dalam satu namespace global.
- 💡 **Rekomendasi:**
  1. Jika multi-tenancy dibutuhkan: Install `stancl/tenancy` dan pilih strategi (single DB + `tenant_id` atau multi-DB)
  2. Jika TIDAK dibutuhkan: Perbarui deskripsi use case project agar tidak misleading

### Skor Sub-area: 1/10 (jika SaaS dibutuhkan) / N/A (jika tidak)

---

## C. API Versioning & Response Structure (PRIORITAS TINGGI)

### ✅ Versioning pada route API
- **Status:** ✅ Ada
- **Temuan:** API menggunakan prefix versioning:
  - Route: `Route::prefix('v1')->group(...)` di `routes/api.php`
  - Namespace: `App\Http\Controllers\Api\V1\`
  - Request: `App\Http\Requests\Api\V1\`
  - Resource: `App\Http\Resources\Api\V1\`
- Siap untuk V2 di masa depan tanpa breaking change pada V1.

### ✅ API Resource (JsonResource) untuk response
- **Status:** ✅ Ada
- **Temuan:** 2 API Resource digunakan:
  - `UserResource` — untuk `/auth/me`, update profile, avatar
  - `CategoryResource` — untuk category CRUD
- Semua response melewati `ApiResponse::success()` wrapper.
- **File:** `app/Http/Resources/Api/V1/`

### ✅ Format response konsisten
- **Status:** ✅ Ada
- **Temuan:** Envelope standar:
  ```json
  // Success
  {"success": true, "message": "OK", "data": {...}, "meta": {"pagination": {...}}}

  // Error
  {"success": false, "message": "...", "code": "...", "errors": {...}}
  ```
- `ApiResponse::success()` menangani tiga tipe data: Resource collection + paginator, paginator langsung, atau data biasa.
- `ApiResponse::error()` mendukung error code opsional dan field-level errors.
- **File:** `app/Support/ApiResponse.php`

### ✅ Wrapper response standar
- **Status:** ✅ Ada
- **Temuan:** `ApiResponse` class di `app/Support/ApiResponse.php` (78 baris) adalah helper statis yang digunakan di semua controller. Desainnya sederhana dan efektif:
  - `success($data, $message, $status, $meta)`
  - `error($message, $status, $errors, $code)`
  - `paginationMeta($paginator)` — private helper

### ✅ Pagination format standar
- **Status:** ✅ Ada
- **Temuan:** Pagination meta otomatis ditambahkan ketika data berupa `AbstractPaginator` atau `AnonymousResourceCollection`:
  ```json
  "meta": {"pagination": {"current_page": 1, "per_page": 15, "total": 50, "last_page": 4}}
  ```
- `CategoryController` menggunakan `paginate($perPage)` dengan batas 1-100 items per page (baris 22).

### ⚠️ Error response RFC 7807
- **Status:** ⚠️ Sebagian
- **Temuan:** Error response tidak mengikuti RFC 7807 (Problem Details) secara penuh, tapi memiliki format internal yang konsisten:
  ```json
  {"success": false, "message": "...", "code": "AUTH_001", "errors": {"field": ["..."]}}
  ```
- Ini acceptable untuk project ini, dan konsisten di seluruh codebase.
- 💡 **Rekomendasi:** Pertimbangkan migrasi ke RFC 7807 di masa depan jika API akan dikonsumsi oleh pihak ketiga.

### ✅ Dokumentasi API (Scramble/OpenAPI)
- **Status:** ✅ Ada
- **Temuan:** Scramble dikonfigurasi di `config/scramble.php`:
  - Endpoint docs: `/docs/api` (Stoplight Elements UI)
  - OpenAPI JSON: `/docs/api.json`
  - Security scheme: Bearer token
  - API version: dari `env('API_VERSION', '1.0.0')`
  - Access: dibatasi via `RestrictedDocsAccess` middleware
- **File:** `config/scramble.php`, `app/Providers/AppServiceProvider.php` baris 58-61

### Contoh Format Response yang Ditemukan

**Success (single resource):**
```php
// AuthController::me()
return ApiResponse::success(new UserResource($user), 'OK');
```

**Success (paginated collection):**
```php
// CategoryController::index()
return ApiResponse::success(CategoryResource::collection($categories));
```

**Error (custom code):**
```php
// ApiResponse::error()
return ApiResponse::error('Your account is inactive.', 403);
```

### Skor Sub-area: 9/10

---

## D. Filament Panel & Resource (PRIORITAS TINGGI)

### ✅ Filament dikonfigurasi dengan benar
- **Status:** ✅ Ada
- **Temuan:** `AdminPanelProvider.php` mengonfigurasi:
  - Panel: default, id `admin`, path `/admin`
  - Login: enabled
  - Branding: custom name, logo, favicon, warna Indigo
  - Database notifications: enabled
  - Auto-discovery: resources, pages, widgets
  - Middleware stack: lengkap (cookies, session, auth, CSRF, error sharing)
- **File:** `app/Providers/Filament/AdminPanelProvider.php`

### ✅ Resource untuk Model utama
- **Status:** ✅ Ada
- **Temuan:** 5 Filament Resource:
  1. `UserResource` — dengan `RelationManagers/`, `Schemas/`, `Tables/`, `Pages/`
  2. `RoleResource` — dengan `Schemas/`, `Tables/`, `Pages/`
  3. `CategoryResource` — dengan `Schemas/`, `Tables/`, `Pages/`
  4. `AppConfigResource` — inline (lebih sederhana)
  5. `AppVersionResource` — inline (lebih sederhana)

### ✅ Custom Page
- **Status:** ✅ Ada
- **Temuan:** `SendNotificationPage.php` (3.4 KB) — halaman untuk mengirim push notification dari admin panel.
- **File:** `app/Filament/Pages/SendNotificationPage.php`

### ✅ Widget di Dashboard
- **Status:** ✅ Ada
- **Temuan:** 2 widget terdaftar:
  - `StarterOverview` — statistik custom
  - `AccountWidget` — widget Filament bawaan
- **File:** `app/Filament/Widgets/StarterOverview.php`

### ⚠️ Filament Shield / Permission enforcement
- **Status:** ⚠️ Sebagian
- **Temuan:**
  - `filament/shield` TIDAK terpasang di `composer.json`
  - Akses panel dibatasi oleh `User::canAccessPanel()` (role-based)
  - Namun RBAC per-resource di Filament TIDAK secara eksplisit menerapkan policy. Resource Filament mengandalkan default behavior Filament yang mungkin atau tidak mungkin mengecek policy tergantung konfigurasi.
- 💡 **Rekomendasi:** Install `filament/shield` atau minimal override `canViewAny()`, `canCreate()`, `canEdit()`, `canDelete()` di setiap resource.

### ✅ Branding Filament dikustomisasi
- **Status:** ✅ Ada
- **Temuan:** Brand name "Laravel Starter", logo custom, favicon, warna Indigo.
- **File:** `AdminPanelProvider.php` baris 31-37

### ❌ Pemisahan panel Super Admin vs Tenant Admin
- **Status:** ❌ Tidak Ada
- **Temuan:** Hanya ada satu panel (`admin`). Tidak ada panel terpisah untuk super admin vs tenant admin. Ini terkait dengan tidak adanya multi-tenancy.

### Skor Sub-area: 7/10

---

## E. Testing Setup (PRIORITAS TINGGI)

### ✅ Menggunakan PHPUnit
- **Status:** ✅ Ada
- **Temuan:** PHPUnit v12 dengan konfigurasi di `phpunit.xml`. Tidak menggunakan Pest (meski `pestphp/pest-plugin` ada di `allow-plugins` composer).

### ✅ Feature Test untuk endpoint API
- **Status:** ✅ Ada
- **Temuan:** 10 API test files:
  - `AuthTest.php` (5.7 KB), `OtpTest.php` (8 KB), `ProfileTest.php` (3.2 KB)
  - `AvatarTest.php` (4.3 KB), `CategoryTest.php` (3 KB)
  - `NotificationTest.php` (6.9 KB), `DeviceTrackingTest.php` (5.4 KB)
  - `AppTest.php` (5.9 KB), `HealthTest.php` (1 KB), `DatabaseSmokeTest.php` (0.5 KB)
- Coverage estimate: ~90% endpoint API tercakup di feature test.

### ❌ Unit Test untuk Service/Action class
- **Status:** ❌ Tidak Ada
- **Temuan:** `tests/Unit/Services/` hanya berisi `.gitkeep`. Tidak ada unit test untuk `AuthService`, `OtpService`, `FileUploadService`, `PushNotificationService`, atau `AppConfig`.
- 💡 **Rekomendasi:** Prioritaskan unit test untuk `AuthService` (logika token issuance, revocation) dan `OtpService` (generation, verification, expiry).

### ⚠️ Test untuk Filament/back-office
- **Status:** ⚠️ Sebagian
- **Temuan:** 4 back-office test files:
  - `DashboardTest.php` (0.9 KB)
  - `PanelAccessTest.php` (1.2 KB)
  - `CategoryManagementTest.php` (1.4 KB)
  - `UserRoleManagementTest.php` (3.2 KB)
- Coverage: panel access dan manajemen dasar tercakup, tapi tidak ada test untuk `AppConfigResource`, `AppVersionResource`, atau `SendNotificationPage`.

### ⚠️ Database testing strategy
- **Status:** ⚠️ Sebagian
- **Temuan:** `phpunit.xml` menggunakan SQLite `:memory:` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), padahal production menggunakan PostgreSQL. Ini bisa menyembunyikan bugs spesifik PostgreSQL (JSONB, UUID, interval, enum handling).
- 💡 **Rekomendasi:** Buat database PostgreSQL `laravel_starter_test` dan gunakan untuk testing. Ini sudah disebut di `README.md` tapi `phpunit.xml` masih override ke SQLite.

### ✅ Factory untuk semua Model
- **Status:** ✅ Ada
- **Temuan:** 7 factory tersedia, diverifikasi di `ModelFactoryTest.php`.

### ❌ CI yang menjalankan test otomatis
- **Status:** ❌ Tidak Ada
- **Temuan:** Tidak ada file `.github/workflows/` atau CI/CD pipeline configuration.
- 💡 **Rekomendasi:** Tambahkan GitHub Actions workflow yang menjalankan `pint`, `phpstan`, dan `test` pada setiap PR.

### Coverage Estimate

| Area | Endpoint/Komponen | Test File | Coverage |
|------|-------------------|-----------|----------|
| Auth API | 5 endpoint | AuthTest | ~90% |
| OTP API | 4 endpoint | OtpTest | ~95% |
| Profile API | 3 endpoint | ProfileTest | ~80% |
| Avatar API | 1 endpoint | AvatarTest | ~90% |
| Category API | 5 endpoint | CategoryTest | ~80% |
| Notification API | 4 endpoint | NotificationTest | ~90% |
| Device Tracking | implisit | DeviceTrackingTest | ~85% |
| App Config API | 2 endpoint | AppTest | ~90% |
| Health API | 1 endpoint | HealthTest | ~100% |
| Back-office | 5 resource | 4 test files | ~40% |
| Unit (Services) | 5 service | 0 test | 0% |

### Skor Sub-area: 6/10

---

## F. Code Architecture

### ✅ Pemisahan concern yang jelas
- **Status:** ✅ Ada
- **Temuan:** Controller tipis (umumnya <20 baris per method), logika bisnis di Service layer:
  - `AuthController` — delegation ke `AuthService`
  - `OtpController` — delegation ke `OtpService`
  - `CategoryController` — CRUD sederhana, langsung Eloquent (tanpa Service — sesuai konvensi ARCHITECTURE.md §2)
- Keputusan desain "Service hanya untuk logika non-trivial" terdokumentasi dan konsisten.

### ✅ Repository Pattern tidak digunakan (by design)
- **Status:** ✅ By Design
- **Temuan:** Keputusan eksplisit untuk TIDAK menggunakan Repository pattern, terdokumentasi di `ARCHITECTURE.md` §2 dan §9. Alasan: Eloquent sudah berperan sebagai data-access layer yang memadai.

### ✅ Form Request untuk validasi input
- **Status:** ✅ Ada
- **Temuan:** 7 Form Request class untuk semua endpoint yang menerima input:
  - `LoginRequest`, `RefreshTokenRequest`, `AvatarRequest`
  - `ChangePasswordRequest`, `UpdateProfileRequest`
  - `StoreCategoryRequest`, `UpdateCategoryRequest`
- Tidak ada validasi inline di controller.

### ⚠️ Event & Listener untuk side effects
- **Status:** ⚠️ Sebagian
- **Temuan:** Event system tidak digunakan secara aktif. Side effects ditangani langsung:
  - Login → upsert device (`AuthService::login()` baris 36-38)
  - Logout → nullify push token (`AuthService::logout()` baris 94-98)
  - Tidak ada event `UserLoggedIn`, `UserRegistered`, dll.
- 💡 **Rekomendasi:** Untuk starter project, ini acceptable. Pertimbangkan Event/Listener ketika side effects bertambah (logging, analytics, notification).

### ⚠️ Job/Queue untuk proses berat
- **Status:** ⚠️ Sebagian
- **Temuan:** Queue dikonfigurasi (`QUEUE_CONNECTION=database`), dan `composer dev` menyertakan `queue:listen`. Namun tidak ada Job class yang diimplementasikan saat ini. Push notification (`PushNotificationService`) berjalan synchronous.
- 💡 **Rekomendasi:** Dispatch push notification via Job untuk menghindari blocking API response.

---

## Ringkasan Skor Per Area

| Area | Skor | Justifikasi |
|------|------|-------------|
| Auth & Authorization | 8/10 | Passport + Spatie solid, kurang email verification & 2FA |
| Multi-tenancy | 1/10 | Belum ada sama sekali |
| API Versioning & Response | 9/10 | Sangat baik, versioning + response standar + Scramble docs |
| Filament Panel | 7/10 | 5 resource termodulasi, kurang permission enforcement |
| Testing Setup | 6/10 | Feature test bagus, unit test kosong, SQLite vs PostgreSQL |
| Code Architecture | 8/10 | Separation of concerns jelas, kurang Event/Queue |

---

## Skor Akhir: 7.5/10

**Justifikasi:** Arsitektur project mengikuti best practice Laravel modern dengan baik. Separation of concerns jelas, API versioning sejak awal, response format konsisten, dan auth menggunakan Passport OAuth2 yang proper. Poin utama yang mengurangi skor: tidak ada multi-tenancy (jika memang dibutuhkan), email verification tidak aktif, unit test kosong, dan Filament permission enforcement belum lengkap.
