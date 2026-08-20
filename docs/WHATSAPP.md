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

Saat status pengajuan berubah melalui `ServiceRequest::transitionTo()`, sistem mengirim pesan ke nomor `service_requests.phone` bila `WHATSAPP_NOTIFICATIONS_ENABLED=true`.

## Catatan production

- Jalankan bridge dengan process manager/supervisor/systemd/PM2 terpisah.
- Jangan expose bridge ke publik tanpa reverse proxy auth/firewall.
- Gunakan `WHATSAPP_BRIDGE_TOKEN` kuat.
- Session WhatsApp tersimpan di private storage dan setara dengan kredensial akun. Jangan commit, jangan bagikan, enkripsi backup, dan ikutkan dalam backup hanya jika pairing perlu bertahan setelah restart.
- Pengiriman notifikasi berjalan lewat queue job `SendWhatsAppNotification` dan tercatat di tabel `notification_logs`.
