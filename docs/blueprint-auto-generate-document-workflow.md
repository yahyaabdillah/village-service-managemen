# Blueprint: Approval Otomatis Menghasilkan Dokumen

## Sasaran

Petugas dapat menyelesaikan pengajuan dari satu workflow yang jelas. Pada persetujuan final, sistem memilih template layanan yang benar, memvalidasi nomor surat, menghasilkan PDF, mencatat artefak, mengubah status, dan menampilkan aksi download. Upload manual tetap tersedia sebagai fallback berizin, bukan jalur utama.

Menu Pengajuan diubah menjadi tabel operasional dengan filter dan aksi per baris. Detail pengajuan menjadi workspace review dengan data pemohon, berkas, timeline, status dokumen, dan action bar yang hanya menampilkan aksi sah untuk status saat ini.

## Keputusan arsitektur yang direkomendasikan

1. Pisahkan istilah bisnis dengan tegas:
   - `Verifikasi Berkas`: `submitted -> verified`, belum menerbitkan dokumen.
   - `Setujui & Terbitkan`: dari `verified`/`processing`, generate PDF dan menjadi `completed` dalam satu command.
   - Jika pemilik aplikasi memang menganggap verifikasi awal sebagai approval final, command yang sama dapat dipasang pada tahap `submitted`; keputusan ini harus dikonfirmasi sebelum coding.
2. Persetujuan final wajib atomic dari sudut pandang bisnis: dokumen valid harus ada sebelum status `completed`. Bila generate gagal, status tidak berubah dan error operasional terlihat.
3. Template dipilih server-side dari template default aktif milik `service_type_id` pengajuan. Client tidak boleh mengirim template lintas layanan.
4. Nomor surat tetap input wajib pada dialog approval untuk versi pertama. Auto-numbering tidak dibuat sampai format, sequence, reset tahunan, dan aturan concurrency disepakati.
5. File hasil selalu PDF untuk jalur otomatis. Upload manual menjadi aksi sekunder dengan MIME dan filename asli yang benar.
6. Notifikasi status dikirim setelah transaksi commit agar warga tidak menerima pesan “selesai” ketika penyimpanan dokumen gagal.

## Target flow

```text
Warga mengajukan
    -> submitted
Petugas membuka tabel Pengajuan
    -> review data + berkas
    -> Verifikasi Berkas
    -> verified
Petugas memilih Setujui & Terbitkan
    -> isi/konfirmasi nomor surat
    -> server lock pengajuan
    -> resolve template default aktif untuk layanan
    -> generate PDF ke private storage
    -> verifikasi file ada, MIME PDF, ukuran > 0
    -> simpan GeneratedDocument + pointer ServiceRequest
    -> transition completed + histori actor
    -> commit
    -> notifikasi setelah commit
Admin melihat Preview/Download
Warga cek status
    -> melihat Dokumen siap
    -> Download
```

## Kontrak UI tabel Pengajuan

### Kolom

| Kolom | Isi |
|---|---|
| Pengajuan | kode, waktu masuk |
| Pemohon | nama, NIK dimasking |
| Layanan | nama layanan |
| Status | badge status bisnis |
| Dokumen | `Belum dibuat`, `Siap (otomatis)`, `Manual`, atau `Bermasalah` |
| Diperbarui | relative time + tanggal penuh pada tooltip |
| Aksi | tombol utama kondisional + menu aksi lain |

### Filter

- Search kode pengajuan, nama, atau NIK.
- Status.
- Jenis layanan.
- Kesiapan dokumen.
- Rentang tanggal masuk.
- Tombol reset dan empty state yang menjelaskan filter aktif.

### Aksi per status

| Kondisi | Aksi utama | Aksi tambahan |
|---|---|---|
| `submitted` | Review / Verifikasi Berkas | Tolak |
| `verified`/`processing`, dokumen belum ada | Setujui & Terbitkan | Detail, Tolak, Upload manual (fallback) |
| Dokumen otomatis sudah ada tetapi belum final | Preview Dokumen | Terbitkan, Generate ulang dengan konfirmasi |
| `completed` + file tersedia | Download | Detail, histori dokumen |
| Record ada tetapi file hilang | Perbaiki Dokumen | Detail error, generate ulang |
| `rejected`/`cancelled` | Lihat Detail | Tidak ada aksi mutasi default |

Pada mobile, tabel dibungkus overflow container dengan kolom prioritas Pengajuan, Status, Dokumen, Aksi; kolom lain dapat disederhanakan dalam dua baris cell, bukan mengubah seluruh daftar menjadi kartu acak.

