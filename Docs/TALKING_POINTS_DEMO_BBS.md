# 🎙️ PANDUAN TALKING POINTS & SKRIP DEMO HRIS BBS
**Tanggal Presentasi:** Rabu, 28 Oktober 2026  
**Target Audiens:** Manajemen & Direksi PT Berkah Bersaudara Sentosa (BBS)  
**Tujuan Demo:** Memperagakan sistem otomasi penggajian yang menjawab 100% kebutuhan bisnis notulensi rapat MoM 7 Oktober 2026, mulai dari struktur organisasi multi-cabang, kedisiplinan presensi, hingga pencairan payroll perbankan.

---

## 🧭 Ikhtisar Alur Presentasi (Total Waktu: ± 25 - 30 Menit)

```
[01. Pembukaan (3 Menit)]
       │
       ▼
[02. Master Data & Standar Upah (5 Menit)]
       │
       ▼
[03. Skenario Aturan MoM: Presensi, P24 & Kasbon (7 Menit)]
       │
       ▼
[04. Eksekusi Payroll: Generate & Finalisasi (7 Menit)] ──> The "WOW" Moment!
       │
       ▼
[05. Laporan Eksekutif: Slip Gaji, Rekap Divisi, Payroll Mandiri (5 Menit)]
       │
       ▼
[06. Penutup & Sesi Tanya Jawab (Q&A)]
```

---

## 📌 Sesi 1: Pembukaan & Nilai Tambah Sistem (± 3 Menit)

### 🎯 Tujuan:
Membangun kesan profesional, menyampaikan bahwa sistem ini dikembangkan khusus sesuai karakter operasional BBS hasil diskusi 7 Oktober 2026 lalu.

### 🗣️ Apa yang Diucapkan (Talking Points):
> *"Selamat pagi/siang Bapak/Ibu Direksi dan Manajemen PT Berkah Bersaudara Sentosa.*  
> *Terima kasih atas waktu dan kesempatan yang diberikan pada hari ini. Hari ini kami sangat antusias menunjukkan sistem HRIS BBS yang telah kami sempurnakan.*  
>   
> *Fokus utama pengembangan ini adalah menjawab seluruh poin kesepakatan notulensi rapat tanggal 7 Oktober 2026 lalu, di mana Bapak/Ibu membutuhkan sistem penggajian yang:*  
> 1. *Mendukung struktur multi-cabang secara fleksibel tanpa kerumitan administrasi.*  
> 2. *Mengotomasi aturan kedisiplinan secara adil—seperti denda keterlambatan Rp 25.000 per 30 menit, namun tetap manusiawi dengan dispensasi izin resmi P24.*  
> 3. *Menangani akumulasi izin jam-jaman yang dikonversikan ke cuti dan pemotongan upah proporsional jika cuti habis.*  
> 4. *Mengunci alur kasbon karyawan secara otomatis agar tidak ada pinjaman yang luput dipotong.*  
> 5. *Menyediakan dokumen siap saji—mulai dari Slip Gaji 1 lembar A4, Rekapitulasi per Divisi, hingga File Batch Payroll Bank Mandiri.*  
>   
> *Mari kita langsung lihat bagaimana sistem ini bekerja dari hulu ke hilir."*

---

## 📌 Sesi 2: Pondasi Struktur Organisasi & Standar Upah (± 5 Menit)

### 🖥️ Apa yang Dibuka di Layar:
1. Masuk menu **Master Data** $\rightarrow$ **Direktorat / Cabang** (`m_dir`).
2. Masuk menu **Master Data** $\rightarrow$ **Standar Gaji & Upah per Zona** (`m_standart_gaji`).

### 🗣️ Apa yang Diucapkan (Talking Points):
> *"Bapak/Ibu, sistem ini dibangun dengan struktur organisasi yang sangat rapi dan berjenjang:*  
> - *Di level tertinggi operasional, kita memiliki **Direktorat / Cabang**, seperti yang sudah terdaftar di sistem yaitu **BBS CABANG A**.*  
> - *Setiap cabang menaungi **Divisi**, dan setiap divisi membawahi **Departemen**.*  
> - *Karyawan kita diikat langsung ke cabang masing-masing.*  
>   
> *Selain itu, untuk menjamin kepatuhan regulasi upah:*  
> - *Kita memiliki Master **Standar Upah per Zona** dan **Grading (Golongan Karyawan)**.*  
> - *Gaji pokok, uang saku, tunjangan posisi, tunjangan tetap, hingga tunjangan kemahalan langsung terisi otomatis sesuai zona penempatan karyawan, sehingga tim HR tidak perlu menginput angka gaji secara manual berulang-ulang."*

---

## 📌 Sesi 3: Skenario Presensi & Kasbon (MoM 7 Oktober 2026) (± 7 Menit)

### 🎯 Tujuan:
Membuktikan bahwa seluruh *business rules* yang diminta klien benar-benar berfungsi secara otomatis tanpa manipulasi spreadsheet manual.

### 🖥️ Apa yang Ditunjukkan di Layar:
Buka menu **Presensi / Absensi** dan menu **Potongan Pinjaman (Kasbon)** (`t_potongan`).

