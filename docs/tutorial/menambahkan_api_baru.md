# Panduan Lengkap Best Practice: Menambahkan API Baru di Laravel Starter

Panduan step-by-step ini menjelaskan cara menambahkan modul API baru di proyek **Laravel Starter** dengan menerapkan best practice arsitektur yang konsisten, bersih, dan aman. 

Sebagai contoh kasus (usecase), kita akan membangun **Quotes Management API** (Manajemen Kutipan) dari **Step 0 (belum ada tabel)** hingga selesai. API ini akan mendukung fitur:
- **Create** (Menambahkan kutipan baru)
- **Update** (Mengubah data kutipan)
- **Delete** (Menghapus kutipan dengan *Soft Delete*)
- **Get / Show** (Mengambil satu data kutipan)
- **Get All / List** (Mengambil semua data kutipan)
- **Search & Filter** (Pencarian teks kutipan/penulis dan filter status aktif)
- **Sorting & Pagination** yang aman.

---

## 🗺️ Alur Arsitektur Request-Response

Di Laravel Starter, alur data mengikuti pola berikut:
```
                    ┌─────────────────────────┐
                    │       HTTP Request      │
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      routes/api.php     │
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      Form Request       │ ◄── Validasi & Otorisasi Aksi
                    │  (Store/Update Request) │
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │     API Controller      │ ◄── Controller super tipis
                    └────────────┬────────────┘
                                 │
         ┌───────────────────────┴───────────────────────┐
         │ (Jika ada logika bisnis non-trivial / rumit)  │ (Untuk CRUD standar / sederhana)
         ▼                                               ▼
┌───────────────────┐                         ┌───────────────────┐
│   Service Layer   │                         │  Eloquent Model   │
│  (app/Services/*) │                         │  (app/Models/*)   │
└─────────┬─────────┘                         └──────────┬────────┘
          │                                              │
          ▼                                              │
┌───────────────────┐                                    │
│  Eloquent Model   │                                    │
└─────────┬─────────┘                                    │
          │                                              │
          └───────────────────────┬───────────────────────┘
                                  ▼
                    ┌─────────────────────────┐
                    │      API Resource       │ ◄── Transformasi Output JSON
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      ApiResponse        │ ◄── Envelope JSON Terstandar
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      HTTP Response      │
                    └─────────────────────────┘
```

---

## 🛠️ Langkah-Langkah Implementasi

### Step 0: Merancang Migrasi Database
Kita mulai dari pembuatan tabel database. Buat migrasi baru menggunakan Artisan:

```bash
php artisan make:migration create_quotes_table
```

Buka file migrasi yang dihasilkan di `database/migrations/xxxx_xx_xx_xxxxxx_create_quotes_table.php`, lalu sesuaikan skemanya dengan kaidah berikut:
- Gunakan tipe data yang sesuai.
- Gunakan `softDeletes()` agar data tidak langsung hilang dari database saat didelete.
- Tambahkan index pada kolom yang sering digunakan untuk pencarian/filtering (`author`, `is_active`) demi performa query yang optimal.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->text('text'); // Teks kutipan
            $table->string('author'); // Nama penulis kutipan
            $table->string('source')->nullable(); // Sumber kutipan (buku, pidato, dll)
            $table->boolean('is_active')->default(true); // Status aktif kutipan
            $table->timestamps();
            $table->softDeletes(); // Dukungan Soft Delete

            // Indexes untuk optimalisasi pencarian
            $table->index('author');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
```

---

### Step 1: Membuat Model dan Factory

#### 1.1 Model (`app/Models/Quote.php`)
Buat file model baru di `app/Models/Quote.php`. 

> [!IMPORTANT]
> **Aturan Penting Model di Laravel Starter:**
> 1. Gunakan PHP Attribute `#[Fillable([...])]` di atas deklarasi kelas model untuk mendefinisikan fillable properties (gaya modern Laravel).
> 2. Sertakan **PHPDoc `@property`** lengkap di atas kelas agar IDE/Larastan dapat mengenali tipe data kolom dan relasi secara statis (*no magic issues*).
> 3. Gunakan trait `LogsActivity` untuk logging otomatis perubahan data master.
> 4. Definisikan metode `casts()` untuk tipe data non-string seperti boolean.

```php
<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $text
 * @property string $author
 * @property string|null $source
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
#[Fillable(['text', 'author', 'source', 'is_active'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Konfigurasi Log Aktivitas Spatie
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Konfigurasi Cast Atribut
     * 
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
```

