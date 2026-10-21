# 📜 Riwayat Perkembangan & Perubahan (Changelog)
### Jepara Cargo App — Platform Ekspedisi & Logistik Mebel

Semua catatan perkembangan, iterasi fitur, perbaikan bug, dan keputusan arsitektur proyek didokumentasikan di sini secara kronologis.

---

## [v2.5.0] — 21 Oktober 2026
### 🌐 Go-Live Produksi & Perencanaan Aplikasi Mobile Lapangan
- **Deployment Hosting Produksi (cPanel)**:
  - Aplikasi resmi online dan beroperasi penuh pada domain: `https://cargo.juraganfurniture.com`.
  - Konfigurasi basis data produksi (SQLite), eksekusi migrasi skema tabel, dan inisialisasi seeder akun awal (`UserSeeder`) serta identitas kargo (`SettingSeeder`).
  - Penyelarasan aset frontend Vite produksi (`public/build`) dan ekosistem PHP hosting.
- **Rencana Kerja & Roadmap Aplikasi Supir & Petugas Gudang (Capacitor Android)**:
  - Penyusunan blueprint terstruktur untuk 3 peran operasional lapangan:
    1. **Supir Penjemputan (Pickup)**: Alur jemput mebel ke pengrajin, scan muat barcode koli, integrasi navigasi Google Maps, background live GPS tracking, dan sinkronisasi offline-first.
    2. **Petugas Gudang (Checker Inbound)**: Scan verifikasi koli tiba, sistem deteksi koli tertinggal (`Sebagian di Gudang` vs `Di Gudang`), serta penyusunan koli untuk manifest truk lintas luar kota.
    3. **Supir Lintas Kota**: Surat jalan digital manifest delivery, pengaturan urutan rute antar-toko dinamis, rekapitulasi penagihan uang COD, scan serah terima barang di tujuan.
  - **Penyelarasan Role-Based Mobile Navigation**: Pengalihan login otomatis (auto-routing) bagi akun supir dan petugas gudang langsung ke antarmuka kerja masing-masing.
  - **Spesifikasi Packaging APK Android (Capacitor)**:
    - Penyelarasan target server URL ke `https://cargo.juraganfurniture.com`.
    - Pemanfaatan hardware native: Google MLKit Barcode Scanning, Geolocation Service background/foreground, Deep Link scheme `jeparacargo://`.
    - Pipeline build APK lokal menggunakan Gradle wrapper & Java JBR Android Studio.

---

## [v2.4.0] — 21 Oktober 2026
### 🚀 Pembaruan & Dokumentasi Sistem
- **Dokumentasi Terpadu Berbasis Tab (`/dokumentasi`)**:
  - Menghadirkan sistem tab ganda: **Panduan Pengguna (User SOP)** dan **Developer & Teknis (Engineering)**.
  - Memetakan 7 siklus operasional end-to-end mebel kargo: Input Resi ➔ Penjadwalan Jemput ➔ Jemput Pengrajin (Magic Link) ➔ Inbound Gudang ➔ Manifest Muat Truk ➔ Pengiriman Lintas & Live GPS ➔ Pelacakan Resi Publik.
  - Dokumentasi teknis lengkap: Model Eloquent, State Machine Resi & Koli, Katalog API/Routes, dan Komponen Reusable.
- **Keputusan Arsitektur Distribusi Aplikasi (Dual-Platform Strategy)**:
  - **Dashboard & Kasir Kantor (Web Browser / PWA Desktop)**: Dirancang untuk PC/laptop layar lebar, penginputan resi massal, monitoring peta GPS armada, dan pencetakan surat jalan A4 / struk thermal USB secara instan tanpa perlu instalasi aplikasi tambahan.
  - **Supir Armada (Android APK Native)**: Membutuhkan APK untuk menjalankan *Foreground Background GPS Service* agar pelacakan tidak mati saat layar HP terkunci atau saat supir membuka Google Maps, mendukung *offline-first* saat sinyal drop di pelosok bengkel mebel, dan integrasi deep link WhatsApp.
  - **Checker Gudang & Samping Bak Truk (Android APK Native)**: Menggunakan engine pemindai Google MLKit native yang super cepat untuk barcode kardus tertekuk/miring, fitur *WakeLock* agar layar HP tidak mati saat ditinggal angkat barang mebel, dan lampu senter hardware untuk muat malam hari.
  - **PWA Web Fallback (Akses Darurat)**: Magic Link berbasis web tetap aktif sebagai cadangan bagi supir tamu/lepas tanpa kewajiban menginstal APK terlebih dahulu.
