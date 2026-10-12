# Blueprint Sistem Perpus Zaky

Peta lengkap aplikasi: file apa melakukan apa, saling bergantung ke mana,
dan **kalau muncul error, file mana yang harus dibuka**.

Cara pakai cepat:
1. Ada error? Langsung ke **Bagian 7 (Peta Error)**, cari gejala yang mirip.
2. Mau mengubah fitur? Lihat **Bagian 4 (Peta File)** untuk tahu file mana
   yang terlibat, lalu **Bagian 6 (Alur)** untuk tahu urutan prosesnya.
3. Mau mengubah aturan (masa pinjam, denda)? Jangan ubah kode, buka menu
   **Pengaturan** (Bagian 5).

---

## 1. Gambaran besar

```
Browser
  │
  ├── Halaman publik  : index, katalog, detail_buku, login, daftar, lupa_password
  │
  └── Halaman aplikasi (wajib login)
        │
        ├── inc/header.php  ──► cek login, menu sidebar, notifikasi
        ├── (isi halaman)
        └── inc/footer.php  ──► jQuery, Bootstrap, AdminLTE, DataTables, SweetAlert2

Semua halaman ──► koneksi.php ──► inc/aturan.php   (aturan bisnis & helper)
                      │
                      └──► MySQL/MariaDB "dbperpuszaky"
                              └── trigger: stok & denda dihitung di database
```

Teknologi: PHP 8 (mysqli), MariaDB (XAMPP), AdminLTE 3 + Bootstrap 4,
Chart.js 2, DataTables, SweetAlert2. Semua library ada di folder `plugins/`
dan `dist/`, jadi aplikasi tetap jalan tanpa internet. Satu-satunya yang butuh
internet adalah font Source Sans 3 (kalau offline otomatis memakai Segoe UI/Arial).

---

## 2. Lapisan inti (dipakai hampir semua halaman)

Urutan pemuatan: `inc/security.php` → `koneksi.php` → `inc/aturan.php`.
Jika salah satu file ini rusak, **seluruh aplikasi** ikut error.

| File | Fungsi | Yang ada di dalamnya |
|---|---|---|
| `inc/security.php` | Keamanan dasar (juga menyetel zona waktu WIB) | Session aman (habis setelah 30 menit tidak aktif), token CSRF otomatis di setiap form POST, `e()` untuk menampilkan teks dengan aman, pembatasan login gagal (5x per username / 10x per IP → dikunci 15 menit), hash password |
| `koneksi.php` | Koneksi database | Kredensial bawaan XAMPP (`root`, tanpa password). Bisa ditimpa `config.local.php`. Menyetel zona waktu PHP & MySQL ke WIB (`Asia/Jakarta`, `+07:00`). Memuat `inc/aturan.php` |
| `config.local.php` | Kredensial server (opsional) | Salin dari `config.local.example.php`. Tidak ikut ke GitHub |
| `inc/aturan.php` | Aturan bisnis & helper | Lihat tabel di bawah |
| `inc/admin_only.php` | Penjaga halaman admin | Menolak akses selain status `admin` |
| `inc/header.php` | Kerangka atas halaman aplikasi | Cek login, hitung notifikasi, menu sidebar (menu aktif otomatis), navbar |
| `inc/footer.php` | Kerangka bawah | Memuat semua JavaScript. **Halaman tanpa footer = dropdown, sidebar, dan tabel tidak berfungsi** |
| `assets/theme.css` | Satu-satunya sumber warna | Variabel `--pz-navy-*`, `--pz-gold-*`, status. Dimuat paling akhir |
| `assets/style.css` | Tata letak lama | Ukuran, jarak, komponen. Warnanya sudah memakai variabel tema |

### Fungsi penting di `inc/aturan.php`

