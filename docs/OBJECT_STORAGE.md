# Object storage privat

Aplikasi siap memakai object storage S3-compatible untuk seluruh file bisnis yang direferensikan oleh database. Database tetap menyimpan object key relatif, misalnya `service-requests/REQ-1/ktp.pdf`, bukan binary file atau URL provider. Karena itu perpindahan provider tidak membutuhkan perubahan schema maupun data path.

## Cakupan

Disk `private` dipakai oleh:

- berkas persyaratan pengajuan;
- template PDF;
- dokumen hasil generate dan upload manual;
- file sementara import CSV/XLSX penduduk;
- archive backup.

Download tetap melewati controller aplikasi dan pemeriksaan hak akses. Bucket tidak perlu dibuat public.

File runtime WhatsApp (session, QR, PID, dan log) tetap berada di filesystem server karena dipakai langsung oleh proses Node.js dan bukan file bisnis yang direferensikan database.

## Konfigurasi umum S3-compatible

Isi environment production:

```dotenv
PRIVATE_STORAGE_DRIVER=s3
OBJECT_STORAGE_ACCESS_KEY_ID=your-access-key
OBJECT_STORAGE_SECRET_ACCESS_KEY=your-secret-key
OBJECT_STORAGE_REGION=your-region
OBJECT_STORAGE_BUCKET=village-service-private
OBJECT_STORAGE_ENDPOINT=https://your-s3-endpoint
OBJECT_STORAGE_URL=
OBJECT_STORAGE_PREFIX=
OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT=true
```

Credential hanya boleh tersedia pada server. Jangan menaruh access key di frontend, repository, log, atau database.

Setelah mengganti environment:

```bash
php artisan config:clear
php artisan config:cache
```

Endpoint `/healthz` menguji write, read, dan delete pada disk aktif tanpa meninggalkan object health-check.

## Supabase Storage

1. Buat bucket private, misalnya `village-service-private`.
2. Aktifkan koneksi S3 di **Storage > Configuration > S3**.
3. Buat S3 access key dan secret untuk penggunaan server-side.
4. Salin region dan direct storage endpoint dari dashboard. Bentuk endpoint cloud saat ini adalah:

```text
https://<project-ref>.storage.supabase.co/storage/v1/s3
```

5. Gunakan `OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT=true`.

Konfigurasi aplikasi sengaja tidak mengirim ACL dan optional checksum headers karena endpoint S3 Supabase tidak mendukung header tersebut. Status private/public ditentukan pada bucket Supabase.

## Migrasi file lokal

Siapkan credential object storage tetapi pertahankan sementara:

```dotenv
PRIVATE_STORAGE_DRIVER=local
```

Lihat rencana salin tanpa menulis object:

```bash
php artisan storage:migrate-private --dry-run
```

Salin seluruh file dengan object key yang sama:

```bash
php artisan storage:migrate-private
```

Command bersifat idempotent: object yang sudah ada dilewati. Gunakan `--force` hanya jika memang ingin menimpa object tujuan. File sumber tidak dihapus, sehingga rollback tetap aman.

Setelah migrasi selesai, aktifkan object storage:

```dotenv
PRIVATE_STORAGE_DRIVER=s3
```

Kemudian jalankan:

```bash
php artisan config:clear
php artisan config:cache
php artisan storage:migrate-private --from=object --to=private-local --dry-run
```

Command terakhir hanya audit arah balik. Verifikasi aplikasi melalui `/healthz`, preview/download berkas, generate dokumen, import penduduk, dan `php artisan backup:run`.

## Rollback

Karena migrasi tidak menghapus sumber, rollback cukup dengan:

```dotenv
PRIVATE_STORAGE_DRIVER=local
```

Lalu jalankan `php artisan config:clear && php artisan config:cache`. Jika ada file baru yang terlanjur masuk ke object storage, salin kembali terlebih dahulu:

```bash
php artisan storage:migrate-private --from=object --to=private-local
```

Supabase Storage tidak menyediakan S3 object versioning. Penghapusan object bersifat permanen, jadi pertahankan backup terpisah dan batasi siapa yang memiliki S3 access key.

Rujukan resmi:

- [Laravel filesystem dan S3-compatible storage](https://laravel.com/framework/docs/filesystem)
- [Supabase S3 authentication](https://supabase.com/docs/guides/storage/s3/authentication)
- [Supabase S3 API compatibility](https://supabase.com/docs/guides/storage/s3/compatibility)
