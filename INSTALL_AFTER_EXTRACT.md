# Install Setelah Extract

## Kebutuhan
- PHP sesuai `composer.json`
- Composer
- Node.js/NPM hanya jika ingin build aset tambahan
- Database SQLite atau MySQL sesuai konfigurasi server

## Langkah native PHP/Composer
1. Copy `.env.example` menjadi `.env`, lalu sesuaikan database/app URL.
2. Jalankan `composer install --no-dev --optimize-autoloader`.
3. Jalankan `php artisan key:generate` jika APP_KEY belum diisi.
4. Restore database dari `database-export/village_service_dump.sql` atau jalankan `php artisan migrate --seed --force`.
5. Pastikan `storage/` dan `bootstrap/cache/` writable oleh user web server.
6. Untuk produksi: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.

## Update template surat
- Semua jenis layanan memiliki Template Resmi siap pakai.
- Template berisi kop surat Desa gringo, logo Kabupaten Ponorogo, nomor surat, tabel data warga, isi surat, tanda tangan, dan footer verifikasi.
- Jika data alamat/pejabat desa perlu disesuaikan, edit dari menu Profil Desa.
- `.env` sengaja tidak disertakan dalam ZIP ini.