| Fungsi | Untuk apa | Dipakai di |
|---|---|---|
| `setting()`, `masa_pinjam_hari()`, `denda_per_hari()`, `maks_*()` | Membaca tabel `tbpengaturan` | Semua halaman sirkulasi |
| `cek_boleh_pinjam()` | Satu pintu validasi pinjam: batas jumlah, masa berlaku anggota, punya pinjaman telat, buku dobel, batas buku aktif, **antrean reservasi** | `pinjam.php`, `pinjam_user.php`, `pengajuan.php` |
| `cek_boleh_perpanjang()` | Validasi perpanjangan | `perpanjang.php`, `kembali.php`, `sirkulasi.php` (tombol hanya untuk admin) |
| `sql_jatuh_tempo()`, `sql_terlambat()` | Potongan SQL jatuh tempo/terlambat | header, dashboard, statistik, terlambat, kembali |
| `jatuh_tempo_baris()`, `hari_telat_dari_tempo()`, `denda_dari_tempo()` | Hitung di PHP untuk tampilan | kembali, terlambat, beranda & riwayat anggota |
| `badge_status()` | Label status yang seragam | Semua tabel peminjaman |
| `catat_log()` | Tulis ke `tblog` | Semua aksi yang mengubah data |
| `flash_set()`, `flash_tampil()` | Pesan hasil aksi setelah redirect | Halaman baru (pengajuan, kembali, profil, dll.) |
| `sampul_buku()`, `unggah_sampul()`, `hapus_file_sampul()`, `daftar_kategori()` | Sampul & kategori buku | Form buku, katalog, koleksi, detail |
| `id_dari_kode_anggota()` | Ubah hasil scan "AGT-0003"/"agt3"/"3" menjadi 3 | Meja sirkulasi, buku tamu |
| `antrean_di_depan()`, `selesaikan_reservasi()` | Hitung posisi antrean, tutup reservasi saat anggota mengajukan | Reservasi, `cek_boleh_pinjam()`, pinjam |
| `rating_buku()`, `bintang()` | Rata-rata rating & ikon bintang | Koleksi anggota, detail buku |
| `sapaan_waktu()`, `tanggal_indo()`, `nama_bulan()`, `bulan_singkat()` | Sapaan pagi/siang/sore/malam & tanggal berbahasa Indonesia | Dashboard, laporan, bukti, grafik |
| `pengingat_anggota()` | Isi lonceng anggota: jatuh tempo dekat, terlambat, reservasi tersedia, usulan ditanggapi | Header, beranda anggota |

---

## 3. Database

Impor berurutan: `dbperpuszaky.sql` → `migrasi_v2.sql` → `migrasi_v3.sql`
→ `migrasi_v4.sql` → `migrasi_v5.sql` → `data_contoh.sql` (opsional).
`migrasi_v3` s/d `migrasi_v5` aman diimpor ulang. `migrasi_v5` hanya menambah indeks (optimasi), tidak mengubah data.

> **Perhatian:** mengimpor ulang `dbperpuszaky.sql` MENGHAPUS semua data, termasuk akun yang dibuat lewat Daftar.
> Untuk memperbarui database yang sudah berisi data, cukup impor file migrasi yang belum pernah diimpor.

| Tabel | Isi | Kolom kunci |
|---|---|---|
| `tbanggota` | Data anggota | `id_ang`, `masa_berlaku`, `foto` (file di `assets/img/`) |
| `tbuser` | Akun login | `username` (unik), `password` (hash), `status` admin/user, `id_ang` → anggota |
| `tbbuku` | Koleksi | `jml_buku` = **stok tersedia di rak**, `kategori`, `gambar` (file di `assets/img/buku/`) |
| `tbpinjam` | Transaksi | `status`, `tgl_pinjam`, `tgl_jatuh_tempo`, `perpanjang`, `denda`, `denda_dibayar` |
| `tbpengaturan` | Aturan bisnis | `nama` → `nilai` |
| `tblog` | Log aktivitas | `waktu`, `username`, `aksi`, `detail` |
| `tblogin_gagal` | Catatan login gagal | Untuk penguncian sementara |
| `tbreservasi` | Antrean buku yang stoknya habis (v4) | `status` menunggu/selesai/batal, urutan = `tgl_reservasi` |
| `tbulasan` | Rating 1-5 & ulasan (v4) | Unik per (`kode_buku`, `id_ang`) |
| `tbusulan` | Usulan pengadaan buku (v4) | `status` diajukan/diterima/ditolak, `tanggapan` |
| `tbkunjungan` | Buku tamu (v4) | `id_ang` NULL = tamu umum |

Relasi: `tbpinjam.id_ang → tbanggota` (`fk_anggota`), `tbpinjam.kode_buku → tbbuku`
(`fk_buku`). Karena relasi ini, anggota/buku yang punya riwayat pinjam **tidak
bisa dihapus**. Ini disengaja supaya laporan tidak rusak.

