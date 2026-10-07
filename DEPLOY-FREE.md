# Deploy gratis di InfinityFree

Status 3 Oktober 2026: alamat utama website adalah https://nomcom.42web.io/ pada paket gratis InfinityFree. Database sudah diimpor; HTTPS dan redirect HTTP aktif. Akun guru, 4 materi, 12 set kuis, 8 soal, tugas dan PhET telah diperiksa di hosting. Registrasi siswa dan akses konten lintas kelas diuji secara lokal; 16 pemeriksaan autentikasi dan lint PHP lulus.

Domain utama saat ini memakai document root `nomcom.42web.io/htdocs` pada akun hosting yang sama. Semua redeploy berikutnya harus menuju folder ini. Alamat classroom-sesa.42web.io dialihkan ke domain baru; database tetap sama dan tidak perlu diimpor ulang. Jangan mengimpor database lokal di atas database produksi.

Konfigurasi mendukung hosting yang tidak menerapkan `putenv()`, melalui fallback `$_ENV`. Konten pemulihan dengan `class_name=NULL` tersedia untuk semua kelas dan tetap dapat dikelola guru pemilik. Edit konten global mempertahankan cakupan globalnya.

1. Daftar di https://dash.infinityfree.com/register dan verifikasi email. Gunakan akun gratis.
2. Buat hosting account, pilih free subdomain seperti `nomcomp` dengan ekstensi gratis yang masih tersedia. Jangan memilih pembelian domain atau paket premium.
3. Buka control panel → MySQL Databases. Buat database lalu catat database hostname, database name, username dan password ke file privat `.tools/HOSTING-SETUP.env`. Isikan juga URL website dan informasi FTP dari panel jika akan menggunakan upload FTP. Jangan menulis secret di chat.
4. Gunakan paket terbaru di `.tools/deploy-*/`: `website.zip` untuk aplikasi, `database-import.sql` untuk impor melalui phpMyAdmin. SQL ini privat dan tidak boleh diupload ke folder publik `htdocs`.
5. Upload ISI direktori `website` ke `htdocs` melalui File Manager/FTP. Pastikan `index.php` langsung berada di `htdocs`, bukan `htdocs/website`. Jika upload arsip tersedia, upload `website.zip`, ekstrak ke `htdocs`, lalu hapus arsip di server.
6. Buat `.env` di root aplikasi menggunakan konfigurasi DB hosting, dengan `CHEMNAMA_DB_AUTO_CREATE=0`, `CHEMNAMA_SESSION_SECURE=1`, `CHEMNAMA_DEBUG_MODE=0`, `CHEMNAMA_SEED_SAMPLE_CONTENT=0`. Isi kode undangan guru dengan nilai acak baru. Kode siswa boleh kosong untuk pendaftaran terbuka. SMTP boleh kosong tetapi reset email membutuhkan SMTP yang didukung hosting.
7. Aktifkan SSL gratis untuk subdomain melalui panel lalu gunakan alamat HTTPS. Aktifkan redirect HTTPS setelah sertifikat siap.
8. Uji beranda, registrasi siswa dan auto-login, materi, kuis, tugas, login guru, PhET, dan logout pada URL publik. Akses `/.env` serta `/database/schema.sql` harus ditolak, dan tidak ada file SQL/backup/credential di folder publik.

Database ekspor membawa data aplikasi aktif: konten yang dipulihkan, guru pemilik dan akun siswa yang baru didaftarkan. Tidak membawa murid atau nilai lama dari backup sebelum pembersihan. Akun guru pemilik masih menggunakan email contoh lokal; ganti email dan passwordnya sebelum website digunakan oleh klien. Paket tidak menyertakan konfigurasi/credential database lokal, `.tools`, `.git`, script lokal, backup PHP, atau database import dalam arsip website.

Upload harus mengikuti batas provider. Jika file video/PDF ditolak karena ukuran atau kebijakan, simpan media di layanan gratis yang sesuai dan gunakan tautan/embed resmi pada materi. Jangan menghapus media lokal sampai alternatifnya terverifikasi.

Kredensial aplikasi guru dan kode undangan produksi disimpan privat di `.tools/AKUN-HOSTING.md`. SMTP belum dikonfigurasi, sehingga pengiriman email reset password belum aktif. Sebagian materi lama hanya memiliki metadata nama dokumen tanpa path file: nama dokumen tidak menjamin file aslinya tersedia. File upload yang ada di source dipertahankan; jangan mengaitkannya ke materi lain secara sembarang.

Update 2 Oktober 2026: pola SVG khusus layar HP sudah dideploy, opacity 0.48 dan ukuran mengikuti lebar layar; animasi tetap menghormati prefers-reduced-motion. Stylesheet memakai versi filemtime agar browser mengambil CSS terbaru. Tampilan produksi diuji pada lebar 390 px tanpa overflow horizontal. Petunjuk akun guru privat berada di .tools/PANDUAN-GURU-KLIEN.md. File register.php lokal sudah dipulihkan kembali setelah pemilik menangani antivirus.
