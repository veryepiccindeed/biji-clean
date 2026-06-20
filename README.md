# ☕ BIJI — Blockchain Integrated Journey Intelligence

Pilih Bahasa / Choose Language:
[Bahasa Indonesia](README.md) | [English](README.en.md)
---
> Platform manajemen rantai pasok kopi specialty Indonesia, dengan fokus pada kopi khas **Sulawesi**, yang didukung teknologi blockchain untuk menjamin keaslian dan transparansi setiap batch dari ladang ke tangan pembeli.

---

## 📖 Daftar Isi

- [Tentang Proyek](#-tentang-proyek)
- [Siapa Saja Penggunanya?](#-siapa-saja-penggunanya)
- [Fitur Utama](#-fitur-utama)
- [Alur Kerja Sistem](#-alur-kerja-sistem)
- [Tech Stack](#-tech-stack)
- [Struktur Proyek](#-struktur-proyek)
- [Cara Menjalankan Lokal](#-cara-menjalankan-lokal)
- [Variabel Lingkungan](#-variabel-lingkungan)
- [API Overview](#-api-overview)
- [Database](#-database)
- [Blockchain & Smart Contract](#-blockchain--smart-contract)
- [Desain & Tampilan](#-desain--tampilan)
- [Menjalankan Tes](#-menjalankan-tes)
- [Kontribusi](#-kontribusi)

---

## 🌱 Tentang Proyek

**BIJI** adalah sistem berbasis web yang menghubungkan tiga pihak dalam rantai pasok kopi:

1. **Petani** — mencatat dan memantau hasil panen mereka
2. **Eksportir** — mengakuisisi batch panen, menerbitkan sertifikat digital, dan memasarkannya
3. **Pembeli (Buyer)** — membeli batch kopi bersertifikat dan memverifikasi keasliannya

Yang membuat BIJI berbeda dari sistem manajemen biasa adalah penggunaan **blockchain Polygon** sebagai "notaris digital". Setiap sertifikat yang diterbitkan eksportir akan didaftarkan ke jaringan blockchain, sehingga pembelinya bisa membuktikan secara kriptografis bahwa dokumen tersebut asli dan belum pernah dimanipulasi — bahkan tanpa harus mempercayai pihak manapun.

Sistem ini dirancang untuk ekosistem **tertutup** — semua aktor harus terdaftar dan terautentikasi. Tidak ada data yang bisa diakses secara publik tanpa login.

---

## 👥 Siapa Saja Penggunanya?

| Peran | Akses Utama | Perangkat |
|---|---|---|
| **Petani** | Mencatat batch panen, input log harian (suhu & kelembapan), menandai batch siap ekspor | HP / Mobile (dirancang offline-first) |
| **Eksportir** | Melihat & mengakuisisi batch dari petani, menerbitkan sertifikat digital ke blockchain, merilis batch untuk dijual | Desktop / Tablet |
| **Buyer** | Menelusuri marketplace, membeli batch bersertifikat, memverifikasi keaslian via QR code atau hash | Desktop & HP |

Setiap peran memiliki **tema warna tersendiri** yang otomatis diterapkan setelah login:
- 🌿 **Petani** → Hijau daun
- ☕ **Eksportir** → Cokelat kopi
- ✨ **Buyer** → Emas

---

## ✨ Fitur Utama

### Untuk Petani
- ✅ Daftar batch panen baru (varietas, GPS kebun, elevasi, tanggal panen)
- ✅ Input log harian suhu & kelembapan dengan slider interaktif
- ✅ Lihat status semua batch milik sendiri
- ✅ Ubah status batch menjadi "siap ekspor"
- ✅ **Mode offline** — data tetap bisa diinput tanpa internet, dan akan tersinkronisasi otomatis saat koneksi pulih

### Untuk Eksportir
- ✅ Lihat daftar batch petani yang sudah siap diakuisisi
- ✅ Indikator peringatan kesehatan data log (jika suhu/kelembapan tidak normal)
- ✅ Akuisisi batch dengan sekali klik (dijamin tidak bisa diklaim dua kali)
- ✅ Terbitkan sertifikat digital: generate PDF → hitung SHA-256 → daftarkan ke blockchain Polygon
- ✅ Auto-retry jika pendaftaran ke blockchain gagal (maks. 3x, dengan interval yang makin panjang)
- ✅ Rilis batch ke marketplace dengan menetapkan harga jual

### Untuk Buyer
- ✅ Jelajahi marketplace batch kopi bersertifikat
- ✅ Lihat profil petani, data produksi, grafik log pascapanen per batch
- ✅ Beli batch dengan alur 3 langkah yang sederhana
- ✅ Verifikasi keaslian sertifikat via **scan QR code** atau **input manual hash**
- ✅ Unduh PDF sertifikat dan lihat bukti transaksi di Polygon Explorer
- ✅ Kelola koleksi batch yang sudah dibeli

### Untuk Semua Pengguna
- ✅ Manajemen sesi multi-perangkat (lihat & logout dari perangkat lain)
- ✅ Preferensi personal (satuan suhu, elevasi, alamat, dll.)
- ✅ Notifikasi real-time status aktivitas

---

## 🔄 Alur Kerja Sistem

Berikut alur lengkap dari ladang ke tangan pembeli:

```
[PETANI]
  1. Daftarkan batch panen baru
     → Sistem generate kode internal unik
  2. Input log harian (suhu & kelembapan)
  3. Tandai batch "Siap Ekspor"

       ↓ Notifikasi ke eksportir

[EKSPORTIR]
  4. Lihat batch siap ekspor & cek data log
  5. Akuisisi batch
     → Sertifikat "draft" otomatis dibuat
  6. Terbitkan sertifikat:
     a. Generate PDF sertifikat
     b. Hitung SHA-256 dokumen
     c. Kirim hash ke Smart Contract Polygon
     d. Simpan tx_hash → status "published"
     e. Generate QR Code verifikasi
  7. Rilis batch ke marketplace (tetapkan harga)

       ↓ Batch muncul di marketplace

[BUYER]
  8. Temukan & beli batch di marketplace
  9. Konfirmasi pembayaran
     → Kepemilikan digital terkunci atas nama buyer
 10. Verifikasi keaslian via QR / hash
     → Sistem query Smart Contract Polygon
 11. Unduh PDF sertifikat
```

---

## 🛠 Tech Stack

### Backend
| Teknologi | Versi | Fungsi |
|---|---|---|
| **PHP** | 8.3+ | Bahasa pemrograman utama |
| **Laravel** | v13 | Backend framework & API layer |
| **Laravel Sanctum** | v4 | Autentikasi berbasis sesi & token |
| **Laravel Queues** | — | Pemrosesan blockchain secara async |
| **DomPDF** | v3 | Generate PDF sertifikat |
| **MySQL** | — | Database operasional utama |

### Frontend
| Teknologi | Versi | Fungsi |
|---|---|---|
| **Vue.js** | v3 | Framework SPA (Single Page App) |
| **Vue Router** | v5 | Navigasi antar halaman |
| **Pinia** | v3 | State management |
| **Chart.js** | v4 | Grafik log pascapanen |
| **Leaflet** | v1.9 | Peta interaktif lokasi kebun |
| **Axios** | v1 | HTTP client |
| **TailwindCSS** | v4 | Styling |
| **Vite** | v8 | Bundler frontend |

### Blockchain
| Teknologi | Fungsi |
|---|---|
| **Polygon Amoy Testnet** | Jaringan blockchain untuk registrasi hash |
| **Solidity Smart Contract** | Menyimpan & memverifikasi hash sertifikat |
| **ethers.js / Laravel-Web3** | Library interaksi blockchain dari sisi server |
| **Alchemy / Infura** | RPC Provider (gateway ke jaringan Polygon) |

---

## 📁 Struktur Proyek

```
biji-kopi/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # API controllers per modul
│   │   ├── Middleware/        # Auth & role middleware
│   │   └── Requests/          # Form validation
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic (BlockchainService, dll.)
│   └── Traits/                # Reusable logic
├── context/                   # Dokumen desain (SRS, SDD, UI Guide, DB Schema)
├── database/
│   ├── migrations/            # Skema tabel database
│   ├── factories/             # Factory untuk testing
│   └── seeders/               # Data awal
├── resources/
│   ├── css/                   # Stylesheet global
│   └── js/
│       ├── views/             # Halaman Vue per role
│       │   ├── auth/          # Login, register, forgot password
│       │   ├── farmer/        # Dashboard petani
│       │   ├── exporter/      # Dashboard eksportir
│       │   └── buyer/         # Dashboard buyer
│       ├── components/        # Komponen Vue yang dapat digunakan ulang
│       ├── stores/            # Pinia stores
│       └── router/            # Konfigurasi Vue Router
├── routes/
│   ├── api.php                # Semua endpoint API
│   └── web.php                # Catch-all untuk SPA
└── tests/                     # Feature & unit tests (PHPUnit)
```

---

## 🚀 Cara Menjalankan Lokal

### Prasyarat
- PHP 8.3+
- Composer
- Node.js & npm
- MySQL

### Langkah Instalasi

**1. Clone repositori**
```bash
git clone https://github.com/veryepiccindeed/biji-clean.git
cd biji-kopi
```

**2. Setup otomatis (satu perintah)**
```bash
composer run setup
```

Perintah ini akan secara otomatis:
- Menginstal semua dependensi PHP (`composer install`)
- Menyalin `.env.example` ke `.env`
- Generate application key
- Menjalankan migrasi database
- Menginstal dependensi Node.js
- Build aset frontend

**3. Konfigurasi database**

Edit file `.env` dan sesuaikan kredensial database:
```env
DB_DATABASE=biji_kopi
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan migrasi (jika belum):
```bash
php artisan migrate
```

**4. Jalankan server pengembangan**
```bash
composer run dev
```

Perintah ini menjalankan **empat proses sekaligus** secara paralel:
- 🟦 `php artisan serve` — server Laravel di `http://localhost:8000`
- 🟣 `php artisan queue:listen` — worker untuk antrian blockchain jobs
- 🟥 `php artisan pail` — live log viewer
- 🟠 `npm run dev` — Vite HMR untuk hot reload frontend

Buka browser ke: **`http://localhost:8000`**

---

## 🔧 Variabel Lingkungan

Salin `.env.example` ke `.env`, lalu sesuaikan nilai berikut:

```env
# Aplikasi
APP_NAME=BIJI
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biji_kopi
DB_USERNAME=root
DB_PASSWORD=

# Queue (diperlukan untuk blockchain jobs)
QUEUE_CONNECTION=database

# Blockchain (isi dengan konfigurasi Alchemy/Infura Anda)
# BLOCKCHAIN_RPC_URL=
# BLOCKCHAIN_PRIVATE_KEY=
# BLOCKCHAIN_CONTRACT_ADDRESS=

# Email (untuk fitur reset password)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
```

> **⚠️ Keamanan:** Jangan pernah meng-commit file `.env` ke version control. Private key blockchain khususnya **tidak boleh** pernah ter-expose.

---

## 📡 API Overview

Semua endpoint API berada di bawah prefix `/api/` dan **wajib autentikasi** (menggunakan Laravel Sanctum). Tidak ada endpoint publik.

### Modul Auth
| Method | Endpoint | Keterangan |
|---|---|---|
| `POST` | `/api/auth/register` | Daftar akun baru |
| `POST` | `/api/auth/login` | Login, dapatkan sesi |
| `POST` | `/api/auth/logout` | Logout sesi aktif |
| `POST` | `/api/auth/forgot-password` | Kirim link reset via email |
| `POST` | `/api/auth/reset-password` | Reset password dengan token |

### Modul Petani (Production)
| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/productions` | Daftar batch milik petani |
| `POST` | `/api/productions` | Buat batch panen baru |
| `GET` | `/api/productions/{id}` | Detail batch + log-nya |
| `PATCH` | `/api/productions/{id}/status` | Update status batch |
| `POST` | `/api/productions/{id}/logs` | Tambah log harian |

### Modul Eksportir (Acquisition & Certification)
| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/productions/available` | Batch petani siap diakuisisi |
| `POST` | `/api/productions/{id}/acquire` | Akuisisi batch |
| `POST` | `/api/certificates/{id}/mint` | Terbitkan ke blockchain |
| `POST` | `/api/certificates/{id}/retry-mint` | Retry jika gagal |
| `PATCH` | `/api/certificates/{id}/release` | Rilis ke marketplace |

### Modul Buyer (Marketplace & Verification)
| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/marketplace` | Daftar batch yang dijual |
| `POST` | `/api/orders` | Buat order pembelian |
| `POST` | `/api/orders/{id}/confirm-payment` | Konfirmasi pembayaran |
| `GET` | `/api/verify/{hash}` | Verifikasi keaslian via hash |
| `GET` | `/api/scan-histories` | Riwayat verifikasi |


---

## 🗄 Database

Sistem menggunakan **6 tabel utama** yang saling berelasi:

| Tabel | Fungsi |
|---|---|
| `users` | Semua aktor sistem (petani, eksportir, buyer) dengan role & preferensi personal |
| `productions` | Data batch panen (varietas, GPS, elevasi, status) |
| `production_logs` | Log harian suhu & kelembapan per batch |
| `certificates` | Sertifikat digital (hash PDF, status blockchain, QR code, harga) |
| `orders` | Riwayat transaksi pembelian batch oleh buyer |
| `blockchain_logs` | Audit trail setiap interaksi dengan jaringan Polygon |
| `scan_histories` | Riwayat verifikasi sertifikat oleh buyer |

**Hubungan antar tabel (ringkas):**
```
users
 ├── productions (sebagai farmer_id)
 │    └── production_logs
 │    └── certificates (1-to-1, UNIQUE constraint → cegah akuisisi ganda)
 │         ├── orders
 │         ├── blockchain_logs
 │         └── scan_histories
 ├── certificates (sebagai exporter_id)
 └── certificates (sebagai buyer_id, diisi setelah pembelian selesai)
```

> Kolom `production_id` di tabel `certificates` memiliki constraint `UNIQUE`, yang menjadi pengaman utama agar satu batch tidak bisa diklaim oleh lebih dari satu eksportir.


---

## ⛓ Blockchain & Smart Contract

### Cara Kerjanya (Sederhana)

Ketika eksportir menerbitkan sertifikat, sistem melakukan langkah berikut:
1. **Generate PDF** sertifikat formal
2. **Hitung fingerprint** dokumen menggunakan algoritma SHA-256 (menghasilkan kode unik 64 karakter)
3. **Kirim fingerprint** ke Smart Contract di jaringan Polygon
4. Blockchain **menyimpannya secara permanen** — siapapun bisa membuktikan bahwa dokumen tersebut terdaftar pada waktu tertentu

Saat buyer ingin **memverifikasi**, sistem memanggil fungsi `verifyHash()` di Smart Contract. Jika hash cocok, berarti dokumen asli. Jika tidak cocok, dokumen kemungkinan telah dimanipulasi.

### Smart Contract (Ringkas)

```solidity
// Menyimpan hash dokumen ke blockchain (hanya admin yang bisa)
function recordHash(bytes32 _hash) external onlyAdmin;

// Mengembalikan true jika hash sudah terdaftar
function verifyHash(bytes32 _hash) external view returns (bool);

// Mengambil timestamp pendaftaran untuk keperluan audit
function getTimestamp(bytes32 _hash) external view returns (uint256);
```

### Penanganan Kegagalan

Jika pengiriman ke blockchain gagal (misalnya jaringan sibuk), sistem otomatis mencoba ulang hingga **3 kali** dengan jeda yang makin panjang (30 detik → 2 menit → 10 menit). Eksportir juga bisa memicu retry manual dari dashboard jika diperlukan.

---

## 🎨 Desain & Tampilan

BIJI dirancang dengan konsep **"Bumi & Teknologi"** — memadukan kehangatan estetika pertanian dengan presisi tampilan aplikasi teknologi modern.

### Prinsip Desain
- **Dark mode** sebagai default, dengan latar belakang cokelat sangat gelap (`#0F0D0B`), bukan hitam pekat
- **Tipografi premium**: Playfair Display (judul), DM Sans (konten), JetBrains Mono (kode/hash)
- **Warna aksen berbeda per role** yang otomatis diterapkan setelah login
- Kartu dengan **efek garis tepi cahaya hijau tipis** untuk kesan futuristik
- **Responsif**: Mobile-first untuk petani, desktop-first untuk eksportir

### Responsivitas
| Layar | Pengguna Utama |
|---|---|
| HP (< 768px) | Petani (navigasi bawah layar, form ramah jempol) |
| Tablet (768–1024px) | Eksportir (sidebar tersembunyi) |
| Desktop (> 1024px) | Eksportir (sidebar penuh), Buyer |


---

## 🧪 Menjalankan Tes

Proyek ini menggunakan **PHPUnit** untuk testing. Semua fitur kritis harus di-cover oleh tes sebelum dianggap production-ready.

```bash
# Jalankan semua tes
php artisan test --compact

# Jalankan file tes tertentu
php artisan test --compact tests/Feature/ProductionTest.php

# Filter berdasarkan nama tes
php artisan test --compact --filter=testAcquireBatch
```

Skenario penting yang wajib di-tes:
- Perpindahan kepemilikan batch (akuisisi & pembelian)
- Pencegahan akuisisi ganda oleh dua eksportir secara bersamaan (*concurrent request*)
- Alur minting sertifikat dan penanganan kegagalan blockchain



## 🤝 Kontribusi
Proyek ini dikembangkan sebagai proyek akhir akademik (ALP Semester 6) oleh:
* [@veryepiccindeed](https://github.com/veryepiccindeed) - Backend & DevOps Architect
* [@bigbosspramana](https://github.com/bigbosspramana) - Frontend & DevOps Engineer
* [@Apryadi](https://github.com/Apryadi) - UI/UX & Smart Contract Developer
* [@FranklinJaya2006](https://github.com/FranklinJaya2006) - Database & IoT Engineer
* [@chaidenfoanto](https://github.com/chaidenfoanto) - ML Engineer
* [@levinn1](https://github.com/levinn1) - ML Engineer


Jika Anda ingin berkontribusi:

1. Fork repositori ini
2. Buat branch fitur: `git checkout -b feature/nama-fitur`
3. Commit perubahan Anda: `git commit -m 'feat: tambah fitur X'`
4. Push ke branch: `git push origin feature/nama-fitur`
5. Buat Pull Request

### Standar Kode
- Ikuti konvensi **PSR-12** untuk PHP
- Jalankan `vendor/bin/pint --dirty` sebelum commit untuk auto-format PHP
- Setiap fitur baru **wajib disertai tes**
- Gunakan `php artisan make:` untuk membuat file baru (controller, model, migration, dll.)

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).

---

<p align="center">
  Dibuat dengan ❤️ untuk kopi Sulawesi yang lebih transparan dan terpercaya.
</p>
