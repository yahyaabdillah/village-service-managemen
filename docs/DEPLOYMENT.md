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

## WhatsApp bridge

Bridge WhatsApp (Baileys) sekarang deploy terpisah dari aplikasi Laravel ini — lihat branch `service-wa` untuk kode dan panduan deploy-nya (biasanya di VPS, dijalankan via PM2).

Di `.env` Laravel, arahkan ke bridge tersebut:

```env
WHATSAPP_BRIDGE_URL=http://IP-VPS:3555
WHATSAPP_BRIDGE_TOKEN=[REDACTED]
```

Buka `/admin/whatsapp` untuk melihat status koneksi dan scan QR — halaman ini fetch langsung dari bridge lewat HTTP, tidak perlu proses apa pun dijalankan dari Laravel/PHP.

## Backup

- Gunakan `php artisan backup:run` untuk backup private storage aktif dan database SQLite jika dipakai.
- Untuk MySQL production, pastikan `mysqldump` tersedia agar `database/mysql.sql` masuk ke archive backup.
- Untuk object storage/Supabase, ikuti `docs/OBJECT_STORAGE.md` dan tetap backup session WhatsApp lokal secara terpisah.

## Security checklist

- Ganti semua credential demo.
- `APP_ENV=production`, `APP_DEBUG=false`.
- HTTPS aktif.
- Web server hanya expose folder `public/`.
- Permission storage/cache writable oleh user web server.
- Aktifkan scanner upload jika ClamAV tersedia.