## Kontrak halaman detail

- Header ringkas: kode, status, layanan, waktu masuk, back link.
- Kolom utama: identitas pemohon, alamat, jawaban field dinamis, dan daftar berkas persyaratan dengan preview/download aman.
- Kolom samping: timeline status dan panel Dokumen Final.
- Panel dokumen menampilkan source, template, nomor surat, generated time, actor, ukuran, serta Preview/Download.
- Action bar sticky hanya menampilkan aksi yang valid menurut state machine dan permission.
- Hapus label debug/pattern seperti “Modal form” dan “Drawer form”.
- Dialog `Setujui & Terbitkan` menampilkan template terpilih (read-only), input nomor surat, ringkasan pemohon, dan konsekuensi bahwa dokumen akan langsung tersedia.
- Upload manual dipindah ke menu sekunder dengan peringatan bahwa ini fallback.

## Kontrak download warga

- Setelah kombinasi kode + NIK berhasil pada cek status, simpan otorisasi singkat di session untuk pengajuan tersebut.
- Bila status `completed` dan file fisik valid, hasil status menampilkan card `Dokumen siap diunduh` dan tombol Download.
- Download harus memakai metadata dokumen (`original_file_name`, MIME aktual) atau `Storage::download`, bukan selalu memaksa PDF.
- Jangan mengizinkan download hanya karena record/path string ada; cek keberadaan file.
- Catat download authorized/denied seperti sekarang dan tambahkan document id/source.

## Rencana implementasi

### Phase 1 — Kontrak dan fondasi data

#### Task 1: Tetapkan semantic approval dan penomoran

Deskripsi: konfirmasi apakah approval final terjadi setelah `verified` atau langsung dari `submitted`, serta tetapkan bahwa versi pertama memakai nomor surat manual pada dialog approval.

Acceptance criteria:

- [ ] Nama aksi dan transisi target disepakati.
- [ ] Format/otoritas nomor surat dicatat; auto-numbering dinyatakan in-scope atau out-of-scope.
- [ ] Pengaduan masyarakat diputuskan apakah memang menghasilkan dokumen PDF.

Verification: review keputusan dengan pemilik proses desa.  
Dependencies: none.  
Estimated scope: XS, dokumentasi saja.

#### Task 2: Tambahkan konsep template default

Deskripsi: migration menambah `is_default` pada template dan service/domain memastikan maksimal satu default aktif per layanan. Backfill memilih template aktif yang ada sekarang untuk setiap layanan.

Acceptance criteria:

- [ ] Setiap layanan yang dapat menerbitkan dokumen memiliki tepat satu template default aktif.
- [ ] Template lintas layanan/nonaktif tidak dapat dipakai approval.
- [ ] Admin template dapat melihat dan mengganti default secara aman.

Verification:

- [ ] Migration test untuk backfill lima template seed.
- [ ] Feature test menolak template lintas layanan dan nonaktif.

Dependencies: Task 1.  
Files likely touched: migration baru, `DocumentTemplate.php`, `DocumentTemplateController.php`, form/index template, test.  
Estimated scope: M; pecah UI default menjadi task terpisah bila melebihi lima file.

### Checkpoint A

- [ ] Schema test lulus.
- [ ] Semua layanan penerbit punya template default deterministik.
- [ ] Tidak ada perubahan workflow produksi sebelum fondasi siap.

### Phase 2 — Vertical slice “Setujui & Terbitkan”

#### Task 3: Buat resolver kesiapan dokumen

Deskripsi: satu domain service menentukan template default, validitas nomor surat, keberadaan file, MIME, ukuran, dan status readiness. Service ini dipakai approval, completion guard, index, detail, dan download.

Acceptance criteria:

- [ ] Readiness membedakan missing template, missing number, missing file, ready generated, dan ready manual.
- [ ] Record orphan tidak dianggap dokumen siap.
- [ ] Hasil resolver memiliki kode error yang dapat dirender UI/log.

Verification: unit test setiap state readiness.  
Dependencies: Task 2.  
Files likely touched: service/value object baru, test unit, model relation helper.  
Estimated scope: S/M.

#### Task 4: Refactor generator agar gagal secara eksplisit

Deskripsi: hilangkan catch-all fallback generik pada runtime normal, validasi sumber PDF dan output, gunakan nama file unik, dan pastikan file parsial dibersihkan bila persist gagal. Fallback invalid-PDF hanya boleh ada sebagai fixture/test behavior yang eksplisit, bukan produksi diam-diam.