#### 1.2 Factory (`database/factories/QuoteFactory.php`)
Factory sangat penting untuk testing otomatis dan seeding data lokal. Buat factory dengan Artisan:

```bash
php artisan make:factory QuoteFactory --model=Quote
```

Edit file `database/factories/QuoteFactory.php` menjadi seperti ini:

```php
<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->definition();
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text' => $this->faker->paragraph(2),
            'author' => $this->faker->name(),
            'source' => $this->faker->optional(0.7)->sentence(3),
            'is_active' => $this->faker->boolean(80), // 80% kemungkinan bernilai true
        ];
    }
}
```

---

### Step 2: Mengonfigurasi RBAC (Role-Based Access Control) & Policy

Di proyek ini, otorisasi dilindungi secara ketat oleh sistem Role & Permission menggunakan package `spatie/laravel-permission`.

#### 2.1 Menambahkan Resource ke `RolePermissionSeeder`
Buka file [RolePermissionSeeder.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/database/seeders/RolePermissionSeeder.php). Tambahkan `'quotes'` ke konstanta `RESOURCES` dan daftarkan permission untuk role spesifik jika diperlukan:

```diff
     /** @var list<string> */
-    private const RESOURCES = ['users', 'roles', 'categories', 'app_configs', 'app_versions', 'notifications'];
+    private const RESOURCES = ['users', 'roles', 'categories', 'quotes', 'app_configs', 'app_versions', 'notifications'];
```

Di dalam method `run()`, tentukan role apa saja yang memiliki akses ke resource `quotes` (misalnya `admin` memiliki akses penuh, `staff` hanya bisa melihat dan mengedit tapi tidak bisa mendelete):

```php
        $admin->syncPermissions([
            // ... permission lainnya
            'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
        ]);

        $staff->syncPermissions([
            // ... permission lainnya
            'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update',
        ]);
```

#### 2.2 Membuat Policy (`app/Policies/QuotePolicy.php`)
Buat policy menggunakan Artisan:

```bash
php artisan make:policy QuotePolicy --model=Quote
```

Modifikasi `app/Policies/QuotePolicy.php` agar membaca permission dari DB secara dinamis:

```php
<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.viewAny');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->can('quotes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.create');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }

    public function restore(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update');
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }
}
```

Jalankan migrasi ulang dan seeder lokal untuk menerapkan permission baru di DB Anda:
```bash
php artisan migrate:fresh --seed
```

---

### Step 3: Membuat Form Requests untuk Validasi Input

Jangan lakukan validasi input langsung di dalam Controller. Gunakan **Form Request** untuk menjaga controller tetap bersih dan memisahkan logika validasi & otorisasi aksi.

