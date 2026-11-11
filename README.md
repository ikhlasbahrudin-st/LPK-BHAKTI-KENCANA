# 🌐 LPK Bhakti Kencana Official Web & Management System

<div align="center">

[![Website Status](https://img.shields.io/badge/Website-Online-emerald?style=for-the-badge&logo=google-chrome&logoColor=white)](http://lpkpbk.my.id/)
[![PHP Version](https://img.shields.io/badge/PHP-8.x-indigo?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/Database-MySQL-blue?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/UI-TailwindCSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-Proprietary-orange?style=for-the-badge)]()

**Sistem Informasi Manajemen Terpadu & Portal Layanan Homecare / Pelatihan Kerja LPK Bhakti Kencana Cianjur.**  
Platform digital resmi yang dirancang untuk mengelola pendaftaran peserta, manajemen pekerja/layanan homecare, booking sistem, verifikasi dokumen, hingga rekapitulasi laporan keuangan secara real-time.

</div>

---

## 📌 Tentang Proyek

Website **LPK Bhakti Kencana** (`http://lpkpbk.my.id/`) dikembangkan untuk mendigitalisasi seluruh alur operasional lembaga. Sistem ini dibangun dengan pendekatan *mobile-first* menggunakan antarmuka modern yang responsif, dilengkapi sistem keamanan berbasis peran (*Role-Based Access Control*), serta fitur pelaporan terintegrasi untuk mendukung transparansi dan efisiensi manajemen lembaga.

---

## ✨ Fitur Utama Sistem

### 1. 👥 Portal Publik & Pendaftaran Mandiri
* **Landing Page Interaktif:** Informasi profil lembaga, program pelatihan, dan galeri kegiatan.
* **Pendaftaran Peserta / Pekerja Online:** Formulir pendaftaran terintegrasi dengan upload berkas (CV, KTP, Sertifikat Keahlian).
* **Sistem Booking Layanan:** Memungkinkan klien/pengguna memesan layanan homecare atau tenaga kerja secara langsung dengan kode booking unik.

### 2. 📊 Dashboard Admin & Manajemen Operasional (`/ADMIN/`)
* **Statistik & Grafik Interaktif:** Memantau tren keuangan bulanan, jumlah pendaftaran, dan sebaran data secara visual.
* **Manajemen Keuangan (`keuangan.php` & `rekap_keuangan.php`):** Pencatatan uang masuk/keluar, kategori transaksi, status pembayaran (Lunas, Sebagian, Belum Lunas, Jatuh Tempo), serta fitur cetak rekap laporan berdasarkan filter bulan dan tahun.
* **Manajemen Pekerja & Penugasan:** Monitoring status ketersediaan tenaga kerja dan riwayat penugasan.
* **Log Aktivitas (Activity Logs):** Rekam jejak seluruh aktivitas administratif yang dilakukan oleh admin/operator sistem.

### 3. 🔐 Keamanan & Kontrol Akses (RBAC)
* **Autentikasi Sesi Aman:** Sistem login ketat dengan validasi sesi dan peran pengguna.
* **Hak Akses Bertingkat:** 
  * `Super Admin`: Akses penuh konfigurasi sistem, manajemen akun admin, dan penghapusan data krusial.
  * `Admin / Staf Pengelola`: Pengelolaan data operasional harian, transaksi, dan verifikasi pendaftaran.
* **Proteksi Halaman:** Pemblokiran akses langsung (*direct URL blocking*) ke halaman sensitif bagi pengguna yang belum masuk atau tidak memiliki hak akses.

---

## 🛠️ Teknologi yang Digunakan

* **Backend:** Native PHP (Modular Architecture)
* **Database:** MySQL / MariaDB (Dikelola via phpMyAdmin / XAMPP)
* **Frontend Framework:** Tailwind CSS (CDN & Utility-first styling)
* **JavaScript & AJAX:** Live search, dynamic filtering, dan modal interaktif tanpa reload halaman penuh.
* **Server Environment:** Apache (Local XAMPP / Production Web Server)

---

## 📂 Struktur Direktori Proyek (Ringkas)

```text
/
├── assets/                  # Folder aset statis (CSS, JS, Gambar, Logo)
├── config.php               # Konfigurasi koneksi database & fungsi helper global
├── ADMIN/                   # Modul khusus Administrator & Pengelola
│   ├── header.php           # Navigasi & Header Admin
│   ├── footer.php           # Footer Admin
│   ├── index.php            # Dashboard Utama Admin & Statistik
│   ├── keuangan.php         # Kelola Transaksi Keuangan (CRUD + AJAX)
│   ├── rekap_keuangan.php   # Halaman Cetak & Filter Rekap Keuangan Bulanan/Tahunan
│   ├── booking.php          # Manajemen Pesanan / Booking Layanan
│   ├── pekerja.php          # Manajemen Data Pekerja & Homecare
│   ├── pendaftaran.php      # Verifikasi Data Pendaftar & Berkas
│   └── laporan_harian.php   # Laporan Aktivitas & Rekam Jejak
├── user_lpk.php             # Portal khusus pengguna/peserta terdaftar
└── README.md                # Dokumentasi Repositori
