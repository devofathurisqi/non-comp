# Nom Comp / ChemNama LMS

Website pribadi guru kimia: PHP native 8.1+, PDO/MySQL atau MariaDB, HTML/CSS/SVG dan JavaScript vanilla. Tidak memakai Laravel, React, bundler, atau build frontend. Dependency Composer yang sudah ada: PHPMailer 6.9 untuk pemulihan password SMTP. Tidak ada dependency/service berbayar atau dependency aplikasi baru.

## Struktur dan fitur yang ditemukan

- `config/bootstrap.php`: membaca `.env`, session, koneksi database dan data beranda. Environment dari server lebih diutamakan daripada `.env`.
- `config/database.php`: PDO prepared statements, inisialisasi schema, migrasi kompatibilitas dan seed konten. Schema masih dijalankan secara idempotent setiap request seperti sebelumnya.
- `app/helpers.php`: otorisasi role, kelas aktif guru, profil, kemajuan belajar, pembacaan materi, game dan leaderboard.
- `database/schema.sql`: users/profil, modul/materi, kuis dan jawaban, essay/PR dan penilaian, forum, simulasi, games, tracking bacaan, quick quizzes dan intro siswa.
- `guru_*.php` / `siswa_*.php` / `dashboard.php`: fitur guru/siswa, pengelolaan kelas, materi/file/video, quiz PG dan timer, tugas, forum, profil, permainan cari kata/tarik garis, simulasi reaksi internal dan leaderboard.
- `api_*.php`: endpoint pencatatan penyelesaian dan leaderboard. Simulasi reaksi internal tetap tersedia.

Login sebelumnya sudah memakai `users` + `password_hash`/`password_verify`, bukan penyimpanan akun per-device. Masalah utamanya: akun demo otomatis dengan password publik, kode akses default, dan pengamanan session yang kurang. Banyak device perlu mengakses URL server PHP dan database yang sama; `localhost` di setiap device adalah server yang berbeda.

## Perubahan

- Login NIP/NIS + password, tanpa credential demo atau kode akses login. Akun didaftarkan ke database, password di-hash, session setiap browser terpisah. Satu akun juga bisa masuk di beberapa browser secara bersamaan.
- Regenerasi session saat login, cookie HttpOnly/SameSite/Secure pada HTTPS, idle timeout 2 jam, pengecekan role dari database, dan invalidasi session setelah password berubah atau akun dihapus.
- CSRF untuk login/daftar/reset dan pembatasan percobaan berbasis tabel `auth_attempts`: 10 per identifier, 60 per IP, jendela 15 menit. Batas berlaku bersama antar-browser; IP dibaca dari REMOTE_ADDR. Tidak mempercayai header proxy dari pengguna.
- Pendaftaran guru memerlukan kode undangan privat dari environment; konfigurasi kosong menutup pendaftaran guru. Siswa boleh daftar tanpa undangan jika kode siswa kosong. Email dan NIP/NIS unik; password baru 8–72 byte.
- Seed akun demo dihapus. Akun dan data lama tidak dihapus. Password demo yang sudah ada TIDAK otomatis diubah: reset atau ganti sebelum aplikasi dipublikasikan. Session lama perlu login ulang.
- Viewer `phet_viewer.php` tersedia dari halaman Materi siswa. Pilihan HTML5 resmi dalam iframe, fullscreen, atribusi, dan tautan cadangan. URL dibatasi daftar resmi, tidak menerima URL bebas.
- Soal Kuis PG mendukung gambar opsional (JPG, PNG, GIF, WEBP; maksimal 5 MB), preview, penggantian dan penghapusan gambar. Gambar tampil di kuis dalam materi, halaman kuis siswa, dan review jawaban. Kolom `questions.image_url` ditambahkan otomatis tanpa mengubah soal lama. Tes validasi gambar: `php tests/question_images_test.php`.
- Background SVG atom/orbital/molekul menggantikan foto pada halaman utama/auth/LMS, animasi transform ringan, dekorasi tidak menerima klik, responsive dan `prefers-reduced-motion`.
- File contoh konfigurasi tidak mengandung secret. `.htaccess` membatasi akses HTTP ke konfigurasi, SQL, backup, script admin, tes dan direktori internal.

