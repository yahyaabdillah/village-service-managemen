# Master Plan: Document System, Design System, Flow, Task List, dan QA

Tanggal audit: 4 Agustus 2026  
Status: planning-only, siap dieksekusi bertahap  
Scope: template dokumen, variable form, mapping, generate/publish/regenerate, download admin/warga, serta perapian UI terkait

## 1. Outcome yang wajib tercapai

1. Admin dapat membuat variable input warga langsung dari Template Builder tanpa membuat sumber data ganda.
2. Setiap elemen dokumen mempunyai mapping eksplisit: sumber data, nilai tetap, tanggal/waktu, atau gabungan beberapa segmen.
3. Template tidak dapat diaktifkan sebelum file, variable, mapping, posisi, dan contoh hasil lolos validasi.
4. Approval/publish menghasilkan PDF valid secara otomatis dan atomik; status tidak berubah menjadi selesai jika generasi gagal.
5. Dokumen lama/manual dapat di-generate ulang dengan aman dan menyimpan histori artefak.
6. Download mengirim MIME dan ekstensi sesuai isi file, tidak lagi menyamarkan PNG sebagai PDF.
7. Admin dan warga mendapat state UI yang jelas untuk loading, kosong, gagal, siap, dan berhasil.
8. Seluruh jalur normal, error, retry, otorisasi, responsif, dan aksesibilitas mempunyai acceptance test.

## 2. Batas sistem dan sumber kebenaran

| Domain | Sumber kebenaran | Catatan |
|---|---|---|
| Jenis layanan | `service_types` | Menentukan form dan template yang sah |
| Variable input warga | `service_type_fields` | Direuse; jangan membuat tabel variable form kedua |
| Nilai jawaban | `service_request_field_values` | Menyimpan snapshot key/label/value saat pengajuan |
| Template | `document_templates` | File PDF, lifecycle, versi, dan default template |
| Elemen di kanvas | `document_template_fields` | Posisi, halaman, ukuran, dan `mapping_config` |
| Pengajuan | `service_requests` | Data inti, status proses, dan pointer artefak aktif |
| Artefak final | `generated_documents` | Histori file, MIME, ukuran, checksum, template/version |
| Nomor surat | Generator nomor yang sudah ada | Harus dialokasikan hanya dalam transaksi publish |

Prinsip penting: label boleh berubah, tetapi key yang sudah dipakai pengajuan atau template bersifat immutable. Pengajuan lama tetap dirender dari snapshot nilainya.

## 3. Design system

### 3.1 Arah visual

Nama kerja: **Ruang Desa**. Karakternya administratif, tenang, mudah dipindai, dan tidak terasa seperti kumpulan kartu dekoratif.

- Warna utama: forest green untuk navigasi dan aksi utama.
- Latar: cream/warm paper agar area kerja dokumen terasa natural.
- Aksen: terracotta hanya untuk CTA penting atau perhatian, amber untuk peringatan.
- Tipografi: Fraunces untuk judul halaman; Instrument Sans untuk navigasi, form, tabel, dan data operasional.
- Kepadatan: tabel dan inspector bersifat compact; ruang lega dipakai pada header dan empty state.

### 3.2 Token yang dikunci

Gunakan token yang sudah ada di `resources/css/app.css` sebagai basis:

| Token | Nilai/peran |
|---|---|
| `forest-950..600` | navigasi, teks penting, tombol primer, hover |
| `sage-50/100` | latar lembut dan selected state |
| `terracotta` | CTA kontekstual dan indikator placement |
| `amber` | warning dan draft |
| `cream`, `paper` | app background dan surface |
| `ink`, `muted`, `line` | teks, helper, border |
| `danger`, `success`, `info` | semantic state |
| `radius-sm/md/lg` | input, panel, dialog |
| `shadow-sm/md` | surface elevation, dialog |

Tambahan token yang perlu dinormalisasi saat implementasi:

- Spacing: `4, 8, 12, 16, 24, 32, 48px`.
- Control height: compact `32px`, default `40px`, large `48px`.
- Focus ring: `2px` forest/white offset, selalu terlihat untuk keyboard.
- Layer: sticky header `20`, drawer `40`, modal `60`, toast `80`.
- Motion: 120–180ms; dinonaktifkan melalui `prefers-reduced-motion` yang sudah tersedia.

### 3.3 Komponen inti