### Status peminjaman

```
diajukan ──(admin: Serahkan buku)──► pinjam ──(admin: Kembalikan)──► kembali
    │                                   │
    └──(admin tolak / anggota batal)──► ditolak
                                        └──(Perpanjang)──► pinjam (jatuh tempo + masa pinjam)
```

### Trigger (logika yang berjalan di database, bukan di PHP)

| Trigger | Kapan | Yang dilakukan |
|---|---|---|
| `sebelum_peminjaman` | BEFORE INSERT `tbpinjam` | Validasi jumlah & buku, isi `tgl_jatuh_tempo`, **kurangi stok** jika status `pinjam` |
| `sebelum_pengembalian` | BEFORE UPDATE `tbpinjam` | `diajukan→pinjam`: isi tanggal pinjam & jatuh tempo, kurangi stok. `pinjam→kembali`: isi `tgl_kembali`, **hitung denda dari jatuh tempo** |
| `setelah_pengembalian` | AFTER UPDATE `tbpinjam` | `pinjam→kembali`: **tambah stok** |

Artinya: kalau stok atau denda salah, periksa trigger dulu sebelum PHP.

---

## 4. Peta file per modul

Kolom "Akses": **Publik** = tanpa login, **Login** = admin & anggota,
**Admin** = khusus admin, **Anggota** = khusus anggota.

### Halaman publik
| File | Akses | Fungsi | Bergantung pada |
|---|---|---|---|
| `index.php` | Publik | Halaman depan, 10 buku terbaru | `tbbuku`, `sampul_buku()` |
| `katalog.php` | Publik | Katalog + pencarian + filter kategori | `tbbuku`, `daftar_kategori()` |
| `detail_buku.php` | Publik | Detail satu buku (`?kode_buku=`) | `tbbuku` |
| `login.php` | Publik | Login, penguncian login gagal | `security.php`, `tbuser`, `tblogin_gagal` |
| `daftar.php` | Publik | Buat akun untuk anggota yang emailnya sudah terdaftar | `tbanggota`, `tbuser` |
| `register.php` | Publik | Hanya mengalihkan ke `daftar.php` | - |
| `lupa_password.php` | Publik | Reset password | `tbuser`, `tbanggota` |
| `logout.php` | Login | Hapus session, catat log | `catat_log()` |

### Sirkulasi (admin)
| File | Fungsi | Tabel | Catatan |
|---|---|---|---|
| `pengajuan.php` | Setujui ("Serahkan buku") / tolak pengajuan anggota | `tbpinjam` | Stok berkurang lewat trigger saat disetujui |
| `pinjam.php` | Peminjaman langsung di meja sirkulasi | `tbpinjam`, `tbbuku` | Memakai `cek_boleh_pinjam()` |
| `kembali.php` | Terima pengembalian, tombol perpanjang | `tbpinjam` | Denda dihitung trigger |
| `perpanjang.php` | Proses perpanjangan (POST) | `tbpinjam` | **Khusus admin** (`admin_only.php`). Anggota meminta ke petugas |
| `terlambat.php` | Daftar lewat jatuh tempo + pengingat WhatsApp | `tbpinjam`, `tbanggota` | Nomor 08xx diubah jadi 628xx |
| `riwayat_kembali.php` | Riwayat pengembalian & status denda | `tbpinjam` | |
| `tandai_denda.php` | Tandai denda lunas/belum (POST) | `tbpinjam.denda_dibayar` | Dipanggil dari riwayat |

### Layanan perpustakaan (v4)
| File | Akses | Fungsi |
|---|---|---|
| `sirkulasi.php` | Admin | Meja sirkulasi: scan kartu → pinjam (kode/ISBN), kembalikan, perpanjang, serahkan pengajuan, terima denda, perpanjang keanggotaan 3 tahun |
| `reservasi.php` | Login | Anggota: pesan & batal. Admin: lihat semua antrean |
| `usulan.php` | Login | Anggota: usulkan judul. Admin: terima/tolak + tanggapan |
| `kunjungan.php` | Admin | Buku tamu + grafik 7 hari |
| `backup.php` | Admin | Unduh `.sql` lengkap (tabel, data, trigger) |
| `detail_buku.php` (bagian `#ulasan`) | Publik baca, anggota tulis | Rating & ulasan |

