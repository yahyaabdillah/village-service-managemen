# Plan: Perbaikan Dokumen, Regenerasi, dan Mapping Variable

> Plan ini telah dikonsolidasikan dan diperluas dengan design system, alur end-to-end, state machine, task list, gap coverage, dan QA checklist di [Master Plan Document System](./master-plan-document-system-design-flow-task-qa.md). Dokumen ini tetap menjadi catatan audit teknis rinci dan kontrak awal mapping.

Tanggal: 4 Agustus 2026  
Status: planning only — belum ada source code atau data yang diubah.

## Target akhir

1. Pengajuan baru menghasilkan PDF final yang valid dan berisi data form warga.
2. Pengajuan lama yang selesai lewat upload manual dapat digenerate ulang dari template tanpa mengubah histori status.
3. Download mengirim MIME, ekstensi, dan nama file yang sesuai isi sebenarnya.
4. Builder hanya menawarkan variable yang benar-benar didukung dan menunjukkan sumber datanya.
5. Variable salah, kosong, atau tidak dikenal tidak lagi gagal diam-diam.

## Temuan terverifikasi

### Mapping template Domisili yang sudah benar

| Tampilan/form | Penyimpanan | Variable generator | Field template #1 | Status |
|---|---|---|---|---|
| Nama Pemohon | `service_requests.applicant_name` | `applicant_name` | field 2 | Terhubung |
| NIK | `service_requests.nik` | `nik` | field 3 | Terhubung |
| Alamat | `service_requests.address` | `address` | field 4 | Terhubung |
| RT dan RW | `service_requests.rt`, `rw` | `rt_rw` | field 5 | Terhubung |
| Nomor HP | `service_requests.phone` | `phone` | field 6 | Terhubung |
| Keperluan dinamis | `service_request_field_values[field_key=keperluan]` | `keperluan` | field 7 | Terhubung |
| Nomor surat dari approval | `service_requests.letter_number` | `letter_number` | field 1 | Terhubung bila diisi |
| Tanggal surat | dihitung saat generate | `letter_date` | field 8 | Terhubung |

Bukti source: `resources/views/public/request-form.blade.php:23-76`, `app/Http/Controllers/PublicController.php:51-123`, dan `app/Services/DocumentGenerationService.php:90-126`.

### Gap variable builder

- Builder menawarkan `current_date`, tetapi generator tidak mempunyai key tersebut; generator hanya mempunyai `letter_date`/`tanggal_surat`.
- Builder menawarkan `custom_text`, tetapi schema `template_fields` tidak memiliki kolom untuk menyimpan isi teks kustom dan generator tidak mempunyai nilainya.
- `variable_key` berupa input bebas tanpa validasi registry. Typo atau key tidak dikenal disimpan dan saat generate berubah menjadi string kosong.
- Template #1 saat ini mempunyai enam field `custom_text` tambahan (id 41-46); empat berada pada posisi identik. Semuanya tidak mempunyai sumber nilai dan akan kosong pada PDF.
- Palette builder tidak menampilkan seluruh variable yang sebenarnya tersedia seperti `phone`, `rt_rw`, `keperluan`, `letter_date`, signer, dan field dinamis layanan.

Bukti source: `resources/views/admin/document-templates/builder.blade.php:38-45`, `resources/js/document-builder.js:65-121`, schema `template_fields` pada migration utama baris 115-129, serta data lokal `template_fields` template id 1.

## Kontrak fitur mapping yang dituju

Mapping dibuat ketika admin menambah atau memilih field di Template Builder. Admin tidak lagi mengetik `variable_key` bebas, tetapi memilih sumber data dan format melalui panel properti.

### Jenis sumber

