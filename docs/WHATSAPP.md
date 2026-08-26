# Baileys WhatsApp Bridge

Integrasi notifikasi WhatsApp memakai Baileys melalui bridge Node lokal tanpa Chromium.

## Setup

```bash
npm install
cp .env.production.example .env.production
```

Set env Laravel:

```env
WHATSAPP_NOTIFICATIONS_ENABLED=true
WHATSAPP_BRIDGE_URL=http://127.0.0.1:3100
WHATSAPP_BRIDGE_TOKEN=isi-token-rahasia
```

Set env untuk proses Node bridge:

```bash
export WHATSAPP_BRIDGE_TOKEN=isi-token-rahasia
export WA_BRIDGE_PORT=3100
export WA_BRIDGE_STORAGE=storage/app/private/whatsapp
npm run wa:bridge
```

Buka admin:

```text
/admin/whatsapp
```

Klik tombol **Mulai Pairing / Tampilkan QR**. Sistem akan menjalankan bridge Baileys dari aplikasi web, lalu status/QR bisa direfresh dari halaman yang sama.

Scan QR dari WhatsApp mobile. Status dan QR terakhir disimpan di private storage:

```text
storage/app/private/whatsapp/status.json
storage/app/private/whatsapp/qr.txt
storage/app/private/whatsapp/qr.png
storage/app/private/whatsapp/session-baileys/
```

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

- Jalankan bridge dengan process manager/supervisor/systemd/PM2 terpisah.
- Jangan expose bridge ke publik tanpa reverse proxy auth/firewall.
- Gunakan `WHATSAPP_BRIDGE_TOKEN` kuat.
- Session WhatsApp tersimpan di private storage dan setara dengan kredensial akun. Jangan commit, jangan bagikan, enkripsi backup, dan ikutkan dalam backup hanya jika pairing perlu bertahan setelah restart.
- Pengiriman notifikasi berjalan lewat queue job `SendWhatsAppNotification` dan tercatat di tabel `notification_logs`.
