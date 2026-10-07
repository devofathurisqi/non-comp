# Menjalankan Nom Comp di komputer ini

Runtime portabel gratis sudah disiapkan di `.tools`: PHP 8.4.26 dan MariaDB 11.4.11. Keduanya diunduh dari sumber resmi dan diverifikasi dengan checksum SHA-256 sumbernya. Tidak perlu instalasi XAMPP tambahan atau membeli layanan.

PHP mengaktifkan PDO MySQL/SQLite, mbstring, OpenSSL, fileinfo dan curl. Database memakai `127.0.0.1:3307`, nama `nom_comp_local`, dan user aplikasi `nom_comp_app`. Password database dan kode undangan baru tersimpan di `.env`, dengan credential administrator lokal di `.tools`. Konfigurasi lama dibackup ke `.tools/env-before-local.backup` dan tidak digunakan untuk database baru.

## Status setup

Database dan akun guru/siswa lokal sudah dibuat. Aplikasi berjalan di `http://127.0.0.1:8000`. File yang sempat dikarantina AVG sudah dibuat ulang setelah pemilik komputer menangani antivirus.

Jika deteksi AVG `IDP.Generic` muncul lagi ketika antivirus diaktifkan, tinjau runtime PHP resmi dan file project melalui AVG. Jangan menghapus data database. Pengecualian terbatas pada runtime/project lebih sesuai daripada membiarkan seluruh antivirus nonaktif.

## Start dan stop setelah setup selesai

Buka terminal PowerShell di folder project:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Kemudian buka `http://127.0.0.1:8000`. Script menjalankan PHP dan database sebagai proses background dengan jendela tersembunyi. Keduanya hanya bind ke loopback komputer ini.

Untuk menghentikan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-local.ps1
```

Stop tidak menghapus data. Data tersimpan di `.tools/db-data`; jangan menghapus folder tersebut. Log tersedia di `.tools/runtime`. Script tidak mendaftarkan Windows service dan tidak mengubah PATH sistem.

## Akun dan email

Konten pembelajaran dipulihkan dari backup tanpa akun dan riwayat murid lama: 4 materi, 12 paket kuis, 8 soal, 1 essay, dan 1 PR. Konten dipindahkan ke guru pemilik dengan akses seluruh pilihan kelas. Login guru pemilik tersimpan privat di `.tools/GURU-PEMILIK.md`. Akun siswa baru yang didaftarkan pengguna tetap dipertahankan. Data nilai/jawaban murid lama tidak diimpor. Tenggat tugas lama dipertahankan dari backup; guru dapat memperbaruinya melalui pengelolaan tugas.

Detail NIP/NIS, password baru dan kode undangan lokal tersimpan di `.tools/AKUN-LOKAL.md`. Akun guru/siswa sudah tersedia di database. File ini privat: jangan upload atau commit.

Email `example.test` hanya untuk pengujian lokal. Ubah menjadi email pemilik/guru/siswa yang sebenarnya sebelum deploy. Tidak ada credential SMTP klien yang digunakan. Mode debug lokal diaktifkan untuk pemulihan password melalui loopback; produksi harus menggunakan `CHEMNAMA_DEBUG_MODE=0`.

## Deploy nanti

Belum ada website publik atau link deployment baru dibuat. Setelah versi lokal lulus uji, gunakan PHP/MySQL hosting gratis dan subdomain gratis. Jangan upload `.tools`, `.git`, file akun, backup atau log. Ekspor database aplikasi saja agar akun/data bisa dipindahkan. Pada hosting atur password database baru, HTTPS, `CHEMNAMA_DB_AUTO_CREATE=0`, dan konfigurasi produksi sesuai README.
