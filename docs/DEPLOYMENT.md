# Deployment tanpa Docker

User menyatakan aplikasi tidak akan memakai Docker, jadi setup produksi diarahkan ke server PHP/Nginx/MySQL biasa.

## Requirement server

- PHP 8.3+ dengan extension umum Laravel: mbstring, pdo, pdo_mysql/sqlite, tokenizer, xml, ctype, json, fileinfo, zip.
- Composer.
- Node.js + npm untuk build asset dan `whatsapp-web.js` bridge.
- MySQL/MariaDB untuk production.
- Redis disarankan untuk queue/cache.
- Nginx/Apache mengarah ke folder `public/`.

## Setup aplikasi

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Queue dan scheduler

Jalankan queue worker dengan supervisor/systemd:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Cron scheduler:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## WhatsApp bridge tanpa Docker

Di admin, buka:

```text
/admin/whatsapp
```

Klik **Mulai Pairing / Tampilkan QR** agar aplikasi menjalankan bridge `whatsapp-web.js` dan menampilkan QR tanpa perlu terminal.

Syarat agar tombol ini bekerja:

- server mengizinkan PHP menjalankan `shell_exec`,
- command `npm` tersedia untuk user web server,
- folder `storage/app/private/whatsapp` dan `storage/logs` writable.

Jika ingin menjalankan manual/process manager, command-nya:

```bash
export WHATSAPP_BRIDGE_TOKEN=[REDACTED]
export WA_BRIDGE_PORT=3100
export WA_BRIDGE_STORAGE=storage/app/private/whatsapp
npm run wa:bridge
```

Jalankan bridge dengan PM2/systemd/supervisor jika ingin selalu hidup setelah restart.

## Backup

- Gunakan `php artisan backup:run` untuk backup app/private storage dan database SQLite jika dipakai.
- Untuk MySQL production, pastikan `mysqldump` tersedia agar `database/mysql.sql` masuk ke archive backup.
- Backup `storage/app/private` karena berisi dokumen warga dan session WhatsApp.

## Security checklist

- Ganti semua credential demo.
- `APP_ENV=production`, `APP_DEBUG=false`.
- HTTPS aktif.
- Web server hanya expose folder `public/`.
- Permission storage/cache writable oleh user web server.
- Aktifkan scanner upload jika ClamAV tersedia.