### Data master (admin)
| File | Fungsi | Catatan |
|---|---|---|
| `anggota.php`, `tambah_anggota.php`, `edit_anggota.php`, `hapus_anggota.php` | Kelola anggota | Foto di `assets/img/anggota_*` |
| `buku.php` | Daftar buku | Menampilkan sampul & kategori |
| `tambah_buku.php`, `edit_buku.php` | Halaman tipis, isinya di `inc/form_buku.php` (tampilan) dan `inc/simpan_buku.php` (validasi & simpan) | Ubah form buku **cukup di satu tempat** |
| `hapus_buku.php` | Hapus buku yang belum pernah dipinjam | Ikut menghapus file sampul unggahan |

### Laporan & sistem (admin)
| File | Fungsi |
|---|---|
| `beranda_admin.php` | Dashboard, grafik 6 bulan |
| `statistik.php` | Analitik per periode, grafik tren & stok |
| `laporan.php` | Tampilan & cetak laporan (9 jenis) dengan filter periode |
| `download_laporan.php` | Unduh CSV (pemisah `;` untuk Excel Indonesia) |
| `inc/laporan_data.php` | **Satu-satunya** tempat filter periode & query laporan. Dipakai kedua file di atas, jadi layar = cetak = CSV |
| `akun.php` | Daftar akun login, login terakhir, gagal 24 jam, reset password anggota |
| `bukti.php` | Struk bukti peminjaman/pengembalian (80 mm) |
| `pengaturan.php` | Ubah isi `tbpengaturan` dari UI |
| `log_aktivitas.php` | Lihat `tblog` dengan filter |

### Halaman anggota
| File | Fungsi |
|---|---|
| `beranda_user.php` | Ringkasan: dipinjam, menunggu, denda belum dibayar, jatuh tempo |
| `buku_user.php` | Koleksi bersampul + filter kategori |
| `pinjam_user.php` | Ajukan & batalkan pengajuan |
| `riwayat_user.php` | Pinjaman berjalan (jatuh tempo, sisa hari) & riwayat denda. Tidak ada tombol perpanjang |

### Bersama (admin & anggota)
| File | Fungsi |
|---|---|
| `profil.php` | Ganti password, ubah kontak (anggota) |
| `kartu_anggota.php` | Kartu cetak + barcode. Admin: `?id=`, anggota: kartunya sendiri |
| `pencarian.php` | Pencarian navbar. Anggota hanya melihat transaksinya sendiri |

### Folder file
| Folder | Isi | Harus bisa ditulis PHP? |
|---|---|---|
| `assets/img/` | Foto anggota | Ya |
| `assets/img/buku/` | Sampul buku (`buku_<kode>_<acak>.jpg`) dan sampul lama bernama ISBN | Ya |
| `plugins/`, `dist/` | Library AdminLTE dll. | Tidak (jangan diubah) |
| `database/` | File SQL | Tidak |

---

## 5. Aturan bisnis (menu Pengaturan)

| Kunci | Bawaan | Berpengaruh ke |
|---|---|---|
| `masa_pinjam_hari` | 7 | Jatuh tempo baru & lama perpanjangan (trigger + PHP) |
| `denda_per_hari` | 5000 | Denda saat pengembalian (trigger) & perkiraan denda (PHP) |
| `maks_jml_per_pinjam` | 3 | Jumlah eksemplar per transaksi |
| `maks_buku_aktif` | 5 | Total buku dipinjam + diajukan per anggota |
| `maks_perpanjang` | 1 | Berapa kali boleh perpanjang (0 = tidak boleh) |
| `cek_masa_berlaku` | 1 | Tolak pinjam jika keanggotaan habis |
| `nama_perpustakaan` | Perpustakaan Zaky | Kartu anggota & pesan WhatsApp |
| `hari_pengingat` | 2 | Pengingat jatuh tempo di lonceng anggota sejak H-sekian |
| `maks_reservasi` | 3 | Reservasi aktif per anggota (0 = reservasi nonaktif) |

Perubahan berlaku untuk transaksi berikutnya. Jatuh tempo yang sudah
tersimpan tidak ikut berubah.

---

