# Changelog - Mobile POS Nyemil Bebs

Semua perubahan pada aplikasi Mobile POS dicatat di file ini.

## [Unreleased / Planned v1.2.0] - Rencana Pembaruan Mendatang

### 🍰 Pilihan Topping Berdasarkan Kategori (Makanan vs Minuman)
- **Opsi Topping per Kategori**: Menambahkan pengatur `allow_topping` (Izinkan Topping) pada Kategori/Produk di Dashboard Admin.
- **Kategori Makanan (Food)**: Opsi topping aktif & ditampilkan di modal kasir web & mobile.
- **Kategori Minuman (Drink)**: Opsi topping disembunyikan secara otomatis pada tampilan kasir Web POS & Mobile POS.

## [1.1.2] - 2026-08-05

### 🐛 Perbaikan Bug
- **Fix Logo Login Tidak Tampil**: Memperbaiki path asset logo Nyemil Bebs dari `assets/logo.png` menjadi `assets/images/logo.png` setelah pemindahan folder untuk kebutuhan adaptive icon.

### 🔥 Firebase Integration
- **Firebase SDK**: Integrasi `firebase_core` & `firebase_messaging` untuk dukungan push notification di masa mendatang.
- **google-services.json**: File konfigurasi Firebase resmi terpasang di proyek Android.

## [1.1.1] - 2026-08-04

### 🎨 Tampilan & Branding (UI/UX)
- **Icon Aplikasi Baru**: Mengganti icon bawaan Flutter dengan logo resmi Nyemil Bebs (lengkap dengan adaptive icon Android).
- **Navigation Bar Bawah (BottomAppBar)**: Memindahkan navigasi utama ke bawah (Home, Riwayat, Setting).
- **Indikator Printer di AppBar**: Menambahkan lampu indikator status koneksi printer Bluetooth (🟢 Hijau = Terhubung, 🟠 Oranye = Terputus) di samping tombol Logout.
- **Pembersihan Layar**: Menghapus kartu peringatan kuning printer di halaman utama agar tampilan kasir lebih rapi.

### 📄 Riwayat Transaksi & Fitur Aksi
- **Halaman Penuh Riwayat (`HistoryPage`)**: Mengubah dialog popup lama menjadi halaman penuh terpisah dengan tampilan Card ringkas per transaksi.
- **Filter Khusus Hari Ini**: Riwayat transaksi difilter secara otomatis oleh API backend agar hanya menampilkan transaksi hari ini.
- **🖨️ Cetak Ulang Struk**: Menambahkan tombol "Print Ulang" pada tiap item riwayat untuk mencetak ulang struk ke printer Bluetooth.
- **📤 Share Struk (JPG)**: Menambahkan tombol "Share" untuk menghasilkan dan membagikan gambar struk belanja ke WhatsApp/media lain.

### 🧾 Preview Struk Thermal Presisi
- **Simulasi Printer 100% Presisi**: Preview struk sebelum cetak kini menggunakan renderer teks baris-per-baris (Courier, 32 kolom) yang identik dengan cetakan fisik printer thermal 58mm.

### 🔄 Sistem Auto-Update (Tanpa Uninstall)
- **In-App Auto Update**: Aplikasi secara otomatis mengecek versi terbaru ke server saat dibuka dan menampilkan dialog pembaruan.
- **Download APK Langsung**: Menambahkan endpoint `/download-apk` dengan MIME type yang benar agar file ter-download sebagai `.apk` di browser.

### ⏰ Penanganan Waktu (Multi-Timezone)
- **Presisi Jam Perangkat**: Memperbaiki masalah double-timezone offset sehingga waktu transaksi mengikuti jam lokal smartphone pengguna secara akurat (WIB / WITA / WIT).