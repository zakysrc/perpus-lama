# Catatan Revisi - Sistem Informasi Perpustakaan (Perpus Zaky)

Peta file dan panduan memperbaiki error ada di `BLUEPRINT.md`.

Dokumen ini merangkum semua perbaikan pada revisi ini dibanding versi yang
sudah dipresentasikan, beserta alasan teknisnya. Ditulis supaya mudah
dijelaskan ke penguji saat pengajuan banding nilai.

## Cara menjalankan
1. Buat database `dbperpuszaky`, lalu impor berurutan:
   1. `database/dbperpuszaky.sql` — skema awal
   2. `database/migrasi_v2.sql` — keamanan, aturan bisnis, status denda
   3. `database/migrasi_v3.sql` — pengajuan, jatuh tempo, perpanjangan,
      kategori, sampul, log aktivitas (**aman diimpor ulang**)
   4. `database/migrasi_v4.sql` — denda Rp5.000, reservasi, ulasan, usulan,
      buku tamu (**aman diimpor ulang**)
   5. `database/migrasi_v5.sql` — indeks optimasi (**aman diimpor ulang**)
   6. `database/data_contoh.sql` — *opsional*, data demo supaya dashboard,
      statistik, dan laporan tidak kosong
2. Salin `config.local.example.php` menjadi `config.local.php` jika kredensial
   database berbeda dari default (`root` tanpa password).
3. Akses lewat `http://localhost/perpuszaky/`.
4. Login admin bawaan: `ZAKY` / `admin123` — halaman **Profil saya** akan
   memperingatkan sampai password ini diganti.
   Akun anggota contoh (jika data_contoh diimpor): `BIMA` / `anggota123`,
   `CITRA` / `anggota123`.

---

## 1. Keamanan

| Sebelum | Sesudah | Kenapa |
|---|---|---|
| Password admin tersimpan polos (`admin123`) di database, dan kode login menerima password polos | Password di-hash dengan `password_hash()` (bcrypt). Password lama yang masih polos otomatis dimigrasi ke hash saat pertama kali login berhasil | Kalau database bocor, penyerang tidak langsung mendapat password asli |
| Hapus buku/anggota lewat link `<a href="hapus_x.php?id=...">` (GET) tanpa proteksi | Diubah jadi `<form method="post">`, dan **setiap** form POST di seluruh aplikasi otomatis mendapat token CSRF (`inc/security.php`) | Link GET yang mengubah data rawan **CSRF**: korban yang sedang login tinggal membuka link/gambar buatan penyerang, datanya langsung terhapus |
| Pengembalian buku lewat link GET (`kembali.php?kembali=..`) | Diubah menjadi form POST + token CSRF | Sama seperti di atas |
| Tidak ada `session_regenerate_id()` saat login | ID session dibuat ulang setiap login berhasil | Mencegah **session fixation** |
| Session tidak pernah kedaluwarsa selama browser terbuka | Timeout otomatis 30 menit tanpa aktivitas | Mengurangi risiko jika perangkat ditinggal dalam keadaan login |
| Percobaan login tidak dibatasi | Dikunci sementara (15 menit) setelah 5x gagal per username / 10x per IP, tercatat di tabel `tblogin_gagal` | Mencegah **brute-force** password |
| Query login pakai string SQL yang digabung manual | Diubah ke **prepared statement** (`mysqli_prepare`) | Lapis pertahanan tambahan terhadap SQL injection, walau input sudah di-escape sebelumnya |
| Cookie session tanpa atribut keamanan | Cookie diberi `HttpOnly`, `SameSite=Lax`, dan `Secure` otomatis saat HTTPS | Mengurangi risiko pencurian session lewat XSS/CSRF |

## 2. Aturan bisnis (dulu tertulis manual di banyak file)

Sebelumnya angka **"7 hari"** dan **"Rp1.000/hari"** ditulis ulang secara
manual di 5 tempat berbeda (trigger database, `terlambat.php`, `kembali.php`,
`beranda_admin.php`, `statistik.php`). Kalau salah satu lupa diubah, aturan
jadi tidak konsisten.