| Komponen | Varian/state wajib | Penggunaan |
|---|---|---|
| Button | primary, secondary, ghost, danger; default/loading/disabled | simpan, aktifkan, generate, download |
| Status badge | draft, ready, active, archived, pending, processing, completed, rejected, failed | lifecycle template/request |
| Form control | text, select, textarea, checkbox, date-format; default/focus/error/disabled | form variable dan mapping |
| Alert | info, warning, error, success; dismissible bila non-blocking | validasi dan operasi |
| Table | filter, sortable header, empty, loading skeleton, pagination | daftar template/request/artefak |
| Dialog | confirmation dan form; focus trap, Escape, restore focus | buat variable, aktifkan, regenerate |
| Drawer | inspector pada tablet/mobile | konfigurasi mapping dan posisi |
| Tabs | Data, Mapping, Posisi, Preview | mengurangi inspector yang terlalu panjang |
| Toast | success/error singkat dengan fallback alert persisten | autosave dan aksi non-blocking |
| File status | type, size, checksum, valid/invalid | upload dan artefak hasil |
| Empty state | alasan + satu next action | template/variable/request kosong |
| Autosave status | belum disimpan, menyimpan, tersimpan, gagal, offline, konflik | builder |

Semua status memakai icon + teks, tidak bergantung pada warna saja. Semua ikon dekoratif `aria-hidden`; tombol ikon wajib mempunyai accessible name.

### 3.4 Layout halaman

#### Daftar template

- Header: judul, ringkasan singkat, tombol `Buat Template`.
- Filter bar: layanan, status, pencarian nama; reset filter eksplisit.
- Tabel: nama, layanan, versi, status validasi, terakhir diubah, template default, aksi.
- Menu aksi: Buka Builder, Duplikasi, Preview, Aktifkan/Arsipkan. Delete hanya untuk draft yang belum direferensikan.

#### Template Builder

Desktop `>=1100px`:

```text
+----------------------+---------------------------+----------------------+
| Variable & Layers    | Canvas PDF                | Inspector            |
| Search/category      | Toolbar/page/zoom         | Data/Mapping/Posisi  |
| Create variable      | placed elements           | Validation           |
+----------------------+---------------------------+----------------------+
| Back | template state | autosave status | Preview | Activate template   |
+--------------------------------------------------------------------------+
```

- Sidebar kiri 240–280px; canvas mengambil ruang sisa; inspector 320–360px.
- Tablet 720–1099px: sidebar variable collapsible, inspector menjadi drawer.
- Mobile `<720px`: halaman tetap dapat mengatur mapping melalui daftar elemen dan input koordinat; drag bukan satu-satunya cara interaksi.
- Unsaved changes, offline state, dan version conflict selalu terlihat sebelum keluar.

#### Detail pengajuan admin

- Header: kode, layanan, status, SLA/tanggal masuk, aksi utama tunggal.
- Dua kolom desktop: data warga + jawaban form di kiri; validasi dokumen + timeline di kanan.
- Bagian dokumen berbentuk tabel: versi, sumber, template, dibuat oleh/waktu, MIME/size, status, aksi Preview/Download/Jadikan Aktif.
- CTA mengikuti state machine, bukan selalu tampil.

#### Status pengajuan warga

- Ringkasan kode dan status.
- Timeline sederhana.
- Tombol download hanya saat artefak final valid dan request selesai.
- Error download menjelaskan apakah file sedang dibuat, gagal, atau sudah tidak tersedia; jangan tampilkan path storage.

### 3.5 Bahasa antarmuka

- Gunakan istilah konsisten: `Variable Form`, `Mapping`, `Template`, `Generate Dokumen`, `Aktifkan Template`.
- Tampilkan contoh nilai di bawah key: `keperluan` → “Keperluan pengajuan warga”.
- Pesan error harus menjawab tiga hal: apa yang gagal, penyebab yang diketahui, tindakan berikutnya.
- Aksi destruktif menyebut objek: “Arsipkan template Domisili v2”, bukan “Apakah Anda yakin?”.

### 3.6 Aksesibilitas dan responsif

- Target WCAG 2.1 AA untuk contrast, focus, label, error association, dan keyboard.
- Drag/drop mempunyai alternatif: pilih elemen lalu isi halaman, X, Y, lebar, tinggi.
- Dialog memindahkan dan mengembalikan focus; Escape menutup jika aman.
- Error summary mengarah ke field yang invalid.
- Tabel mempertahankan header atau berubah menjadi row-card terstruktur pada 320px; aksi tidak terpotong.
- Viewport wajib diuji: 320, 768, 1024, dan 1440px.

## 4. Kontrak variable dan mapping

