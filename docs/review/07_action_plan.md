# 07 — Action Plan & Roadmap Perbaikan

> Kompilasi semua temuan menjadi action plan yang actionable.
> Direview: 2026-05-24 | Reviewer: Antigravity AI Agent

---

## A. Temuan Kritis (Selesaikan Sebelum Digunakan)

| # | Temuan | File/Lokasi | Dampak | Estimasi Effort |
|---|--------|-------------|--------|-----------------|
| 1 | 🔥 Email Verification dinonaktifkan — `MustVerifyEmail` di-comment | `app/Models/User.php` baris 5 | User bisa login tanpa verifikasi email. Risiko spam account di production | S (1-2 jam) |
| 2 | 🔥 Test menggunakan SQLite padahal production PostgreSQL | `phpunit.xml` baris 26-28 | Bug PostgreSQL-specific tidak terdeteksi oleh test suite | S (1 jam) |
| 3 | 🔥 Tidak ada file `LICENSE` | Root project | Ketidakjelasan hukum meskipun `composer.json` menyatakan MIT | S (10 menit) |
| 4 | ⚠️ Filament resource tidak enforce RBAC per-resource | `app/Filament/Resources/*` | User `staff` bisa akses semua resource setelah masuk panel | M (4-6 jam) |
| 5 | ⚠️ Unit test untuk Service layer kosong | `tests/Unit/Services/` | Logika bisnis kritis (auth, OTP) tidak ditest secara isolasi | M (4-8 jam) |

---

## B. Perbaikan Penting (Sprint 1 — 1-2 Minggu)

| # | Item | Alasan Prioritas | Estimasi Effort |
|---|------|-----------------|-----------------|
| 1 | Aktifkan Email Verification + endpoint verify email API | Keamanan dasar — mencegah spam account | S (2 jam) |
| 2 | Tambahkan endpoint User Registration (API) | Fitur fundamental yang belum ada — user hanya bisa dibuat via admin | M (4-6 jam) |
| 3 | Tambahkan endpoint Password Reset (API) | Fitur fundamental untuk mobile user yang lupa password | M (4-6 jam) |
| 4 | Ubah `phpunit.xml` untuk menggunakan PostgreSQL | Memastikan test mendeteksi bug database-specific | S (1 jam) |
| 5 | Implement per-resource permission di Filament | Menjamin RBAC konsisten di back-office | M (4-6 jam) |
| 6 | Tambahkan unit test: `AuthServiceTest`, `OtpServiceTest` | Safety net untuk modifikasi logika bisnis | M (6-8 jam) |
| 7 | Buat file `LICENSE` (MIT) | Kejelasan hukum | S (10 menit) |
| 8 | Buat file `SECURITY.md` | Best practice open source, panduan pelaporan vulnerability | S (30 menit) |
| 9 | Buat ERD diagram di `docs/erd/` | Visualisasi relasi database untuk developer dan AI Agent | S (2 jam) |
| 10 | Tambahkan endpoint "Logout All Devices" | Keamanan — user bisa invalidasi semua session | S (2 jam) |

**Estimasi Total Sprint 1:** ~30-40 jam

---

## C. Peningkatan (Sprint 2 — 2-4 Minggu)

| # | Item | Manfaat | Estimasi Effort |
|---|------|---------|-----------------|
| 1 | Tambahkan GitHub Actions CI pipeline | Quality gate otomatis pada setiap PR | S (2-3 jam) |
| 2 | Tambahkan `spatie/laravel-activitylog` | Audit trail untuk semua CRUD operations | M (4-6 jam) |
| 3 | Buat API Error Code enum (`ApiErrorCode`) | Flutter client bisa branching error tanpa parsing string | S (2 jam) |
| 4 | Tambahkan Filament test untuk semua resource | Back-office coverage naik dari ~40% ke ~80% | M (6-8 jam) |
| 5 | Publish `config/cors.php` dan dokumentasikan | Konfigurasi eksplisit untuk cross-origin requests | S (1 jam) |
| 6 | Tambahkan `Makefile` sebagai shortcut developer | DX improvement — `make test`, `make lint`, `make dev` | S (1 jam) |
| 7 | Buat `docs/deployment.md` | Panduan deployment production | M (3-4 jam) |
| 8 | Dispatch push notification via Queue Job | Menghindari blocking API response saat kirim FCM | S (2-3 jam) |
| 9 | Tambahkan GitHub templates (issue, PR) | Standarisasi kontribusi | S (1 jam) |
| 10 | Tambahkan dark mode logo variant di Filament | UI polish — logo terlihat di dark mode | S (30 menit) |

