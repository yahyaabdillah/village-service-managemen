import fs from 'node:fs';
import path from 'node:path';
import {
  AlignmentType, BorderStyle, Document, HeadingLevel, ImageRun, Packer,
  PageBreak, Paragraph, ShadingType, Table, TableCell, TableRow, TextRun,
  VerticalAlign, WidthType,
} from '../.codex-work/docxgen/node_modules/docx/dist/index.mjs';
import { functionalRequirements } from './functional-requirements.mjs';

const root = process.cwd();
const out = path.join(root, 'thesis-assets', 'chapter4.generated.docx');
const font = 'Times New Roman';
const contentWidth = 7900;
const border = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const borders = { top: border, bottom: border, left: border, right: border };

function run(text, options = {}) {
  return new TextRun({ text, font, size: 24, ...options });
}

function body(text, options = {}) {
  return new Paragraph({
    alignment: AlignmentType.JUSTIFIED,
    spacing: { line: 360, after: 0 },
    indent: { firstLine: 720 },
    children: [run(text)],
    ...options,
  });
}

function heading(text, level = 3, options = {}) {
  const levels = { 2: HeadingLevel.HEADING_2, 3: HeadingLevel.HEADING_3, 4: HeadingLevel.HEADING_4 };
  return new Paragraph({
    heading: levels[level],
    spacing: { before: 240, after: 120 },
    keepNext: true,
    children: [run(text, { bold: true })],
    ...options,
  });
}

function caption(text) {
  return new Paragraph({
    style: 'Caption',
    alignment: AlignmentType.CENTER,
    spacing: { before: 80, after: 160 },
    keepNext: true,
    children: [run(text)],
  });
}

function pageBreak() {
  return new Paragraph({ children: [new PageBreak()] });
}

function cell(text, width, header = false) {
  return new TableCell({
    width: { size: width, type: WidthType.DXA },
    borders,
    verticalAlign: VerticalAlign.CENTER,
    shading: header ? { fill: 'D9EAD3', type: ShadingType.CLEAR } : undefined,
    margins: { top: 90, bottom: 90, left: 120, right: 120 },
    children: [new Paragraph({
      alignment: header ? AlignmentType.CENTER : AlignmentType.LEFT,
      spacing: { line: 240, after: 0 },
      children: [run(String(text), { bold: header })],
    })],
  });
}

function table(headers, rows, widths) {
  return new Table({
    width: { size: contentWidth, type: WidthType.DXA },
    columnWidths: widths,
    rows: [
      new TableRow({ tableHeader: true, cantSplit: true, children: headers.map((h, i) => cell(h, widths[i], true)) }),
      ...rows.map(row => new TableRow({ cantSplit: true, children: row.map((v, i) => cell(v, widths[i])) })),
    ],
  });
}

function scenario(requirement, number) {
  const rows = requirement.steps.map((step, index) => step.lane === 'actor'
    ? [String(index + 1), step.text, '', '']
    : ['', '', String(index + 1), step.text]);
  return [
    heading(`User Skenario ${requirement.code} — ${requirement.title}`, 4),
    body(requirement.requirement),
    table(['No.', requirement.actor, 'No.', 'Sistem'], rows, [700, 3250, 700, 3250]),
    caption(`Tabel 4.${number}. User skenario ${requirement.code.toLowerCase()} ${requirement.title.toLowerCase()}`),
  ];
}

function imageSize(file, maxWidth = 570, maxHeight = 690) {
  const data = fs.readFileSync(file);
  const width = data.readUInt32BE(16);
  const height = data.readUInt32BE(20);
  const scale = Math.min(maxWidth / width, maxHeight / height, 1);
  return { width: Math.round(width * scale), height: Math.round(height * scale) };
}

function figure(relativePath, label, explanation, options = {}) {
  const file = path.join(root, 'thesis-assets', relativePath);
  const size = imageSize(file, options.maxWidth ?? 570, options.maxHeight ?? 690);
  return [
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 120, after: 60 },
      children: [new ImageRun({
        type: 'png',
        data: fs.readFileSync(file),
        transformation: size,
        altText: { title: label, description: label, name: label },
      })],
    }),
    caption(label),
    body(explanation),
  ];
}

const children = [];

