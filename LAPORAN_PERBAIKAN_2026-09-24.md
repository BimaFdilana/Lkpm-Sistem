# Laporan Audit dan Perbaikan Aplikasi LKPM

Tanggal: 24 September 2026
Branch: `fix/audit-production-readiness`

## Ringkasan hasil

Perbaikan sisi aplikasi, pengujian, migrasi database lokal, rebuild baseline, dan smoke test telah diselesaikan. Seluruh 67 pengujian otomatis lulus dengan 301 assertion. Build frontend, kompilasi Blade, daftar route, scheduler, dan audit dependency juga lulus.

Sebelum migrasi, database `test2` dicadangkan ke `storage/app/private/pre-audit-migration-2026-09-24.sql`. Berkas tersebut berada di direktori private yang diabaikan Git.

## Perbaikan yang diterapkan

### 1. Keamanan proses impor

- Waktu visibilitas antrean database diselaraskan dengan timeout impor besar.
- Satu batch tidak dapat diproses bersamaan oleh dua worker.
- File identik ditolak menggunakan checksum.
- Impor yang valid tetap berstatus `ready` bila pemindahan Google Drive gagal.
- Tersedia tombol pengulangan pemindahan file tanpa mengimpor ulang data.

### 2. Siklus file Google Drive

- File baru masuk ke folder sumber sesuai jenis data.
- File berhasil diproses dipindahkan ke `05-ARSIP`.
- File gagal diproses dipindahkan ke `04-IMPORT-GAGAL`.
- Riwayat status Drive, pesan error, dan waktu pemindahan disimpan pada batch.
- Tersedia pemeriksaan akses folder utama beserta lima subfolder.
- File tidak dihapus; proses menggunakan pemindahan dan penamaan arsip.

### 3. Target tahunan dan triwulan

- Kadis wajib mengisi alokasi TW I sampai TW IV.
- Jumlah empat alokasi wajib sama dengan target tahunan.
- Sistem tidak lagi membuat pembagian proporsional berdasarkan data yang belum lengkap.
- Dashboard menggunakan versi target tahunan terbaru sebagai sumber utama.

### 4. Prioritas harian

- Riwayat realisasi menggunakan satu laporan `Disetujui` terakhir pada setiap TW.
- Proyeksi historis dihitung dari selisih antarakumulasi TW, bukan dari beberapa laporan dalam TW yang sama.
- Baseline periode dapat dibangun ulang melalui command yang membutuhkan `--force`.
- Rebuild hanya mengganti baseline turunan dan tidak menghapus snapshot historis.
- Snapshot otomatis dijadwalkan setiap 10 menit dan baru berjalan setelah jam WIB yang diatur.
- Snapshot ditunda bila masih ada impor berstatus `processing`.

### 5. Assignment PIC

- Pembagian awal menggunakan hasil snapshot prioritas, maksimal 100 perusahaan, dengan metode ular agar jumlah tugas antarpic seimbang.
- Urutan antrean mempertimbangkan kelompok prioritas, konsistensi laporan empat TW terakhir, aktivitas dua TW terakhir, proyeksi kontribusi, dan sisa potensi.
- Kandidat cadangan tidak tampil sebagai tugas PIC sebelum diaktifkan otomatis.
- Satu sinyal risiko dapat mengaktifkan satu cadangan per PIC secara adil.
- Setelah PIC pertama kali menindaklanjuti perusahaan, tugas tersebut masuk History dan perusahaan berikutnya diberikan kepada PIC yang sama bila antrean tersedia.
- Riwayat assignment tidak dihapus saat berganti TW.

### 6. Rekonsiliasi kode proyek

- Hanya laporan canonical yang belum terhubung yang masuk daftar rekonsiliasi.
- Filter tahun, TW, dan kode proyek tersedia.
- Daftar dapat diekspor ke CSV.
- Impor DP.Proyek berikutnya otomatis mencoba menautkan laporan lama dengan kode normalisasi yang sama.
- Pemetaan manual yang sudah diverifikasi dipakai kembali pada impor selanjutnya.

### 7. Data kontak perusahaan