### 4.1 Tiga lapis yang tidak boleh tercampur

1. **Membuat variable**: mendefinisikan pertanyaan/field yang akan diisi warga.
2. **Mapping**: menentukan bagaimana data menjadi teks final.
3. **Placement**: menentukan posisi teks final pada halaman PDF.

### 4.2 Registry variable

| Kategori | Contoh | Editable |
|---|---|---|
| Request bawaan | `applicant_name`, `nik`, `address`, `phone`, `rt_rw`, `request_code` | Tidak |
| Form layanan | `keperluan`, variable baru dari `service_type_fields` | Ya, dengan lifecycle guard |
| Sistem | `letter_number`, `letter_date`, `current_date`, tahun/bulan/jam | Format saja |
| Desa | nama desa, kecamatan, kepala desa | Dari setting resmi |
| Literal | prefix/suffix/teks tetap | Ya di mapping |

Variable form baru dibuat sebagai `draft/inactive`, unik per layanan, key `snake_case`, dan baru muncul di form publik setelah diaktifkan. Key tidak dapat diganti bila sudah mempunyai nilai atau dipakai mapping; delete berubah menjadi archive/nonaktif.

### 4.3 Bentuk mapping

Mapping mendukung:

- Sumber tunggal: satu source + optional formatter/fallback.
- Teks tetap: literal terkontrol.
- Gabungan segmen: contoh `Nomor: ` + `letter_number` + `/` + `year`.
- Prefix/suffix: shortcut UI yang disimpan sebagai segmen literal.
- Tanggal/waktu: allowlist format Indonesia; tanpa ekspresi arbitrer.

Contoh kontrak:

```json
{
  "version": 1,
  "mode": "segments",
  "segments": [
    { "type": "literal", "value": "Nomor: " },
    { "type": "source", "source": "system", "key": "letter_number" },
    { "type": "literal", "value": "/" },
    { "type": "date", "source": "system", "key": "letter_date", "format": "Y" }
  ],
  "fallback": "-"
}
```

Legacy `variable_key` tetap dibaca melalui adapter sampai seluruh template termigrasi. Unknown key tidak boleh diam-diam menjadi string kosong: preview/publish harus memberi error terarah.

## 5. Alur end-to-end tanpa jalur buntu

### Flow A — Menyiapkan layanan dan template

1. Admin memilih jenis layanan.
2. Admin membuat template draft dan mengunggah PDF.
3. Sistem memverifikasi magic bytes, MIME, ukuran, jumlah halaman, dan apakah file dapat diparsing.
4. Builder menampilkan registry variable sesuai layanan.
5. Bila variable belum ada, admin memilih `Buat Variable Form`; variable tersimpan draft dan langsung tersedia di builder, belum di form warga.
6. Admin menempatkan elemen atau memilih elemen existing.
7. Admin mengatur source/mode/segmen/format/fallback pada inspector.
8. Sistem memvalidasi key, tipe, source satu layanan, page bounds, overlap berat, serta elemen orphan.
9. Admin preview memakai sample value atau data request yang dipilih dengan izin yang sesuai.
10. Autosave menyimpan perubahan dan menunjukkan saved/error/offline/conflict.
11. `Validasi Template` menghasilkan daftar blocking error dan warning.
12. `Aktifkan Template` hanya tersedia jika semua blocker nol; dalam satu operasi sistem mengaktifkan variable form terkait dan menetapkan satu template default aktif per layanan.
13. Template aktif tidak diedit in-place; perubahan membuat versi draft berikutnya.

Exit state: ada satu default template aktif, semua source valid, dan form publik hanya membaca field aktif.

### Flow B — Warga membuat pengajuan

1. Warga memilih layanan aktif.
2. Sistem memuat field inti + `service_type_fields` aktif dengan urutan stabil.
3. Client memberi helper/error, tetapi server tetap menjadi validasi final.
4. Sistem menyimpan request, nilai field beserta snapshot key/label/type, dan lampiran.
5. Halaman sukses menampilkan kode pelacakan; tidak ada dokumen final sebelum diproses.

Recovery: bila konfigurasi berubah di tengah submit, server menolak dengan pesan refresh; input tidak hilang bila dapat dipertahankan.

### Flow C — Review, approve, dan publish admin