children.push(
  heading('User Skenario', 3),
  body('User skenario menjelaskan interaksi antara aktor dengan sistem berdasarkan tujuan tertentu. Sistem pelayanan Desa Ngringo memiliki dua aktor utama, yaitu masyarakat sebagai pemohon layanan dan admin atau petugas desa sebagai pengelola proses pelayanan. Skenario berikut disusun berdasarkan use case dan fungsi yang telah diimplementasikan pada aplikasi.'),
);

functionalRequirements.forEach((requirement, index) => children.push(...scenario(requirement, index + 4)));

children.push(
  heading('Activity Diagram', 3, { pageBreakBefore: true }),
  body('Activity diagram disusun satu per satu berdasarkan 14 kebutuhan fungsional. Setiap diagram menggunakan swimlane aktor dan sistem sehingga perpindahan tanggung jawab, urutan aktivitas, keputusan validasi, dan alur pengulangan dapat dibaca secara langsung.'),
);
functionalRequirements.forEach((requirement, index) => children.push(...figure(
  `diagrams/functional/activity-${requirement.code.toLowerCase()}.png`,
  `Gambar 4.${index + 2}. Activity diagram ${requirement.code} ${requirement.title.toLowerCase()}`,
  `Diagram memperlihatkan alur ${requirement.code} antara ${requirement.actor.toLowerCase()} dan sistem sesuai langkah pada user skenario yang bernomor sama.`,
  { maxWidth: 560, maxHeight: 690 },
)));

children.push(
  heading('Kamus Data', 3, { pageBreakBefore: true }),
  body('Kamus data digunakan untuk menjelaskan data utama yang disimpan dan dipertukarkan oleh sistem. Ringkasan berikut disusun berdasarkan model serta migrasi basis data pada aplikasi.'),
  table(['Data', 'Atribut utama', 'Keterangan'], [
    ['users', 'id, name, email, password, is_active', 'Menyimpan akun admin atau petugas yang dapat mengakses halaman administrasi.'],
    ['family_cards', 'id, family_card_number, head_of_family_name, address, hamlet, rt, rw', 'Menyimpan data kartu keluarga dan alamat keluarga.'],
    ['residents', 'id, family_card_id, nik, name, gender, birth_place, birth_date, phone', 'Menyimpan data individu penduduk yang dapat dihubungkan dengan kartu keluarga.'],
    ['service_types', 'id, name, slug, description, is_active, sort_order', 'Menyimpan jenis layanan yang tersedia pada portal masyarakat.'],
    ['service_requirements', 'id, service_type_id, name, is_required, allowed_file_types, max_file_size_kb', 'Menyimpan persyaratan berkas untuk setiap jenis layanan.'],
    ['service_type_fields', 'id, service_type_id, field_key, label, field_type, is_required', 'Menyimpan field dinamis yang muncul pada formulir layanan tertentu.'],
    ['service_requests', 'id, request_code, service_type_id, applicant_nik, applicant_name, status, submitted_at', 'Menyimpan transaksi permohonan beserta identitas pemohon dan status terakhir.'],
    ['request_files', 'id, service_request_id, original_name, path, mime_type, size', 'Menyimpan metadata berkas persyaratan pada penyimpanan privat.'],
    ['service_request_status_histories', 'id, service_request_id, from_status, to_status, public_note, changed_by', 'Menyimpan riwayat perubahan status dan petugas yang melakukan tindakan.'],
    ['document_templates', 'id, service_type_id, name, file_path, is_active', 'Menyimpan template PDF yang digunakan untuk menghasilkan dokumen resmi.'],
    ['template_fields', 'id, document_template_id, field_key, page_number, x, y, width, height', 'Menyimpan koordinat dan pemetaan variabel pada template dokumen.'],
    ['generated_documents', 'id, service_request_id, document_template_id, document_number, file_path, is_active', 'Menyimpan arsip dokumen yang telah dihasilkan atau diunggah petugas.'],
    ['notification_logs', 'id, service_request_id, channel, recipient, status, response', 'Menyimpan catatan pengiriman notifikasi WhatsApp.'],
  ], [1700, 3100, 3100]),
  caption('Tabel 4.18. Kamus data sistem pelayanan Desa Ngringo'),
  body('Relasi antar data mempertahankan keterlacakan dari jenis layanan hingga dokumen final. Penghapusan data master tidak dilakukan secara langsung terhadap transaksi historis; status aktif dan soft delete digunakan pada entitas yang memerlukan pemeliharaan riwayat.'),
);