- Sumber laporan dan waktu sinkron kontak disimpan.
- Nomor telepon Indonesia dinormalisasi ke awalan `+62`.
- Nilai lama yang valid tidak dihapus bila laporan terbaru tidak mengisi salah satu kolom kontak.
- Detail assignment menampilkan sumber data kontak untuk kebutuhan audit.

### 8. Hak akses

- PIC hanya dapat melihat dan memperbarui assignment miliknya.
- Kepala Bagian tidak dapat mengubah target tahunan Kadis.
- Kadis tidak dapat menjalankan pembagian assignment atau membuat periode.
- Akses halaman impor dan rekonsiliasi tetap dibatasi sesuai role.

### 9. Kesiapan produksi

- Ditambahkan `.env.production.example` dengan konfigurasi aman.
- README memuat kebutuhan server, Supervisor, cron scheduler, deployment, backup, pemulihan, dan pemeriksaan prarilis.
- Queue worker produksi disarankan memakai timeout 1.200 detik dan `retry_after` 1.260 detik.
- Web root wajib diarahkan ke `public`, HTTPS diwajibkan, dan JSON OAuth harus berada di luar direktori publik.

## Tindakan pada database lokal

- Backup dibuat sebelum migrasi, ukuran sekitar 16 MB.
- SHA-256 backup: `6536579e6b513077f7dd06a1b7dbbb30e67471a6fada034a75023e1ca5abb891`.
- Seluruh migrasi berstatus `Ran`.
- Baseline TW III 2026 dibangun ulang untuk 3.388 proyek.
- Sebanyak 10.164 snapshot historis tetap tersimpan.
- Sumber kontak disinkronkan ulang untuk 224 perusahaan.

## Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---:|
| PHPUnit | 67 lulus, 301 assertion |
| Laravel Pint | Lulus |
| Blade view cache | Lulus |
| Route Laravel | 38 route terdaftar |
| Scheduler | `priority:snapshot --scheduled` setiap 10 menit |
| Build Vite | Lulus |
| Composer validate | Valid |
| Composer security audit | 0 advisory |
| npm security audit | 0 vulnerability |
| HTTP `/login` | 200 |
| HTTP halaman privat tanpa login | 302 ke `/login` |

## Kondisi data saat laporan dibuat

| Data | Jumlah |
|---|---:|
| Perusahaan | 461 |
| Proyek | 3.388 |
| Seluruh laporan LKPM | 5.046 |
| Laporan canonical | 4.838 |
| Laporan canonical belum tertaut | 1.537 |
| Kode LKPM unik belum tertaut | 298 |
| Perusahaan tanpa email | 237 |
| Perusahaan tanpa telepon | 237 |
| Proyek tanpa sektor | 1 |
| Assignment aktif yang sudah ada | 100 |

Sebanyak 1.537 laporan belum tertaut tidak dipetakan secara otomatis karena 298 kode tersebut tidak memiliki kecocokan pasti pada DP.Proyek saat ini. Memaksakan hubungan akan berisiko memasangkan LKPM ke perusahaan yang salah. Data tersebut sudah disediakan pada menu Rekonsiliasi untuk diverifikasi terhadap DP.Proyek resmi atau dipetakan manual oleh Kepala Bagian.

Kontak yang masih kosong juga tidak diisi dengan data rekaan. Angka tersebut berarti laporan LKPM terhubung yang tersedia belum mengandung email atau telepon yang dapat dipercaya dan perlu dilengkapi saat konfirmasi PIC atau impor data resmi berikutnya.

## Langkah sebelum server produksi dibuka

1. Pasang ekstensi PHP `intl` bersama ekstensi yang disebutkan dalam README.
2. Isi `.env` produksi dan gunakan user MySQL khusus aplikasi, bukan `root`.
3. Pasang konfigurasi HTTPS/Nginx, Supervisor, dan cron scheduler di server.
4. Jalankan tombol pemeriksaan enam folder Google Drive setelah OAuth produksi dihubungkan.
5. Lakukan simulasi satu siklus: impor, snapshot, pembagian 100 tugas, tindak lanjut PIC, aktivasi cadangan, dan impor LKPM `Disetujui`.
6. Verifikasi sampel hasil prioritas bersama Kadis/Kabag sebelum sistem dipakai sebagai dasar keputusan operasional.