Sekarang semua aturan disimpan di satu tabel `tbpengaturan` dan dibaca lewat
`inc/aturan.php`:

- `masa_pinjam_hari` (7)
- `denda_per_hari` (Rp1.000)
- `maks_jml_per_pinjam` (3 buku per transaksi) — **baru**
- `maks_buku_aktif` (5 buku aktif per anggota) — **baru**
- `cek_masa_berlaku` — anggota yang masa keanggotaannya habis tidak bisa
  meminjam — **baru**

Validasi ini dipakai bersama oleh `pinjam.php` (admin) dan `pinjam_user.php`
(anggota) lewat fungsi `cek_boleh_pinjam()`, sehingga aturan di dua sisi
selalu sama.

## 3. Integritas data

| Masalah | Perbaikan |
|---|---|
| Menghapus buku/anggota ikut menghapus seluruh riwayat peminjamannya (`ON DELETE CASCADE`) — laporan lama jadi hilang | Foreign key diubah ke `RESTRICT`. `hapus_buku.php` dan `hapus_anggota.php` sekarang menolak penghapusan jika masih ada riwayat peminjaman apa pun (bukan hanya yang aktif), dan menyarankan mengubah stok/masa berlaku saja |
| Menghapus anggota menyisakan akun login (`tbuser`) yang yatim | Akun login ikut dihapus dalam satu transaksi |
| File foto anggota tidak ikut dihapus | File foto dihapus otomatis (jika tidak dipakai anggota lain) |
| Deskripsi beberapa buku tertukar (mis. buku Java berdeskripsi tentang MySQL) | Diperbaiki lewat `migrasi_v2.sql` |

## 4. Pengalaman pengguna

- Tabel `buku.php`, `terlambat.php`, dan `riwayat_kembali.php` sekarang
  punya pencarian, urut kolom, dan paginasi (DataTables) — sebelumnya hanya
  `anggota.php` yang punya.
- Menu "Pengembalian" untuk anggota (yang sebelumnya hanya memunculkan
  `alert('hanya admin')`) dihapus dari navigasi; anggota diarahkan langsung
  ke "Riwayat Saya".
- Form peminjaman menampilkan aturan berlaku (maks buku, masa pinjam, denda)
  secara otomatis dari `tbpengaturan`, bukan teks tetap.
- Password minimal dinaikkan dari 6 ke 8 karakter (pendaftaran & reset).

## 5. Fitur baru: status pelunasan denda

Sebelumnya denda hanya dihitung otomatis oleh sistem, tapi tidak ada catatan
apakah anggota sudah membayarnya atau belum — semua baris di riwayat
pengembalian cuma bertuliskan "Dikembalikan" berulang-ulang, tidak informatif.

Sekarang:
- Setiap peminjaman yang kena denda punya status **Lunas** / **Belum Lunas**.
- Admin bisa menandai lunas (dan membatalkannya lagi) langsung dari halaman
  **Riwayat Pengembalian**, tanpa reload penuh berkat form kecil per baris.
- Kartu ringkasan "Total Denda" sekarang juga menampilkan berapa yang masih
  belum dibayar.
- Laporan Denda (baik yang dicetak maupun diunduh CSV) ikut menampilkan
  status ini di kolom terakhir.

## 6. Kerapian proyek

- File sampah dihapus: `beranda_admin_backup.php.php`, dan dua file
  (`hapus_buku.php`, `hapus_anggota.php`) yang sebelumnya berisi sisa
  potongan markdown (` ```php ... ``` `) di awal/akhir file.
- `koneksi.php` tidak lagi menaruh kredensial database langsung di kode;
  bisa dioverride lewat `config.local.php` (contoh: `config.local.example.php`)
  tanpa mengubah source utama — memudahkan deploy ke hosting.