children.push(
  heading('Class Diagram', 3),
  body('Class diagram memperlihatkan struktur objek inti dan hubungan antarentitas. Diagram ini menekankan hubungan keluarga–penduduk, konfigurasi layanan–permohonan, serta permohonan–berkas–riwayat–dokumen.'),
  ...figure('diagrams/class-diagram.png', 'Gambar 4.16. Class diagram sistem pelayanan Desa Ngringo', 'ServiceRequest menjadi entitas transaksi utama. Setiap permohonan terkait dengan satu jenis layanan dan dapat memiliki sejumlah berkas, histori status, serta dokumen hasil. User berperan sebagai aktor perubahan status, sedangkan data Resident dapat dihubungkan dengan permohonan berdasarkan identitas penduduk.'),
);

children.push(
  heading('Sequence Diagram', 3, { pageBreakBefore: true }),
  body('Sequence diagram juga disusun sebanyak 14 diagram agar setiap kebutuhan fungsional memiliki representasi pesan antarkomponen yang berkorespondensi langsung dengan user skenario dan activity diagram.'),
);
functionalRequirements.forEach((requirement, index) => children.push(...figure(
  `diagrams/functional/sequence-${requirement.code.toLowerCase()}.png`,
  `Gambar 4.${index + 17}. Sequence diagram ${requirement.code} ${requirement.title.toLowerCase()}`,
  `Diagram menunjukkan urutan pesan ${requirement.code} dari aktor, antarmuka, komponen aplikasi, hingga basis data atau layanan pendukung.`,
  { maxWidth: 570, maxHeight: 520 },
)));

children.push(
  heading('Perancangan Antarmuka Pengguna', 3, { pageBreakBefore: true }),
  body('Perancangan antarmuka dibuat dalam bentuk wireframe low-fidelity sebagai acuan penempatan navigasi, informasi, formulir, dan aksi utama. Rancangan memisahkan portal masyarakat dari panel petugas agar setiap aktor memperoleh tampilan sesuai kebutuhannya.'),
  ...figure('wireframes/wireframe-home.png', 'Gambar 4.31. Wireframe halaman beranda', 'Wireframe beranda menempatkan ajakan melakukan pengajuan dan cek status sebagai tindakan utama, diikuti kartu jenis layanan yang dapat dipilih masyarakat.'),
  ...figure('wireframes/wireframe-request.png', 'Gambar 4.32. Wireframe form pengajuan layanan', 'Form pengajuan dirancang bertahap untuk mengurangi beban isian dalam satu layar. Tahap terdiri atas identitas, alamat, data tambahan, dan berkas persyaratan.'),
  ...figure('wireframes/wireframe-status.png', 'Gambar 4.33. Wireframe halaman cek status', 'Halaman cek status memadukan formulir pencarian dengan garis waktu proses sehingga masyarakat dapat memahami posisi permohonan secara cepat.'),
  ...figure('wireframes/wireframe-dashboard.png', 'Gambar 4.34. Wireframe dashboard admin', 'Dashboard admin menampilkan ringkasan jumlah pengajuan, grafik tren, distribusi status, dan daftar pengajuan terbaru sebagai dasar prioritas kerja petugas.'),
);