## Menjalankan lokal

1. Gunakan XAMPP dengan PHP 8.1+ dan MySQL/MariaDB. Aktifkan `pdo_mysql` dan `mbstring`; OpenSSL dibutuhkan untuk SMTP TLS.
2. Letakkan project di `htdocs/Nom_Comp`, nyalakan Apache dan MySQL.
3. Jika belum punya `.env`, salin `.env.example` menjadi `.env`. Jangan menimpa `.env` existing tanpa backup.
4. Isi konfigurasi DB sesuai server. Nama default kode ini adalah `tata_senyawa`. Database/tabel dibuat otomatis untuk lokal jika `CHEMNAMA_DB_AUTO_CREATE=1` dan user DB punya izin.
5. Isi `CHEMNAMA_GURU_ACCESS_CODE` dengan nilai acak privat, lalu daftar guru dengan NIP, email sendiri dan password sendiri. Daftar siswa dengan NIS berbeda. Kode undangan boleh diganti atau dikosongkan setelah guru terdaftar.
6. Buka `http://localhost/Nom_Comp/`. Device lain harus membuka alamat server yang sama melalui LAN/hosting.

Alternatif setelah MySQL aktif: `php -S 127.0.0.1:8000`, kemudian buka `http://127.0.0.1:8000`. Development server PHP hanya untuk lokal. `.htaccess` hanya diproses Apache; jangan memakai development server untuk publik.

Tidak ada perintah build atau lint framework. Jalankan lint PHP pada semua file aplikasi (`php -l nama-file.php`), `node --check assets/js/app.js`, dan `php tests/auth_test.php` dengan ekstensi `pdo_sqlite` untuk fixture tes. Tes mencakup 14 pemeriksaan autentikasi; belum menggantikan pengujian MySQL/browser nyata.

## Environment

File yang berubah/ditambahkan untuk pekerjaan ini: `login.php`, `register.php`, `forgot-password.php`, `app/helpers.php`, `app/auth.php`, `config/bootstrap.php`, `config/database.php`, `database/schema.sql`, `phet_viewer.php`, `siswa_materi.php`, `guru_materi.php`, `assets/css/style.css`, `assets/img/chemistry-pattern.svg`, `.env.example`, `assets/.env.example`, `.htaccess`, `.gitignore`, `scripts/reset-password.php`, `tests/auth_test.php`, dan `README.md`. Perubahan lokal `dashboard.php` yang sudah ada sebelum pekerjaan ini tetap dipertahankan.

- Wajib sesuai hosting: `CHEMNAMA_DB_HOST`, `CHEMNAMA_DB_PORT`, `CHEMNAMA_DB_NAME`, `CHEMNAMA_DB_USER`, `CHEMNAMA_DB_PASS`.
- `CHEMNAMA_DB_AUTO_CREATE`: 1 lokal; 0 pada hosting yang database-nya dibuat lewat control panel.
- `CHEMNAMA_SESSION_SECURE`: 1 pada HTTPS hosting, 0 untuk HTTP localhost. HTTPS langsung juga terdeteksi otomatis; pada reverse proxy atur 1.
- `CHEMNAMA_GURU_ACCESS_CODE`: undangan guru privat; kosong menonaktifkan daftar guru.
- `CHEMNAMA_SISWA_ACCESS_CODE`: opsional untuk membatasi daftar siswa; kosong berarti terbuka.
- `CHEMNAMA_DEBUG_MODE=0` untuk produksi. Kode reset di browser hanya diizinkan jika debug=1 dan REMOTE_ADDR loopback, bukan berdasarkan Host header.
- Opsional SMTP: `CHEMNAMA_SMTP_HOST`, `CHEMNAMA_SMTP_PORT`, `CHEMNAMA_SMTP_USERNAME`, `CHEMNAMA_SMTP_PASSWORD`, `CHEMNAMA_SMTP_ENCRYPTION`, `CHEMNAMA_SMTP_FROM_EMAIL`, `CHEMNAMA_SMTP_FROM_NAME`.