| Sumber | Contoh | Keterangan |
|---|---|---|
| Data Pemohon | nama, NIK, HP, alamat, RT/RW | Berasal dari kolom inti `service_requests`. |
| Form Layanan | keperluan, jenis usaha, tanggal keperluan | Berasal dari `service_type_fields` dan nilai pengajuan. Pilihan otomatis mengikuti layanan template. |
| Data Surat | nomor surat, kode pengajuan, nama layanan | Berasal dari workflow pengajuan. |
| Profil Desa | nama desa, kecamatan, kepala desa, penandatangan | Berasal dari village profile aktif. |
| Tanggal/Waktu Sistem | tanggal, hari, bulan, tahun, jam | Berasal dari waktu generate/submitted/completed dengan format yang dipilih. |
| Teks Tetap | “Nomor:”, “Kepala Desa”, tanda `/` | Disimpan sebagai literal per field/segmen. |

### Mode mapping pada panel builder

1. **Data tunggal** — pilih satu sumber, lalu prefix, suffix, format, dan fallback.
2. **Teks tetap** — isi teks yang selalu dicetak.
3. **Gabungan** — susun beberapa segmen berurutan.

Contoh gabungan:

```text
Nomor: 470/001/DS/2026
```

Disimpan sebagai kontrak terstruktur, bukan expression bebas:

```json
{
  "version": 1,
  "segments": [
    { "type": "literal", "value": "Nomor: " },
    { "type": "request", "key": "letter_number" },
    { "type": "literal", "value": "/" },
    { "type": "datetime", "key": "generated_at", "format": "year" }
  ],
  "fallback": "-"
}
```

`prefix` dan `suffix` pada mode sederhana hanya shortcut UI yang dinormalisasi menjadi segmen literal sebelum/sesudah sumber utama.

### Format tanggal/waktu yang diizinkan

Format memakai pilihan bernama, bukan format string/PHP bebas:

| Format | Contoh |
|---|---|
| `date_long_id` | 04 Agustus 2026 |
| `date_short` | 04/08/2026 |
| `day` | 04 |
| `month_name` | Agustus |
| `month_number` | 08 |
| `year` | 2026 |
| `time` | 20:46 |
| `datetime_long_id` | 04 Agustus 2026, 20:46 |

### Penyimpanan dan kompatibilitas

- Tambahkan kolom JSON nullable `mapping_config` pada `template_fields`.
- Pertahankan `variable_key` selama masa kompatibilitas; field lama dinormalisasi otomatis menjadi satu segmen berdasarkan key tersebut.
- Jangan menghapus atau mengubah arti key lama secara langsung.
- Semua segment/type/key/format divalidasi server-side berdasarkan registry layanan template.
- Generator hanya menerima mapping hasil normalisasi; key tidak dikenal menjadi validation error yang terlihat, bukan string kosong.

### UX Template Builder

Saat field dipilih, panel kanan berisi:

1. Label tampilan.
2. Mode mapping.
3. Sumber data dan field.
4. Prefix dan suffix.
5. Format tanggal/waktu bila relevan.
6. Nilai fallback bila data kosong.
7. Preview hasil memakai sample data atau pengajuan yang dipilih.
8. Status validasi: `Terhubung`, `Data form belum tersedia`, atau `Mapping tidak valid`.

Builder juga menyediakan tab **Mapping Form** berupa tabel ringkas:

| Placeholder dokumen | Sumber | Field form/data | Format | Preview | Status |
|---|---|---|---|---|---|
| Nama Pemohon | Data Pemohon | `applicant_name` | Teks | Budi Santoso | Terhubung |
| Keperluan | Form Layanan | `keperluan` | Teks | Administrasi bank | Terhubung |
| Tanggal Surat | Tanggal Sistem | `generated_at` | 04 Agustus 2026 | 04 Agustus 2026 | Terhubung |

Template tidak dapat ditandai siap dipakai jika masih memiliki mapping invalid. Warning diperbolehkan untuk field opsional yang memakai fallback.

## Kontrak fitur “Buat Variabel”

Benar bahwa membuat variable berbeda dari menempatkan atau memetakan variable. Builder memerlukan tiga lapisan yang terlihat jelas:

