# Observability: Grafana, Loki, dan Alloy

Jejak Audit di aplikasi menyimpan perubahan data bisnis. Stack ini melengkapi fungsi tersebut untuk melihat log aplikasi, security, dan WhatsApp secara terpusat.

## Menjalankan

```powershell
docker compose -f docker-compose.observability.yml up -d
```

Layanan lokal:

- Grafana: `http://127.0.0.1:3000`
- Loki API: `http://127.0.0.1:3101/ready`
- Alloy UI: `http://127.0.0.1:12345`

Login awal Grafana menggunakan `admin` / `admin`. Ganti melalui `GRAFANA_ADMIN_USER` dan `GRAFANA_ADMIN_PASSWORD` sebelum pemakaian selain development lokal.

Datasource Loki dan dashboard **Village Service — Application Logs** diprovision otomatis. Alloy membaca:

- `storage/logs/laravel*.log`
- `storage/logs/security*.log`
- `storage/logs/whatsapp*.log`

Semua port hanya di-bind ke `127.0.0.1`. Untuk produksi, tempatkan Grafana dan Loki di belakang autentikasi/reverse proxy, tetapkan retention sesuai kebutuhan, dan jangan mengekspos port Loki langsung ke internet.

## Operasional

```powershell
docker compose -f docker-compose.observability.yml ps
docker compose -f docker-compose.observability.yml logs --tail=100 alloy loki grafana
docker compose -f docker-compose.observability.yml down
```