- `.gitignore` ditambahkan agar `config.local.php` dan foto anggota tidak
  ikut ter-commit ke GitHub.
- File dead code `kembali_user.php` (halaman kosong yang hanya redirect)
  dihapus setelah menunya dicabut.

---

## Yang sengaja belum diubah

Beberapa hal baik dari versi asli dipertahankan karena sudah benar:
- Transaksi database dengan `FOR UPDATE` pada proses peminjaman (aman dari
  race condition saat dua orang meminjam buku yang sama bersamaan).
- Validasi upload foto (ekstensi, `getimagesize`, `finfo` MIME) di
  `tambah_anggota.php` / `edit_anggota.php`.
- Alur lupa password (verifikasi identitas + prepared statement + batas
  waktu token).
- Escaping output dengan `htmlspecialchars` di hampir seluruh halaman.

---

# Revisi v3 — tema navy + emas, alur sirkulasi, fitur baru

## A. Tampilan konsisten
| Sebelum | Sesudah |
|---|---|
| ±6 warna biru utama berbeda (`#1554b7`, `#2563eb`, `#2864d7`, `#1e40af`, …) ditulis manual di 9 file | Satu file `assets/theme.css` berisi variabel warna (`--pz-navy-*`, `--pz-gold-*`, status). Semua halaman memuatnya; ganti warna cukup di `:root` |
| Kelas Bootstrap (`btn-primary`, `badge-success`, `small-box bg-info`, …) memakai warna bawaan AdminLTE yang tidak senada | Semua kelas tersebut dipetakan ke palet navy + emas |
| Font campur (Source Sans Pro, Segoe UI, Arial) | Judul: Libre Baskerville (serif), isi: Source Sans 3 — dengan cadangan Georgia/Segoe UI jika offline |
| Halaman anggota punya 2 layout berbeda (beranda & riwayat tanpa sidebar) | Semua halaman anggota memakai `inc/header.php` + `inc/footer.php` |
| Menu sidebar tanpa penanda halaman aktif, Statistik & Terlambat tidak ada di menu | Menu dikelompokkan (Utama, Sirkulasi, Data, Sistem), menu aktif diberi garis emas, badge jumlah pengajuan & keterlambatan |
| Grafik Chart.js abu-abu bawaan | Warna grafik mengikuti tema |
| SweetAlert2 dimuat dari CDN (gagal jika XAMPP offline) | Dimuat dari `plugins/sweetalert2` lokal |

## B. Perbaikan bug
- Notifikasi terlambat di `inc/header.php` masih hardcode `> 7` hari → sekarang memakai jatuh tempo per transaksi.
- `beranda_user.php` mengarahkan admin ke `index.php` → sekarang ke `beranda_admin.php`.
- **Kebocoran data:** di `pencarian.php`, akun anggota bisa melihat transaksi anggota lain → anggota hanya melihat transaksinya sendiri, data anggota hanya untuk admin.
- Username login belum unik → indeks `UNIQUE` pada `tbuser.username`.
- Dump SQL tidak punya data anggota padahal akun AYYEVR merujuk `id_ang = 1` → diperbaiki lewat `data_contoh.sql`.
- Kartu denda anggota tidak membedakan lunas / belum lunas → yang ditampilkan kini denda **belum dibayar**.
- Deskripsi buku tidak bisa diubah dari aplikasi → ada di form tambah/ubah buku.
- Komentar menyesatkan di `pinjam_user.php` ("trigger tidak digunakan") dihapus bersama kode lamanya.
- File yatim `fm_login.php` dihapus.