### 🗣️ Apa yang Diucapkan (Talking Points):
> *"Sekarang kita masuk ke bagian yang paling krusial, yaitu aturan kedisiplinan dan hak karyawan:*  
>   
> **1. Denda Keterlambatan vs Izin Resmi P24 (MoM Poin 3.B):**  
> *BBS memiliki aturan: keterlambatan dipotong denda Rp 25.000 setiap kelipatan 30 menit. Sistem kami menghitung menit keterlambatan harian secara presisi.*  
> *Namun, jika karyawan memiliki **Izin Jam-jaman P24** yang sudah disetujui atasan (misal izin urusan keluarga sampai jam 09.30), sistem memberikan dispensasi jam masuk dan **tidak mengenakan denda keterlambatan**.*  
>   
> **2. Akumulasi Izin Jam-jaman & Potongan Cuti (MoM Poin 3.C & 3.D):**  
> *Jika seorang karyawan sering izin jam-jaman dan dalam sebulan totalnya mencapai $\ge$ 8 jam, sistem otomatis mengonversikannya menjadi 1 hari pemotongan kuota cuti tahunan.*  
> *Jika kuota cuti tahunannya sudah habis, sistem secara cerdas akan memotong gaji secara proporsional sebesar upah harian ($Gaji\_Pokok / 25$).*  
> *Sedangkan jika izin karena sakit dengan Surat Keterangan Dokter (SKD), sistem melindunginya sehingga **bebas dari potongan upah maupun kuota cuti**.*  
>   
> **3. Pemotongan Kasbon Otomatis (MoM Poin 3.E):**  
> *Di menu Kasbon (`t_potongan`), saat ada karyawan mengajukan pinjaman dan disetujui, HR tidak perlu mengingat-ingat kapan harus memotongnya. Sistem penggajian akan otomatis menarik cicilan tersebut dan saat gaji disetujui, catatan angsuran langsung terbit dan status pinjaman otomatis ditandai **LUNAS** jika cicilan terakhir selesai."*

---

## 📌 Sesi 4: Eksekusi Payroll (The "WOW" Moment) (± 7 Menit)

### 🎯 Tujuan:
Menunjukkan kecepatan, kemudahan, dan fleksibilitas proses penggajian dari klik *Generate* sampai penguncian data.

### 🖥️ Langkah Demi Langkah di Layar:
1. Buka menu **Perhitungan Gaji** (`t_perhitungan_gaji`) $\rightarrow$ klik tombol **Generate Gaji**.
2. **Pilih Periode:** Tunjukkan fleksibilitas tanggal (bisa mingguan untuk staf borongan/harian, atau bulanan `01/10/2026` s/d `31/10/2026` untuk staf tetap).
3. **Pilih Direktorat / Cabang:** Pilih `BBS CABANG A`.
   - *Tunjukkan:* Dropdown Divisi langsung menyaring hanya divisi milik cabang tersebut.
   - *Tunjukkan:* Jika Direktorat dihapus/diubah, Divisi dan Departemen otomatis direset (tidak ada data nyangkut).
4. Klik tombol biru **Generate**.
5. Muncul tabel rincian draft gaji dengan kolom: *No, Periode, NIK, Nama Karyawan, Direktorat, Divisi, Departemen, Gaji Bersih, Catatan, dan Aksi*.
6. Klik ikon informasi (ℹ️) pada salah satu karyawan untuk membuka **Modal Rincian Gaji**.
7. Klik tombol hijau **Simpan**.
8. Buka menu **Finalisasi Gaji** (`t_final_gaji`) $\rightarrow$ klik **Tambah Data**.
9. Masukkan periode dan filter cabang $\rightarrow$ klik **Generate**.
10. Tunjukkan fitur **Adjustment** (bisa tambah baris insentif/koreksi jika diperlukan manajemen).
11. Klik tombol merah/hijau **Posted Data**.

### 🗣️ Apa yang Diucapkan (Talking Points):
> *"Sekarang mari kita lihat betapa mudahnya HR menjalankan kalkulasi gaji bulanan:*  
>   
> *(Sambil memilih tanggal dan cabang BBS CABANG A)*  
> *'Perhatikan Bapak/Ibu, sistem kita sangat fleksibel. Kita bisa memilih generate untuk seluruh cabang, atau spesifik hanya untuk **BBS CABANG A**. Pilihan Divisi dan Departemennya juga mengikuti cabang yang kita pilih secara otomatis.'*  
>   
> *(Saat klik tombol Generate)*  
> *'Hanya dengan satu kali klik tombol **Generate**, sistem langsung menghitung ratusan variabel di balik layar: gaji pokok ditarik, denda telat dihitung, akumulasi izin dikonversi, dan cicilan kasbon dipotong.*  
> *Mari kita klik tombol info di baris karyawan ini—semua komponen pendapatan (+) dan potongan (-) terpampang sangat transparan tanpa ada angka gaib.'*  
>   
> *(Buka Finalisasi Gaji dan klik Posted Data)*  
> *'Setelah diperiksa oleh Manager HR atau Finance, data masuk ke tahap Finalisasi Gaji. Di sini manajemen memiliki kendali penuh untuk mengunci data dengan status **POSTED**.*  
> *Begitu tombol Posting ditekan, data terkunci permanen agar tidak bisa diedit sembarangan, dan cicilan kasbon karyawan tadi otomatis tercatat lunas di buku piutang perusahaan.'* "

