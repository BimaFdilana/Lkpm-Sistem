# Sistem Target Investasi Satgas LKPM

Aplikasi internal DPMPTSP untuk mengolah DP.Proyek dan LKPM Non-UMK, menyusun prioritas perusahaan, membagi tugas PIC, serta memantau capaian investasi per triwulan dan tahunan.

## Kebutuhan server

- Ubuntu Server 24.04 LTS.
- Nginx.
- PHP 8.4-FPM beserta `bcmath`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pcntl`, `pdo_mysql`, `xml`, `zip`, dan OPcache.
- MySQL 8.
- Python 3.12 dan `openpyxl==3.1.5`.
- Composer 2 dan Node.js 22 untuk proses deployment.
- Supervisor untuk queue worker.

Spesifikasi awal yang disarankan adalah 4 vCPU, RAM 8 GB, dan NVMe SSD 100 GB.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
python3 -m venv .venv
.venv/bin/pip install -r python/requirements.txt
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan reload
```

Salin `.env.production.example` menjadi `.env`, isi seluruh kredensial, lalu jalankan `php artisan key:generate`. Jangan gunakan akun MySQL `root`. JSON OAuth harus berada di direktori `secure` yang tidak dapat diakses dari web.

Web root Nginx wajib diarahkan ke direktori `public`. Batas unggahan minimal 60 MB:

```nginx
client_max_body_size 60M;
```

Worker Supervisor harus memakai pengaturan berikut:

```ini
[program:lkpm-worker]
command=/usr/bin/php /var/www/lkpm/artisan queue:work database --sleep=3 --tries=2 --timeout=1200
directory=/var/www/lkpm
autostart=true
autorestart=true
stopwaitsecs=1260
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/lkpm/storage/logs/worker.log
```

Scheduler dijalankan setiap menit. Aplikasi sendiri akan membuat snapshot setelah `PRIORITY_SNAPSHOT_TIME` dan menunggu sampai proses impor selesai:

```cron
* * * * * cd /var/www/lkpm && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Alur impor

1. Kepala Bagian atau Programmer mengunggah DP.Proyek, LKPM Non-UMK, atau Peta Sektor.
2. File disimpan lokal dan di folder sumber Google Drive.
3. Queue menjalankan normalisasi Python dan penyimpanan MySQL.
4. Impor berhasil dipindahkan ke `05-ARSIP` tanpa menghapus file.
5. Impor gagal dipindahkan ke `04-IMPORT-GAGAL`.
6. Jika pemindahan Drive gagal tetapi data valid, batch tetap `ready` dan tombol pengulangan tersedia.
7. File identik ditolak berdasarkan checksum; laporan logis yang sama diperbarui, bukan diduplikasi.

## Alur periode dan assignment

1. Kadis menetapkan target tahunan beserta alokasi lengkap TW I–IV.
2. Kepala Bagian membuat dan mengaktifkan periode kerja.
3. Setelah impor pagi selesai, sistem membuat snapshot prioritas harian.
4. Kepala Bagian memeriksa Candidates dan membagikan maksimal 100 perusahaan awal secara rata kepada PIC.
5. PIC melihat detail perusahaan dan mencatat hasil kontak, konfirmasi, serta verifikasi.
6. Perusahaan yang sudah ditindaklanjuti berpindah ke History dan tugas prioritas berikutnya masuk otomatis.
7. Nilai indikatif PIC tidak dihitung sebagai realisasi resmi sampai LKPM berstatus `Disetujui` diimpor.
8. Assignment lama tetap tersimpan ketika triwulan baru diaktifkan.

## Rekonsiliasi

Impor DP.Proyek baru otomatis menautkan laporan lama yang kode normalisasinya sama. Pemetaan manual hanya dilakukan Kepala Bagian setelah verifikasi dokumen resmi. Daftar kode belum tertaut dapat difilter dan diekspor dari menu Rekonsiliasi.

Setelah perbaikan algoritme historis atau pembaruan besar DP.Proyek, bangun ulang baseline turunan setelah backup:

```bash
php artisan priority:rebuild-baselines --year=2026 --quarter="TW III" --force
```

Perintah tersebut tidak menghapus snapshot historis.

## Backup dan pemulihan

Backup database dan file aplikasi harus disimpan di mesin berbeda:

```bash
mysqldump --single-transaction --routines --triggers -u lkpm_backup -p lkpm_production | gzip > lkpm-$(date +%F).sql.gz
tar -czf storage-$(date +%F).tar.gz storage/app
```

Pemulihan harus diuji berkala di database simulasi, bukan langsung pada produksi.

## Pemeriksaan sebelum rilis

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
composer audit --locked
npm audit --omit=dev
php artisan route:list --except-vendor
php artisan migrate:status
```
