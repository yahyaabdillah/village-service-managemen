# WA Bridge

Service WhatsApp bridge (Baileys) yang berjalan terpisah dari project Laravel. Project Laravel (di Vercel) memanggil service ini lewat HTTP untuk mengirim pesan/dokumen WhatsApp.

## Deploy di VPS

1. Copy project ini ke VPS, lalu isi env:
   ```bash
   cp .env.example .env
   nano .env   # isi WHATSAPP_BRIDGE_TOKEN, dst.
   ```
2. Install dependency & jalankan:
   ```bash
   npm install
   npm start
   ```
3. Buka port `WA_BRIDGE_PORT` (default `3100`) di firewall VPS:
   ```bash
   sudo ufw allow 3100/tcp
   ```
4. Supaya tetap jalan setelah SSH ditutup / restart VPS, pakai PM2:
   ```bash
   npm install -g pm2
   pm2 start "npm start" --name wa-bridge
   pm2 save
   pm2 startup
   ```
5. Scan QR code untuk login WhatsApp:
   - `GET /status` untuk cek status koneksi
   - QR code tersimpan di `<WA_BRIDGE_STORAGE>/qr.png` dan `qr.txt`, atau tampil di terminal (`pm2 logs wa-bridge`)

## Hubungkan ke Laravel (Vercel)

Di `.env` Laravel, set:
```
WHATSAPP_BRIDGE_URL=http://IP-VPS:3100
WHATSAPP_BRIDGE_TOKEN=<harus sama dengan WHATSAPP_BRIDGE_TOKEN di sini>
```

> Catatan: koneksi ini HTTP polos (tidak terenkripsi) karena diakses langsung lewat IP:port tanpa domain/SSL. Token dan isi pesan/dokumen dikirim tanpa enkripsi transport. Kalau butuh keamanan lebih, pasang Nginx + Let's Encrypt di depan service ini nanti.

## Endpoint

| Method | Path | Fungsi |
|---|---|---|
| GET | `/status` | Status koneksi WhatsApp |
| POST | `/send-message` | Kirim pesan teks (`phone`, `message`) |
| POST | `/send-document` | Kirim dokumen base64 (`phone`, `filename`, `mime_type`, `document`, `caption`) |
| POST | `/disconnect` | Logout & putus sesi WhatsApp |

Semua endpoint butuh header `Authorization: Bearer <WHATSAPP_BRIDGE_TOKEN>` kalau token di-set.
