## Domain Nomcom — 3 Oktober 2026

- Domain utama gratis: https://nomcom.42web.io/ pada akun InfinityFree yang sama.
- 147 berkas aplikasi, konfigurasi dan media produksi disalin serta diverifikasi SHA-256; direktori tes dan contoh PHPMailer tidak diperlukan untuk runtime.
- Database produksi tetap sama, tanpa impor atau perubahan akun, materi, nilai maupun hasil siswa.
- Login guru, dashboard dengan data existing, daftar materi dan viewer PhET diperiksa lewat HTTPS.
- Domain lama dialihkan ke domain baru dengan HTTP 308, mempertahankan path dan query string.
- Tujuan FTP untuk redeploy diperbarui melalui CHEMNAMA_FTP_ROOT privat menjadi nomcom.42web.io/htdocs.
- Backup aturan domain lama: .tools/old-domain.htaccess.private.

## Step 3 terhubung ke PhET — 2 Oktober 2026

- Step 3 siswa sekarang menampilkan viewer PhET yang sama dengan menu Simulasi PhET, menggantikan iframe simulasi internal lama.
- Mode embed menyembunyikan sidebar ganda, mempertahankan pilihan enam simulasi dan atribusi resmi, serta membatasi URL ke daftar simulasi resmi.
- Menu PhET mandiri tetap tersedia. Tidak mengubah database maupun nilai/progres otomatis.
- Validasi: 80 pemeriksaan integrasi dan 16 pemeriksaan autentikasi lulus; fixture lokal dibersihkan.
- Redeploy dua file: siswa_materi_detail.php dan phet_viewer.php; backup remote dan verifikasi SHA-256 selesai.

# Hasil perbaikan dan redeploy — 2 Oktober 2026

Website: https://nomcom.42web.io/

## Pembaruan lanjutan: Games, PhET, dan video

- Pengaturan Games sekarang hanya memiliki ChemMatch dan Cari Kata. Panel dan handler pengelolaan simulasi lama dihapus dari halaman ini; data lama tidak dihapus dari database.
- Simulasi PhET menjadi menu sendiri pada hamburger/sidebar guru dan murid. Viewer memiliki navigasi, pemilih enam simulasi, iframe resmi, serta atribusi. Tombol PhET di konten Kelola Materi/Materi dihapus; tahap simulasi internal materi lama tetap dipertahankan.
- Guru memilih **Kelola Materi → Upload Materi → Tipe: Video → Sumber video: Tautan YouTube / Upload dari perangkat**. Unggahan mendukung MP4/WebM sampai 10.000.000 byte. Validasi URL YouTube memakai hostname resmi dan ID video, termasuk share, Shorts, dan live; unggahan memeriksa tipe file dan ukuran. Guru mendapat pratinjau, murid mendapat pemutar dengan dukungan seek.
- Batas file hosting gratis InfinityFree adalah 10 MB: https://forum.infinityfree.com/t/what-is-the-file-upload-size-limit/49308. Video lebih besar dapat menggunakan tautan YouTube. Pemutaran YouTube mengikuti izin embed pemilik video.
- 75 pemeriksaan integrasi lokal dan 16 pemeriksaan autentikasi lulus; syntax 46 PHP dan JavaScript lulus. Test tambahan menyimpan video YouTube dan MP4, memeriksa kedua sumber pada materi siswa, HTTP Range 206 untuk seeking, serta menu PhET siswa. Fixture lokal sudah dibersihkan.
- Browser produksi memverifikasi menu PhET dari dashboard, iframe Buat Atom yang termuat, pergantian kedua tab game, dan pemilihan sumber video. Database produksi tidak diimpor ulang; akun, perubahan materi oleh pengguna, dan unggahan dipertahankan.
- 28 berkas diunggah dan diverifikasi SHA-256. Backup sebelum pembaruan ini ada di `.tools/redeploy-backup-feature-20261002`; manifest ada di `.tools/redeploy-feature-manifest.json`. Dashboard diverifikasi lagi setelah penyesuaian hitungan/teks Games.
- Tidak ada dependency, service berbayar, atau environment variable baru.

Berkas utama: `guru_games.php`, `guru_materi.php`, `phet_viewer.php`, `dashboard.php`, `guru_carikata.php`, `guru_tarigaris.php`, `siswa_materi.php`, `siswa_materi_detail.php`, `app/helpers.php`, `assets/js/app.js`, `assets/css/style.css`, serta halaman sidebar guru/murid untuk penambahan menu PhET. Test diperluas di `tests/integration_test.php`.

## Perbaikan

