# Gap Analysis: Approval, Generasi, dan Download Dokumen

Tanggal audit: 4 Agustus 2026  
Ruang lingkup: alur admin pengajuan layanan, generator PDF, template, penyimpanan dokumen, download warga, dan tampilan menu Pengajuan.

## Ringkasan fakta

Fitur generator bukan belum tersedia. Aplikasi sudah memiliki generator PDF, lima template aktif, 46 field template, tabel dokumen hasil, endpoint generate, dan endpoint download. Kegagalan alur terjadi karena aksi status dan aksi generate berdiri sendiri, sedangkan halaman admin tidak menyediakan form yang memanggil endpoint generate.

Data lokal saat audit memperlihatkan satu pengajuan `REQ-20260804-L1QYT0` berstatus `completed`. Pengajuan itu tidak memiliki `document_template_id`, `letter_number`, atau `generated_document_path`; sumber dokumennya `manual`, dengan dua record PNG di `generated_documents`. Ini konsisten dengan perilaku yang dilaporkan pengguna.

## Evidence matrix

| Kategori | Temuan | Bukti | Dampak |
|---|---|---|---|
| TERBUKTI GAP | Aksi verifikasi/approve hanya memanggil transisi status, tanpa generator. | `app/Http/Controllers/Admin/ServiceRequestController.php:30-38`, `:95-104` | Approval tidak menghasilkan dokumen. |
| TERBUKTI GAP | Endpoint generate ada, tetapi tidak dipanggil oleh halaman detail admin. | Route ada di `routes/web.php:50`; handler ada di `app/Http/Controllers/Admin/ServiceRequestController.php:68-80`; tidak ada form generate di `resources/views/admin/service-requests/show.blade.php:1-40`. | Petugas hanya melihat upload manual dan complete. |
| TERBUKTI GAP | Tes workflow utama justru mengunci alur manual: verify -> gagal complete -> upload -> complete. | `tests/Feature/VillageServiceMvpTest.php:81-123` | Regression suite menganggap perilaku manual sebagai perilaku benar. |
| TERBUKTI GAP | Generate mensyaratkan `letter_number`, tetapi UI detail tidak menyediakan input nomor surat. | `app/Http/Controllers/Admin/ServiceRequestController.php:70-77`; `resources/views/admin/service-requests/show.blade.php:1-40` | Endpoint generate tidak dapat digunakan dari UI. |
| TERBUKTI GAP | Template yang dipilih hanya divalidasi ada, tidak harus aktif dan tidak harus milik jenis layanan pengajuan. | `app/Http/Controllers/Admin/ServiceRequestController.php:70-77` | Dokumen dapat memakai template layanan lain atau template nonaktif. |
| TERBUKTI GAP | Tidak ada konsep template default ketika lebih dari satu template aktif untuk satu layanan. | Schema `document_templates` di `database/migrations/2026_06_28_000000_create_village_service_tables.php:104-114` | Auto-generation tidak punya pemilihan template yang deterministik. |
| TERBUKTI GAP | Halaman status warga tidak menampilkan aksi download meski route download tersedia. | `resources/views/public/status-result.blade.php:1`; `routes/web.php:26-27` | Dokumen yang sudah tersedia tetap tidak bisa diunduh dari alur status normal. |
| TERBUKTI GAP | GET download hanya mengarahkan kembali ke form cek status. | `app/Http/Controllers/DocumentDownloadController.php:12-15` | Tidak ada jalur satu klik dari hasil cek status. |
| TERBUKTI GAP | Download selalu mengirim `Content-Type: application/pdf` dan nama `.pdf`, termasuk untuk dokumen manual PNG/DOCX/video yang saat ini diizinkan. | `app/Http/Controllers/DocumentDownloadController.php:42-45`; jenis upload di `app/Http/Controllers/Admin/ServiceRequestController.php:48-63` | File manual dapat diunduh dengan MIME dan ekstensi salah. |
| TERBUKTI GAP | Syarat completion memeriksa record/path, bukan keberadaan file fisik. | `app/Http/Controllers/Admin/ServiceRequestController.php:88-90` | Pengajuan bisa selesai dengan artefak hilang/orphan. |
| TERBUKTI GAP | Generator menangkap semua exception dari FPDI dan diam-diam membuat PDF fallback generik. | `app/Services/DocumentGenerationService.php:31-63` | Template rusak dapat terlihat “berhasil” dan menerbitkan dokumen yang salah. |
| TERBUKTI GAP | Index Pengajuan berupa kartu berulang, bukan tabel, tanpa filter, indikator kesiapan dokumen, atau aksi per baris. | `resources/views/admin/service-requests/index.blade.php:9-18` | Sulit memindai dan memproses banyak pengajuan. |
| TERBUKTI GAP | Detail Pengajuan berupa tumpukan form/kartu dengan label implementasi “Modal form” dan “Drawer form”; tidak menampilkan data permohonan, berkas, timeline, daftar dokumen, atau aksi kondisional menurut status. | `resources/views/admin/service-requests/show.blade.php:3-39` | Hierarki informasi dan workflow membingungkan. |
| TERBUKTI GAP | Controller index hanya eager-load jenis layanan dan tidak mendukung pencarian/filter/document state. | `app/Http/Controllers/Admin/ServiceRequestController.php:16-20` | UI tabel yang operasional belum didukung query backend. |
| TERVERIFIKASI BEKERJA | Generator PDF menyimpan file, record `generated_documents`, dan memperbarui pointer pada `service_requests`. | `app/Services/DocumentGenerationService.php:65-87` | Fondasi dapat dipakai kembali. |
| TERVERIFIKASI BEKERJA | Seed menyediakan satu template aktif untuk tiap layanan pada data saat audit. | `database/seeders/DatabaseSeeder.php:37-43`; query DB lokal: 5 template aktif, 46 field. | Auto-resolution template dapat dimigrasikan dari data yang sudah ada. |
| TERVERIFIKASI BEKERJA | Proteksi download mencocokkan kode pengajuan dan NIK. | `app/Http/Controllers/DocumentDownloadController.php:17-35` | Kontrol akses dasar sudah tersedia. |
| TERVERIFIKASI BEKERJA | State machine mencegah transisi status yang tidak sah dan menyimpan histori/audit actor. | `app/Models/ServiceRequest.php:35-44`, `:103-153` | Orkestrasi approval sebaiknya memakai guard ini, bukan menggantinya. |
| TERVERIFIKASI BEKERJA | Tes generate + protected download lulus pada audit. | `php artisan test --filter=test_document_template_builder_generation_and_protected_download` -> 1 test, 14 assertion, lulus. | Generator dapat bekerja bila dipanggil eksplisit dengan input benar. |
| TERVERIFIKASI BEKERJA | Tes workflow manual saat ini lulus. | `php artisan test --filter=test_admin_status_workflow_requires_final_document_before_completion` -> 1 test, 11 assertion, lulus. | Masalah adalah kontrak workflow, bukan test failure insidental. |
| BELUM TERBUKTI | Format dan kewenangan penomoran surat resmi desa. | Tidak ada service/config sequence nomor surat; hanya kolom string nullable dan input manual pada endpoint generate. | Tidak aman mengarang nomor otomatis tanpa keputusan bisnis. |
| BELUM TERBUKTI | Apakah “approve” berarti verifikasi awal atau persetujuan final/penerbitan. | Source hanya memakai istilah `verify`, `process`, `complete`; UI memakai Verify/Process/Complete. | Label dan titik generasi perlu disepakati saat implementasi. |