## C. Fitur baru
| Fitur | File | Keterangan |
|---|---|---|
| Pengajuan peminjaman | `pinjam_user.php`, `pengajuan.php` | Anggota mengajukan dari akun; stok **baru berkurang** saat petugas menekan "Serahkan buku" (trigger `sebelum_pengembalian` menangani transisi `diajukan → pinjam`). Anggota bisa membatalkan, admin bisa menolak dengan alasan |
| Jatuh tempo per transaksi | kolom `tgl_jatuh_tempo` | Denda dihitung dari jatuh tempo, bukan lagi `tgl_pinjam + 7` |
| Perpanjangan | `perpanjang.php` | Dari halaman Pengembalian (admin) atau Pinjaman saya (anggota). Dibatasi `maks_perpanjang` dan ditolak jika sudah terlambat |
| Pengaturan dari UI | `pengaturan.php` | Masa pinjam, denda, batas buku, batas perpanjangan, nama perpustakaan — tidak perlu phpMyAdmin |
| Profil & ganti password | `profil.php` | Wajib password lama, minimal 8 karakter; anggota bisa ubah telepon/alamat/email |
| Log aktivitas | `log_aktivitas.php`, tabel `tblog` | Login, logout, tambah/ubah/hapus buku & anggota, pinjam, kembali, perpanjang, denda, pengaturan |
| Kartu anggota | `kartu_anggota.php` | Ukuran kartu ATM, barcode Code 39 dibuat sebagai SVG tanpa library, siap cetak |
| Pengingat WhatsApp | `terlambat.php` | Tombol "Ingatkan" membuka wa.me dengan pesan berisi judul, jatuh tempo, dan perkiraan denda |
| Kategori buku | kolom `kategori` | Diisi di form buku, filter di katalog publik dan koleksi anggota |
| Unggah sampul | `inc/form_buku.php`, `inc/simpan_buku.php` | JPG/PNG/WEBP ≤ 2 MB, divalidasi `finfo` + `getimagesize`; sampul lama ikut dihapus saat diganti/buku dihapus |

## D. Struktur kode baru
- `inc/aturan.php` — ditambah helper jatuh tempo (`sql_terlambat()`, `jatuh_tempo_baris()`), `catat_log()`, `cek_boleh_perpanjang()`, `badge_status()`, pesan *flash*, dan `sampul_buku()`.
- `inc/form_buku.php` + `inc/simpan_buku.php` — satu form & satu validasi untuk tambah dan ubah buku (prepared statement).
- Pesan hasil aksi memakai *flash message* setelah redirect, bukan `alert()` + `window.location`.

---

# Revisi v3.1

- Tipografi disederhanakan: judul dan isi memakai satu font (Source Sans 3,
  cadangan Segoe UI/Arial), tanpa huruf serif.
- Bug lama: `pinjam.php` tidak memanggil `inc/footer.php`, sehingga dropdown
  notifikasi/akun, tombol menu, dan SweetAlert tidak berfungsi di halaman itu.
- Fungsi sampul lama yang sudah tidak dipakai (`cover_buku`, `cover_buku_detail`)
  dihapus; semua halaman memakai `sampul_buku()`.
- Ditambahkan `BLUEPRINT.md`: peta file, database, alur, dan tabel gejala error.

---

# Revisi v4

## Perbaikan
| Masalah | Penyebab | Perbaikan |
|---|---|---|
| Warna tidak konsisten antar halaman | Pemilihan kelas warna-warni (kotak angka, tombol hijau/merah/abu untuk navigasi, kepala tabel berbeda, status "dipinjam" kadang emas) | Aturan tunggal di `theme.css`: navy = umum, emas = aksi utama, hijau = status berhasil, merah = bahaya. Semua kotak angka navy, semua kepala tabel sama |
| Halaman Peminjaman & Riwayat denda terasa "ter-zoom" | Halaman lama tanpa jarak tepi dan judul 2,5rem | Jarak isi & ukuran judul seragam di semua halaman; ikon judul & breadcrumb dihilangkan (judul sudah di navbar) |
| Tampilan lama muncul lagi setelah update | CSS tersimpan di cache browser | Semua `<link>` CSS diberi versi `?v=4.0` |
| Filter Statistik kembali ke "7 Hari" setelah memilih "30 Hari" | Kunci array PHP `'30'` berubah jadi angka, dibandingkan `===` dengan teks | Dibandingkan sebagai string |
| Tautan "PERPUSTAKAAN ZAKY" & "Kembali" di halaman Daftar mengarah ke `home.php` yang tidak ada | Nama file lama | Diarahkan ke `index.php` |
| Foto wajib saat tambah anggota | Validasi lama | Foto opsional; kartu memakai inisial nama |
| Denda Rp1.000/hari | Pengaturan lama | Rp5.000/hari (`migrasi_v4.sql` + nilai cadangan di PHP & trigger) |

