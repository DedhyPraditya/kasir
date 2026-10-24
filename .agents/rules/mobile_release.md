# Aturan Rilis & Pembaruan Mobile POS (deo'ku)

Setiap kali melakukan perubahan, perbaikan, atau penambahan fitur baru pada aplikasi mobile (`mobile-pos`), langkah-langkah berikut **WAJIB** dilakukan secara sinkron:

1. **Halaman Tentang Aplikasi (`mobile-pos/lib/about_page.dart`)**:
   - Tambahkan entri baru ke array `_changelog` dengan nomor versi, tanggal rilis, dan poin-poin perubahan/fitur baru.
   - Entri paling baru harus berada di posisi teratas (indeks 0).

2. **Changelog Repositori (`mobile-pos/CHANGELOG.md`)**:
   - Tulis dokumentasi rilis lengkap dengan kategori perubahan (fitur, bugfix, visual/UI).

3. **Versi Aplikasi (`mobile-pos/pubspec.yaml`)**:
   - Perbarui versi dan nomor build (`version: X.Y.Z+build_number`).

4. **Konfigurasi Server (`config/app.php`)**:
   - Sesuaikan `mobile_version`, `mobile_build_number`, dan `mobile_changelog` agar endpoint pengecekan pembaruan (`/api/app-version` atau `/download-apk`) sinkron dengan versi rilis.

5. **Build APK Rilis**:
   - Build APK rilis (`flutter build apk --release`) dan salin ke `storage/app/public/mobile-pos-latest.apk` bila diperlukan pembaruan unduhan APK server.