```text
1. Buat Variable  -> definisikan data yang akan diminta/disediakan
2. Mapping        -> tentukan sumber, prefix/suffix, format, atau gabungan
3. Tempatkan      -> atur posisi dan gaya hasil mapping pada PDF
```

### Reuse struktur yang sudah ada

Variable buatan admin yang harus diisi warga tidak memerlukan tabel variable baru. Gunakan `service_type_fields` yang sudah mempunyai:

- `label` dan `field_key`
- `field_type`
- `options`
- `is_required`
- `placeholder` dan `help_text`
- `sort_order`

Struktur ini sudah otomatis dirender pada form warga dan jawabannya sudah disimpan ke `service_request_field_values`. Yang belum ada adalah akses terintegrasi dari Template Builder dan lifecycle variable yang aman.

### Dialog “Buat Variable Form”

| Pengaturan | Contoh |
|---|---|
| Nama field | Nama Usaha |
| Key | `nama_usaha` (dibuat otomatis, dapat dikoreksi sebelum disimpan) |
| Jenis input | Text, textarea, number, date, email, select |
| Wajib diisi | Ya/Tidak |
| Pilihan | Mikro, Kecil, Menengah — hanya untuk select |
| Placeholder | Contoh: Toko Maju Jaya |
| Petunjuk | Masukkan nama usaha sesuai dokumen |
| Urutan form | Setelah field Keperluan |

Setelah disimpan:

1. Variable langsung muncul di palette template layanan tersebut.
2. Field langsung muncul pada form pengajuan warga untuk layanan tersebut.
3. Jawaban warga tersimpan memakai `field_key` yang sama.
4. Generator menemukan nilai melalui registry dan mapping.

### Kategori variable

- **Built-in/System**: disediakan aplikasi, read-only, tidak dapat dibuat/dihapus admin.
- **Form Layanan**: dibuat admin dan diisi warga; scoped pada satu layanan.
- **Teks/Komposisi Template**: dibuat melalui mapping literal/composite, tidak menambah pertanyaan pada form warga.

### Guard lifecycle

- `field_key` harus unik per layanan dan hanya memakai `snake_case`.
- Setelah variable sudah mempunyai jawaban atau dipakai template, key tidak boleh diganti langsung.
- Variable yang dipakai template tidak boleh dihapus; UI harus menunjukkan template pemakainya dan menawarkan nonaktifkan/migrasikan mapping.
- Label, petunjuk, pilihan, dan urutan tetap dapat diperbarui tanpa memutus data lama.
- Variable form layanan A tidak dapat dipakai template layanan B.
- Create/update/delete divalidasi dengan permission `manage service fields`.

### Gap dokumen pengajuan #1

- `REQ-20260804-L1QYT0` berstatus `completed`, tetapi `document_source=manual`.
- `document_template_id`, `letter_number`, dan `generated_document_path` kosong.
- Pointer final mengarah ke PNG `Screenshot (1).png`.
- Controller download memaksa file tersebut menjadi `application/pdf` dengan nama `.pdf`.
- Reproduksi Playwright memperoleh nama `REQ-20260804-L1QYT0.pdf`, tetapi magic bytes-nya `89 50 4E 47 ... IHDR` (PNG), sehingga PDF viewer tidak dapat memuatnya.
- Tombol publish baru hanya muncul untuk status `verified`/`processing`; pengajuan lama berstatus `completed/manual` belum mempunyai aksi regenerate.

Bukti source: `app/Http/Controllers/DocumentDownloadController.php:32-45` dan `resources/views/admin/service-requests/show.blade.php:46-75`.

## Rencana implementasi

### Phase 1 — Kunci bug dengan test

#### Task 1: Regression test MIME download

Deskripsi: buat test yang menyimpan dokumen manual PNG lalu mengunduhnya melalui endpoint yang sama.