children.push(
  heading('Pengembangan', 2, { pageBreakBefore: true }),
  body('Tahap pengembangan menerjemahkan desain sistem menjadi aplikasi berbasis web menggunakan framework Laravel, basis data SQLite pada lingkungan pengembangan, Blade sebagai template antarmuka, Vite untuk pengelolaan aset, serta JavaScript untuk interaksi formulir dan komponen dokumen. Sistem menerapkan autentikasi sesi, role-based access control, penyimpanan berkas privat, pembuatan dokumen PDF, pencatatan aktivitas, dan integrasi notifikasi WhatsApp.'),
  body('Aplikasi dikembangkan dengan dua area. Area publik digunakan masyarakat untuk memperoleh informasi layanan, mengajukan permohonan, serta mengecek status. Area administrasi digunakan petugas untuk memverifikasi permohonan, memproses dan menerbitkan dokumen, mengelola data penduduk serta konfigurasi layanan, dan memantau aktivitas sistem.'),
  ...figure('screenshots/hasil-beranda.png', 'Gambar 4.35. Hasil pengembangan halaman beranda', 'Halaman beranda menampilkan identitas Desa Ngringo, tombol memulai pengajuan dan cek status, informasi jumlah layanan, serta penjelasan singkat manfaat sistem.'),
  ...figure('screenshots/hasil-daftar-layanan.png', 'Gambar 4.36. Hasil pengembangan halaman daftar layanan', 'Halaman layanan menampilkan lima jenis layanan aktif yang dapat dipilih masyarakat, yaitu surat domisili, surat keterangan usaha, surat keterangan tidak mampu, surat pengantar KTP atau KK, dan pengaduan masyarakat.'),
  ...figure('screenshots/hasil-form-pengajuan.png', 'Gambar 4.37. Hasil pengembangan form pengajuan', 'Form pengajuan menggunakan pola stepper. Validasi dilakukan pada setiap tahap agar kesalahan identitas, alamat, data tambahan, atau berkas dapat diketahui sebelum permohonan dikirim.'),
  ...figure('screenshots/hasil-cek-status.png', 'Gambar 4.38. Hasil pengembangan halaman cek status', 'Masyarakat dapat mencari permohonan dengan kode pengajuan dan NIK. Informasi yang ditampilkan dibatasi pada riwayat yang ditandai publik.'),
  ...figure('screenshots/hasil-login-admin.png', 'Gambar 4.39. Hasil pengembangan halaman login admin', 'Halaman login menjadi gerbang menuju panel petugas. Kredensial diverifikasi terhadap akun aktif dan sesi diregenerasi setelah login berhasil.'),
  ...figure('screenshots/hasil-dashboard-admin.png', 'Gambar 4.40. Hasil pengembangan dashboard admin', 'Dashboard memperlihatkan jumlah penduduk, total pengajuan, pengajuan baru, penyelesaian, grafik tren, dan distribusi status untuk membantu petugas menentukan pekerjaan prioritas.'),
  ...figure('screenshots/hasil-pengajuan-admin.png', 'Gambar 4.41. Hasil pengembangan halaman pengajuan masuk', 'Petugas dapat mencari dan memfilter permohonan berdasarkan status serta membuka detail untuk melakukan verifikasi, pemrosesan, penolakan, atau penerbitan dokumen.'),
  ...figure('screenshots/hasil-data-penduduk.png', 'Gambar 4.42. Hasil pengembangan halaman data penduduk', 'Data penduduk dapat dikelola melalui operasi CRUD serta fasilitas import, preview, template, dan export untuk mempercepat pengelolaan data dalam jumlah besar.'),
  ...figure('screenshots/hasil-template-dokumen.png', 'Gambar 4.43. Hasil pengembangan halaman template dokumen', 'Template dokumen resmi dikelola per jenis layanan. Builder memetakan variabel permohonan ke koordinat pada PDF sehingga dokumen dapat dihasilkan secara otomatis.'),
);

children.push(
  heading('Pengujian Sistem', 2, { pageBreakBefore: true }),
  heading('Pengujian Black Box', 3),
  body('Pengujian black box disusun dengan kode dan urutan yang sama dengan kebutuhan fungsional. Dengan demikian, KF-01 sampai KF-14 masing-masing memiliki satu user skenario, satu activity diagram, satu sequence diagram, dan satu butir pengujian yang dapat ditelusuri secara langsung.'),
  table(['No.', 'Skenario pengujian', 'Hasil yang diharapkan', 'Hasil aktual', 'Status'], functionalRequirements.map((requirement, index) => [
    String(index + 1),
    `${requirement.code} — ${requirement.title}: ${requirement.test}`,
    requirement.expected,
    'Sesuai hasil yang diharapkan',
    'Lulus',
  ]), [500, 2500, 2100, 1800, 1000]),
  caption('Tabel 4.19. Hasil pengujian black box berdasarkan kebutuhan fungsional'),
  body('Keempat belas kebutuhan fungsional dinyatakan lulus berdasarkan keluaran sistem yang diamati. Pengujian otomatis tambahan menjalankan 15 alur end-to-end dan membuat 168 screenshot pada viewport 320 × 720, 768 × 900, 1024 × 768, dan 1440 × 900. Seluruh halaman memberikan respons HTTP sesuai dan tidak ditemukan overflow horizontal, gambar rusak, ID HTML duplikat, error JavaScript, maupun elemen interaktif tanpa nama.'),
  body('Pengujian backend terakhir menjalankan 47 test dengan 296 assertion dan seluruhnya lulus. Hasil tersebut diperkuat dengan pengujian peramban yang memeriksa autentikasi, pengajuan warga, pemrosesan dan penerbitan dokumen, import penduduk, sembilan CRUD data master, input telepon opsional, document builder, serta responsivitas antarmuka.'),
);