1. Admin membuka daftar request, memfilter, lalu membuka detail.
2. Sistem menampilkan data inti, jawaban dinamis, lampiran, histori status, dan readiness dokumen.
3. Admin memperbaiki data yang memang diizinkan atau menolak dengan alasan.
4. Saat `Setujui & Generate`, server mengunci request dan memastikan status masih valid.
5. Server memilih default template aktif untuk service yang sama, memuat versi serta mapping snapshot.
6. Server memvalidasi seluruh required source dan nomor surat.
7. Server me-render ke file sementara, memverifikasi PDF/magic bytes/page count/ukuran/checksum.
8. Dalam transaksi, server menyimpan artefak, histori, pointer aktif, nomor surat, dan status `completed`.
9. Setelah commit, notifikasi dikirim. Kegagalan notifikasi tidak membatalkan dokumen; dicatat dan dapat di-retry.
10. UI menampilkan row artefak dengan Preview/Download.

Recovery: double-click/idempotency tidak membuat dua nomor atau dua artefak aktif; kegagalan generate tidak mengubah status request dan file sementara dibersihkan.

### Flow D — Regenerate request lama/manual

1. Admin memilih `Generate Ulang dari Template`.
2. Dialog menunjukkan alasan, template/version, nomor surat existing atau input resmi yang dibutuhkan, serta dampaknya.
3. Sistem memvalidasi mapping terhadap snapshot request lama.
4. Artefak baru dibuat dan diverifikasi lebih dulu.
5. Dalam transaksi, artefak baru dijadikan aktif; artefak lama tetap berada di histori dan tidak dihapus.
6. Audit log menyimpan actor, waktu, alasan, template version, checksum lama/baru.

Khusus request #1: jangan repair sebelum backup DB/storage dan nomor surat resmi tersedia. File PNG lama tetap dicatat sebagai artefak legacy dengan MIME sebenarnya.

### Flow E — Download admin dan warga

1. Route melakukan authorization terhadap request/artefak.
2. Storage path diselesaikan dari record, bukan input pengguna.
3. Sistem mengecek file ada dan membaca MIME aktual/magic bytes.
4. Nama file disanitasi dan ekstensi disesuaikan dengan isi.
5. Response mengirim `Content-Type`, `Content-Length`, dan `Content-Disposition` yang benar.
6. Warga hanya dapat mengunduh artefak aktif dari request selesai setelah lolos mekanisme status/identitas yang berlaku.

Recovery: missing/corrupt file menghasilkan error terarah dan log correlation ID, bukan file palsu atau exception page sebagai `.pdf`.

### Flow F — Mengubah atau memensiunkan konfigurasi

1. Variable yang belum pernah dipakai dapat diubah/dihapus saat draft.
2. Variable yang sudah dipakai hanya dapat diubah label/helper atau dinonaktifkan; key tetap.
3. Template aktif diarsipkan hanya jika tersedia replacement default atau layanan ikut dinonaktifkan.
4. Pengajuan lama tetap merender dari snapshot dan template version yang tercatat.
5. Cleanup file hanya menghapus orphan terverifikasi setelah retention window, tidak saat request pengguna berlangsung.

## 6. State machine

### Template

`draft -> ready -> active -> archived`

- `draft`: bebas diedit, belum digunakan publik.
- `ready`: lolos validasi, menunggu aktivasi.
- `active`: immutable, boleh menjadi default generation.
- `archived`: tidak dipakai request baru, histori tetap valid.

### Request

`submitted -> processing -> completed` atau `submitted/processing -> rejected`

- Generate gagal: tetap pada state sebelumnya + error operation tercatat.
- Completed hanya jika pointer artefak aktif menunjuk file yang tervalidasi.

### Artefak

`generating -> valid -> active`; failure menjadi `failed`; artefak active lama menjadi `superseded` setelah regenerate berhasil.

## 7. Gap coverage matrix

| Gap terverifikasi/potensial | Penutup desain | Task | QA |
|---|---|---|---|
| PNG dikirim sebagai PDF | MIME/magic-byte response | T09 | Q-DL01..05 |
| Request #1 menunjuk file manual | regenerate + histori artefak | T12, T16 | Q-RG01..06 |
| `custom_text` tidak mempunyai source | registry + invalid mapping blocker | T04, T06 | Q-MP04, Q-BL07 |
| `current_date` vs `letter_date` tidak konsisten | canonical registry + legacy alias | T04, T05 | Q-MP02 |
| Unknown variable menjadi kosong | strict resolver | T05 | Q-MP03 |
| Belum ada UI buat variable | dialog variable dalam builder | T06 | Q-VR01..08 |
| Belum ada prefix/date/composite | segment mapping editor | T07 | Q-MP05..10 |
| Variable draft dapat bocor ke form publik | field lifecycle + active scope | T03, T13 | Q-PF01..04 |
| Template setengah jadi dapat dipakai | validation gate + default active | T03, T08 | Q-TP01..08 |
| Fallback PDF menutupi error | explicit generation failure | T10 | Q-GN01..08 |
| Publish berisiko parsial | lock, temp file, transaction, idempotency | T11 | Q-PB01..10 |
| Regenerate menimpa artefak lama | artifact history/superseded | T12 | Q-RG03..05 |
| Builder sulit dipakai/responsif | 3-panel + drawer + keyboard coordinates | T06–T08, T14 | Q-UX01..12 |
| Permission/IDOR download | policy + scoped lookup | T09, T15 | Q-SC01..08 |
| Tidak ada observability/recovery | operation log, correlation ID, cleanup/retry | T10–T12, T17 | Q-OP01..06 |

