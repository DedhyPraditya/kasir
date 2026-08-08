# 📌 Log Pengerjaan: QRIS Statis → Dynamic + Upload QRIS Baru

Dokumen ini mencatat progres implementasi fitur QRIS dynamic (nominal otomatis) dan fitur upload/ganti QRIS dari admin panel, supaya mudah dilanjutkan di sesi berikutnya.

---

## ✅ Sudah Dikerjakan

### 1. Konversi QRIS Statis → Dynamic (EMV QR)
- `app/Services/QrisService.php`
  - `generateDynamicPayload()` — parse payload EMV, ubah tag `01` (Point of Initiation) dari `11` (statis) ke `12` (dynamic), sisipkan tag `54` (nominal), hitung ulang CRC16 tag `63`.
  - `assertValidStaticPayload()` — validasi struktur tag wajib (00, 01, 53, 63) + kecocokan checksum CRC16, dipakai saat admin upload QRIS baru.
  - `getActivePayload()` — ambil payload QRIS yang sedang aktif: prioritas dari tabel `qris_settings` (baris terbaru), fallback ke `config('qris.static_payload')` kalau belum pernah upload.
  - Logic CRC16 & parsing diverifikasi manual (cross-check Node.js) **dan** lewat automated test (lihat poin 7).

### 2. Endpoint API untuk Mobile & Web
- `app/Http/Controllers/Api/QrisController.php` — `GET /api/qris/dynamic?amount=...` di belakang middleware `api.token`, ditambah **rate limit `throttle:30,1`** (30 request/menit) supaya endpoint yang generate gambar ini tidak bisa dispam.
- Route terdaftar di `routes/api.php`.

### 3. Web POS (Livewire)
- `app/Livewire/Pos.php` — computed property `getQrisImageProperty()` generate QR dynamic sesuai `total` keranjang saat ini, sumber payload dari `QrisService::getActivePayload()`.
- `resources/views/livewire/pos.blade.php` — modal pembayaran QRIS render QR dynamic (base64 data URI), bukan lagi `public/qris.png` statis.
- **Sudah dites visual di browser sungguhan** (Playwright, lihat poin 8) — modal pembayaran, radio QRIS, dan QR yang ter-generate dengan nominal yang benar semuanya terkonfirmasi lewat screenshot.

### 4. Mobile POS (Flutter)
- `mobile-pos/lib/pos_page.dart`
  - Saat kasir pilih metode QRIS, app fetch `GET $backendUrl/qris/dynamic?amount=<total>` pakai header `X-Api-Token`.
  - UI menampilkan `Image.memory` dari hasil base64, dengan state loading (`CircularProgressIndicator`) dan error + tombol "Coba lagi".
  - Lolos `flutter analyze` tanpa error baru.
  - **Belum dites di device/emulator asli** — lihat bagian "Belum Dikerjakan".

### 5. Fitur Upload/Ganti QRIS (Admin Panel)
- **Migration** `database/migrations/2026_08_07_084438_create_qris_settings_table.php` — tabel `qris_settings` (`payload`, `image_path`, `updated_by` UUID → `users.id`, timestamps).
- **Model** `app/Models/QrisSetting.php`.
- **Decoder**: composer package `khanamiryan/qrcode-detector-decoder` (`Zxing\QrReader`) untuk baca kode QR dari gambar yang diupload admin.
- **Livewire** `app/Livewire/QrisSettings.php` + view `resources/views/livewire/qris-settings.blade.php`:
  - Upload foto QRIS → didekode otomatis jadi string EMV → divalidasi (`assertValidStaticPayload`) → disimpan ke `qris_settings`.
  - Alternatif: toggle untuk tempel string EMV QRIS langsung, untuk kasus foto QR sulit dibaca.
  - Preview QRIS aktif + info kapan & siapa yang terakhir mengubah.
- **Route** `GET /pengaturan/qris` (`role:admin`) di `routes/web.php`, ditambahkan ke menu sidebar & dropdown akun di `resources/views/layouts/app.blade.php`.

### 6. Riwayat QRIS & Rollback (baru)
- `QrisSetting` sekarang menyimpan histori: setiap upload/aktivasi jadi baris baru, tidak menimpa baris lama.
- `QrisSettings::activate($id)` — tombol "Aktifkan" di riwayat, membuat baris baru dengan payload lama (jadi payload itu aktif lagi), divalidasi ulang sebelum diaktifkan.
- `cleanupOldImages()` — tiap kali ada baris baru jadi aktif, file foto upload dari baris-baris lain otomatis dihapus dari storage (payload teks tetap disimpan untuk histori/rollback; preview QR di riwayat digenerate ulang dari teks payload, bukan dari file foto, jadi aman dihapus).
- View menampilkan section "Riwayat QRIS Sebelumnya" (max 6 terbaru) dengan tombol aktifkan per item + konfirmasi (`wire:confirm`).