---

## 📌 Sesi 5: Dokumen Pelaporan & Payroll Bank Mandiri (± 5 Menit)

### 🖥️ Apa yang Dibuka di Layar:
1. **Laporan Slip Gaji Karyawan** (`web_report_slip_gaji.blade.php`).
2. **Laporan Rekapitulasi Gaji per Divisi** (`web_report_rekap_gaji.blade.php`).
3. **Laporan Payroll Bank Mandiri** (`web_report_payroll.blade.php`).

### 🗣️ Apa yang Diucapkan (Talking Points):
> *"Setelah proses penggajian selesai, sistem telah menyiapkan 3 jenis dokumen luaran resmi:*  
>   
> **1. Slip Gaji Karyawan:**  
> *Tampilannya didesain khusus pas 1 lembar A4 tanpa terpotong. Lengkap dengan identitas NIK, Nama, Cabang, Divisi, rincian komponen pendapatan, dan potongan yang sangat rapi untuk diserahkan ke karyawan.*  
>   
> **2. Laporan Rekapitulasi Gaji per Divisi:**  
> *Dokumen ini ditujukan untuk evaluasi Direksi dan Keuangan. Di pojok kiri atas terdapat ringkasan filter aktif (Cabang dan Divisi), rincian take-home pay, total PPH21, hingga grand total pengeluaran gaji perusahaan yang siap di-export ke Excel atau PDF.*  
>   
> **3. File Batch Payroll Bank Mandiri:**  
> *Ini adalah fitur pamungkas untuk tim Finance. Kita tidak perlu lagi mengetik ulang satu per satu nama karyawan dan nomor rekening ke portal perbankan.*  
> *Format ini sudah disesuaikan persis dengan standar **Corporate Payroll Bank Mandiri**—lengkap dengan nomor rekening pengirim, mata uang IDR, nomor rekening tujuan, dan nominal transfer netto.'* "

---

## 📌 Sesi 6: Penutup & Tanya Jawab (Handling Objections / Q&A)

### 🗣️ Kalimat Penutup:
> *"Demikian demonstrasi alur penggajian HRIS BBS. Seluruh alur mulai dari struktur organisasi, kehadiran, perizinan, kasbon, hingga transfer perbankan kini telah terintegrasi dalam satu pintu tanpa perlu spreadsheet terpisah.*  
> *Waktu dan tempat kami persilakan kepada Bapak/Ibu Direksi jika ada tanggapan, pertanyaan, atau skenario khusus yang ingin dicoba langsung."*

---

### 💡 Contekan Jawaban Cepat untuk Pertanyaan Klien (Q&A Cheat Sheet):

| Pertanyaan Klien | Jawaban Terbaik yang Disarankan |
|---|---|
| **"Bagaimana kalau nanti BBS buka cabang baru selain Cabang A?"** | *"Sangat mudah Bapak/Ibu. Cukup daftarkan nama cabang baru di master Direktorat/Cabang, maka seluruh form filter penggajian, divisi, dan departemen otomatis langsung mengenali cabang baru tersebut tanpa perlu ubah coding."* |
| **"Apakah bisa generate gaji untuk karyawan yang masuk di pertengahan bulan?"** | *"Bisa sekali. Form kami memiliki filter Periode Awal dan Periode Akhir yang fleksibel. Kita bisa generate khusus dari tanggal karyawan mulai masuk hingga akhir bulan, dan upahnya otomatis terhitung proporsional."* |
| **"Bagaimana jika ada atasan yang memberikan dispensasi khusus keterlambatan di luar P24?"** | *"Di tahap Finalisasi Gaji, kami menyediakan fitur **Adjustment**. Tim HRD/Finance dapat menyesuaikan nilai potongan atau menambahkan catatan khusus sebelum data diposting permanen."* |
| **"Apakah data yang sudah difinalkan bisa diedit kembali oleh staf biasa?"** | *"Tidak bisa. Begitu statusnya **POSTED**, data langsung terkunci (*read-only*). Selain itu sistem mencatat siapa pembuat (`creator_id`) dan siapa yang terakhir menyetujui (`last_editor_id`) sebagai rekam jejak audit (*audit trail*)."* |
| **"Bagaimana keamanan data gaji karyawan antar divisi?"** | *"Sistem kami memiliki filter hak akses berbasis role dan menu. Hanya user berwenang (seperti Direksi, Finance, dan HR Payroll) yang memiliki izin untuk melihat modul finalisasi dan nominal gaji."* |

---
*Dokumen ini dirancang khusus untuk memandu presentasi live demo HRIS BBS pada hari Rabu, 28 Oktober 2026.*