Acceptance criteria:

- [ ] Template hilang/rusak menghasilkan error terkontrol dan tidak membuat `GeneratedDocument` sukses.
- [ ] Output harus `%PDF`, ukuran > 0, dan ada pada private storage.
- [ ] Kegagalan tidak meninggalkan pointer service request atau file parsial.

Verification: unit/integration test template valid, hilang, rusak, dan storage failure.  
Dependencies: Task 3.  
Files likely touched: `DocumentGenerationService.php`, exception domain baru, tests.  
Estimated scope: S/M.

#### Task 5: Implement command approval atomic

Deskripsi: orchestration service/controller action melakukan lock pengajuan, validasi transition/permission, resolve template, generate, persist metadata, transition `completed`, dan menjadwalkan notifikasi after-commit. Double click harus idempotent.

Acceptance criteria:

- [ ] Satu submit approval menghasilkan tepat satu dokumen final dan status final.
- [ ] Bila generate gagal, status/histori final tidak berubah.
- [ ] Request ulang/double click tidak membuat duplikat final tanpa aksi regenerate eksplisit.

Verification:

- [ ] Feature test happy path end-to-end.
- [ ] Feature test generation failure rollback.
- [ ] Concurrency/idempotency test pada request yang sama.

Dependencies: Tasks 3-4.  
Files likely touched: orchestration service/action baru, controller, route, notification dispatch boundary, tests.  
Estimated scope: M; pisahkan perubahan notifikasi bila perlu.

### Checkpoint B

- [ ] Approval otomatis lulus end-to-end dengan template seed.
- [ ] Tidak ada status `completed` tanpa file valid.
- [ ] Permission `process service requests` + `generate documents` ditegakkan.
- [ ] Test workflow manual lama diperbarui agar manual upload menjadi fallback, bukan jalur wajib.

### Phase 3 — UI admin operasional

#### Task 6: Query tabel, filter, dan metadata aksi

Deskripsi: index controller menerima filter tervalidasi dan eager-load/count data yang diperlukan tanpa N+1.

Acceptance criteria:

- [ ] Search/filter menghasilkan query yang benar dan mempertahankan query saat pagination.
- [ ] Setiap row memiliki status dokumen tanpa query per row.
- [ ] Sorting default terbaru dan pagination tetap stabil.

Verification: feature test kombinasi filter, pagination, dan query-count smoke test.  
Dependencies: Task 3.  
Files likely touched: controller/query object, request validator, feature test.  
Estimated scope: S/M.

#### Task 7: Ubah menu Pengajuan menjadi tabel + aksi

Deskripsi: ganti daftar kartu dengan tabel sesuai kontrak di atas, toolbar filter, badges, empty state, dan conditional actions berbasis permission/status.

Acceptance criteria:

- [ ] Desktop dapat memindai semua kolom dan menjalankan aksi utama dari row.
- [ ] Mobile 320 px tidak menimbulkan overflow body; tabel memiliki scroll container yang jelas.
- [ ] Aksi yang tidak sah tidak dirender dan tetap ditolak server-side bila dipanggil langsung.

Verification: Blade feature assertions, keyboard navigation, screenshot 1440/1024/768/320, axe/accessibility smoke.  
Dependencies: Tasks 5-6.  
Files likely touched: index Blade, CSS, optional small JS dialog, e2e audit.  
Estimated scope: M.

#### Task 8: Susun ulang detail menjadi review workspace

Deskripsi: render seluruh data yang sudah di-load controller, panel dokumen, timeline, dan sticky conditional action bar. Tambahkan dialog approval nomor surat dan pindahkan upload manual menjadi fallback.

Acceptance criteria:

- [ ] Data pemohon, field dinamis, berkas, histori, dan dokumen terlihat terstruktur.
- [ ] `Setujui & Terbitkan` memanggil command Task 5 dan menampilkan failure reason tanpa kehilangan input.
- [ ] Preview/Download tersedia saat readiness `ready`.

Verification: feature view tests dan browser test untuk tiap status utama pada empat viewport.  
Dependencies: Tasks 5 dan 7.  
Files likely touched: show Blade, CSS, controller load metadata, optional JS, tests.  
Estimated scope: M.

### Checkpoint C