- Dashboard gagal di tengah render karena ada 12 modul tetapi hanya 6 warna grafik. Indeks warna sekarang berulang; halaman selesai dirender dan JavaScript menu terkirim.
- Semua halaman yang memiliki hamburger memakai navigasi bersama. Menu mendukung tutup lewat overlay/Escape, fokus keyboard, penguncian scroll, dan perubahan ukuran layar. Halaman game/intro yang sebelumnya tidak memuat skrip kini memuatnya.
- Tata letak mobile mendapat ruang untuk tombol menu dan judul. Sidebar tidak menutupi merek; konten desktop tidak bertumpuk. Grafik banyak modul bisa digeser, label terbaca, dan batang 0% tidak ditampilkan setinggi batang bernilai positif.
- Pesan Top 5 Siswa pada kelas kosong memakai seluruh lebar panel, sehingga tidak terjepit pada kolom nomor urut.
- Navigasi enam langkah materi menjadi grid: enam kolom desktop dan tiga kolom mobile. Jarak 150 px dan scroll otomatis yang menggeser halaman dihapus.
- Tautan kuis dashboard menuju halaman kuis yang benar. Quick Quiz lama diarahkan ke pengelolaan Quick Quiz yang sesuai schema database saat ini.
- Jumlah murid pemilih kelas dihitung dari akun/profil murid; PR umum ikut dihitung. Materi, kuis, simulasi, dan game yang memiliki kelas tertentu disaring sesuai kelas murid, termasuk URL langsung dan pengiriman hasil.
- Unggahan PR yang ditolak tidak lagi mengganti/menghapus berkas sebelumnya. API penyelesaian game memeriksa metode POST dan ketersediaan game; waktu minimum satu detik.
- URL CSS/JavaScript memakai versi waktu perubahan berkas agar browser memuat pembaruan.

## Verifikasi

- 64 pemeriksaan integrasi lokal lulus: login guru, registrasi murid otomatis masuk, sesi terpisah, render halaman guru/murid, pembuatan materi/kuis/soal/essay/PR/game/Quick Quiz, jawaban essay, diskusi, nilai kuis reguler dan inline, nilai Quick Quiz, unggahan PR, penyelesaian game, leaderboard, pembatasan peran, CSRF autentikasi, logout, dan materi kelas lain.
- 16 pemeriksaan autentikasi lulus dengan fixture SQLite; integrasi utama memakai MariaDB lokal.
- Syntax PHP: 46 berkas tanpa error; `node --check assets/js/app.js` lulus. Berkas endpoint inline dan test integrasi diperiksa lagi setelah perubahan terakhir.
- Browser lokal: menu dan lebar halaman guru/murid diperiksa pada mobile; ukuran 320, 390, 768, dan desktop sekitar 1280–1366 px diperiksa pada halaman terkait. Pergantian tablet ke desktop mengembalikan konten dan sidebar ke keadaan normal.
- ChemMatch lokal diuji dengan klik pasangan hingga notifikasi selesai.
- Browser produksi: login guru berhasil, hamburger kelas X IPA C berfungsi. Sebelas halaman utama guru memiliki skrip navigasi, menu terbuka/tertutup, tidak melebar keluar viewport, dan tidak mencatat error JavaScript selama pemeriksaan.
- 38 berkas kode diunggah; SHA-256 seluruh berkas remote cocok dengan lokal. Backup kode produksi sebelum redeploy disimpan di `.tools/redeploy-backup-20261002`.
- Data uji hanya dibuat di database lokal dan sudah dibersihkan. Database, akun, materi, dan unggahan produksi tidak diimpor ulang.

## Berkas berubah dibanding backup online

`api_record_game_completion.php`, `dashboard.php`, `guru_carikata.php`, `guru_data_siswa.php`, `guru_essay.php`, `guru_forum.php`, `guru_games.php`, `guru_homework.php`, `guru_intro_siswa.php`, `guru_materi.php`, `guru_pilih_kelas.php`, `guru_profil.php`, `guru_quick_quiz.php`, `guru_quiz_pg.php`, `guru_soal_pg.php`, `guru_tarigaris.php`, `index.php`, `login.php`, `register.php`, `siswa_carikata.php`, `siswa_essay.php`, `siswa_forum.php`, `siswa_games.php`, `siswa_homework.php`, `siswa_materi_detail.php`, `siswa_materi.php`, `siswa_profil.php`, `siswa_quiz.php`, `siswa_simulasi.php`, `siswa_tarigaris.php`, `submit_quiz_pg_inline.php`, `assets/css/style.css`, `assets/js/app.js`.

Lokal: `app/auth.php` dipulihkan dari backup, `scripts/local-router.php` dibuat kembali, dan `tests/integration_test.php` ditambahkan. Berkas operasional dan bukti pengujian berada di `.tools/` dan tidak dipublikasikan.

## Menjalankan dan menguji lokal

Jalankan PowerShell dari folder project:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-local.ps1
.tools/php/php.exe tests/auth_test.php
.tools/php/php.exe tests/integration_test.php
.tools/php/php.exe tests/integration_test.php cleanup
```

Buka http://127.0.0.1:8000/. Test integrasi memakai panduan akun lokal privat dan membuat data sementara; jalankan cleanup setelah selesai. Tidak ada build bundler pada stack PHP ini.

## Service dan keterbatasan

Tidak ada dependency, service berbayar, atau environment variable baru untuk perbaikan ini. Hosting tetap InfinityFree gratis; konfigurasi dan credential produksi dipertahankan. PhET tetap menggunakan iframe resmi dan membutuhkan internet. Lampiran materi lama yang tidak disertakan dalam source/backup tidak dapat direkonstruksi dari judul saja. Pengujian browser dilakukan melalui Chromium; pengujian perangkat Safari/iPhone fisik belum dilakukan. Pemeriksaan ini tidak merupakan jaminan bahwa seluruh kemungkinan bug sudah tereliminasi.
