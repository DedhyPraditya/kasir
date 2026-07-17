# Nyemil Bebs POS Mobile

Proyek Android terpisah untuk membuka POS Laravel production dan menjadi tempat integrasi printer thermal Bluetooth.

## Persiapan

1. Salin `.env.example` menjadi `.env`.
2. Isi `CAPACITOR_SERVER_URL` dengan domain HTTPS production Laravel, misalnya `https://pos.nyemilbebs.id`.
3. Jalankan `npm install`.
4. Jalankan `npm run sync`, lalu `npm run android` untuk membuka Android Studio.

Folder `android/` adalah proyek Android yang dibuka dan dibuild melalui Android Studio.

## Printer thermal

Target integrasi berikutnya adalah Bluetooth Classic (SPP) dengan perintah ESC/POS, supaya struk dikirim langsung ke RPP02N setelah printer dipilih satu kali pada aplikasi Android.
