# Panduan Lengkap Best Practice: Menambahkan API Baru di Laravel Starter

Panduan step-by-step ini menjelaskan cara menambahkan modul API baru di proyek **Laravel Starter** dengan menerapkan best practice arsitektur yang konsisten, bersih, dan aman.

Sebagai contoh kasus (use case), kita akan membangun **Quotes Management API** (Manajemen Kutipan) dari **Step 0 (belum ada tabel)** hingga selesai. API ini akan mendukung fitur:
- **Create** — Menambahkan kutipan baru
- **Update** — Mengubah data kutipan
- **Delete** — Menghapus kutipan (Soft Delete)
- **Get / Show** — Mengambil satu data kutipan
- **Get All / List** — Mengambil semua data kutipan dengan pagination
- **Search & Filter** — Pencarian teks kutipan/penulis dan filter status aktif
- **Sorting** — Pengurutan kolom yang diizinkan

---

## 📋 Daftar Isi

1. [Prasyarat & Konteks Arsitektur](#-prasyarat--konteks-arsitektur)
2. [Peta Arsitektur Request-Response](#-peta-arsitektur-request-response)
3. [Step 0 — Merancang & Membuat Migrasi Database](#step-0--merancang--membuat-migrasi-database)
4. [Step 1 — Membuat Model & Factory](#step-1--membuat-model--factory)
5. [Step 2 — Mengonfigurasi RBAC & Policy](#step-2--mengonfigurasi-rbac--policy)
6. [Step 3 — Membuat Form Requests untuk Validasi Input](#step-3--membuat-form-requests-untuk-validasi-input)
7. [Step 4 — Membuat API Resource untuk Transformasi Output](#step-4--membuat-api-resource-untuk-transformasi-output)
8. [Step 5 — Membuat Controller (Tipis) & Integrasi Spatie Query Builder](#step-5--membuat-controller-tipis--integrasi-spatie-query-builder)
9. [Step 6 — Mendaftarkan Route API](#step-6--mendaftarkan-route-api)
10. [Step 7 — Membuat Filament Back-Office Resource (Opsional)](#step-7--membuat-filament-back-office-resource-opsional)
11. [Step 8 — Menulis Automated Feature Test (API)](#step-8--menulis-automated-feature-test-api)
12. [Step 9 — Menulis Automated Test (Back-Office)](#step-9--menulis-automated-test-back-office)
13. [Step 10 — Validasi Kode dengan Quality Gates](#step-10--validasi-kode-dengan-quality-gates)
14. [Step 11 — Commit & Push dengan Git Flow](#step-11--commit--push-dengan-git-flow)
15. [Checklist Final Best Practice](#-checklist-final-best-practice)
16. [Lampiran: Ringkasan File yang Dibuat/Dimodifikasi](#-lampiran-ringkasan-file-yang-dibuatdimodifikasi)

---

## 🏗 Prasyarat & Konteks Arsitektur

Sebelum mulai, pastikan Anda memahami aturan-aturan inti proyek ini:

### Prinsip Arsitektur yang WAJIB Diikuti

| Prinsip | Penjelasan |
|---|---|
| **Tanpa Repository Pattern** | Akses Eloquent langsung di Controller/Service. Jangan membuat interface repository. |
| **Controller Super Tipis** | Idealnya < 20 baris per method. Tidak ada logika bisnis di controller. |
| **Service Layer untuk Logika Kompleks** | Jika ada logika bisnis non-trivial, buat class Service. CRUD sederhana **tidak perlu** Service. |
| **Form Request untuk Validasi** | Semua validasi input dan otorisasi aksi `create`/`update` dilakukan di Form Request. |
| **API Resource untuk Output** | Jangan pernah mengembalikan model Eloquent mentah. Selalu gunakan API Resource. |
| **Envelope JSON Standar** | Semua response menggunakan `ApiResponse::success()` atau `ApiResponse::error()`. |
| **Exception Handling Terpusat** | Jangan menulis try-catch di Controller. Exception ditangani global di `bootstrap/app.php`. |
| **RBAC dengan Spatie** | Setiap resource harus terdaftar di `RolePermissionSeeder` dan dilindungi Policy. |

### Dokumen Referensi Penting

Sebelum menambahkan API baru, **wajib baca** dokumen-dokumen berikut:

- **`CLAUDE.md`** — Pedoman umum project, coding conventions, dan perintah-perintah penting.
- **`docs/architecture.md`** — Arsitektur lengkap, layering, response standard, dan hal yang dihindari.
- **`docs/data_master_pattern.md`** — Ringkasan pola data master yang wajib diikuti.
- **`CONTRIBUTING.md`** — Alur kerja Git Flow, konvensi commit, dan quality gates.

### Blueprint Referensi

Model `Category` beserta seluruh artefak-nya (controller, form request, API resource, policy, Filament resource, dan test) adalah **blueprint resmi** proyek ini. Saat membangun modul baru, **selalu** gunakan `Category` sebagai acuan pola.

---

## 🗺 Peta Arsitektur Request-Response

Alur data di Laravel Starter mengikuti pola yang **sangat terstruktur**. Setiap layer punya tanggung jawab spesifik dan **tidak boleh melanggar** batas tanggung jawab layer lain.

```
                    ┌─────────────────────────┐
                    │       HTTP Request       │
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      routes/api.php      │  ◄── Definisi endpoint & middleware
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      Form Request        │  ◄── Validasi input & otorisasi aksi
                    │  (Store/Update Request)   │      (create/update di authorize())
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │     API Controller       │  ◄── Controller TIPIS (< 20 baris/method)
                    └────────────┬────────────┘      Otorisasi read/delete via $this->authorize()
                                 │
         ┌───────────────────────┴───────────────────────┐
         │ (Jika ada logika bisnis kompleks)              │ (Untuk CRUD standar)
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
                    │      API Resource        │  ◄── Transformasi model → JSON
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      ApiResponse         │  ◄── Envelope JSON standar
                    │  ::success() / ::error() │      {success, message, data, meta}
                    └────────────┬────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │      HTTP Response       │
                    └─────────────────────────┘
```

### Tanggung Jawab per Layer

| Layer | Tanggung Jawab | ❌ Yang TIDAK Boleh Dilakukan |
|---|---|---|
| **Routes** | Definisi endpoint, middleware, route binding | Tidak ada logika apapun |
| **Controller** | Panggil Form Request / `$this->authorize()`, panggil Model/Service, kembalikan API Resource | Tidak ada logika bisnis, tidak ada query kompleks, tidak ada try-catch |
| **Form Request** | Aturan validasi, `authorize()` untuk create/update | Tidak ada side-effect |
| **Service** | Logika bisnis, orkestrasi, transaksi DB | Tidak tahu soal HTTP (request/response) |
| **Model** | Skema, relasi, scope, casting, accessor/mutator | Tidak ada logika bisnis lintas-entitas |
| **API Resource** | Transformasi model → format JSON konsisten | Tidak ada query (hindari N+1) |
| **Policy** | Aturan otorisasi per-aksi | — |

> [!IMPORTANT]
> **Kapan membuat Service?** Hanya jika ada logika bisnis nyata (misalnya: validasi lintas tabel, kalkulasi kompleks, integrasi API pihak ketiga, event/notification triggers). Untuk CRUD sederhana seperti `Quote`, **tidak perlu** membuat Service. Jangan buat Service kosong yang hanya meneruskan panggilan.

---

## Step 0 — Merancang & Membuat Migrasi Database

Kita mulai dari hal paling fundamental: membuat tabel database.

### 0.1 Generate File Migrasi

Jalankan perintah Artisan untuk membuat file migrasi:

```bash
php artisan make:migration create_quotes_table
```

Perintah ini akan membuat file baru di `database/migrations/` dengan nama berformat:
`YYYY_MM_DD_HHMMSS_create_quotes_table.php`

### 0.2 Desain Skema Tabel

Buka file migrasi yang baru dibuat dan definisikan skema tabel.

**Prinsip desain migrasi:**
1. **`id()`** — Primary key auto-increment (bigint).
2. **Kolom bisnis** — Definisikan kolom sesuai kebutuhan domain dengan tipe data yang tepat.
3. **`is_active`** — Boolean dengan default `true` untuk mendukung soft-toggle status.
4. **`timestamps()`** — Otomatis membuat kolom `created_at` dan `updated_at`.
5. **`softDeletes()`** — Otomatis membuat kolom `deleted_at` untuk mendukung soft delete.
6. **Index** — Tambahkan index pada kolom yang sering digunakan untuk pencarian/filtering demi performa query.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->text('text');                          // Isi teks kutipan
            $table->string('author');                      // Nama penulis kutipan
            $table->string('source')->nullable();          // Sumber kutipan (buku, pidato, dll.)
            $table->boolean('is_active')->default(true);   // Status aktif
            $table->timestamps();
            $table->softDeletes();

            // Index untuk kolom yang sering difilter/dicari
            $table->index('author');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
```

> [!TIP]
> **Mengapa menambahkan index?** Kolom `author` dan `is_active` akan sering digunakan sebagai filter/search di API. Tanpa index, database harus melakukan full table scan yang lambat. Selalu pertimbangkan pola query yang akan sering dipakai saat mendesain migrasi.

### 0.3 Jalankan Migrasi

```bash
php artisan migrate
```

Pastikan tidak ada error. Jika ada masalah koneksi database, periksa file `.env` Anda.

---

## Step 1 — Membuat Model & Factory

### 1.1 Membuat Model (`app/Models/Quote.php`)

Model adalah representasi tabel database di dalam kode PHP. Di Laravel Starter, model harus mengikuti aturan ketat.

> [!IMPORTANT]
> **Aturan Wajib untuk Model di Proyek Ini:**
> 1. **PHPDoc `@property`** — WAJIB ada di atas kelas model. Deklarasikan semua kolom database beserta tipe datanya. Ini membantu IDE, Larastan (static analysis), dan developer lain memahami struktur model tanpa harus membuka migrasi.
> 2. **Attribute `#[Fillable([...])]`** — Gunakan PHP Attribute modern (bukan property `$fillable`) untuk mendefinisikan kolom yang boleh di-mass-assign.
> 3. **Trait `LogsActivity`** — Wajib digunakan untuk logging otomatis perubahan data (audit trail).
> 4. **Trait `SoftDeletes`** — Wajib jika tabel menggunakan `softDeletes()` di migrasi.
> 5. **Method `casts()`** — Definisikan casting untuk tipe data non-string (boolean, integer, date, dll.).
> 6. **Typed Factory hint** — Gunakan `/** @use HasFactory<QuoteFactory> */` di atas trait `HasFactory`.

Buat file `app/Models/Quote.php`:

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
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

**Penjelasan setiap bagian:**

| Bagian | Fungsi |
|---|---|
| `@property` docblocks | Memberitahu IDE dan PHPStan tipe data setiap kolom. Menghilangkan "magic property" warning. |
| `#[Fillable([...])]` | Mendefinisikan kolom yang boleh di-mass-assign (`create()`, `update()`). **Jangan** memasukkan `id`, `created_at`, `updated_at`, `deleted_at`. |
| `HasFactory` | Mengizinkan penggunaan `Quote::factory()` di test dan seeder. |
| `LogsActivity` | Mencatat setiap perubahan data ke tabel `activity_log` secara otomatis. |
| `SoftDeletes` | `delete()` tidak menghapus row dari database, hanya mengisi `deleted_at`. |
| `casts()` | Memastikan `is_active` selalu dikembalikan sebagai PHP `bool`, bukan integer `0`/`1`. |

### 1.2 Membuat Factory (`database/factories/QuoteFactory.php`)

Factory digunakan untuk menghasilkan data palsu (fake data) yang konsisten untuk testing dan seeding. **Setiap model WAJIB punya factory.**

Generate file factory:

```bash
php artisan make:factory QuoteFactory --model=Quote
```

Edit file `database/factories/QuoteFactory.php`:

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
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text' => fake()->paragraph(2),
            'author' => fake()->name(),
            'source' => fake()->optional(0.7)->sentence(3),
            'is_active' => true,
        ];
    }

    /**
     * State: kutipan dalam kondisi non-aktif.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

**Poin penting:**

- **`@extends Factory<Quote>`** — PHPDoc generics agar Larastan mengenali tipe return.
- **`is_active => true`** — Default factory menghasilkan data aktif. Ini memudahkan test — data yang dibuat otomatis bisa langsung dipakai tanpa perlu set `is_active` manual.
- **`inactive()` state** — State method opsional yang berguna saat test perlu data non-aktif: `Quote::factory()->inactive()->create()`.
- **`fake()->optional(0.7)`** — 70% kemungkinan menghasilkan data, 30% kemungkinan `null`. Ini memastikan test juga menguji kolom nullable.

---

## Step 2 — Mengonfigurasi RBAC & Policy

Di proyek ini, setiap resource API **wajib** dilindungi oleh sistem Role-Based Access Control (RBAC) menggunakan package `spatie/laravel-permission`.

### 2.1 Mendaftarkan Resource di `RolePermissionSeeder`

Buka file `database/seeders/RolePermissionSeeder.php`.

**Langkah 1:** Tambahkan `'quotes'` ke konstanta `RESOURCES`:

```diff
  /** @var list<string> */
- private const RESOURCES = ['users', 'roles', 'categories', 'app_configs', 'app_versions', 'notifications'];
+ private const RESOURCES = ['users', 'roles', 'categories', 'quotes', 'app_configs', 'app_versions', 'notifications'];
```

Dengan menambahkan `'quotes'` ke array `RESOURCES`, seeder secara otomatis akan membuat 5 permission berikut:
- `quotes.viewAny`
- `quotes.view`
- `quotes.create`
- `quotes.update`
- `quotes.delete`

**Langkah 2:** Tentukan permission untuk setiap role yang memerlukan akses ke quotes.

Di dalam method `run()`, tambahkan permission `quotes` ke role yang sesuai:

```php
$admin->syncPermissions([
    // ... permission yang sudah ada ...
    'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
]);

$staff->syncPermissions([
    // ... permission yang sudah ada ...
    'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update',
    // Catatan: staff TIDAK bisa delete quotes
]);
```

> [!WARNING]
> **Jangan lupa `super-admin`.** Role `super-admin` menggunakan `Permission::all()`, sehingga secara otomatis mendapatkan semua permission baru. Anda tidak perlu menambahkan permission quotes secara manual untuk `super-admin`.

### 2.2 Membuat Policy (`app/Policies/QuotePolicy.php`)

Policy menentukan siapa yang boleh melakukan apa terhadap resource `Quote`. Laravel secara otomatis memetakan policy berdasarkan konvensi penamaan (model `Quote` → policy `QuotePolicy`).

Generate file policy:

```bash
php artisan make:policy QuotePolicy --model=Quote
```

Edit file `app/Policies/QuotePolicy.php`:

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

**Pola yang wajib diikuti:**
- Setiap method membaca permission dari database via `$user->can('resource.ability')`.
- Format permission: `{nama_resource}.{ability}`.
- `restore` mengikuti permission `update`; `forceDelete` mengikuti permission `delete`.
- Method `viewAny` dan `create` hanya menerima parameter `User` (tidak ada instance model).
- Method `view`, `update`, `delete`, `restore`, `forceDelete` menerima parameter `User` **dan** instance model.

### 2.3 Jalankan Ulang Seeder

Agar permission baru terekam di database, jalankan:

```bash
# Di environment lokal/development — reset semua data dan seed ulang
php artisan migrate:fresh --seed
```

> [!CAUTION]
> **`migrate:fresh --seed` menghapus SEMUA data!** Hanya jalankan ini di environment lokal/development. Di staging/production, gunakan seeder secara incremental atau buat migration khusus untuk menambah permission baru.

---

## Step 3 — Membuat Form Requests untuk Validasi Input

**Prinsip utama:** Validasi input **tidak pernah** dilakukan di dalam Controller. Gunakan Form Request terpisah untuk setiap operasi write (Store dan Update).

Form Request menangani dua hal sekaligus:
1. **`authorize()`** — Mengecek apakah user yang sedang login boleh melakukan aksi ini (membaca Policy).
2. **`rules()`** — Mendefinisikan aturan validasi input.

### 3.1 Store Request (`app/Http/Requests/Api/V1/StoreQuoteRequest.php`)

Buat file `app/Http/Requests/Api/V1/StoreQuoteRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    /**
     * Cek otorisasi: apakah user boleh membuat Quote baru?
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quote::class) ?? false;
    }

    /**
     * Aturan validasi input.
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

### 3.2 Update Request (`app/Http/Requests/Api/V1/UpdateQuoteRequest.php`)

Buat file `app/Http/Requests/Api/V1/UpdateQuoteRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    /**
     * Cek otorisasi: apakah user boleh mengubah Quote ini?
     */
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            && ($this->user()?->can('update', $quote) ?? false);
    }

    /**
     * Aturan validasi input.
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

**Perbedaan kunci antara Store dan Update Request:**

| Aspek | StoreQuoteRequest | UpdateQuoteRequest |
|---|---|---|
| `authorize()` | Mengecek `can('create', Quote::class)` — tanpa instance model | Mengecek `can('update', $quote)` — **dengan** instance model dari route |
| Validasi `text` | `required` — wajib diisi | `sometimes, required` — boleh tidak dikirim, tapi jika dikirim harus valid |
| Validasi `author` | `required` — wajib diisi | `sometimes, required` — boleh tidak dikirim, tapi jika dikirim harus valid |

> [!TIP]
> **Mengapa `sometimes` + `required`?** Pada Update, user mungkin hanya ingin mengubah `is_active` tanpa mengirim ulang `text` dan `author`. Rule `sometimes` berarti "jika field ini ada di request, baru validasi". Rule `required` memastikan jika field tersebut dikirim, nilainya tidak boleh kosong. Kombinasi ini memungkinkan **partial update** yang aman.

> [!IMPORTANT]
> **Mengapa otorisasi `create`/`update` di Form Request, tapi `viewAny`/`view`/`delete` di Controller?**
>
> Ini adalah pola baku proyek ini:
> - Operasi **write** (create/update) membutuhkan **validasi input + otorisasi** sekaligus → keduanya dilakukan di Form Request.
> - Operasi **read** (index/show) dan **delete** **tidak** memiliki input yang perlu divalidasi → otorisasi dilakukan langsung di Controller via `$this->authorize()`.

---

## Step 4 — Membuat API Resource untuk Transformasi Output

API Resource mengisolasi format JSON response dari struktur database internal. Ini memberikan **kontrak stabil** kepada konsumen API (misalnya app Flutter) — jika Anda mengganti nama kolom database, cukup ubah di Resource saja, konsumen tidak terpengaruh.

### 4.1 Membuat Resource (`app/Http/Resources/Api/V1/QuoteResource.php`)

Buat file `app/Http/Resources/Api/V1/QuoteResource.php`:

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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Aturan yang wajib diikuti:**

1. **`@mixin Quote`** — PHPDoc directive yang memberitahu IDE bahwa resource ini "mixin" dari model `Quote`. Ini memungkinkan autocomplete property seperti `$this->text`, `$this->author`, dll.

2. **Format tanggal ISO-8601** — Selalu gunakan `->toIso8601String()` untuk kolom datetime. Ini menghasilkan format `2026-05-25T10:00:00+00:00` yang standar internasional dan mudah diparsing di Flutter/mobile app.

3. **Nullsafe operator `?->`** — Gunakan pada `created_at` dan `updated_at` karena kolom ini bisa `null` saat model belum disimpan.

4. **Jangan sertakan `deleted_at`** — Kolom ini untuk keperluan internal. Jika record sudah soft-deleted, ia tidak akan muncul di query biasa.

5. **Jangan melakukan query di dalam Resource** — Jika perlu data dari relasi, pastikan relasi sudah di-eager-load di Controller/Query Builder. Query di dalam Resource menyebabkan N+1 problem.

---

## Step 5 — Membuat Controller (Tipis) & Integrasi Spatie Query Builder

Controller adalah layer paling tipis di arsitektur ini. Tugasnya hanya:
1. Memanggil otorisasi (untuk read/delete).
2. Menerima data yang sudah divalidasi (dari Form Request).
3. Memanggil Model atau Service.
4. Mengembalikan response via API Resource + ApiResponse envelope.

### 5.1 Membuat Controller (`app/Http/Controllers/Api/V1/QuoteController.php`)

Buat file `app/Http/Controllers/Api/V1/QuoteController.php`:

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
     * GET /api/v1/quotes — Mendapatkan daftar quotes (List & Search)
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $quotes = QueryBuilder::for(Quote::class)
            ->allowedFilters([
                AllowedFilter::partial('text'),
                AllowedFilter::partial('author'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(QuoteResource::collection($quotes));
    }

    /**
     * POST /api/v1/quotes — Menyimpan quote baru (Create)
     */
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $quote = Quote::query()->create($request->validated());

        return ApiResponse::success(new QuoteResource($quote), 'Quote created successfully', 201);
    }

    /**
     * GET /api/v1/quotes/{quote} — Mendapatkan detail satu quote (Show)
     */
    public function show(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        return ApiResponse::success(new QuoteResource($quote));
    }

    /**
     * PUT/PATCH /api/v1/quotes/{quote} — Memperbarui data quote (Update)
     */
    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        $quote->update($request->validated());

        return ApiResponse::success(new QuoteResource($quote->refresh()), 'Quote updated successfully');
    }

    /**
     * DELETE /api/v1/quotes/{quote} — Menghapus quote (Soft Delete)
     */
    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return ApiResponse::success(null, 'Quote deleted successfully');
    }
}
```

### 5.2 Anatomi Detail Setiap Method

#### `index()` — List & Search

```php
public function index(Request $request): JsonResponse
{
    // 1. Otorisasi: cek apakah user boleh melihat daftar quotes
    $this->authorize('viewAny', Quote::class);

    // 2. Pembatasan pagination: min 1, max 100 record per halaman
    //    Ini mencegah client meminta per_page=999999 (DDoS protection)
    $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

    // 3. Spatie Query Builder: mengizinkan HANYA filter/sort yang didaftarkan
    $quotes = QueryBuilder::for(Quote::class)
        ->allowedFilters([
            // partial = LIKE '%value%' (pencarian teks)
            AllowedFilter::partial('text'),
            AllowedFilter::partial('author'),
            // exact = WHERE is_active = value (filter boolean)
            AllowedFilter::exact('is_active'),
        ])
        // HANYA kolom-kolom ini yang boleh di-sort
        ->allowedSorts('author', 'is_active', 'created_at', 'updated_at')
        // Default: urutkan berdasarkan created_at DESC (terbaru di atas)
        ->defaultSort('-created_at')
        // Paginate dengan limit yang sudah dibatasi
        ->paginate($perPage)
        // Pertahankan query parameter di link pagination
        ->appends($request->query());

    // 4. Kembalikan response dengan envelope standar + pagination metadata
    return ApiResponse::success(QuoteResource::collection($quotes));
}
```

**Contoh URL yang bisa digunakan konsumen:**
- `GET /api/v1/quotes` — Ambil semua quotes, halaman 1, 15 per halaman
- `GET /api/v1/quotes?per_page=10&page=2` — Halaman 2, 10 per halaman
- `GET /api/v1/quotes?filter[author]=Einstein` — Cari quotes dari penulis yang mengandung "Einstein"
- `GET /api/v1/quotes?filter[text]=code&filter[is_active]=1` — Cari quotes aktif yang mengandung "code"
- `GET /api/v1/quotes?sort=-created_at` — Urutkan berdasarkan terbaru
- `GET /api/v1/quotes?filter[author]=Torvalds&sort=author&per_page=5` — Kombinasi filter + sort + pagination

> [!WARNING]
> **Mengapa whitelist eksplisit?** Tanpa `allowedFilters` dan `allowedSorts`, client bisa sembarang memfilter/mengurutkan kolom database apapun — termasuk kolom sensitif. Spatie Query Builder secara otomatis **menolak** filter/sort yang tidak didaftarkan (mengabaikannya secara diam-diam, bukan error).

#### `store()` — Create

```php
public function store(StoreQuoteRequest $request): JsonResponse
{
    // Otorisasi + validasi sudah dilakukan di StoreQuoteRequest
    // Di sini kita hanya perlu memanggil create() dengan data yang sudah bersih
    $quote = Quote::query()->create($request->validated());

    // HTTP 201 Created — bukan 200 OK
    return ApiResponse::success(new QuoteResource($quote), 'Quote created successfully', 201);
}
```

#### `show()` — Get Single

```php
public function show(Quote $quote): JsonResponse
{
    // Route model binding: Laravel otomatis mengambil Quote berdasarkan {quote} di URL
    // Jika tidak ditemukan, otomatis return 404 (ditangani bootstrap/app.php)
    $this->authorize('view', $quote);

    return ApiResponse::success(new QuoteResource($quote));
}
```

#### `update()` — Update

```php
public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
{
    // Otorisasi + validasi sudah dilakukan di UpdateQuoteRequest
    $quote->update($request->validated());

    // refresh() memuat ulang data dari database agar response mencerminkan data terbaru
    return ApiResponse::success(new QuoteResource($quote->refresh()), 'Quote updated successfully');
}
```

#### `destroy()` — Delete (Soft Delete)

```php
public function destroy(Quote $quote): JsonResponse
{
    $this->authorize('delete', $quote);

    // Karena model menggunakan SoftDeletes, ini hanya mengisi kolom deleted_at
    $quote->delete();

    return ApiResponse::success(null, 'Quote deleted successfully');
}
```

> [!TIP]
> **Perhatikan pattern otorisasi:**
> - `store()` dan `update()` → otorisasi di **Form Request** (`authorize()` method)
> - `index()`, `show()`, `destroy()` → otorisasi di **Controller** (`$this->authorize()` method)

---

## Step 6 — Mendaftarkan Route API

Buka file `routes/api.php` dan daftarkan route untuk Quote controller.

### 6.1 Tambahkan Import

Di bagian atas file, tambahkan import untuk `QuoteController`:

```php
use App\Http\Controllers\Api\V1\QuoteController;
```

### 6.2 Daftarkan Resource Route

Di dalam middleware group `['auth:api', 'check.maintenance']`, tambahkan:

```php
Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
    Route::post('assets/upload', [AssetController::class, 'upload'])->middleware('throttle:30,1');

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('quotes', QuoteController::class);  // ← Tambahkan di sini

    Route::prefix('notifications')->group(function (): void {
        // ... route notification yang sudah ada ...
    });
});
```

### 6.3 Endpoint yang Otomatis Dihasilkan

Dengan `Route::apiResource('quotes', QuoteController::class)`, Laravel secara otomatis mendaftarkan endpoint berikut:

| HTTP Method | URL | Controller Method | Fungsi |
|---|---|---|---|
| `GET` | `/api/v1/quotes` | `index` | List & Search |
| `POST` | `/api/v1/quotes` | `store` | Create |
| `GET` | `/api/v1/quotes/{quote}` | `show` | Get Single |
| `PUT/PATCH` | `/api/v1/quotes/{quote}` | `update` | Update |
| `DELETE` | `/api/v1/quotes/{quote}` | `destroy` | Delete |

### 6.4 Verifikasi Route

Jalankan perintah berikut untuk memverifikasi route berhasil terdaftar:

```bash
php artisan route:list --path=api/v1/quotes
```

Output yang diharapkan:

```
GET|HEAD   api/v1/quotes .............. quotes.index › Api\V1\QuoteController@index
POST       api/v1/quotes .............. quotes.store › Api\V1\QuoteController@store
GET|HEAD   api/v1/quotes/{quote} ...... quotes.show › Api\V1\QuoteController@show
PUT|PATCH  api/v1/quotes/{quote} ...... quotes.update › Api\V1\QuoteController@update
DELETE     api/v1/quotes/{quote} ...... quotes.destroy › Api\V1\QuoteController@destroy
```

---

## Step 7 — Membuat Filament Back-Office Resource (Opsional)

Jika resource ini juga perlu dikelola melalui panel admin (back-office) di `/admin`, buat Filament Resource mengikuti pola modular proyek ini.

> [!NOTE]
> Step ini **opsional**. Jika resource hanya diakses via API, Anda bisa melewati step ini.

### 7.1 Struktur Direktori Filament

Proyek ini menggunakan **modular Filament structure** — bukan satu file raksasa. Buat struktur direktori berikut:

```
app/Filament/Resources/Quotes/
├── QuoteResource.php              # Main resource definition
├── Schemas/
│   └── QuoteForm.php              # Konfigurasi form create/edit
├── Tables/
│   └── QuotesTable.php            # Konfigurasi tabel list
└── Pages/
    ├── ListQuotes.php             # Halaman daftar quotes
    ├── CreateQuote.php            # Halaman buat quote baru
    └── EditQuote.php              # Halaman edit quote
```

### 7.2 Main Resource (`app/Filament/Resources/Quotes/QuoteResource.php`)

```php
<?php

namespace App\Filament\Resources\Quotes;

use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use App\Models\Quote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $recordTitleAttribute = 'text';

    public static function form(Schema $schema): Schema
    {
        return QuoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }
}
```

### 7.3 Form Schema (`app/Filament/Resources/Quotes/Schemas/QuoteForm.php`)

```php
<?php

namespace App\Filament\Resources\Quotes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->required()
                    ->minLength(5)
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                TextInput::make('source')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
```

### 7.4 Table Config (`app/Filament/Resources/Quotes/Tables/QuotesTable.php`)

```php
<?php

namespace App\Filament\Resources\Quotes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### 7.5 Pages

**`app/Filament/Resources/Quotes/Pages/ListQuotes.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

**`app/Filament/Resources/Quotes/Pages/CreateQuote.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;
}
```

**`app/Filament/Resources/Quotes/Pages/EditQuote.php`:**

```php
<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

---

## Step 8 — Menulis Automated Feature Test (API)

Testing adalah **kewajiban mutlak**. Tidak ada fitur yang boleh masuk ke codebase tanpa test yang memverifikasinya.

### 8.1 Skenario Test Minimum yang Wajib Ada

Setiap resource API **wajib** memiliki minimal 4 skenario test:

| # | Skenario | Tujuan |
|---|---|---|
| 1 | Guest ditolak (401) | Memastikan endpoint terproteksi auth |
| 2 | List dengan filter, sort, pagination | Memastikan Spatie Query Builder bekerja |
| 3 | Siklus CRUD lengkap (create → show → update → delete) | Memastikan semua operasi berfungsi end-to-end |
| 4 | User tanpa permission mendapat 403 | Memastikan RBAC berjalan |

### 8.2 File Test (`tests/Feature/Api/QuoteTest.php`)

Buat file `tests/Feature/Api/QuoteTest.php`:

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

        // Seed permission & role sebelum setiap test dijalankan
        $this->seed(RolePermissionSeeder::class);
    }

    // ──────────────────────────────────────────────────────────
    // Skenario 1: Guest tidak bisa mengakses API quotes
    // ──────────────────────────────────────────────────────────

    public function test_guest_cannot_access_quotes(): void
    {
        $this->getJson('/api/v1/quotes')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    // ──────────────────────────────────────────────────────────
    // Skenario 2: Admin bisa list quotes dengan filter, sort, pagination
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_list_quotes_with_filter_sort_and_pagination(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        // Buat mock data dengan factory
        Quote::factory()->create([
            'text' => 'Belajar Laravel itu menyenangkan.',
            'author' => 'Developer A',
            'is_active' => true,
        ]);
        Quote::factory()->create([
            'text' => 'Clean code selalu menang.',
            'author' => 'Martin Fowler',
            'is_active' => false,
        ]);

        // Test: filter aktif + search teks 'Laravel' + sort + pagination
        $this->getJson('/api/v1/quotes?filter[is_active]=1&filter[text]=Laravel&sort=-author&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.author', 'Developer A')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    // ──────────────────────────────────────────────────────────
    // Skenario 3: Admin bisa melakukan siklus CRUD lengkap
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_create_show_update_and_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        // 1. CREATE
        $quoteId = $this->postJson('/api/v1/quotes', [
            'text' => 'Talk is cheap. Show me the code.',
            'author' => 'Linus Torvalds',
            'source' => 'Linux Kernel Mailing List',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Linus Torvalds')
            ->json('data.id');

        // 2. SHOW (Read single)
        $this->getJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJsonPath('data.text', 'Talk is cheap. Show me the code.');

        // 3. UPDATE (Partial update)
        $this->putJson("/api/v1/quotes/{$quoteId}", [
            'text' => 'Membaca kode adalah keterampilan utama.',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.author', 'Linus Torvalds'); // Tetap, karena tidak di-update

        // 4. DELETE (Soft delete)
        $this->deleteJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJson(['message' => 'Quote deleted successfully']);

        // Verifikasi: record masih ada di DB tapi sudah soft-deleted
        $this->assertSoftDeleted('quotes', ['id' => $quoteId]);
    }

    // ──────────────────────────────────────────────────────────
    // Skenario 4: Staff tidak boleh menghapus quote (RBAC)
    // ──────────────────────────────────────────────────────────

    public function test_staff_cannot_delete_quote(): void
    {
        Passport::actingAs($this->userWithRole('staff'));
        $quote = Quote::factory()->create();

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertForbidden();

        // Verifikasi: record TIDAK terhapus
        $this->assertNotSoftDeleted('quotes', ['id' => $quote->id]);
    }

    // ──────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
```

**Penjelasan pola testing:**

| Pola | Penjelasan |
|---|---|
| `use RefreshDatabase` | Setiap test berjalan di database yang bersih (migrasi + rollback otomatis). |
| `$this->seed(RolePermissionSeeder::class)` | Wajib di `setUp()` karena test butuh permission/role yang sudah ada di DB. |
| `Passport::actingAs(...)` | Simulasi autentikasi API OAuth2. Ini setara dengan mengirim Bearer token yang valid. |
| `assertCreated()` | Assert HTTP 201. |
| `assertJson(['code' => '...'])` | Memverifikasi error code di envelope response. |
| `assertJsonPath('data.0.author', '...')` | Memverifikasi nilai spesifik di dalam nested JSON. |
| `assertSoftDeleted(...)` | Memastikan row ada di DB dengan `deleted_at` terisi (bukan `null`). |
| `assertNotSoftDeleted(...)` | Memastikan row masih ada dengan `deleted_at = null`. |

---

## Step 9 — Menulis Automated Test (Back-Office)

Jika Anda membuat Filament Resource di Step 7, tambahkan juga smoke test untuk back-office.

### 9.1 File Test (`tests/Feature/BackOffice/QuoteManagementTest.php`)

Buat file `tests/Feature/BackOffice/QuoteManagementTest.php`:

```php
<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_quote_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $quote = Quote::factory()->create();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('edit', ['record' => $quote]))
            ->assertOk();
    }

    public function test_user_without_quote_permission_cannot_access_quote_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(QuoteResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
```

> [!NOTE]
> Perhatikan perbedaan autentikasi:
> - **Test API** menggunakan `Passport::actingAs()` — simulasi OAuth2 bearer token.
> - **Test Back-Office** menggunakan `$this->actingAs()` — simulasi session/cookie login.

---

## Step 10 — Validasi Kode dengan Quality Gates

Sebelum commit, **wajib** lolos 3 quality gate berikut:

### 10.1 Linter & Formatter (Laravel Pint)

Pint akan memformat kode PHP Anda agar sesuai standar PSR-12 secara otomatis.

```bash
vendor/bin/pint
```

Pint akan memperbaiki formatting secara otomatis (indentasi, spasi, ordering `use` statements, dll.). Jika ada perubahan, Pint akan menunjukkan file mana saja yang diformat ulang.

### 10.2 Static Analysis (Larastan/PHPStan)

PHPStan menganalisis kode tanpa menjalankannya. Ini mendeteksi bug seperti:
- Memanggil method yang tidak ada
- Tipe parameter yang salah
- Property yang tidak dideklarasikan
- Return type yang tidak konsisten

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

**Target: `[OK] No errors`**

Jika ada error, baca pesan error PHPStan dengan teliti. Error yang sering muncul:
- `Access to an undefined property` → Tambahkan `@property` docblock di model.
- `Parameter ... expects ..., ... given` → Perbaiki tipe data yang dikirim ke method.

### 10.3 Automated Tests (PHPUnit)

Jalankan seluruh test suite untuk memastikan fitur baru tidak merusak fitur lama:

```bash
php artisan test
```

**Target: Semua test PASS (hijau).**

Jika ada test yang gagal:
- Baca pesan error dengan seksama.
- Pastikan migrasi dan seeder sudah benar.
- Pastikan `RolePermissionSeeder` sudah diperbarui dengan permission baru.

> [!CAUTION]
> **Jangan pernah commit kode yang gagal di quality gate.** Ini adalah aturan mutlak. Jika salah satu dari 3 gate di atas gagal, perbaiki terlebih dahulu sebelum commit.

---

## Step 11 — Commit & Push dengan Git Flow

Setelah semua quality gate berstatus hijau, lakukan commit mengikuti konvensi proyek ini.

### 11.1 Buat Branch Fitur

```bash
git checkout develop
git pull origin develop
git checkout -b feature/quotes-api
```

### 11.2 Commit Secara Atomik

**Jangan** commit semua file dalam satu commit besar. Buat commit kecil yang fokus pada satu perubahan logis:

```bash
# Commit 1: Migration & Model
git add database/migrations/*create_quotes_table* app/Models/Quote.php database/factories/QuoteFactory.php
git commit -m "feat(quotes): add migration, model, and factory"

# Commit 2: RBAC & Policy
git add database/seeders/RolePermissionSeeder.php app/Policies/QuotePolicy.php
git commit -m "feat(quotes): add RBAC permissions and policy"

# Commit 3: API layer (requests, resource, controller, routes)
git add app/Http/Requests/Api/V1/StoreQuoteRequest.php \
        app/Http/Requests/Api/V1/UpdateQuoteRequest.php \
        app/Http/Resources/Api/V1/QuoteResource.php \
        app/Http/Controllers/Api/V1/QuoteController.php \
        routes/api.php
git commit -m "feat(quotes): add API controller, form requests, resource, and routes"

# Commit 4: Filament back-office (opsional)
git add app/Filament/Resources/Quotes/
git commit -m "feat(quotes): add Filament back-office resource"

# Commit 5: Tests
git add tests/Feature/Api/QuoteTest.php tests/Feature/BackOffice/QuoteManagementTest.php
git commit -m "test(quotes): add API and back-office feature tests"

# Commit 6: Update CHANGELOG.md (WAJIB!)
git add CHANGELOG.md
git commit -m "docs(changelog): add quotes API to unreleased section"
```

### 11.3 Update CHANGELOG.md

Buka `CHANGELOG.md` dan tambahkan entry di bawah `## [Unreleased]`:

```markdown
## [Unreleased]

### Added
- Quotes Management API (CRUD, search, filter, pagination)
- Filament back-office resource for Quotes management
- RBAC permissions for quotes resource (admin: full access, staff: no delete)
- Automated API and back-office tests for Quotes
```

### 11.4 Push ke Remote

```bash
git push -u origin feature/quotes-api
```

Kemudian buat Pull Request dari `feature/quotes-api` ke `develop` melalui GitHub.

---

## ✅ Checklist Final Best Practice

Sebelum membuat Pull Request, pastikan Anda sudah mencentang **semua** item berikut:

### Database & Model
- [ ] Migrasi menggunakan tipe data yang tepat (`text`, `string`, `boolean`, dll.)
- [ ] `softDeletes()` ditambahkan di migrasi
- [ ] Index ditambahkan pada kolom yang sering di-filter/search
- [ ] Model memiliki `@property` docblock lengkap untuk setiap kolom
- [ ] Model menggunakan `#[Fillable([...])]` attribute (bukan property `$fillable`)
- [ ] Model menggunakan trait `HasFactory`, `LogsActivity`, `SoftDeletes`
- [ ] Method `casts()` mendefinisikan tipe non-string (boolean, etc.)
- [ ] Factory dibuat dengan data yang realistis dan state method untuk variasi

### RBAC & Policy
- [ ] Nama resource ditambahkan ke `RolePermissionSeeder::RESOURCES`
- [ ] Permission ditambahkan ke role `admin` dan `staff` sesuai kebutuhan bisnis
- [ ] Policy dibuat dengan method: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`
- [ ] Setiap method Policy membaca permission format `{resource}.{ability}`

### API Layer
- [ ] **Form Request Store**: Validasi `required` + `authorize()` mengecek `can('create', Model::class)`
- [ ] **Form Request Update**: Validasi `sometimes, required` + `authorize()` mengecek `can('update', $model)`
- [ ] **API Resource**: `@mixin Model` docblock, tanggal format ISO-8601, tidak ada query di dalamnya
- [ ] **Controller**: Setiap method < 20 baris, tanpa logika bisnis, tanpa try-catch
- [ ] **Controller**: Otorisasi read/delete via `$this->authorize()`, write via Form Request
- [ ] **Controller index()**: Menggunakan Spatie QueryBuilder dengan whitelist eksplisit
- [ ] **Controller index()**: `per_page` dibatasi `min(max(..., 1), 100)` (anti-DDoS)
- [ ] **Controller store()**: Mengembalikan HTTP 201 (bukan 200)
- [ ] **Controller update()**: Menggunakan `$model->refresh()` sebelum mengembalikan response
- [ ] Response selalu dibungkus `ApiResponse::success()` atau `ApiResponse::error()`

### Route
- [ ] Didaftarkan di dalam middleware group `['auth:api', 'check.maintenance']`
- [ ] Menggunakan `Route::apiResource()` untuk CRUD standar
- [ ] Import controller ditambahkan di bagian atas `routes/api.php`
- [ ] Diverifikasi via `php artisan route:list --path=api/v1/quotes`

### Filament (Opsional)
- [ ] Struktur modular: `QuoteResource.php`, `Schemas/`, `Tables/`, `Pages/`
- [ ] Navigation group: `Data Master`
- [ ] Form, table, dan pages mengikuti pola `Category` blueprint

### Testing
- [ ] **Test API**: Minimal 4 skenario (guest 401, list+filter+sort, CRUD cycle, RBAC 403)
- [ ] **Test API**: Menggunakan `Passport::actingAs()` untuk autentikasi
- [ ] **Test Back-Office**: Smoke test halaman index/create/edit + unauthorized user
- [ ] **Test Back-Office**: Menggunakan `$this->actingAs()` untuk autentikasi
- [ ] Semua test berstatus PASS hijau

### Quality Gates
- [ ] `vendor/bin/pint` — formatter berjalan tanpa error
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G` — `[OK] No errors`
- [ ] `php artisan test` — semua test PASS

### Git & Dokumentasi
- [ ] Branch dibuat dari `develop` dengan nama `feature/quotes-api`
- [ ] Commit atomik dengan format Conventional Commits (`feat(quotes): ...`)
- [ ] `CHANGELOG.md` diperbarui di bagian `## [Unreleased]`
- [ ] Tidak ada file rahasia yang ter-commit (`.env`, private keys)
- [ ] Push ke remote dan buat Pull Request ke `develop`

---

## 📁 Lampiran: Ringkasan File yang Dibuat/Dimodifikasi

### File Baru yang Dibuat

| # | File | Tipe | Deskripsi |
|---|---|---|---|
| 1 | `database/migrations/xxxx_create_quotes_table.php` | Migration | Skema tabel `quotes` |
| 2 | `app/Models/Quote.php` | Model | Eloquent model dengan fillable, casts, soft deletes |
| 3 | `database/factories/QuoteFactory.php` | Factory | Data faker untuk testing dan seeding |
| 4 | `app/Policies/QuotePolicy.php` | Policy | Aturan otorisasi per-aksi |
| 5 | `app/Http/Requests/Api/V1/StoreQuoteRequest.php` | Form Request | Validasi input create |
| 6 | `app/Http/Requests/Api/V1/UpdateQuoteRequest.php` | Form Request | Validasi input update |
| 7 | `app/Http/Resources/Api/V1/QuoteResource.php` | API Resource | Transformasi output JSON |
| 8 | `app/Http/Controllers/Api/V1/QuoteController.php` | Controller | Endpoint handler (tipis) |
| 9 | `app/Filament/Resources/Quotes/QuoteResource.php` | Filament | Main resource definition |
| 10 | `app/Filament/Resources/Quotes/Schemas/QuoteForm.php` | Filament | Form schema |
| 11 | `app/Filament/Resources/Quotes/Tables/QuotesTable.php` | Filament | Table config |
| 12 | `app/Filament/Resources/Quotes/Pages/ListQuotes.php` | Filament | Halaman list |
| 13 | `app/Filament/Resources/Quotes/Pages/CreateQuote.php` | Filament | Halaman create |
| 14 | `app/Filament/Resources/Quotes/Pages/EditQuote.php` | Filament | Halaman edit |
| 15 | `tests/Feature/Api/QuoteTest.php` | Test | Feature test API |
| 16 | `tests/Feature/BackOffice/QuoteManagementTest.php` | Test | Feature test back-office |

### File yang Dimodifikasi

| # | File | Perubahan |
|---|---|---|
| 1 | `database/seeders/RolePermissionSeeder.php` | Tambah `'quotes'` ke `RESOURCES`, tambah permission ke role `admin` & `staff` |
| 2 | `routes/api.php` | Tambah `Route::apiResource('quotes', QuoteController::class)` + import |
| 3 | `CHANGELOG.md` | Tambah entry di `## [Unreleased]` |

---

## 🎓 Ringkasan: Alur Berpikir Saat Menambah API Baru

```
1. Rancang tabel        → Migration (skema + index)
2. Representasi data    → Model (fillable, casts, docblock) + Factory
3. Siapa boleh apa?     → RolePermissionSeeder + Policy
4. Input masuk valid?   → Form Request (Store + Update)
5. Output keluar rapi?  → API Resource
6. Orkestrasi request?  → Controller (tipis!) + Query Builder
7. Akses dimana?        → Route registration
8. Back-office?         → Filament Resource (opsional)
9. Buktikan benar!      → Feature Test (API + Back-Office)
10. Kode berkualitas?   → Pint + PHPStan + PHPUnit
11. Riwayat rapi?       → Git Flow + Conventional Commits + CHANGELOG
```

Selamat! Anda telah menambahkan modul API baru yang lengkap, aman, teruji, dan mengikuti seluruh best practice proyek Laravel Starter. 🚀