## 6. Alur utama (urutan file yang dilewati)

**Login**
`login.php` → `login_menit_terkunci()` → cek `tbuser` → `verifikasi_password()`
→ set session → `catat_log('login')` → admin ke `beranda_admin.php`, anggota ke `beranda_user.php`.

**Anggota mengajukan pinjaman**
`buku_user.php` (tombol Ajukan) → `pinjam_user.php` → `cek_boleh_pinjam()`
→ INSERT `tbpinjam` status `diajukan` (stok belum berubah) → log `ajukan_pinjam`.

**Admin menyerahkan buku**
`pengajuan.php` → `cek_boleh_pinjam(..., kecuali_no)` → UPDATE status `pinjam`
→ trigger `sebelum_pengembalian` mengisi tanggal & mengurangi stok → log `setujui_pinjam`.

**Perpanjangan**
`kembali.php` / `sirkulasi.php` (admin) → POST `perpanjang.php` → `cek_boleh_perpanjang()`
→ `tgl_jatuh_tempo` + masa pinjam, `perpanjang` + 1 → log `perpanjang`.

**Pengembalian**
`kembali.php` → UPDATE status `kembali` → trigger menghitung denda dari
`tgl_jatuh_tempo` → trigger `setelah_pengembalian` menambah stok → log `kembali`.
Denda tercatat **belum lunas** sampai ditandai di `riwayat_kembali.php`.

**Tambah/ubah buku**
`tambah_buku.php` / `edit_buku.php` → `inc/simpan_buku.php` (validasi, cek ISBN
dobel) → `unggah_sampul()` → redirect ke `buku.php` dengan pesan flash.

---

## 6b. Filter laporan

Semua mode diubah menjadi rentang tanggal `[dari, sampai]` di `laporan_filter()`,
lalu dipakai sebagai `DATE(kolom) BETWEEN dari AND sampai`.

| Mode (`?mode=`) | Parameter lain | Rentang |
|---|---|---|
| `semua` | - | tanpa batas |
| `hari` | - | hari ini |
| `7hari` / `30hari` | - | 6 / 29 hari lalu s/d hari ini |
| `bulan` | `bulan`, `tahun` | tanggal 1 s/d akhir bulan |
| `tahun` | `tahun` | 1 Jan s/d 31 Des |
| `rentang` | `mulai`, `akhir` | ditukar otomatis jika terbalik |

| Jenis | Disaring berdasarkan |
|---|---|
| anggota / buku | tanggal daftar / input (`tgl_entry`) |
| pinjam | `tgl_pinjam` (status pinjam & kembali) |
| kembali / denda | `tgl_kembali` |
| kunjungan / usulan | `waktu` / `tgl` |
| terlambat | tidak disaring (kondisi hari ini) |

Di Ringkasan, label kartu menyesuaikan periode ("Anggota baru" bila difilter,
"Total anggota" bila Semua data). Bagian "Kondisi saat ini" tidak ikut filter.

---

## 7. Peta error: gejala → penyebab → file yang diperbaiki

### Semua halaman
| Gejala | Kemungkinan penyebab | Periksa |
|---|---|---|
| "Koneksi database gagal. Periksa pengaturan database." | MySQL di XAMPP belum dinyalakan, nama database bukan `dbperpuszaky`, atau password root berbeda | Start MySQL di XAMPP; `koneksi.php` / `config.local.php` |
| Halaman putih kosong | Error PHP fatal (display_errors mati) | Lihat `xampp/apache/logs/error.log`; biasanya typo di file yang terakhir diubah |
| "Sesi Formulir Tidak Valid" (kode 419) | Token CSRF kedaluwarsa: halaman dibuka terlalu lama atau dibuka di tab lain | Muat ulang halaman lalu kirim lagi. Kalau terjadi terus: pastikan file memuat `inc/security.php` sebelum ada output |
| Tiba-tiba kembali ke login | Tidak aktif 30 menit | Normal. Ubah `SESI_MAKS_IDLE_DETIK` di `inc/security.php` |
| Tampilan masih versi lama (judul serif, tombol biru terang) setelah update | Browser memakai CSS lama dari cache | Tekan **Ctrl+F5**; naikkan `?v=` di tag `<link>` theme.css |
| Tampilan berantakan / warna kembali biru AdminLTE | `assets/theme.css` tidak termuat atau dimuat sebelum `adminlte.min.css` | `<head>` di `inc/header.php` (halaman aplikasi) atau di halaman publik |
| Dropdown notifikasi/akun, tombol ☰, atau pencarian tabel tidak berfungsi | Halaman tidak memanggil `inc/footer.php`, atau ada error JavaScript | Baris terakhir file halaman harus `include "inc/footer.php"`; cek Console browser (F12) |
| Font terlihat seperti Arial | Offline, Google Fonts tidak termuat | Normal, tidak perlu diperbaiki |

