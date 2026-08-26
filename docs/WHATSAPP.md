# Baileys WhatsApp Bridge

Integrasi notifikasi WhatsApp memakai Baileys. Bridge Node-nya deploy terpisah dari aplikasi Laravel ini — hidup di branch `service-wa`, biasanya dijalankan di VPS (lihat `README.md` di branch tersebut untuk cara deploy).

Laravel hanya memanggil bridge itu lewat HTTP; tidak ada proses Node yang dijalankan dari dalam aplikasi Laravel.

## Setup

Set env Laravel supaya tahu ke mana harus memanggil bridge-nya:

```env
WHATSAPP_NOTIFICATIONS_ENABLED=true
WHATSAPP_BRIDGE_URL=http://IP-VPS:3555
WHATSAPP_BRIDGE_TOKEN=isi-token-rahasia
```

`WHATSAPP_BRIDGE_TOKEN` **harus sama persis** dengan `WHATSAPP_BRIDGE_TOKEN` yang di-set di `.env` service `service-wa` di VPS.

Buka admin:

```text
/admin/whatsapp
```

Halaman ini otomatis fetch status dan QR dari bridge lewat HTTP (`GET /status`, `GET /qr`). Kalau belum ter-pairing, QR langsung muncul di halaman ini — tidak ada tombol "start" karena bridge di VPS sudah selalu berjalan (dikelola PM2).

Scan QR dari WhatsApp mobile. Status, QR, dan sesi login tersimpan di VPS (bukan di server Laravel), di `WA_BRIDGE_STORAGE` yang dikonfigurasi di `.env` service-wa.

## Trigger notifikasi

Notifikasi WhatsApp otomatis terkirim pada dua momen (bila `WHATSAPP_NOTIFICATIONS_ENABLED=true`):

1. **Saat warga submit pengajuan** (`PublicController::submitRequest()`) — pesan konfirmasi berisi kode pengajuan, NIK, jenis layanan, dan link cek status.
2. **Saat status pengajuan berubah** melalui `ServiceRequest::transitionTo()` (diverifikasi/diproses/selesai/ditolak/dibatalkan) — pesan menyesuaikan status terbaru, plus catatan admin (kalau ada).

Semua pesan dikirim ke `service_requests.phone`, dengan bahasa formal + emoji, dan selalu menyertakan cara cek status (kode pengajuan + NIK di halaman `/cek-status`).

## Rate limiting

Untuk melindungi akun WhatsApp bridge dari flagging/ban akibat mengirim pesan terlalu cepat, setiap pengiriman (pesan status maupun dokumen) dibatasi dari sisi Laravel sebelum memanggil bridge:

```env
WHATSAPP_RATE_LIMIT_PER_MINUTE=20      # batas global, semua penerima
WHATSAPP_RATE_LIMIT_PER_RECIPIENT=5    # batas per nomor tujuan, per 10 menit
```

Kalau limit tercapai, pesan ditandai `failed` di `notification_logs` (untuk notifikasi status) atau melempar error yang ditampilkan ke admin (untuk kirim dokumen manual) — tidak pernah memanggil bridge sama sekali.

## Catatan production

- Bridge berjalan lewat PM2 di VPS, terpisah dari Laravel (yang di-deploy ke Vercel).
- Jangan expose bridge ke publik tanpa token yang kuat (`WHATSAPP_BRIDGE_TOKEN`); idealnya juga tambahkan reverse proxy + firewall.
- Session WhatsApp tersimpan di VPS dan setara dengan kredensial akun. Jangan commit, jangan bagikan, dan enkripsi backup-nya bila di-backup.
- Pengiriman notifikasi berjalan lewat queue job `SendWhatsAppNotification` dan tercatat di tabel `notification_logs`.
