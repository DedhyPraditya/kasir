# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
- Kasir: melayani pembeli di toko, memakai halaman POS untuk input pesanan, pembayaran (tunai/QRIS), dan cetak struk thermal; boleh melihat laporan transaksi dan cetak ulang struk.
- Admin: pemilik/pengelola toko; mengelola produk, varian, topping, QRIS, laporan pendapatan, dan pengaturan struk/tampilan login.
- Developer: pengembang aplikasi; akses admin plus hapus transaksi uji coba dan kelola akun pengguna.

## Product Purpose
Aplikasi kasir (POS) untuk toko cemilan Nyemil Bebs: mencatat penjualan, menerima pembayaran tunai dan QRIS dinamis, mencetak struk ke printer thermal 58 mm, dan menyajikan laporan transaksi. Tersedia versi web (Laravel + Livewire) dan aplikasi Android (Flutter) yang tersinkron lewat API.

## Positioning
POS milik sendiri untuk satu toko cemilan, bukan produk SaaS umum: alur, struk, dan tampilan mengikuti identitas Nyemil Bebs.

## Operating Context
Dipakai di toko pada siang/malam hari, di PC kasir (Chrome/Edge) dengan printer thermal Bluetooth/USB, dan di HP Android lewat aplikasi mobile.

## Capabilities and Constraints
- Stack: Laravel 13, Livewire 4, Bootstrap 5.3 (CDN) + Tailwind build, Spatie Permission.
- Peran: kasir, admin, developer.
- Struk thermal 58 mm (32 karakter) lewat Web Serial di web; blue_thermal_printer di mobile.
- Header/footer struk serta logo, judul, dan deskripsi halaman login dapat diubah admin dari halaman pengaturan.

## Brand Commitments
- Nama: Nyemil Bebs (ditulis "NYEMIL BEBS" pada logo/struk).
- Warna utama hijau Bootstrap success #198754 (dipertahankan atas permintaan pemilik).
- Logo: public/logo.png (dapat diganti lewat pengaturan tampilan login).
- Produk andalan: Gabin Fla dan Banana Roll.

## Evidence on Hand
- Logo: public/logo.png.
- Tidak ada foto produk; halaman login sengaja hanya berisi tulisan (pemilik menilai foto produk terlihat kurang profesional). Jangan mengarang harga, testimoni, atau klaim lain.

## Product Principles
- Cepat dan jelas untuk kasir: tugas utama tidak boleh terhalang dekorasi.
- Identitas toko terasa di setiap layar yang dilihat pelanggan atau karyawan (login, struk).
- Pengaturan yang sering berubah (QRIS, struk, tampilan) dikelola dari aplikasi, bukan dari kode.