#### 3.1 Request Pembuatan (`app/Http/Requests/Api/V1/StoreQuoteRequest.php`)
Buat request untuk menampung input quote baru:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    /**
     * Periksa otorisasi apakah user saat ini boleh membuat Quote baru
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quote::class) ?? false;
    }

    /**
     * Aturan validasi input
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:5'],
            'author' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

#### 3.2 Request Pembaruan (`app/Http/Requests/Api/V1/UpdateQuoteRequest.php`)
Buat request untuk pembaruan data quote:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    /**
     * Periksa otorisasi apakah user saat ini boleh mengubah Quote ini
     */
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote 
            && ($this->user()?->can('update', $quote) ?? false);
    }

    /**
     * Aturan validasi input
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string', 'min:5'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

---

### Step 4: Membuat API Resource untuk Transformasi Output

Selalu gunakan **API Resource** untuk mentransformasi model Eloquent menjadi format JSON. Ini melindungi API konsumen dari perubahan skema database mentah (misal penggantian nama kolom) dan mencegah isu N+1 Query.

Buat API Resource di `app/Http/Resources/Api/V1/QuoteResource.php`:

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * Transformasi model ke dalam format array JSON.
     * 
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'author' => $this->author,
            'source' => $this->source,
            'is_active' => $this->is_active,
            // Tanggal dikembalikan dalam format ISO-8601 UTC yang konsisten untuk Flutter
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

---

### Step 5: Membuat Controller (Tipis) & Integrasi Spatie Query Builder

Di Laravel Starter, aturan utamanya adalah: **Controller harus super tipis (< 20 baris per method).**
- Otorisasi dan validasi sudah dihandle di **Form Request** (untuk write operations).
- Untuk operasi read/list, panggil `$this->authorize()` di awal method.
- Gunakan package `spatie/laravel-query-builder` untuk menangani filtering, pencarian, pengurutan, dan pembatasan pagination secara aman dengan whitelist eksplisit.
- Respons wajib dibungkus dengan wrapper global `ApiResponse::success()`.

Buat controller di `app/Http/Controllers/Api/V1/QuoteController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuoteRequest;
use App\Http\Requests\Api\V1\UpdateQuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Quote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuoteController extends Controller
{
    /**
     * Mendapatkan daftar quotes (Get All & Search)
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        // Batasi per_page antara 1 - 100 untuk mencegah loading data berlebih (DDoS protection)
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $quotes = QueryBuilder::for(Quote::class)
            ->allowedFilters([
                // Pencarian teks parsial (LIKE %value%) untuk search
                AllowedFilter::partial('text'),
                AllowedFilter::partial('author'),
                // Pencarian exact (sama persis) untuk status
                AllowedFilter::exact('is_active'),
            ])
            // Whitelist kolom yang boleh di-sort
            ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
            // Default sort jika parameter query kosong
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(QuoteResource::collection($quotes));
    }

    /**
     * Menyimpan quote baru (Create)
     */
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        // Data input dipastikan valid karena melewati StoreQuoteRequest
        $quote = Quote::query()->create($request->validated());

        return ApiResponse::success(new QuoteResource($quote), 'Quote created successfully', 201);
    }

    /**
     * Mendapatkan detail satu quote (Get Single)
     */
    public function show(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        return ApiResponse::success(new QuoteResource($quote));
    }

    /**
     * Memperbarui data quote (Update)
     */
    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        // Data input dipastikan valid dan authorized melewati UpdateQuoteRequest
        $quote->update($request->validated());

        return ApiResponse::success(new QuoteResource($quote->refresh()), 'Quote updated successfully');
    }

    /**
     * Menghapus quote secara soft delete (Delete)
     */
    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return ApiResponse::success(null, 'Quote deleted successfully');
    }
}
```

---

### Step 6: Mendaftarkan Route API

Buka file [routes/api.php](file:///c:/Users/62822/Documents/Work/laravel/laravel-starter/routes/api.php). 
Daftarkan resource route baru Anda di bawah middleware group `['auth:api', 'check.maintenance']` agar endpoint terproteksi oleh OAuth2 Passport dan status pemeliharaan sistem.

```php
    Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
        Route::post('assets/upload', [AssetController::class, 'upload'])->middleware('throttle:30,1');

        Route::apiResource('categories', CategoryController::class);
        
        // Pendaftaran route quotes baru
        Route::apiResource('quotes', QuoteController::class);

        Route::prefix('notifications')->group(function (): void {
            // ...
        });
    });