## 8. Task list implementasi

Urutan mengikuti dependency. Setiap task harus menghasilkan test yang dapat dijalankan sebelum lanjut.

### Phase 0 — Safety dan baseline

- [ ] **T01 — Bekukan kontrak dan fixture regresi**  
  Tambahkan fixture PDF valid, PNG tersamar, PDF rusak, template legacy, request dynamic fields, dan request manual. Snapshot backup request/template #1.  
  Selesai jika test reproduksi merah untuk MIME salah, unknown mapping, dan fallback generator tersedia.

- [ ] **T02 — Audit data dan keputusan nomor surat**  
  Inventaris semua `variable_key`, file extension vs magic bytes, template aktif per layanan, artefak orphan, dan nomor duplikat. Hasil berupa migration report tanpa mutation.  
  Selesai jika setiap record berisiko mempunyai klasifikasi: auto-migrate, manual-review, atau quarantine.

### Phase 1 — Kontrak data

- [ ] **T03 — Migration lifecycle dan metadata**  
  Tambahkan lifecycle/version/default template, status aktif variable form, `mapping_config` versioned, serta metadata artefak (MIME, size, checksum, template/version, status). Sertakan backfill dan rollback aman.  
  Selesai jika hanya satu default active template per layanan dapat dipertahankan dan record lama tetap terbaca.

- [ ] **T04 — Canonical Variable Registry**  
  Buat registry server-side untuk request, form, system, village, literal; definisikan alias legacy seperti `current_date`; hapus palette hard-coded sebagai sumber kebenaran.  
  Selesai jika builder dan generator membaca registry yang sama.

- [ ] **T05 — Strict Mapping Resolver**  
  Implement source tunggal, literal, composite segments, prefix/suffix, date allowlist, fallback, escaping, dan error terstruktur.  
  Selesai jika unknown/missing required source menjadi validation error, bukan string kosong.

### Phase 2 — Builder dan design system

- [ ] **T06 — Variable Manager dalam Builder**  
  Tambahkan search/category, dialog buat/edit, lifecycle guard, empty/loading/error states, dan refresh registry tanpa reload.  
  Selesai jika variable baru tersedia untuk placement tetapi belum muncul di form publik sebelum aktif.

- [ ] **T07 — Mapping Inspector**  
  Buat tabs Data/Mapping/Posisi, editor segmen berurutan, date format preview, fallback, serta list mapping status. Legacy field dapat dibuka dan dikonversi eksplisit.  
  Selesai jika seluruh mapping dapat dibuat tanpa mengetik arbitrary key.

- [ ] **T08 — Preview, validator, autosave, dan aktivasi template**  
  Implement sample/real-request preview, bounds/overlap/orphan/source validation, autosave state, conflict detection, readiness summary, immutable active version, dan one-default activation.  
  Selesai jika template invalid tidak dapat aktif melalui UI maupun direct HTTP request.

### Phase 3 — Generation dan download

- [ ] **T09 — Benarkan kontrak download**  
  Centralize response berdasarkan MIME/magic bytes, sanitize filename, scoped authorization, missing/corrupt state, dan headers yang benar.  
  Selesai jika PNG tidak pernah lagi dikirim dengan `.pdf`/`application/pdf`.

- [ ] **T10 — Harden DocumentGenerationService**  
  Hilangkan generic fallback yang menutupi parsing/render error; validasi source, output PDF, page count, size, checksum; gunakan temp file dan typed failure.  
  Selesai jika setiap kegagalan dapat dibedakan dan tidak menghasilkan artefak valid palsu.