Acceptance criteria:

- [ ] Test awal gagal karena response masih `application/pdf` dan filename `.pdf`.
- [ ] Test mengharapkan MIME `image/png` dan filename asli/aman berekstensi `.png`.
- [ ] Test PDF generated tetap mengharapkan `%PDF` dan `application/pdf`.

Verification: jalankan hanya dua test download dan pastikan failure awal berasal dari header/filename yang salah.  
Dependencies: none.  
Files likely touched: `tests/Feature/ProductionReadinessTest.php`.  
Scope: S.

#### Task 2: Contract test mapping variable

Deskripsi: test nilai input form lengkap terhadap array variable generator, termasuk field dinamis.

Acceptance criteria:

- [ ] Nama, NIK, alamat, RT/RW, HP, keperluan, nomor surat, dan tanggal menghasilkan nilai tepat.
- [ ] `current_date` mempunyai alias yang valid atau dihapus dari builder; tidak boleh blank diam-diam.
- [ ] Variable tidak dikenal terdeteksi sebagai error validasi template.

Verification: unit test `DocumentGenerationService::variables()` dan validator template.  
Dependencies: none.  
Files likely touched: test unit/feature document generation.  
Scope: S.

### Checkpoint A

- [ ] Kedua kelompok test gagal karena bug yang benar, bukan setup test.
- [ ] Belum ada perubahan produksi sebelum RED terverifikasi.

### Phase 2 — Benarkan kontrak file download

#### Task 3: Response download berdasarkan metadata artefak

Deskripsi: resolve `GeneratedDocument` yang path-nya benar, lalu gunakan MIME dan nama file dari metadata/finfo. Jangan menebak semua file sebagai PDF.

Acceptance criteria:

- [ ] Generated PDF diunduh sebagai `.pdf` dengan `application/pdf`.
- [ ] Manual PNG/DOCX memakai MIME dan ekstensi aktual.
- [ ] Record ada tetapi file fisik hilang menghasilkan 404 terkontrol.

Verification: test Task 1 hijau; tes unauthorized download tetap hijau.  
Dependencies: Task 1.  
Files likely touched: `DocumentDownloadController.php`, test.  
Scope: S.

### Phase 3 — Registry dan pointing variable

#### Task 4: Satu registry variable sebagai sumber kebenaran

Deskripsi: centralize type, key, label, sumber, formatter yang diizinkan, dan sample value. Registry menggabungkan variable inti dengan `service_type_fields` milik layanan.

Registry inti minimal:

- `request_code`, `letter_number`, `service_name`
- `applicant_name`, `nik`, `phone`, `address`, `hamlet`, `rt`, `rw`, `rt_rw`
- `letter_date`, `current_date` sebagai alias kompatibilitas
- data desa dan penandatangan
- seluruh `field_key` dinamis, termasuk `keperluan`

Acceptance criteria:

- [ ] Generator dan builder membaca registry yang sama.
- [ ] Setiap key menunjukkan sumber: Data Pemohon, Alamat, Data Tambahan, Sistem, atau Profil Desa.
- [ ] Type/key/format lintas layanan atau typo ditolak saat save.

Verification: unit test registry untuk Domisili dan satu layanan lain.  
Dependencies: Task 2.  
Files likely touched: service registry baru atau method terpusat pada generator, controller template, tests.  
Scope: M.

#### Task 5: Tambahkan fitur “Buat Variable Form” di Builder

Deskripsi: tambah dialog/modal builder yang membuat `service_type_fields` milik layanan template melalui endpoint terdedikasi, lalu memperbarui palette tanpa reload penuh.

Acceptance criteria:

- [ ] Admin dapat membuat variable text/textarea/number/date/email/select.
- [ ] Variable muncul pada form warga dan palette template layanan yang sama.
- [ ] Key duplikat, key invalid, dan opsi select kosong ditolak dengan pesan jelas.
- [ ] Built-in variable dibedakan secara visual dari variable buatan admin.