### 7. Automated Test (PHPUnit)
- `tests/Unit/QrisServiceTest.php` — 9 test murni logic (parsing EMV, CRC16, validasi payload) tanpa DB, jalan cepat.
- `tests/Feature/QrisSettingsTest.php` — 5 test: upload manual valid, upload invalid ditolak, rollback/`activate()`, dan precedence DB vs config.
- `tests/Feature/QrisApiTest.php` — 3 test: endpoint butuh token, endpoint balikin `qr_base64` untuk token valid, endpoint tolak `amount` invalid.
- **Total 17/17 test QRIS lolos.** Full suite (`php artisan test`) juga dijalankan — 22 kegagalan lain semuanya bug lama scaffolding Laravel Breeze (asumsi kolom `name`/`email` yang memang tidak ada di skema `users` project ini) — **tidak terkait fitur QRIS, tidak disentuh**.
- Untuk bisa jalan, extension `pdo_sqlite`/`sqlite3` diaktifkan di `php.ini` Laragon (backup tersimpan di `php.ini.bak`) — dipakai khusus buat DB test in-memory Laravel, terpisah total dari MySQL project.

### 8. Test Visual di Browser (Playwright, baru)
- Login admin → `/pengaturan/qris`: preview QRIS aktif, form upload, dan riwayat semuanya render dengan benar.
- `/pos` → tambah produk ke keranjang → buka modal pembayaran → pilih QRIS → **QR code dynamic ter-generate dengan nominal yang sesuai keranjang** ("Scan QRIS untuk bayar Rp 10.000") — terkonfirmasi lewat screenshot, tanpa error di console browser.
- **Catatan**: sempat ketemu 2 baris data test nyasar di database MySQL asli (bekas verifikasi manual di sesi sebelumnya yang tidak sepenuhnya kebersihan) — sudah dibersihkan (`qris_settings` kembali 0 baris sebelum diserahkan).

---

## 🔖 Versi & Rilis

- Fitur ini dirilis sebagai **v1.2.0** aplikasi mobile (`mobile-pos/pubspec.yaml`, `mobile-pos/CHANGELOG.md`) — sebelumnya v1.1.3.
- **Penting**: `config('app.mobile_version')` di `config/app.php` (dipakai endpoint `/api/app-version` untuk notifikasi update ke pengguna yang sudah install) **sengaja belum di-bump** dari `1.1.3`. Ini baru boleh dinaikkan ke `1.2.0` setelah APK baru benar-benar di-build (`flutter build apk`) dan file-nya diupload ke `storage/app/public/mobile-pos-latest.apk` di server — supaya pengguna tidak diarahkan update ke APK yang belum tersedia.
- Perubahan backend (Laravel) tidak punya nomor versi terpisah — mengikuti versi mobile di atas sebagai penanda rilis gabungan.

---

## ⏳ Belum Dikerjakan / Next Step

- **Mobile app belum ditest di device/emulator asli.** Ada HP Android fisik tersambung (Android 13) tapi lagi dipakai user untuk debug sendiri, jadi tidak disentuh. Catatan penting kalau mau lanjut: `backendUrl` di `pos_page.dart` di-hardcode ke domain production (`https://kasir.madignet.site/api`), jadi kalau mau test perubahan QRIS yang masih lokal, perlu diarahkan sementara ke IP LAN komputer dev (lalu dikembalikan lagi setelah selesai) — atau deploy dulu ke production kalau memang sudah siap rilis.
- **Belum ada audit log terpisah** untuk siapa memanggil endpoint `/api/qris/dynamic` berapa kali (rate limit sudah ada, tapi belum ada logging/monitoring).
- **`config/qris.php` (`QRIS_STATIC_PAYLOAD` di `.env`) masih jadi fallback awal** — perlu dipastikan nilai di `.env` production sudah benar sebelum admin pertama kali upload QRIS lewat panel.
- **Migration `add_api_token_to_users_table` & `create_qris_settings_table`** sudah jalan di DB lokal — perlu dipastikan juga dijalankan (`php artisan migrate`) di environment staging/production sebelum fitur ini dipakai di sana.
- **UserFactory (`database/factories/UserFactory.php`) tidak sesuai skema `users` asli** (masih pakai field `name`/`email` bawaan Laravel, padahal tabel `users` project ini cuma punya `username`) — ini bug lama yang bikin beberapa test bawaan Breeze gagal total. Di luar scope QRIS, belum diperbaiki; tes QRIS sendiri menghindarinya dengan `User::create()` manual.