- [ ] **T11 — Atomic approve/publish**  
  Tambahkan request lock, status transition guard, default template check, allocation nomor yang idempotent, verified artifact write, transaction, cleanup, serta notification after-commit/retry.  
  Selesai jika concurrency test tidak membuat dua nomor atau completed request tanpa file.

- [ ] **T12 — Safe regenerate dan artifact history**  
  Tambahkan dialog alasan/template/version/nomor, generate-first switch-later, active/superseded history, dan audit trail.  
  Selesai jika failure mempertahankan pointer lama dan success tidak menghapus artefak lama.

### Phase 4 — Alur admin dan warga

- [ ] **T13 — Public form membaca konfigurasi aktif**  
  Filter field aktif, pertahankan urutan, server validation, snapshot field metadata, dan graceful config-version mismatch.  
  Selesai jika draft/archived variable tidak pernah muncul atau diterima diam-diam.

- [ ] **T14 — Rapikan layar operasional admin**  
  Terapkan table/filter/pagination, header dan action hierarchy, readiness panel, artifact table, status/error/empty/loading, responsive layout, serta keyboard operation.  
  Selesai jika aksi yang tersedia selalu sesuai state request/template.

- [ ] **T15 — Status dan download warga**  
  Tampilkan timeline, dokumen-ready state, authorized download, dan pesan recovery yang aman.  
  Selesai jika request lain tidak dapat diakses dengan menebak ID/kode.

### Phase 5 — Migrasi, repair, dan release

- [ ] **T16 — Migrasi template dan repair request #1**  
  Jalankan dry-run report, backup, konversi mapping legacy, tangani elemen `custom_text` orphan/overlap, lalu regenerate request #1 menggunakan nomor surat resmi.  
  Selesai jika PDF berisi data request #1 yang benar dan PNG legacy tetap tercatat dengan MIME sebenarnya.

- [ ] **T17 — Observability, cleanup, dan runbook**  
  Tambahkan structured event/correlation ID, metric generation success/failure/duration, retry notifikasi, orphan cleanup terjadwal dengan dry-run, serta langkah rollback.  
  Selesai jika operator dapat mendiagnosis kegagalan tanpa membuka database secara manual.

- [ ] **T18 — Full regression dan release gate**  
  Jalankan seluruh QA checklist, test suite, asset build, browser E2E, accessibility scan, migration rehearsal, dan restore rehearsal.  
  Selesai hanya jika blocker nol; warning yang diterima dicatat dengan owner dan tenggat.

## 9. QA checklist

### Data dan migration

- [ ] **Q-DB01** Migration jalan pada database berisi data lama.
- [ ] **Q-DB02** Backfill tidak mengubah key/value snapshot pengajuan.
- [ ] **Q-DB03** Constraint mencegah dua default active template untuk layanan yang sama.
- [ ] **Q-DB04** Rollback rehearsal mengembalikan schema tanpa kehilangan artefak.
- [ ] **Q-DB05** Dry-run melaporkan orphan mapping/file dan tidak menulis data.
- [ ] **Q-DB06** Checksum/size/MIME terisi untuk artefak yang dapat dibaca; legacy invalid dikarantina.

### Variable lifecycle

- [ ] **Q-VR01** Membuat text/textarea/select/date/number field dari builder berhasil.
- [ ] **Q-VR02** Key dinormalisasi ke snake_case dan unik per layanan.
- [ ] **Q-VR03** Options wajib untuk select/radio dan ditolak untuk tipe tak relevan.
- [ ] **Q-VR04** Variable draft muncul di builder tetapi tidak di form warga.
- [ ] **Q-VR05** Aktivasi template mengaktifkan variable dependency secara konsisten.
- [ ] **Q-VR06** Key yang sudah dipakai tidak dapat diubah/dihapus.
- [ ] **Q-VR07** Variable tidak dapat dimapping lintas layanan.
- [ ] **Q-VR08** Unauthorized user tidak dapat create/edit/archive variable via HTTP langsung.

### Mapping resolver

- [ ] **Q-MP01** Request field inti dan dynamic field resolve benar.
- [ ] **Q-MP02** `letter_date` dan alias legacy `current_date` konsisten.
- [ ] **Q-MP03** Unknown key menghasilkan blocker terarah.
- [ ] **Q-MP04** Elemen legacy `custom_text` tidak silently blank.
- [ ] **Q-MP05** Prefix, suffix, literal, dan urutan composite tepat.
- [ ] **Q-MP06** Format hari/tanggal/bulan/tahun/jam Indonesia sesuai timezone Asia/Jakarta.
- [ ] **Q-MP07** Empty optional memakai fallback; missing required gagal.
- [ ] **Q-MP08** Input berisi HTML/script dirender sebagai teks, bukan dieksekusi.
- [ ] **Q-MP09** NIK/nomor telepon mempertahankan leading zero.
- [ ] **Q-MP10** Teks panjang, Unicode, line break, dan karakter khusus tidak mematahkan PDF.