- **Fitur Pembaruan Sistem Manual dari GitHub (Tombol 1-Klik di Settings)**:
  - Mengadaptasi arsitektur updater dari project `final`: Layanan [UpdateService.php](file:///c:/Users/HP/.gemini/antigravity-ide/scratch/jepara-cargo-app/app/Services/UpdateService.php) dan controller [SystemUpdateController.php](file:///c:/Users/HP/.gemini/antigravity-ide/scratch/jepara-cargo-app/app/Http/Controllers/SystemUpdateController.php).
  - Dilengkapi 4 fase eksekusi bertahap:
    1. **Fase 1 (Backup)**: Otomatis mencadangkan database SQLite ke `storage/app/backups/`.
    2. **Fase 2 (Download)**: Mengunduh paket kode ZIP terbaru dari GitHub branch target.
    3. **Fase 3 (Extract & Overwrite)**: Mengekstrak dan menimpa berkas kode sistem dengan proteksi ketat (tidak menimpa `.env`, `database/*.sqlite`, dan direktori `storage/`).
    4. **Fase 4 (Finalize)**: Menjalankan migrasi database otomatis `php artisan migrate --force`, reset cache `optimize:clear`, dan mencatat SHA commit aktif.
  - Tab UI interaktif di menu **Pengaturan (`/settings`)**: Menampilkan status commit lokal vs GitHub, tombol *Cek Update*, tombol *Perbarui Sekarang*, progress bar real-time, dan terminal log console.
- **Pembersihan & Optimalisasi Workspace (>2.3 GB Dibebaskan)**:
  - Membersihkan build cache mobile flutter `mobile/build/` (~2.3 GB) dan `android/build/`.
  - Menghapus file dump/temporary di root (`wilayah_head.json`, `emsifa.zip`, `cookie.txt`, file script scratch satu kali pakai).
  - Mengosongkan file log besar (`laravel.log`, `browser.log`) dan reset cache bootstrap framework via `php artisan optimize:clear`.

---

## [v2.3.0] — 13 Oktober 2026
### 🛡️ Peningkatan UX Lapangan & Checker Bak Truk
- **Modul Manifest (`Manifest.vue`)**:
  - Menambahkan **Safety Button** (proteksi tombol simpan) agar tidak rawan tertekan secara tidak sengaja oleh petugas checker saat bekerja di samping bak truk.
  - Menerapkan **Toggle Dinamis Input Resi vs Scan Barcode**: Form input teks manual dan tombol scanner kamera bergantian otomatis ketika kolom input diisi teks.
  - Menambahkan **Counter / Penghitung Jumlah Resi Real-time** yang telah dimuat ke dalam manifest truk.
- **Modul Inbound Gudang (`Inbound.vue`)**:
  - Pemindaian barcode koli yang tiba dari armada penjemputan.
  - Validasi status fisik otomatis: `Sebagian di Gudang` vs `Di Gudang` (jika seluruh koli lengkap).

---

## [v2.2.0] — 11 Oktober 2026
### 📱 Supir Magic Link & Live Telemetri GPS
- **Akses Supir Tanpa Sandi (Magic Link)**:
  - Route `/supir/pickup?token=...` dan `/supir/lintas?token=...` dengan pengamanan token acak 48 karakter.
  - Integrasi WhatsApp Click-to-Chat otomatis mengirim link tugas langsung ke supir armada.
  - Dukungan URL scheme mobile deep link: `jeparacargo://supir/pickup?token=...`.
- **Live Background Geolocation Tracking**:
  - Implementasi Composable `useLocationTracking.js`.
  - Supir memancarkan titik koordinat lintang & bujur (`lat`, `lng`) secara berkala ke endpoint `POST /api/driver/location`.
  - Radar pemantauan armada supir di halaman `/lokasi-supir`.
- **Pelacakan Publik Transparan (`/track-resi/{resi}`)**:
  - Halaman landing `Welcome.vue` terhubung ke API pelacakan resi publik tanpa login.
  - Menampilkan timeline perjalanan, rincian koli mebel, dan foto bukti.

---

## [v2.1.0] — 10 Oktober 2026
### 📷 Barcode Scanner & Integrasi Wilayah Indonesia
- **Komponen Global `<BarcodeScanner />`**:
  - Engine pemindai barcode kamera real-time berbasis browser BarcodeDetector API dengan fallback pustaka ZXing.
  - Fitur Audio Beep nada konfirmasi scan dan getaran haptic feedback.
  - Saklar lampu senter (Torch / Flashlight toggle) untuk pemindaian malam hari.
  - Composable `useCameraManager.js` untuk manajemen stream dan pemilihan kamera belakang/depan.
- **Integrasi Master Data Wilayah (EMSIFA)**:
  - Database caching wilayah berjenjang: Provinsi ➔ Kota/Kabupaten ➔ Kecamatan ➔ Desa/Kelurahan.
  - Mekanisme *self-healing* fallback fetch on-the-fly jika master data lokal kosong.

---

## [v2.0.0] — 7–8 Oktober 2026
### 🏢 Multi-Tenant Ekspedisi & Manajemen Mitra
- **Arsitektur Multi-Tenant**:
  - Model `Expedition` sebagai root tenant kargo.
  - Pemisahan data per ekspedisi menggunakan Global Scope Eloquent.
  - Dashboard Super Admin agregator platform untuk memantau performa antar-ekspedisi se-Indonesia.
- **Pembaruan Format Cetak Resi**:
  - Desain thermal printer & surat jalan A4 standar ekspedisi kargo mebel.

---

## [v1.0.0] — 4–5 Oktober 2026
### 🏁 Rilis Fondasi Sistem (Initial Architecture)
- **Fondasi Teknologi**:
  - Laravel 13, PHP 8.5, Inertia.js v2, Vue 3 Composition API, Vite, dan Tailwind CSS.
  - PWA Support (`manifest.json`, Service Worker, offline capability).
- **Desain UI/UX Mobile-First**:
  - Auto-Hide Header saat scroll layar sentuh.
  - Komponen `<SwipeableTabs />` untuk navigasi sentuh swipe antar-halaman.
  - Komponen `<AppDrawer />` yang adaptif (Bottom Sheet di layar HP, Sticky Sidebar di Desktop).
  - Skema basis data dasar: `Shipment`, `Koli`, `Sender`, `Driver`, `Warehouse`, `TrackingLog`.