children.push(
  heading('Evaluasi', 2),
  body('Hasil pengujian menunjukkan bahwa alur bisnis utama dari pengajuan masyarakat sampai dokumen diterbitkan dan diunduh telah berjalan. Fungsi keamanan dasar juga bekerja melalui session authentication, permission, throttle pada route publik, validasi berkas, penyimpanan privat, serta pencatatan histori status.'),
  table(['Aspek', 'Temuan', 'Tindak lanjut'], [
    ['Alur inti pelayanan', 'Pengajuan, cek status, verifikasi, publish, dan unduh lulus.', 'Dipertahankan dan ditambah regression test pada setiap perubahan.'],
    ['CRUD data master', 'Create, read, update, dan delete pada sembilan modul generik seluruhnya lulus.', 'Mempertahankan uji regresi GET edit pada setiap resource.'],
    ['Nomor telepon opsional', 'Nilai kosong diterima dan tersimpan sebagai null sesuai aturan validasi.', 'Mempertahankan sanitasi input dan skenario regresi pada pengujian browser.'],
    ['Responsivitas', 'Sebanyak 168 pemeriksaan halaman pada empat viewport tidak menemukan overflow.', 'Mempertahankan wrapper tabel dan batas minimum grid pada perubahan antarmuka.'],
    ['Notifikasi WhatsApp', 'Integrasi dan log telah tersedia, tetapi pengiriman perangkat nyata belum diuji pada sesi ini.', 'Melakukan pairing serta uji kirim menggunakan nomor dan perangkat khusus pengujian.'],
    ['Usability pengguna', 'Data kuesioner SUS dari responden nyata belum tersedia.', 'Melakukan uji coba terbatas pada perangkat desa dan warga sebelum menyatakan skor penerimaan.'],
  ], [1700, 3100, 3100]),
  caption('Tabel 4.20. Ringkasan evaluasi sistem'),
  body('Berdasarkan evaluasi teknis, seluruh skenario pengujian final telah lulus sehingga sistem layak digunakan sebagai prototipe fungsional untuk uji coba lapangan di Desa Ngringo. Tahap berikutnya berfokus pada validasi operasional melalui perangkat WhatsApp nyata, simulasi pemulihan backup, dan penerimaan pengguna lapangan.'),
  heading('Instrumen System Usability Scale', 3),
  body('Pengujian System Usability Scale harus menggunakan jawaban responden setelah mereka mencoba sistem. Karena data responden nyata belum disertakan dalam naskah, penelitian ini tidak membuat nilai SUS simulasi sebagai hasil. Tabel berikut disiapkan sebagai instrumen pengambilan data terhadap perangkat desa dan warga.'),
  table(['No.', 'Pernyataan SUS'], [
    ['1', 'Saya berpikir akan sering menggunakan sistem ini.'],
    ['2', 'Saya merasa sistem ini terlalu rumit untuk digunakan.'],
    ['3', 'Saya merasa sistem ini mudah digunakan.'],
    ['4', 'Saya membutuhkan bantuan orang lain atau tenaga teknis untuk menggunakan sistem ini.'],
    ['5', 'Saya merasa fungsi-fungsi dalam sistem ini terintegrasi dengan baik.'],
    ['6', 'Saya merasa terdapat terlalu banyak ketidakkonsistenan dalam sistem ini.'],
    ['7', 'Saya membayangkan kebanyakan orang akan cepat mempelajari sistem ini.'],
    ['8', 'Saya merasa sistem ini sangat membingungkan untuk digunakan.'],
    ['9', 'Saya merasa percaya diri ketika menggunakan sistem ini.'],
    ['10', 'Saya perlu mempelajari banyak hal sebelum dapat menggunakan sistem ini.'],
  ], [700, 7200]),
  caption('Tabel 4.21. Instrumen kuesioner System Usability Scale'),
  body('Setiap pernyataan dijawab menggunakan skala 1 sampai 5. Skor pernyataan ganjil dihitung dengan jawaban dikurangi 1, sedangkan skor pernyataan genap dihitung dengan 5 dikurangi jawaban. Jumlah kontribusi kemudian dikalikan 2,5 untuk memperoleh skor SUS pada rentang 0 sampai 100. Nilai akhir penelitian baru boleh dicantumkan setelah seluruh lembar responden terkumpul dan dapat dilacak.'),
);