### Template Builder

- [ ] **Q-BL01** Upload valid/invalid MIME, ukuran, dan encrypted/corrupt PDF tertangani.
- [ ] **Q-BL02** Placement melalui drag dan input keyboard menghasilkan koordinat sama.
- [ ] **Q-BL03** Elemen tidak dapat keluar halaman tanpa warning/blocker.
- [ ] **Q-BL04** Overlap berat dan duplicate mapping terdeteksi sesuai aturan.
- [ ] **Q-BL05** Multi-page navigation menyimpan page index benar.
- [ ] **Q-BL06** Autosave menampilkan saving/saved/error/offline dan retry.
- [ ] **Q-BL07** Orphan source memblokir readiness/aktivasi.
- [ ] **Q-BL08** Dua tab edit memunculkan version conflict, bukan last-write-wins diam-diam.
- [ ] **Q-BL09** Active template immutable; edit membuat draft version baru.
- [ ] **Q-BL10** Preview sample dan real request memakai resolver identik dengan production.

### Generate dan publish

- [ ] **Q-GN01** Output mempunyai magic bytes `%PDF-`, MIME PDF, page count > 0, size > minimum.
- [ ] **Q-GN02** Parser/render failure tidak menghasilkan generic fallback yang dianggap valid.
- [ ] **Q-GN03** Missing font/logo/template memberi typed error dan correlation ID.
- [ ] **Q-GN04** File sementara dibersihkan setelah success/failure.
- [ ] **Q-GN05** Storage write/rename failure tidak meninggalkan DB pointer rusak.
- [ ] **Q-GN06** Checksum hasil tercatat dan dapat diverifikasi ulang.
- [ ] **Q-GN07** Semua nilai utama dapat diekstrak dari PDF test (`applicant_name`, `nik`, alamat, RT/RW, telepon, keperluan, nomor, tanggal).
- [ ] **Q-GN08** Tidak ada placeholder mentah `{{ ... }}` atau box debug tersisa.
- [ ] **Q-PB01** Approve sukses menghasilkan artefak lalu status completed.
- [ ] **Q-PB02** Mapping/template invalid mempertahankan status awal.
- [ ] **Q-PB03** Double-click menghasilkan satu nomor dan satu active artifact.
- [ ] **Q-PB04** Dua admin concurrent tidak dapat publish request yang sama dua kali.
- [ ] **Q-PB05** Template bukan layanan request ditolak.
- [ ] **Q-PB06** Template nonaktif/draft ditolak server.
- [ ] **Q-PB07** Notification dikirim hanya after commit.
- [ ] **Q-PB08** Notification failure tercatat dan retryable tanpa regenerate.
- [ ] **Q-PB09** User tanpa permission tidak dapat approve/publish.
- [ ] **Q-PB10** Audit history mencatat actor, transisi, template version, dan artefak.

### Regenerate dan repair

- [ ] **Q-RG01** Regenerate membutuhkan alasan dan template valid.
- [ ] **Q-RG02** Nomor surat existing dipertahankan kecuali perubahan eksplisit berizin.
- [ ] **Q-RG03** Success mengaktifkan artefak baru dan menandai lama superseded.
- [ ] **Q-RG04** Failure mempertahankan artefak aktif lama.
- [ ] **Q-RG05** Histori dan checksum lama/baru dapat diaudit.
- [ ] **Q-RG06** Request #1 menghasilkan PDF dengan data yang benar; PNG lama tetap berlabel image/png.

### Download dan keamanan

- [ ] **Q-DL01** PDF dikirim sebagai `application/pdf` dengan `.pdf`.
- [ ] **Q-DL02** PNG legacy dikirim sebagai `image/png` dengan `.png`, atau diblokir dengan pesan yang benar sesuai policy.
- [ ] **Q-DL03** Missing/corrupt file tidak dikirim sebagai response sukses.
- [ ] **Q-DL04** Filename aman dari CRLF/path traversal dan Unicode edge cases.
- [ ] **Q-DL05** Content-Length dan body checksum sesuai record.
- [ ] **Q-SC01** Admin route memerlukan auth dan permission sesuai aksi.
- [ ] **Q-SC02** Warga tidak dapat mengunduh artefak request lain (IDOR).
- [ ] **Q-SC03** Direct artifact ID tidak melewati ownership/status check.
- [ ] **Q-SC04** CSRF berlaku untuk create/edit/activate/generate/regenerate.
- [ ] **Q-SC05** Literal/prefix/suffix tidak menerima executable expression.
- [ ] **Q-SC06** Storage path tidak berasal dari raw request input.
- [ ] **Q-SC07** Log tidak membocorkan NIK, nomor telepon, atau isi dokumen penuh.
- [ ] **Q-SC08** Rate limiting berlaku pada status lookup/download publik.