### Tanggal, jam, dan tampilan
| Gejala | Penyebab | Periksa |
|---|---|---|
| Sapaan salah (mis. "Selamat sore" jam 21.00) atau tanggal "hari ini" meleset | Zona waktu PHP bukan WIB | `date_default_timezone_set('Asia/Jakarta')` di `koneksi.php` & `inc/security.php` |
| Data "hari ini" di dashboard beda dengan laporan dekat tengah malam | Zona waktu MySQL beda dengan PHP | `SET time_zone = '+07:00'` di `koneksi.php` |
| Nama bulan muncul dalam bahasa Inggris | Memakai `date('F')`/`date('M')` | Ganti dengan `tanggal_indo()` / `bulan_singkat()` |
| Klik menu di bagian bawah sidebar, sidebar kembali ke atas | Skrip posisi sidebar tidak termuat | Bagian akhir `inc/footer.php` (`pz_sidebar_scroll`) |
| Dropdown filter kembali ke pilihan lama setelah tombol Kembali browser | Browser memulihkan isian form | Sudah ditangani `pageshow` di `laporan.php` & `statistik.php` |

### Login & akun
| Gejala | Penyebab | Periksa |
|---|---|---|
| "Terlalu banyak percobaan…" | 5 gagal per username atau 10 per IP dalam 15 menit | Tunggu 15 menit, atau kosongkan tabel `tblogin_gagal`. Konstanta di `inc/security.php` |
| Password benar tapi selalu salah | Password diubah manual di phpMyAdmin dengan fungsi MD5/SHA1, sehingga tidak dikenali `password_verify()` | Isi kolom `password` dengan teks biasa (otomatis di-hash saat login berikutnya), atau pakai Lupa password |
| Login ditolak "Username atau password salah", padahal yakin benar | (a) akun terhapus karena database diimpor ulang, (b) salah ketik username, (c) password berubah | Admin → **Akun login**: cek username ada. Admin → **Log aktivitas → Login gagal**: alasan tercatat ("tidak terdaftar" / "password salah"). Solusi cepat: tombol **Reset password** |
| Anggota login tapi "Akun belum terhubung dengan data anggota" | `tbuser.id_ang` kosong atau menunjuk anggota yang tidak ada | Isi `id_ang` di `tbuser` |
| Gagal daftar karena username | Username sudah dipakai (kolom unik) | Normal. Validasi di `daftar.php` |
| Ganti password: "Password lama salah" | Salah ketik, atau hash lama rusak | `profil.php` → `verifikasi_password()` |

### Peminjaman & pengajuan
| Gejala | Penyebab | Periksa |
|---|---|---|
| "Stok buku tidak mencukupi." | Pesan dari trigger: stok < jumlah | Data `tbbuku.jml_buku`; trigger `sebelum_peminjaman` / `sebelum_pengembalian` |
| "Masa berlaku keanggotaan sudah habis…" | `tbanggota.masa_berlaku` lewat | Perpanjang di `edit_anggota.php`, atau matikan di Pengaturan |
| "…peminjaman yang melewati jatuh tempo" | Anggota punya pinjaman telat | Kembalikan dulu di `kembali.php` (aturan di `cek_boleh_pinjam()`) |
| "Buku ini sedang dipinjam atau diajukan oleh anggota yang sama" | Buku dobel | Normal |
| Tombol "Serahkan buku" tidak bisa diklik | Alasannya tertulis merah di atas tombol (stok habis, batas, dll.) | `pengajuan.php` |
| Stok tidak berkurang saat buku diserahkan | Trigger belum versi v3 | Impor ulang `migrasi_v3.sql` |
| Error "Unknown column 'tgl_jatuh_tempo'" / "'kategori'" / "Table 'tblog' doesn't exist" | `migrasi_v3.sql` belum diimpor | Impor `migrasi_v3.sql` |
| Tombol Perpanjang tidak muncul | Sudah mencapai `maks_perpanjang`, sudah terlambat, atau `maks_perpanjang` = 0 | Pengaturan; `cek_boleh_perpanjang()` |