Verification: feature test create variable dari builder -> render public form -> submit warga -> nilai tersimpan dengan key yang sama.  
Dependencies: Task 4.  
Files likely touched: route/controller field layanan, builder Blade/JS, tests. Pisahkan endpoint dan UI bila melebihi lima file.  
Scope: M.

#### Task 6: Tambahkan kontrak `mapping_config` yang kompatibel

Deskripsi: migration menambah JSON nullable `mapping_config`; normalizer menerjemahkan `variable_key` lama menjadi mapping version 1 tanpa mengubah perilakunya.

Acceptance criteria:

- [ ] Seluruh delapan default field Domisili dapat dinormalisasi tanpa kehilangan mapping.
- [ ] Mapping baru mendukung literal, request, form field, village profile, dan datetime segments.
- [ ] Payload invalid menghasilkan validation error konsisten.

Verification: migration/normalizer test untuk legacy field, simple mapping, composite mapping, dan invalid mapping.  
Dependencies: Task 4.  
Files likely touched: migration baru, cast model, mapping normalizer/validator, tests.  
Scope: M.

#### Task 7: Builder memakai mapping tervalidasi

Deskripsi: ganti input bebas `variable_key` dengan editor mode Data Tunggal/Teks Tetap/Gabungan. Palette dan opsi sumber dirender dari registry layanan, bukan daftar hard-coded.

Acceptance criteria:

- [ ] Semua field form Domisili dapat dipilih dengan label dan sumber yang jelas.
- [ ] Prefix, suffix, fallback, tanggal, bulan, tahun, dan jam dapat disetel tanpa expression bebas.
- [ ] Mapping tidak tersedia tidak dapat disimpan melalui UI maupun request langsung.
- [ ] Tab Mapping Form menampilkan status dan preview setiap field.

Verification: feature test store/update field dan browser test builder.  
Dependencies: Tasks 4-6.  
Files likely touched: `DocumentTemplateController.php`, builder Blade, `document-builder.js`, tests.  
Scope: M.

#### Task 8: Resolver mapping menghasilkan nilai final

Deskripsi: generator menyusun nilai setiap field dari segments yang tervalidasi. Prefix/suffix adalah literal segments; formatter tanggal memakai allowlist; fallback dipakai hanya ketika hasil sumber kosong.

Acceptance criteria:

- [ ] Dua teks tetap dapat mempunyai isi berbeda.
- [ ] Mapping gabungan mempertahankan urutan segmen.
- [ ] Mapping lama dan baru menghasilkan nilai yang sama untuk field default.
- [ ] Generator menolak mapping invalid sebelum membuat file.

Verification: unit test resolver untuk setiap source type, formatter, prefix/suffix, fallback, dan composite segments.  
Dependencies: Tasks 6-7.  
Files likely touched: mapping resolver, `DocumentGenerationService.php`, tests.  
Scope: M.

### Checkpoint B

- [ ] Builder hanya menyimpan variable valid.
- [ ] Semua variable template #1 mempunyai sumber nilai atau static text.
- [ ] Preview builder membedakan placeholder variable dari isi static text.

### Phase 4 — Regenerate pengajuan lama

#### Task 9: Aksi “Generate Ulang dari Template”

Deskripsi: sediakan endpoint berizin untuk request `completed/manual` atau generated lama. Input mencakup nomor surat; template dipilih aktif berdasarkan layanan. Status tetap `completed`, histori dokumen baru ditambahkan, dan pointer final berpindah hanya setelah PDF valid berhasil dibuat.

Acceptance criteria:

- [ ] Pengajuan #1 dapat menghasilkan PDF tanpa reset status atau menghapus dokumen manual lama.
- [ ] Jika generate gagal, pointer file lama tetap aktif.
- [ ] Double submit tidak merusak pointer; setiap regenerate tercatat dengan actor/time.