**Estimasi Total Sprint 2:** ~25-35 jam

---

## D. Nice to Have (Backlog)

| # | Item | Manfaat |
|---|------|---------|
| 1 | Two-Factor Authentication (2FA / TOTP) | Keamanan lanjutan untuk akun sensitif |
| 2 | Social Login (Google, Apple) via `laravel/socialite` | Convenience untuk user mobile |
| 3 | `spatie/laravel-medialibrary` untuk manajemen file | File management yang lebih matang dari `FileUploadService` |
| 4 | Export Excel/PDF di Filament resource | Download data untuk reporting |
| 5 | Import CSV/Excel di Filament | Bulk data upload |
| 6 | Global Search (Filament global search) | Navigasi cepat antar resource |
| 7 | `CHANGELOG.md` (Keep a Changelog format) | Tracking perubahan publik |
| 8 | Custom Artisan command `make:master-data` | Auto-generate CRUD boilerplate |
| 9 | Test coverage reporting (PHPUnit coverage) | Metrik objektif test quality |
| 10 | Cursor pagination untuk API list endpoints | Performa lebih baik untuk large datasets |
| 11 | Multi-tenancy (jika dibutuhkan) | SaaS readiness — ini XL effort |
| 12 | Broadcast / WebSocket support | Real-time notifications |
| 13 | Email notification templates | Transactional email (welcome, verification, reset) |
| 14 | Soft delete pada model User | Data retention untuk compliance |

---

## E. File yang Harus Dibuat (dengan outline)

### 1. `LICENSE` (Root)
```
MIT License

Copyright (c) 2026 [Nama/Organisasi]

Permission is hereby granted, free of charge, to any person obtaining a copy...
[MIT License full text]
```

### 2. `SECURITY.md` (Root)
```markdown
# Security Policy

## Supported Versions
| Version | Supported |
|---------|-----------|
| 0.x     | ✅        |

## Reporting a Vulnerability
- Email: security@[domain].com
- Response SLA: 48 jam
- Jangan buat public issue untuk vulnerability

## Security Considerations
- Passport keys (`storage/oauth-*.key`) harus di-gitignore dan di-generate per environment
- Rate limiting sudah diterapkan pada endpoint auth
- HTTPS di-enforce di production via AppServiceProvider
- Credential tidak hardcoded — semua via .env
```

### 3. `docs/erd/database_erd.md`
```markdown
# Entity Relationship Diagram

## Diagram

[Mermaid ERD diagram menunjukkan:]
- users (1) → (N) user_devices
- users (1) → (N) notifications
- users (M) → (N) roles (via model_has_roles)
- roles (M) → (N) permissions (via role_has_permissions)
- categories (standalone, soft-deletable)
- app_configs (standalone, key-value)
- app_versions (standalone)
- otp_codes (standalone, linked by phone)
- regions (self-referential: parent_id)

## Keterangan Tabel
[Deskripsi tiap tabel dan kolom utama]
```

### 4. `docs/deployment.md`
```markdown
# Panduan Deployment Production

## Prasyarat
- PHP 8.3+ dengan extensions: pdo_pgsql, redis, gd, bcmath
- PostgreSQL 16+
- Redis (opsional, recommended)
- Nginx / Apache
- SSL certificate

## Environment Variables Production
[Daftar variable yang WAJIB diisi untuk production]

## Langkah Deployment
1. Clone repository
2. `composer install --no-dev --optimize-autoloader`
3. Copy .env dan isi variable production
4. `php artisan key:generate`
5. `php artisan passport:keys`
6. `php artisan migrate --force`
7. `php artisan db:seed` (hanya pertama kali)
8. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
9. Setup queue worker (Supervisor)
10. Setup web server (Nginx config)

## Queue Worker (Supervisor)
[Konfigurasi Supervisor untuk queue:work]

## Nginx Configuration
[Contoh konfigurasi Nginx untuk Laravel]

## Monitoring
[Rekomendasi monitoring: Laravel Pulse, log rotation, uptime check]
```