### Pengembalian & denda
| Gejala | Penyebab | Periksa |
|---|---|---|
| Denda 0 padahal telat | Trigger lama (menghitung dari `tgl_pinjam`), atau `tgl_jatuh_tempo` sudah bergeser karena perpanjangan | `migrasi_v3.sql`; data `tbpinjam` |
| Besar denda tidak sesuai | `denda_per_hari` di Pengaturan berbeda | Menu Pengaturan |
| Stok tidak bertambah setelah dikembalikan | Trigger `setelah_pengembalian` hilang | Impor ulang `migrasi_v3.sql` |
| Badge "Terlambat" di menu tidak cocok dengan daftar | Keduanya memakai `sql_terlambat()`. Jika beda, ada query yang masih menulis `DATEDIFF(... tgl_pinjam) > 7` | Cari teks `DATEDIFF` di semua file |

### Buku & sampul
| Gejala | Penyebab | Periksa |
|---|---|---|
| "Sampul harus berupa gambar JPG, PNG, atau WEBP." | File bukan gambar asli | Normal (pengaman upload) |
| "Sampul tidak bisa disimpan. Periksa izin folder…" | Folder `assets/img/buku/` tidak bisa ditulis | Izin folder (di hosting: 755/775) |
| "Ukuran sampul maksimal 2 MB." atau upload diam-diam gagal | Batas `unggah_sampul()` atau `upload_max_filesize` di `php.ini` lebih kecil | `inc/aturan.php` / `php.ini` |
| Sampul tidak tampil | File terhapus, atau nama file ISBN tidak cocok | `sampul_buku()`; folder `assets/img/buku/` |
| "ISBN sudah dipakai buku lain." | ISBN dobel | Normal. Validasi di `inc/simpan_buku.php` |
| Buku/anggota tidak bisa dihapus | Punya riwayat peminjaman (disengaja) | Ubah stok jadi 0 / biarkan anggota nonaktif |

### Fitur v4
| Gejala | Penyebab | Periksa |
|---|---|---|
| "Table 'tbreservasi' / 'tbulasan' / 'tbusulan' / 'tbkunjungan' doesn't exist" atau kotak kuning "Impor migrasi_v4.sql" | `migrasi_v4.sql` belum diimpor | Impor `migrasi_v4.sql` |
| "Stok buku ini sedang disiapkan untuk N anggota yang lebih dulu reservasi" | Ada antrean reservasi di depan | Normal. Aturan no. 6 di `cek_boleh_pinjam()` |
| Tombol Reservasi tidak muncul | Stok buku masih ada (tombol hanya untuk stok 0) | `buku_user.php` |
| Form ulasan tidak muncul | Anggota belum pernah mengembalikan buku itu | Normal. `detail_buku.php` bagian ULASAN |
| Meja sirkulasi: "Kartu tidak ditemukan" | Kode salah atau anggota dihapus | Format `AGT-0001`; `id_dari_kode_anggota()` |
| Meja sirkulasi: buku tidak ditemukan saat scan | ISBN di `tbbuku` kosong/berbeda | Isi ISBN di Ubah buku |
| File backup berisi HTML / rusak | Ada output sebelum header download | `backup.php` (buffer dibersihkan sebelum header) |
| Impor backup gagal di trigger | phpMyAdmin tidak mengenali `DELIMITER` saat mode lain | Impor lewat menu Import (bukan tab SQL) |
| Pilihan filter kembali ke opsi pertama setelah diterapkan | Nilai `<option>` dibandingkan dengan tipe berbeda (angka vs teks) | Bandingkan sebagai string, contoh `statistik.php` |

### Laporan & grafik
| Gejala | Penyebab | Periksa |
|---|---|---|
| Dashboard/grafik kosong | Belum ada transaksi | Impor `data_contoh.sql` untuk demo |
| Grafik tidak muncul | `plugins/chart.js/Chart.min.js` tidak termuat | Bagian bawah `beranda_admin.php` / `statistik.php`; Console browser |
| Pengajuan ikut terhitung di laporan | Query tanpa filter status | Query laporan wajib `LOWER(status) IN ('pinjam','kembali')` |

