# Scan Tracker — ESP32 HC-SR04 → HiveMQ → PHP/MySQL Dashboard

Stack ringan: PHP + MySQL, jalan di Docker. Ada 3 service:

- **mysql** — nyimpen history hasil scan.
- **bridge** — proses PHP CLI yang jalan terus (`restart: always`), subscribe topic MQTT di HiveMQ Cloud, dan insert tiap pesan masuk ke MySQL.
- **web** — dashboard PHP + Apache yang baca dari MySQL dan nampilin data (auto-refresh tiap 3 detik lewat `fetch`, gak perlu reload).

```
ESP32 --MQTT/TLS--> HiveMQ Cloud --MQTT/TLS--> bridge --SQL--> mysql <--SQL-- web (dashboard)
```

## 1. Setup awal

```bash
cp .env.example .env
nano .env   # isi kredensial DB & MQTT sesuai punyamu
```

Isi minimal yang wajib diganti di `.env`:
- `DB_ROOT_PASSWORD`, `DB_PASS` — password MySQL, jangan pakai default.
- `MQTT_HOST`, `MQTT_USER`, `MQTT_PASS`, `MQTT_TOPIC` — samain dengan yang dipakai di kode ESP32 (`main.py`).

## 2. Jalankan

```bash
docker compose up -d --build
```

Cek log bridge buat mastiin dia berhasil connect & nerima data:

```bash
docker compose logs -f bridge
```

Harusnya kelihatan baris kayak:
```
[DB] Terhubung ke MySQL.
[MQTT] Terhubung ke xxxxx.s1.eu.hivemq.cloud:8883, subscribe topic 'esp32/sensor1/jarak'
[MQTT] [esp32/sensor1/jarak] {"status":"ok","jarak_cm":23.4,...}
```

Dashboard bisa diakses di `http://localhost:8080` (atau IP server kamu di port 8080).

## 3. Pasang ke subdomain

Container `web` expose port `8080` ke host. Kamu tinggal arahin reverse proxy yang udah ada di server (nginx/Apache/Traefik, yang mana pun yang dipakai proyek lain kamu) ke `127.0.0.1:8080`.

Contoh config nginx kalau reverse proxy-nya nginx:

```nginx
server {
    listen 443 ssl;
    server_name scan.domainkamu.com;

    ssl_certificate     /path/ke/fullchain.pem;
    ssl_certificate_key /path/ke/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

Kalau kamu pakai Traefik atau reverse proxy lain, tinggal sesuaikan — intinya cukup forward ke port 8080 di host tempat docker compose ini jalan.

## 4. Struktur proyek

```
scan-tracker/
├── docker-compose.yml
├── .env.example
├── db/
│   └── schema.sql          # struktur tabel `scans`
├── bridge/
│   ├── Dockerfile
│   ├── composer.json       # library php-mqtt/client
│   └── bridge.php          # subscriber MQTT -> insert MySQL
└── web/
    ├── Dockerfile
    ├── config.php          # koneksi PDO
    ├── api.php             # endpoint JSON buat dashboard
    └── index.php           # halaman dashboard (radar gauge, chart, log tabel)
```

## 5. Troubleshooting cepat

| Gejala | Kemungkinan penyebab |
|---|---|
| Bridge log muncul "MQTT error... reconnect" terus | Cek `MQTT_HOST`/`MQTT_USER`/`MQTT_PASS` di `.env`, atau firewall server blok port 8883 outbound |
| Dashboard nunjukin "TERPUTUS" terus padahal bridge jalan | Cek `docker compose logs web` dan `logs mysql`, biasanya masalah koneksi PDO ke MySQL |
| Data gak nambah padahal ESP32 kelihatan publish | Pastikan `MQTT_TOPIC` di `.env` bridge **sama persis** dengan topic yang dipublish ESP32 (`main.py`) |
| Halaman dashboard blank / error 500 | `docker compose logs web`, biasanya kredensial DB salah di `config.php`/`.env` |

## Catatan keamanan

- Ganti semua password default di `.env` sebelum deploy ke server publik.
- Kalau server ini publik-facing, sebaiknya tutup port `3306` di `docker-compose.yml` (hapus baris `ports` di service `mysql`) supaya MySQL cuma bisa diakses dari dalam docker network, gak dari luar.
- Jangan commit file `.env` ke git — udah ada `.env.example` sebagai template.