### 5. `.github/workflows/ci.yml`
```yaml
name: CI
on: [push, pull_request]
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: vendor/bin/pint --test

  analyse:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: vendor/bin/phpstan analyse --memory-limit=1G

  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: laravel_starter_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: secret
        ports: ['5432:5432']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: php artisan test
        env:
          DB_CONNECTION: pgsql
          DB_DATABASE: laravel_starter_test
          DB_USERNAME: postgres
          DB_PASSWORD: secret
```

### 6. `.github/ISSUE_TEMPLATE/bug_report.md`
```markdown
---
name: Bug Report
about: Report a bug to help us improve
labels: bug
---

## Describe the bug
[Deskripsi jelas]

## Steps to reproduce
1. ...
2. ...

## Expected behavior
[Apa yang seharusnya terjadi]

## Actual behavior
[Apa yang terjadi]

## Environment
- PHP: [version]
- Laravel: [version]
- OS: [os]

## Screenshots / Logs
[Jika ada]
```

### 7. `.github/pull_request_template.md`
```markdown
## What does this PR do?
[Deskripsi singkat]

## Related issue
Closes #[number]

## Checklist
- [ ] Quality gate passed (`composer test && composer lint && composer analyse`)
- [ ] Documentation updated (if needed)
- [ ] `.env.example` updated (if needed)
- [ ] Migration included (if needed)
- [ ] Test added for new feature / fix
```

### 8. `Makefile`
```makefile
.PHONY: dev test lint analyse setup fresh

dev:
	composer dev

test:
	composer test

lint:
	composer lint

analyse:
	composer analyse

setup:
	composer setup

fresh:
	php artisan migrate:fresh --seed

quality:
	composer lint
	composer analyse
	composer test
```

---

## F. Estimasi Total Effort

| Fase | Effort | Timeline |
|------|--------|----------|
| **Temuan Kritis** (wajib sebelum digunakan) | ~15-20 jam | 2-3 hari |
| **Sprint 1** (perbaikan penting) | ~30-40 jam | 1-2 minggu |
| **Sprint 2** (peningkatan) | ~25-35 jam | 2-4 minggu |
| **Backlog** (nice to have) | ~50-80 jam | Ongoing |
| **TOTAL ke "Production-Ready Starter"** | **~70-95 jam** | **~3-5 minggu** |

> **Catatan:** Estimasi di atas **TIDAK** termasuk implementasi multi-tenancy. Jika multi-tenancy dibutuhkan, tambahkan ~40-60 jam dan 2-3 minggu tambahan.

---

## Ringkasan Prioritas Visual

```
🔥 KRITIS (Sebelum Digunakan)
├── Email Verification aktifkan
├── phpunit.xml → PostgreSQL
├── LICENSE file
├── Filament RBAC per-resource
└── Unit test Service layer

⚠️ SPRINT 1 (1-2 Minggu)
├── User Registration API
├── Password Reset API
├── Logout All Devices
├── ERD diagram
├── SECURITY.md
└── Unit test AuthService + OtpService

💡 SPRINT 2 (2-4 Minggu)
├── CI Pipeline (GitHub Actions)
├── Activity Log (Spatie)
├── API Error Codes
├── Filament test coverage
├── Deployment guide
├── Makefile
└── Queue untuk push notification

🔲 BACKLOG
├── 2FA, Social Login
├── Media Library, Export/Import
├── Multi-tenancy (jika dibutuhkan)
├── CHANGELOG, Global Search
└── Broadcast / WebSocket
```

---

✅ **Review selesai. Semua dokumen tersimpan di `docs/review/`**