## Root cause

Root cause utama adalah pemisahan tiga command yang tidak terorkestrasi:

1. `verify()`/`process()` hanya mengubah status.
2. `generateDocument()` bekerja sendiri dan memerlukan template + nomor surat.
3. `complete()` hanya memeriksa apakah dokumen sudah ada.

UI hanya mengekspos nomor 1 dan 3 serta upload manual. Karena command nomor 2 tidak punya form dan tidak terhubung ke approval, petugas dipaksa memasukkan dokumen secara manual agar guard completion lolos.

## KOREKSI terhadap dugaan awal

| Dugaan | Koreksi berbasis bukti |
|---|---|
| “Generator dokumen belum dibuat.” | Salah. Generator dan endpoint-nya sudah ada serta test-nya lulus. Yang hilang adalah orkestrasi dan UI pemanggilnya. |
| “Template belum tersedia.” | Salah untuk data lokal yang diaudit. Lima layanan masing-masing memiliki template aktif dan field mapping. |
| “Tidak ada endpoint download.” | Salah. Endpoint ada dan dilindungi kode + NIK, tetapi tidak diekspos pada halaman hasil cek status. |

## Batas audit

- Tidak dilakukan perubahan source, migration, data, queue, atau proses server.
- Tidak dilakukan approval baru pada database produksi/lokal karena permintaan adalah investigasi dan planning.
- Screenshot audit lama mengonfirmasi route admin Pengajuan merender tanpa overflow/error pada empat viewport, tetapi penilaian kerapian terutama berasal dari struktur Blade aktual; screenshot detail pengajuan tidak tersedia di artefak audit lama.
