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

Saat status pengajuan berubah melalui `ServiceRequest::transitionTo()`, sistem mengirim pesan ke nomor `service_requests.phone` bila `WHATSAPP_NOTIFICATIONS_ENABLED=true`.

## Catatan production

- Bridge berjalan lewat PM2 di VPS, terpisah dari Laravel (yang di-deploy ke Vercel).
- Jangan expose bridge ke publik tanpa token yang kuat (`WHATSAPP_BRIDGE_TOKEN`); idealnya juga tambahkan reverse proxy + firewall.
- Session WhatsApp tersimpan di VPS dan setara dengan kredensial akun. Jangan commit, jangan bagikan, dan enkripsi backup-nya bila di-backup.
- Pengiriman notifikasi berjalan lewat queue job `SendWhatsAppNotification` dan tercatat di tabel `notification_logs`.
