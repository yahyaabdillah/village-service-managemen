# Village Service Management System

Laravel production-oriented MVP berdasarkan dokumentasi di `../../design.md`, `../../database-schema.md`, `../../implementation-plan.md`, dan `../../erd.mmd`.

## Implemented

### Public

- Landing page, daftar layanan, detail layanan.
- Hanya service aktif yang bisa dilihat/diajukan.
- Form pengajuan warga dengan base applicant fields.
- Nomor HP memakai select kode negara + bendera dan input nomor lokal yang otomatis menghapus huruf/non-digit serta angka 0 di depan.
- Dynamic fields per jenis layanan via `service_type_fields`.
- Upload berkas persyaratan ke private disk.
- Validasi upload mengikuti aturan per persyaratan (`allowed_file_types`, `max_file_size_kb`) dengan batas global 5 MB.
- Request code otomatis.
- Cek status dengan `request_code` + `nik`.
- Timeline publik hanya menampilkan history `is_public = true`.
- Success page pengajuan tidak bisa dienumerasi tanpa session hasil submit.
- Public submission/status/download routes memakai throttle dasar.
- Protected final document download: harus validasi `request_code` + `nik`.
- Upload security hook: scanner service untuk signature blokir dasar + optional ClamAV command.
- Health endpoint aman di `/healthz` untuk dependency check database/cache/private storage.

### Admin

- Login/logout session auth.
- Inactive user tidak bisa login via credential guard.
- RBAC via `spatie/laravel-permission`.
- Admin middleware: route admin butuh login dan permission granular per modul/action.
- Admin dashboard cards: total penduduk, total pengajuan, baru, diproses, selesai, ditolak.
- CRUD admin untuk:
  - village profiles,
  - family cards,
  - residents,
  - service types,
  - service requirements,
  - service type fields,
  - announcements,
  - users,
  - roles.
- Import/export CSV untuk data penduduk.
- Template Excel `.xlsx` untuk import penduduk dan import mendukung CSV/XLSX.
- Preview import penduduk untuk validasi error baris sebelum commit.
- Service request workflow:
  - verify,
  - process,
  - reject,
  - upload final document manual,
  - generate PDF document,
  - complete.
- Status transition guard:
  - `submitted -> verified/rejected/cancelled`
  - `verified -> processing/completed/rejected/cancelled`
  - `processing -> completed/rejected/cancelled`
- Actor audit fields populated for status changes:
  - `verified_by`
  - `processed_by`
  - `completed_by`
  - `rejected_by`
  - `service_request_status_histories.changed_by`
- Document template upload.
- Template field builder data model using normalized percentage coordinates.
- PDF generation service uses FPDI to overlay mapped fields onto uploaded PDF templates. If an invalid/corrupt PDF is uploaded, generation fails gracefully into a controlled generated PDF instead of throwing a 500.
- Audit trait fills `created_by`, `updated_by`, and `deleted_by` on core business models when an authenticated admin creates/updates/soft-deletes records.

### Database / Storage

Includes schema for:

- `users`
- `roles`, `permissions`, Spatie pivots
- `activity_log`
- `family_cards`
- `residents`
- `village_profiles`
- `service_types`
- `service_requirements`
- `service_type_fields`
- `service_requests`
- `service_request_field_values`
- `request_files`
- `service_request_status_histories`
- `document_templates`
- `template_fields`
- `generated_documents`
- `announcements`

- Deployment docs tanpa Docker: `docs/DEPLOYMENT.md`.
- GitHub Actions CI workflow: `.github/workflows/ci.yml`.
- Backup command dan scheduler:
  - `php artisan backup:run`
  - scheduled daily at `02:00`
  - backup disimpan ke private disk `backups/*.zip`.
- Security log channel terpisah: `storage/logs/security.log` untuk login/download/upload security events.
- Admin observability pages:
  - `/admin/activity-logs` dengan filter event/log/deskripsi
  - `/admin/security-logs`
- Admin UI polish: sidebar terstruktur, toolbar search/import/export, responsive layout.
- Pola form konsisten: input sedikit = modal, input sedang = drawer, input banyak (>10 field) = stepper.
- WhatsApp notification integration via Baileys bridge tanpa Chromium:
  - menu admin `/admin/whatsapp` untuk status/QR pairing,
  - tombol **Mulai Pairing / Tampilkan QR** agar admin tidak perlu menjalankan bridge dari terminal,
  - Node bridge `wa-bridge/server.js`,
  - command `npm run wa:bridge`,
  - notifikasi otomatis saat status pengajuan berubah,
  - queued job `SendWhatsAppNotification`,
  - database log `notification_logs`,
  - admin page `/admin/notification-logs`.
- Observability stack lokal melalui Grafana, Loki, dan Alloy:
  - log Laravel, security, dan WhatsApp dikirim otomatis ke Loki,
  - datasource dan dashboard Grafana diprovision dari repository,
  - jalankan dengan `docker compose -f docker-compose.observability.yml up -d`,
  - panduan operasional tersedia di `docs/OBSERVABILITY.md`.
- Backup service mendukung SQLite file backup dan optional MySQL dump (`database/mysql.sql`) saat production memakai MySQL dan `mysqldump` tersedia.

Filesystem includes explicit private disk:

```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => false,
    'throw' => true,
    'report' => true,
]
```

## Demo account

- Email: `admin@desa.test`
- Password: `[REDACTED]`

Change this before any real deployment.

## Commands tanpa Docker

Project disiapkan untuk native PHP/Composer/Node, bukan Docker.

```bash
cd /path/to/village-service-management
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Untuk WhatsApp bridge:

```bash
npm run wa:bridge
```

## Verification status

Last verified:

- `php artisan test`: 34 passed, 174 assertions.
- `pint --test`: 72 files passed.
- `migrate:fresh --seed`: passed.
- `npm install --package-lock-only --ignore-scripts`: passed, 0 vulnerabilities.
- Route list: 96 routes.

## Production notes before real deployment

- Install native PHP 8.3+, Composer, Node, MySQL.
- Set a real `.env` with MySQL, mail, queue, cache, and storage settings.
- Change demo admin password immediately.
- Configure web server: Nginx/Apache to `public/`.
- Run `php artisan config:cache`, `route:cache`, and `view:cache` after final env setup.
- Use private storage for uploaded/generated documents.
- Add HTTPS and backup policy.
- Add malware scanning/quarantine if this will accept public document uploads in production.