Ganti credential SMTP dan kode akses lama yang sebelumnya tercantum di file contoh. `.env` aktif sengaja tidak diubah. Jangan commit atau membagikan `.env`.

Pemulihan admin tanpa SMTP: set environment proses `CHEMNAMA_ADMIN_RESET_PASSWORD` ke password unik, jalankan `php scripts/reset-password.php NIP_ATAU_NIS`, kemudian hapus environment tersebut. Script hanya berjalan di CLI dan tidak mencetak password. Gunakan untuk merotasi semua akun demo lama; data materi/nilai tetap utuh. Pada hosting tanpa CLI gunakan pemulihan email dengan SMTP yang didukung atau lakukan reset di server lokal sebelum ekspor database.

## Deploy Rp0

InfinityFree menawarkan PHP/MySQL dan sertifikat SSL gratis: https://www.infinityfree.com/ . Gunakan free subdomain agar tidak perlu membeli domain.

1. Buat akun hosting gratis, subdomain, database dan SSL melalui control panel.
2. Backup database lama lalu ekspor/impor ke database hosting melalui phpMyAdmin agar akun, nilai dan materi tetap tersedia. Instalasi baru bisa membuat tabel otomatis.
3. Upload file aplikasi, `vendor`, aset, dan file unggahan yang dibutuhkan ke `htdocs`. Jangan upload `.git`, `.tools`, tes, backup, atau artefak sementara. Jika `vendor` belum tersedia, jalankan `composer install --no-dev` secara lokal lalu upload hasilnya.
4. Buat `.env` privat di root aplikasi dengan host/nama/user/password DB dari panel. Set `CHEMNAMA_DB_AUTO_CREATE=0`, `CHEMNAMA_SESSION_SECURE=1`, `CHEMNAMA_DEBUG_MODE=0`; aktifkan HTTPS.
5. Pastikan `.htaccess` aktif: akses langsung ke `.env` dan `database/schema.sql` harus ditolak. Hosting non-Apache perlu aturan setara; jangan menerbitkan secret jika perlindungan server tidak tersedia.
6. Pastikan storage/upload writable dan izin CREATE TABLE/ALTER TABLE tersedia untuk inisialisasi/migrasi existing. Rotasi semua credential demo lama sebelum publik.
7. Uji daftar akun, login dua browser/device, role guru/siswa, logout, reset password, materi/game/nilai existing dan PhET.

Free hosting memiliki kuota dan kebijakan layanan; SMTP keluar bisa dibatasi. Login/registrasi tidak bergantung SMTP, tetapi pemulihan email memerlukannya. Tidak ada jaminan kuota atau uptime unlimited; cek ketentuan provider. GitHub Pages tidak menjalankan PHP/MySQL.

## PhET dan batasannya

Referensi resmi: https://phet.colorado.edu/en/help-center/getting-started dan https://phet.colorado.edu/en/licensing . PhET mendukung embed HTML5 dan penggunaan pendidikan nonkomersial gratis dengan atribusi. Penggunaan komersial (termasuk situs beriklan/monetisasi) memerlukan lisensi komersial sesuai ketentuan saat ini.

Viewer memakai simulasi resmi yang dihosting PhET, mempertahankan branding/kontrol mereka dan tidak mem-proxy atau menghapus pembatasan. Koneksi internet diperlukan; iframe bisa gagal karena jaringan sekolah, kebijakan browser, atau perubahan PhET. Tautan tab baru selalu tersedia karena aplikasi tidak bisa memeriksa keberhasilan frame lintas-origin secara andal. Java/Flash legacy tidak termasuk viewer. Nilai/progres internal PhET tidak otomatis tersinkron ke LMS; fitur tersebut memerlukan integrasi terpisah yang diizinkan PhET.

Tidak ditemukan URL PhET dalam kode sumber awal; tautan tersebut mungkin tersimpan sebagai konten database yang belum dapat diinspeksi tanpa MySQL. Viewer baru tersedia tanpa mengubah konten database atau simulasi reaksi existing.