## Fitur baru
| Fitur | File |
|---|---|
| Meja sirkulasi dengan scan kartu & ISBN, termasuk terima denda dan perpanjang keanggotaan | `sirkulasi.php` |
| Reservasi & antrean buku yang stoknya habis | `reservasi.php`, aturan no. 6 `cek_boleh_pinjam()` |
| Pengingat jatuh tempo & reservasi di lonceng anggota | `pengingat_anggota()`, `inc/header.php` |
| Ulasan & rating (hanya setelah mengembalikan buku) | `detail_buku.php`, `tbulasan` |
| Usulan pengadaan buku | `usulan.php`, `tbusulan` |
| Buku tamu + grafik 7 hari | `kunjungan.php`, `tbkunjungan` |
| Backup database satu klik (tabel, data, trigger) | `backup.php` |
| Ringkasan layanan di dashboard (kunjungan, pengajuan, reservasi, usulan) | `beranda_admin.php` |

---

# Revisi v5

## Perbaikan
| Keluhan | Penyebab yang ditemukan | Perbaikan |
|---|---|---|
| Password user ditolak | Kode login & lupa password terbukti benar (diuji: daftar → login → lupa password → login). Penyebab paling mungkin: akun terhapus saat `dbperpuszaky.sql` diimpor ulang | Halaman **Akun login** (cek akun, reset password), log login gagal mencatat alasan, peringatan di BLUEPRINT |
| Filter laporan bermasalah | Layar & CSV memakai dua kode filter berbeda; label "Total Buku" padahal yang dihitung buku baru di periode itu; isian tersembunyi ikut terkirim | Ditulis ulang: satu modul `inc/laporan_data.php`, 7 pilihan periode, label sesuai periode, isian yang tidak dipakai disembunyikan & tidak ikut terkirim |
| Titik di judul halaman depan | Gaya penulisan | Dihapus |
| Periode Analisis tidak berubah | Dropdown baru berlaku setelah tombol diklik; tombol Kembali memulihkan pilihan lama | Langsung diterapkan saat dipilih; halaman dimuat ulang saat kembali |
| Sidebar kembali ke atas setelah klik menu | Posisi gulir tidak disimpan | Posisi disimpan per sesi; menu aktif selalu terlihat |
| Anggota bisa memperpanjang sendiri | Tombol di halaman anggota | Perpanjangan khusus admin (`perpanjang.php` memakai `admin_only.php`) |
| Sapaan "Selamat sore" jam 21.00 | PHP XAMPP memakai zona waktu Eropa | Zona waktu WIB untuk PHP & MySQL; sapaan diperbarui otomatis; nama bulan Indonesia |
| Grafik tren harian berbentuk "paku" | Grafik garis melengkung dengan banyak nol | Diganti grafik batang |

## Fitur & optimasi baru
| Fitur | File |
|---|---|
| Akun login + reset password anggota | `akun.php` |
| Bukti transaksi siap cetak (80 mm) | `bukti.php` (tombol di Meja sirkulasi, Pengembalian, Riwayat) |
| Laporan terlambat, kunjungan, usulan buku (+CSV) | `inc/laporan_data.php` |
| Indeks database & query yang memakai indeks | `migrasi_v5.sql`; `LOWER(status)` diganti `status` |
| Cache CSS dipaksa baru | `?v=5.0` di semua tag `<link>` |