Verification: feature test manual -> regenerate -> generated pointer, serta failure rollback test.  
Dependencies: Tasks 3 dan 8.  
Files likely touched: route, `ServiceRequestController.php`, detail Blade, tests.  
Scope: M.

#### Task 10: Validasi output generator secara eksplisit

Deskripsi: jangan menganggap catch-all fallback sebagai sukses. Output harus file PDF valid; error template ditampilkan pada admin dan tidak mengganti dokumen final.

Acceptance criteria:

- [ ] Template hilang/rusak tidak membuat record sukses.
- [ ] Output dimulai `%PDF`, ukurannya > 0, dan file ada.
- [ ] Error dapat ditelusuri melalui log tanpa menampilkan data sensitif.

Verification: test template valid, corrupt, missing, dan storage failure.  
Dependencies: Task 9.  
Files likely touched: `DocumentGenerationService.php`, controller, tests.  
Scope: S/M.

### Phase 5 — Verifikasi isi PDF end-to-end

#### Task 11: Test PDF berdasarkan teks yang dirender

Deskripsi: buat pengajuan Domisili dengan nilai unik, publish/regenerate, download file, lalu ekstrak teks menggunakan `pdfjs-dist` yang sudah terpasang.

Acceptance criteria:

- [ ] PDF dapat dibuka oleh PDF.js tanpa error.
- [ ] Teks mengandung nomor surat, nama, NIK, alamat, RT/RW, HP, keperluan, dan tanggal.
- [ ] PDF tidak mengandung placeholder literal `{{ ... }}`.

Verification: automated E2E + browser download pada `localhost:8000`.  
Dependencies: Tasks 4-10.  
Files likely touched: test E2E/feature saja.  
Scope: S.

#### Task 12: Repair data lokal template #1 dan request #1

Deskripsi: setelah backup DB, bersihkan enam `custom_text` legacy yang tidak mempunyai isi, lalu jalankan regenerate request #1 dengan nomor surat yang disetujui pengguna/admin.

Acceptance criteria:

- [ ] Backup DB tersedia sebelum perubahan data.
- [ ] Template #1 kembali memiliki delapan field fungsional kecuali custom text memang diisi secara sengaja.
- [ ] Request #1 menunjuk ke generated PDF valid; dokumen manual lama tetap ada sebagai histori.

Verification: query DB sebelum/sesudah, magic bytes `%PDF`, parse PDF.js, dan download browser.  
Dependencies: Tasks 8-11.  
Files/data touched: backup SQLite, rows template 1/request 1 melalui command repair yang terukur.  
Scope: S.

## Checkpoint final

- [ ] Test spesifik RED -> GREEN terdokumentasi.
- [ ] `php artisan test` seluruh suite lulus.
- [ ] `npm run build` lulus.
- [ ] Browser admin request #1 dapat download PDF yang terbuka.
- [ ] Teks PDF cocok dengan nilai database request #1.
- [ ] Cek status warga dapat mengunduh file yang sama.
- [ ] Console browser bersih dan response memiliki MIME/filename benar.

## Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Regenerate mengganti file final ketika generate gagal | Tinggi | Generate + validasi dulu, update pointer terakhir. |
| Field builder typo menjadi kosong | Tinggi | Registry + server-side allowlist. |
| Custom text lama terhapus tanpa sengaja | Sedang | Backup dan cleanup hanya field legacy kosong yang teridentifikasi. |
| Request completed kehilangan histori | Tinggi | Jangan reset status/hapus manual document; tambah generated record baru. |
| Test hanya memeriksa header PDF | Tinggi | Parse isi dengan PDF.js dan assert nilai form. |

## Input yang dibutuhkan saat eksekusi data repair

Nomor surat resmi untuk request #1. Kode tidak boleh mengarang nomor administratif. Implementasi source dan test dapat dikerjakan lebih dahulu; regenerate data request #1 dilakukan setelah nomor diberikan.