- [ ] Workflow admin selesai tanpa upload manual.
- [ ] Tabel dan detail responsif serta dapat dioperasikan keyboard.
- [ ] Copy UI seluruhnya Bahasa Indonesia dan tidak ada label “Modal form/Drawer form”.

### Phase 4 — Download warga dan hardening

#### Task 9: Ekspos download pada hasil cek status

Deskripsi: setelah verifikasi kode + NIK, buat session authorization scoped dan tampilkan tombol download hanya untuk dokumen final yang file-nya valid.

Acceptance criteria:

- [ ] Warga yang berhasil cek status dapat download satu klik.
- [ ] Kombinasi salah, session berbeda, atau dokumen belum final ditolak.
- [ ] Halaman menjelaskan alasan bila dokumen belum siap.

Verification: feature test authorized, denied, expired/session mismatch, missing file, dan completed happy path.  
Dependencies: Task 3 dan Task 5.  
Files likely touched: `PublicController.php`, status-result Blade, download controller, tests.  
Estimated scope: M.

#### Task 10: Benarkan response file dan observability

Deskripsi: response memakai MIME/nama file asli; log mencatat document id/source dan kegagalan generation/readiness tanpa data sensitif.

Acceptance criteria:

- [ ] PDF otomatis diunduh sebagai PDF.
- [ ] Fallback manual mempertahankan tipe/nama yang benar.
- [ ] Error generation dan missing file dapat ditelusuri dari log dengan request id.

Verification: feature test PDF, PNG/DOCX fallback, log assertions, dan security regression.  
Dependencies: Task 9.  
Files likely touched: download controller/service, logging, tests.  
Estimated scope: S.

### Checkpoint D — Release candidate

- [ ] `php artisan test` seluruh suite lulus.
- [ ] `npm run build` lulus.
- [ ] UI audit seluruh route target pada 1440/1024/768/320 tanpa console error, broken image, atau body overflow.
- [ ] Uji manual: submit warga -> verifikasi -> setujui & terbitkan -> download admin -> cek status -> download warga.
- [ ] Backup DB dibuat sebelum migration dan rollback migration diuji.

## Rollout

1. Deploy migration/template default lebih dahulu tanpa mengaktifkan command approval baru.
2. Jalankan preflight untuk menghitung layanan tanpa template default, template file hilang, serta generated record orphan; blok aktivasi bila ada masalah.
3. Deploy backend command di balik config flag `documents.auto_publish_on_approval` default false.
4. Deploy tabel/detail UI dan aktifkan flag untuk akun admin uji.
5. Jalankan satu pengajuan uji tiap jenis layanan dan inspeksi visual PDF.
6. Aktifkan untuk seluruh petugas, monitor generation failure, orphan file, denied download, dan duplicate approval selama 24-48 jam.
7. Pertahankan upload manual sebagai fallback berizin; jangan hapus sampai alur otomatis stabil.

## Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Format nomor surat salah/duplikat | Tinggi | Input manual v1; unique/reservation hanya setelah aturan resmi disepakati. |
| Template salah layanan | Tinggi | Resolve server-side berdasarkan default aktif dan test lintas layanan. |
| Status selesai tetapi file gagal | Tinggi | Lock + orchestration atomic + readiness check + cleanup. |
| Notifikasi terkirim sebelum commit | Tinggi | Dispatch after-commit. |
| Double click menghasilkan duplikat | Sedang | Idempotency/lock dan aksi regenerate terpisah. |
| Template rusak menghasilkan fallback generik | Tinggi | Fail explicitly; jangan catch semua exception sebagai sukses. |
| Query tabel lambat | Sedang | Eager-load/count, index kolom filter, query-count test. |
| Mobile tabel sulit dipakai | Sedang | Kolom prioritas, wrapper scroll, sticky action, viewport audit. |

## Pertanyaan keputusan sebelum implementasi

1. Apakah “approve” adalah verifikasi awal atau persetujuan final yang langsung mengubah status menjadi selesai?
2. Apakah nomor surat tetap diketik petugas, atau harus otomatis? Jika otomatis: apa format, sequence per jenis layanan, dan kapan sequence reset?
3. Apakah Pengaduan Masyarakat juga harus menghasilkan surat PDF?
4. Apakah warga boleh download segera setelah PDF dibuat, atau hanya setelah status `completed`?

Rekomendasi default: gunakan `Verifikasi Berkas` untuk pengecekan awal, lalu `Setujui & Terbitkan` sebagai satu aksi final yang generate + complete; nomor surat diketik di dialog; download warga hanya setelah transaksi final berhasil.