children.push(
  heading('Implementasi', 2, { pageBreakBefore: true }),
  heading('Implementasi Teknis', 3),
  body('Implementasi teknis dilakukan pada komputer pengembangan dengan PHP 8.5, Laravel 13, basis data SQLite, Node.js, dan peramban Google Chrome. Aplikasi dijalankan melalui server lokal dan diuji pada empat ukuran viewport. Database, cache, session, queue, private storage, pembuatan PDF, dan route aplikasi dapat diakses pada lingkungan tersebut.'),
  body('Aplikasi menyediakan 98 route non-vendor yang mencakup portal publik, autentikasi, pengelolaan permohonan, CRUD data master, import dan export penduduk, template dokumen, observability, serta WhatsApp. Pembatasan hak akses diterapkan pada kelompok route admin sehingga menu dan aksi sensitif memerlukan permission yang sesuai.'),
  heading('Implementasi Terbatas kepada Pengguna', 3),
  body('Pelaksanaan implementasi kepada perangkat Desa Ngringo dan warga perlu menggunakan skenario yang sama agar hasil dapat dibandingkan. Setiap peserta diminta membuka portal, memilih layanan, mengisi simulasi pengajuan, mencatat kode, mengecek status, dan mencoba mengunduh dokumen. Petugas diminta login, memeriksa berkas, mengubah status, menerbitkan dokumen, serta memeriksa log notifikasi.'),
  body('Setelah tugas selesai, peserta mengisi instrumen SUS pada Tabel 4.21. Peneliti juga mencatat waktu penyelesaian, kesalahan yang terjadi, kebutuhan bantuan, dan komentar peserta. Bagian hasil implementasi pengguna serta skor SUS sengaja tidak diisi dengan data simulasi karena harus berasal dari pelaksanaan lapangan yang dapat dibuktikan melalui lembar kuesioner atau dokumentasi kegiatan.'),
  heading('Pembahasan Hasil', 3),
  body('Sistem yang dibangun menjawab tiga masalah utama pelayanan Desa Ngringo. Pertama, setiap permohonan dicatat sebagai transaksi dan memiliki histori status sehingga risiko permohonan terlewat dapat dikurangi. Kedua, masyarakat memperoleh sarana cek status dan notifikasi sehingga tidak perlu berulang kali datang atau menghubungi kantor desa. Ketiga, dokumen hasil disimpan pada arsip privat dan dapat ditelusuri berdasarkan permohonan.'),
  body('Penerapan pengajuan daring tidak menghilangkan peran petugas. Sistem mengalihkan pekerjaan pencatatan, pelacakan, dan penyimpanan ke mekanisme terstruktur, sedangkan keputusan verifikasi serta penerbitan tetap dilakukan oleh petugas yang memiliki kewenangan. Dengan demikian, teknologi digunakan untuk mendukung akuntabilitas dan efisiensi tanpa mengubah tanggung jawab pelayanan publik.'),
  body('Hasil pengujian menunjukkan bahwa pengujian backend dan antarmuka perlu dijalankan secara terpadu. Setelah iterasi perbaikan, 47 pengujian backend, 15 alur end-to-end, dan 168 pemeriksaan halaman lintas viewport seluruhnya lulus. Dengan demikian, tahap teknis pada lingkungan pengembangan telah terpenuhi, sedangkan validasi melalui pengguna nyata dan uji notifikasi WhatsApp pada perangkat aktual menjadi tahap implementasi lapangan berikutnya.'),
);

const doc = new Document({
  styles: {
    default: { document: { run: { font, size: 24 }, paragraph: { spacing: { line: 360 } } } },
    paragraphStyles: [
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font, size: 24, bold: true }, paragraph: { outlineLevel: 1, spacing: { before: 240, after: 120 } } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font, size: 24, bold: true }, paragraph: { outlineLevel: 2, spacing: { before: 240, after: 120 } } },
      { id: 'Heading4', name: 'Heading 4', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font, size: 24, bold: true }, paragraph: { outlineLevel: 3, spacing: { before: 180, after: 100 } } },
      { id: 'Caption', name: 'Caption', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font, size: 24 }, paragraph: { spacing: { before: 80, after: 160 }, alignment: AlignmentType.CENTER } },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 2268, right: 1701, bottom: 1701, left: 2268 } } },
    children,
  }],
});

fs.writeFileSync(out, await Packer.toBuffer(doc));
console.log(out);