---

## 8. Aturan saat menambah atau mengubah kode

1. **Halaman aplikasi baru:** proses POST dan `header("Location: …")` dulu,
   baru `include "inc/header.php"`, lalu isi, lalu `include "inc/footer.php"`.
   Contoh paling ringkas: `pengaturan.php`.
2. **Halaman admin:** baris pertama `include "inc/admin_only.php";`.
3. **Query dengan input pengguna:** pakai `mysqli_prepare()` atau `(int)`.
   Jangan menempel `$_POST`/`$_GET` langsung ke SQL.
4. **Tampilkan teks dari database:** selalu lewat `e()`.
5. **Aturan pinjam baru:** tambahkan di `cek_boleh_pinjam()` saja, supaya
   berlaku di admin, anggota, dan pengajuan sekaligus.
6. **Hitung terlambat/jatuh tempo:** pakai `sql_terlambat()` / `jatuh_tempo_baris()`,
   jangan menulis angka 7 atau `DATEDIFF` sendiri.
7. **Aksi yang mengubah data:** tambahkan `catat_log('nama_aksi', 'detail')`.
   Jika aksinya baru, daftarkan labelnya di `$label_aksi` di `log_aktivitas.php`.
8. **Warna:** hanya lewat variabel di `assets/theme.css`. Jangan menulis kode warna
   heksadesimal baru di halaman.
9. **Perubahan database:** buat file `database/migrasi_v4.sql` baru dengan
   `IF NOT EXISTS`. Jangan mengubah file migrasi lama.
10. **CSS diubah tapi tampilan tidak berubah?** Browser memakai versi lama dari cache.
    Naikkan nomor `?v=` di semua tag `<link>` (cari teks `theme.css?v=`), atau tekan Ctrl+F5.
11. **Setelah mengubah:** uji minimal login admin, login anggota, pengajuan →
    serahkan → perpanjang → kembalikan.

---

## 9. Checklist uji cepat (±5 menit)

| No | Langkah | Hasil yang benar |
|---|---|---|
| 1 | Login `ZAKY` / `admin123` | Masuk dashboard, muncul peringatan ganti password di Profil |
| 2 | Login `BIMA` / `anggota123` → Koleksi buku → Ajukan 1 buku | Muncul di "Pengajuan menunggu", stok tidak berubah |
| 3 | Admin → Pengajuan → Serahkan buku | Stok berkurang 1, jatuh tempo = hari ini + masa pinjam |
| 4 | Admin → Pengembalian → tombol kalender (perpanjang) | Jatuh tempo bertambah, tombol hilang jika batas tercapai. Di akun anggota tidak ada tombol perpanjang |
| 5 | Admin → Pengembalian → Kembalikan | Stok bertambah 1, denda sesuai keterlambatan |
| 6 | Admin → Riwayat & denda → tandai lunas | Denda anggota berkurang di beranda anggota |
| 7 | Admin → Tambah buku dengan sampul & kategori | Tampil di Buku, Koleksi anggota, dan Katalog publik |
| 8 | Admin → Log aktivitas | Semua langkah di atas tercatat |
| 9 | Admin → Meja sirkulasi → scan `AGT-0003` → scan ISBN buku | Buku dipinjam, stok berkurang |
| 10 | Stok buku = 0 → anggota A dan B reservasi → stok jadi 1 → B mengajukan | B ditolak (A antre lebih dulu); A mendapat pengingat "sudah tersedia" |
| 11 | Admin → Backup database → unduh → impor ke database kosong | Jumlah baris tiap tabel sama, 3 trigger ada |
| 12 | Admin → Laporan → Peminjaman → Periode "Per bulan" → pilih bulan tanpa transaksi | "Tidak ada data", Jumlah data 0, pilihan bulan tetap |
| 13 | Unduh CSV dari laporan yang sama | Jumlah baris CSV = jumlah baris di layar |
| 14 | Admin → Akun login → Reset password anggota → login dengan password sementara | Berhasil; password lama ditolak |
| 15 | Buka dashboard malam hari | Sapaan "Selamat malam", jam sesuai WIB |