```

Dengan mendaftarkan `Route::apiResource('quotes', QuoteController::class)`, Laravel otomatis menyediakan endpoint berikut:
- `GET /api/v1/quotes` -> `QuoteController@index` (List & Search)
- `POST /api/v1/quotes` -> `QuoteController@store` (Create)
- `GET /api/v1/quotes/{quote}` -> `QuoteController@show` (Get Single)
- `PUT/PATCH /api/v1/quotes/{quote}` -> `QuoteController@update` (Update)
- `DELETE /api/v1/quotes/{quote}` -> `QuoteController@destroy` (Delete)

---

### Step 7: Menulis Automated Feature Test

Menulis pengujian otomatis (automated testing) adalah kewajiban mutlak. Test memastikan API berjalan sesuai spesifikasi dan mencegah *regression* di masa depan.

Buat file test baru di `tests/Feature/Api/QuoteTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permission & role awal sebelum setiap test dijalankan
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Pengujian: Tamu/Guest tidak boleh mengakses API quotes
     */
    public function test_guest_cannot_access_quotes(): void
    {
        $this->getJson('/api/v1/quotes')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /**
     * Pengujian: Admin dapat membaca list quotes dengan filter, search, sorting dan pagination
     */
    public function test_admin_can_list_quotes_with_filter_sort_and_pagination(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        // Buat mock data menggunakan factory
        Quote::factory()->create(['text' => 'Belajar Laravel Starter sangat menyenangkan.', 'author' => 'Developer A', 'is_active' => true]);
        Quote::factory()->create(['text' => 'Clean code selalu menang.', 'author' => 'Martin Fowler', 'is_active' => false]);

        // Filter aktif, search teks 'Laravel', sort berdasarkan author descending
        $this->getJson('/api/v1/quotes?filter[is_active]=1&filter[text]=Laravel&sort=-author&per_page=1')
            ->assertOk()
            // Pastikan data pertama yang dikembalikan sesuai filter
            ->assertJsonPath('data.0.author', 'Developer A')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    /**
     * Pengujian: Admin dapat melakukan siklus CRUD lengkap
     */
    public function test_admin_can_create_show_update_and_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        // 1. Create
        $quoteId = $this->postJson('/api/v1/quotes', [
            'text' => 'Talk is cheap. Show me the code.',
            'author' => 'Linus Torvalds',
            'source' => 'Linux Kernel News',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Linus Torvalds')
            ->json('data.id');

        // 2. Show (Read)
        $this->getJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJsonPath('data.text', 'Talk is cheap. Show me the code.');

        // 3. Update
        $this->putJson("/api/v1/quotes/{$quoteId}", [
            'text' => 'Membaca kode adalah keterampilan utama.',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.author', 'Linus Torvalds'); // Tetap karena tidak diupdate

        // 4. Delete
        $this->deleteJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJson(['message' => 'Quote deleted successfully']);

        // Pastikan terhapus secara soft-delete di DB
        $this->assertSoftDeleted('quotes', ['id' => $quoteId]);
    }

    /**
     * Pengujian: Staff tidak boleh menghapus quote (Otorisasi RBAC berjalan)
     */
    public function test_staff_cannot_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('staff'));
        $quote = Quote::factory()->create();

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertForbidden();

        // Pastikan record tidak terhapus di DB
        $this->assertNotSoftDeleted('quotes', ['id' => $quote->id]);
    }

    /**
     * Helper untuk membuat user dengan role spesifik
     */
    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
```

---

### Step 8: Validasi Kode dengan Quality Gates

Sebelum melakukan commit kode Anda, wajib melewati gerbang kualitas (Quality Gates) untuk memastikan standardisasi kode dan tidak ada celah bug.

Jalankan perintah-perintah berikut secara berurutan:

#### 1. Linter & Formatter (`Laravel Pint`)
Gunakan Pint untuk merapikan style penulisan kode PHP agar sesuai standar PSR-12 proyek.
```bash
vendor/bin/pint
```

#### 2. Static Analysis (`Larastan/PHPStan`)
Analisis kode secara statis tanpa menjalankannya untuk mendeteksi *type errors*, variabel tidak dikenal, atau parameter method yang tidak valid.
```bash
vendor/bin/phpstan analyse --memory-limit=1G
```
*Pastikan analysis mengembalikan status `[OK] No errors`.*

#### 3. Automated Test Runner (`PHPUnit`)
Jalankan seluruh rangkaian pengujian unit dan fitur untuk memastikan API baru Anda (dan fitur lama) berfungsi 100% dengan baik.
```bash
php artisan test
```
*Pastikan semua test suite berstatus `PASS`.*

---

## 💡 Checklist Ringkas Best Practice

Saat menambahkan API baru di kemudian hari, pastikan Anda mencentang checklist ini:
- [ ] **Migrasi & Model**: Kolom ter-indeks dengan baik, Soft Delete aktif, casts tipe data terdefinisi.
- [ ] **Model Docblocks**: Properti `@property` terdokumentasi lengkap di model.
- [ ] **RBAC & Policy**: Nama resource didaftarkan di `RolePermissionSeeder`, validasi policy dipetakan per aksi.
- [ ] **Validation**: Input divalidasi terpisah menggunakan Form Requests (`Store` dan `Update`), otorisasi dipanggil di `authorize()`.
- [ ] **API Resource**: Struktur JSON response diisolasi dari tabel DB asli. Tanggal dikonversi ke format ISO-8601 UTC.
- [ ] **Query Builder**: Pencarian teks menggunakan filter partial (`AllowedFilter::partial`), sorting & pagination dibatasi aman.
- [ ] **Controller**: Tipis (<20 baris per method), tidak ada logika SQL mentah, tidak ada try-catch manual, respon menggunakan `ApiResponse::success()`.
- [ ] **Automated Testing**: Menulis minimal 4 skenario pengujian utama (Guest, List/Search, CRUD, RBAC Forbidden).
- [ ] **Quality Gates**: Pint, PHPStan, dan PHPUnit dijalankan sebelum commit/push.