### Public form

- [ ] **Q-PF01** Hanya variable aktif untuk layanan terpilih yang tampil.
- [ ] **Q-PF02** Required/type/options divalidasi identik client dan server.
- [ ] **Q-PF03** Snapshot label/key/type/value tetap tersedia setelah variable diarsipkan.
- [ ] **Q-PF04** Submit saat konfigurasi berubah memberi recovery tanpa silent data loss.

### UI, accessibility, dan responsive

- [ ] **Q-UX01** Semua halaman mempunyai loading, empty, success, warning, dan error state yang relevan.
- [ ] **Q-UX02** Action utama tunggal dan action berbahaya tidak berdekatan tanpa guard.
- [ ] **Q-UX03** Seluruh alur dapat dijalankan hanya dengan keyboard.
- [ ] **Q-UX04** Focus order dan focus-visible jelas; dialog mengelola focus dengan benar.
- [ ] **Q-UX05** Label/error/helper terhubung melalui semantic HTML/ARIA.
- [ ] **Q-UX06** Status tidak disampaikan dengan warna saja.
- [ ] **Q-UX07** Contrast memenuhi WCAG AA.
- [ ] **Q-UX08** Reduced motion dihormati.
- [ ] **Q-UX09** Tidak ada horizontal overflow pada 320px.
- [ ] **Q-UX10** Builder usable pada 768/1024/1440px; inspector tidak menutup aksi penting.
- [ ] **Q-UX11** Tabel tetap terbaca dan aksi dapat diakses pada mobile.
- [ ] **Q-UX12** Browser test minimal Chromium/Edge desktop dan emulasi mobile.

### Operasional dan release

- [ ] **Q-OP01** Log generation mempunyai request ID, template version, actor, duration, result, correlation ID.
- [ ] **Q-OP02** Dashboard/metric membedakan mapping, parsing, storage, dan notification failure.
- [ ] **Q-OP03** Orphan cleanup berjalan dry-run dahulu dan menghormati retention.
- [ ] **Q-OP04** Backup dan restore database/storage telah direhearsal.
- [ ] **Q-OP05** Rollback aplikasi tidak membuat schema baru tak terbaca oleh versi lama selama deployment window.
- [ ] **Q-OP06** Runbook mencakup stuck generation, missing file, duplicate number, dan regenerate.
- [ ] Full automated test suite lulus.
- [ ] `npm run build` lulus tanpa warning blocker.
- [ ] Browser E2E merekam approve → generate → preview/download dan regenerate.
- [ ] Tidak ada perubahan data request #1 sebelum backup dan nomor surat resmi disetujui.

## 10. Definition of Done

Fitur dianggap selesai hanya jika:

1. Semua task T01–T18 selesai sesuai dependency.
2. Seluruh QA blocker lulus; exception terdokumentasi dengan owner dan tenggat.
3. Template aktif tidak mempunyai orphan/unknown mapping atau elemen debug.
4. Approve/publish dan regenerate terbukti atomik melalui concurrency/failure test.
5. Isi PDF diverifikasi, bukan hanya HTTP 200 atau file exists.
6. Download memverifikasi body, MIME, ekstensi, authorization, dan checksum.
7. UI lolos keyboard, responsive viewport, dan accessibility checks.
8. Backup, migration, rollback, serta repair request #1 mempunyai bukti eksekusi.

## 11. Dependency dan keputusan yang masih dibutuhkan

- Nomor surat resmi untuk repair request #1.
- Kebijakan siapa yang boleh mengubah nomor saat regenerate.
- Daftar format tanggal resmi desa yang akan masuk allowlist.
- Retention period artefak superseded/orphan.
- Permission role untuk mengelola variable, mengaktifkan template, publish, dan regenerate.

Keputusan tersebut tidak menghalangi T01–T15; hanya activation policy, production repair, dan release gate yang menunggu keputusan final.
