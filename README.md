# �️ PHP Remote File Manager

> **Single-file PHP administration panel** dengan tampilan terminal — manajemen file server, eksekusi perintah, monitoring sistem, dan tools keamanan untuk keperluan **penetration testing & CTF**.

> ⚠️ **Hanya untuk digunakan pada server milik sendiri atau dengan izin tertulis yang sah.**

---

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [File Manager](#-file-manager)
- [Tools Panel](#-tools-panel)
- [Cara Install & Akses](#-cara-install--akses)
- [Konfigurasi](#-konfigurasi)
- [Tampilan UI](#-tampilan-ui)
- [Info Sistem](#-info-sistem)

---

## ✨ Fitur Utama

| Kategori | Deskripsi |
|---|---|
| 🗂️ File Manager | Browse, upload, download, edit, rename, delete, chmod |
| ⚡ Command Runner | Eksekusi perintah OS secara remote |
| 🐘 PHP Sandbox | Evaluasi kode PHP langsung dari browser |
| 🗄️ SQL Client | Koneksi dan query ke database MySQL/MariaDB |
| 🌐 Network Tools | TCP port scanner, payload generator |
| 🔬 Server Recon | Informasi server, privilege, file sensitif |
| � File Encryption | Enkripsi / dekripsi file dengan kunci rahasia |
| � Code Injector | Penyisipan kode massal ke file tertentu |
| � Page Overwriter | Timpa halaman index di direktori target |
| �️ Security Tools | Timestamp editor, log cleaner, header modifier |
| 👻 Stealth Deploy | Pemasangan modul tersembunyi via konfigurasi server |
| 📊 System Monitor | Real-time CPU, RAM, disk usage, info PHP |
| 🌍 Translator | Integrasi Google Translate |
| 🔄 Auto Refresh | File listing & system info update otomatis |

---

## 🗂️ File Manager

Navigasi direktori server secara penuh dengan tampilan tabel yang dapat diurutkan.

### Operasi pada File & Folder

| Aksi | Keterangan |
|---|---|
| **VIEW** | Melihat isi konten file (read-only) |
| **EDIT** | Mengedit & menyimpan isi file langsung dari browser |
| **RN** | Mengganti nama file atau folder |
| **CP** | Menduplikasi file / folder ke lokasi lain |
| **MV** | Memindahkan file / folder ke direktori lain |
| **CH** | Mengubah permission akses (misal: 0755, 0644) |
| **HASH** | Menampilkan checksum MD5, SHA1, SHA256 |
| **DL** | Mengunduh file ke komputer lokal |
| **DEL** | Menghapus file / folder (rekursif untuk folder) |
| **ZIP** | Mengekstrak arsip `.zip` langsung di server |

### Aksi Massal (Multi-Select)

Centang lebih dari satu item lalu pilih aksi:

- **Multi Delete** — hapus banyak item sekaligus
- **Multi Copy / Move** — salin atau pindahkan ke folder tujuan
- **Multi Chmod** — ubah permission banyak item sekaligus
- **Multi Encrypt** — enkripsi / dekripsi banyak file sekaligus

### Upload & Buat Item Baru

- **Upload File** — unggah file dari perangkat lokal ke server
- **Buat File** — membuat file teks kosong baru
- **Buat Folder** — membuat direktori baru
- **Compress (ZIP)** — mengarsipkan seluruh isi folder aktif

### Sorting

Klik header kolom untuk mengurutkan: `Name` · `Type` · `Size` · `Permissions`

---

## ⚙️ Tools Panel

Semua tools diakses melalui baris tombol menu di bagian atas panel.

---

### [>_] Command Runner

Jalankan perintah sistem operasi (Linux/Windows) secara remote dari browser.

Mendukung berbagai metode eksekusi: `system`, `shell_exec`, `exec`, `passthru`, `popen`, `proc_open`, dan `COM` (Windows).

---

### [PHP] PHP Sandbox

Evaluasi dan jalankan kode PHP secara langsung tanpa membuat file baru.

Berguna untuk debugging, testing fungsi, atau eksplorasi environment PHP server.

---

### [SQL] Database Client

Koneksi ke database dan eksekusi query SQL secara langsung.

- Mendukung **MySQL / MariaDB** (via PDO extension)
- Hasil query ditampilkan dalam format tabel
- Input: Host, Username, Password, Nama Database, Query

---

### [NET] Port Scanner

Pemindai port TCP sederhana untuk mengecek status port pada suatu host.

- Input: IP / Domain target + daftar nomor port (dipisah koma)
- Status: OPEN / CLOSED / FILTERED

---

### [!] Payload Generator

Generator payload koneksi balik (reverse connection) untuk pengujian jaringan.

Mendukung berbagai jenis: Bash, Python, Python3, Netcat, PHP, Perl.

---

### [?] Server Recon

Pengumpulan informasi server secara otomatis:

- Informasi user yang menjalankan proses web
- Ketersediaan tool downloader di server (`wget`, `curl`)
- Pencarian file konfigurasi sensitif (`.env`, `config.php`, `database.php`, dll.)

---

### [#] Process Manager

Manajemen proses yang sedang berjalan di server:

- **Linux**: Menampilkan proses via `ps aux`
- **Windows**: Menampilkan proses via `tasklist`
- Kirim signal terminate ke proses tertentu berdasarkan PID

---

### [⚙] Code Injector

Penyisipan kode massal ke file-file di direktori yang ditentukan:

- Tentukan direktori target dan ekstensi / nama file yang diincar
- Tulis kode yang ingin disisipkan (prepend ke setiap file yang cocok)
- Berjalan secara rekursif ke seluruh sub-folder

---

### [X] Security Cleanup

Protokol pembersihan jejak dan modifikasi respons server:

1. **Timestamp Editor** — menyamakan tanggal modifikasi file dengan file lain di server
2. **Log Cleaner** — menghapus access/error log web server dan history shell (Linux)
3. **Header Modifier** — mengubah nilai header HTTP respons server

---

### [☢] Stealth Module Deployer

Pemasangan modul tersembunyi menggunakan dua metode berbeda:

| Metode | Cara Kerja |
|---|---|
| Config Injection | Menyisipkan auto-prepend ke file konfigurasi PHP (`.user.ini`) |
| Polyglot File | Membuat file gambar yang sekaligus valid sebagai skrip PHP via konfigurasi server |

Modul yang terpasang hanya aktif apabila ada header HTTP khusus pada request, sehingga tidak terdeteksi pada akses normal.

---

### [!] File Encryption Module

Enkripsi dan dekripsi file menggunakan algoritma XOR + Base64:

- **Mode Lock** — mengenkripsi file dan menambahkan ekstensi `.locked`
- **Mode Unlock** — mendekripsi kembali dengan kunci yang sama
- **Notice Page** — membuat halaman pemberitahuan HTML di direktori target
- Mendukung satu file atau seluruh isi folder (rekursif)

> Pastikan menyimpan kunci dengan aman — file tidak dapat dipulihkan tanpa kunci yang sama.

---

### [☠] Index Page Overwriter

Menimpa file halaman utama (index) di dalam direktori target secara massal:

- Target: `index.php`, `index.html`, `default.php`, `default.html`, `home.php`, `home.html`
- File asli otomatis disimpan dengan ekstensi `.bak` sebelum ditimpa
- Berjalan rekursif ke seluruh sub-folder

---

## 🚀 Cara Install & Akses

### 1. Upload File

Upload `1.php` ke server tujuan melalui FTP, SFTP, cPanel file manager, atau metode lain.

### 2. Buka di Browser

```
http://alamat-server.com/path/ke/1.php
```

### 3. Login

Halaman awal menyerupai tampilan **Google Error 404** sebagai kamuflase.

- Klik area di pojok kanan bawah halaman
- Ketik password, lalu tekan `Enter`

> Password default: `123` — **wajib diganti sebelum digunakan!**

---

## 🔧 Konfigurasi

Ubah nilai password di bagian `Config` pada baris awal file:

```php
class Config
{
    public static $password = "IsiPasswordKamu";
}
```

---

## 🖥️ Tampilan UI

- **Tema**: Dark mode bergaya terminal (hijau di atas hitam)
- **Modal SPA**: Popup aksi file tanpa full page reload
- **HUD Panel**: Arahkan kursor ke tombol untuk melihat penjelasan kontekstual
- **Real-time**: File listing & info sistem diperbarui otomatis setiap 2.5 detik
- **Responsif**: Mendukung layar mobile tanpa layout shift
- **Keyboard**: Tekan `ESC` untuk menutup modal
- **Translator**: Google Translate terintegrasi (tombol 🌐)
- **Back to Top**: Tombol ▲ muncul otomatis saat scroll ke bawah

---

## ℹ️ Info Sistem

Panel sistem menampilkan informasi real-time server berikut:

| Info | Keterangan |
|---|---|
| OS | Nama & versi sistem operasi server |
| Privilege | User & group yang menjalankan proses web |
| Server | Software web server aktif (Apache/Nginx/IIS) |
| PHP | Versi PHP yang berjalan |
| IP Server | Alamat IP internal server |
| IP Visitor | IP yang sedang mengakses panel |
| Storage | Sisa ruang disk / total + tipe media (SSD/HDD) |
| Server Load | CPU load average & konsumsi RAM |
| Active Mods | Ekstensi PHP yang aktif (curl, pdo, zip, dll.) |
| Disabled Funcs | Fungsi PHP yang dinonaktifkan konfigurasi server |
| Waktu | Jam server real-time lengkap dengan timezone |

---

## 📁 Struktur Proyek

```
bekdor/
└── 1.php     # Panel utama (single-file application)
```

---

<div align="center">

**© 2026 PHP Remote Manager**

*"Menulislah dengan kode, biarkan sistem yang bercerita."*

</div>
