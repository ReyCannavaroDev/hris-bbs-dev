# 📝 NOTULENSI & REKAP HASIL SHOWING HRIS BBS
**Tanggal Pelaksanaan Showing:** Rabu, 28 Oktober 2026  
**Tanggal Penyusunan Rekap:** Kamis, 29 Oktober 2026  
**Target Rilis / Presentasi Lanjutan:** Jumat, 6 November 2026 (Pukul 13:00 WIB via Zoom)  
**Narasumber / Pencatat Lapangan:** Mas Duta (On-Site BBS Kupang Indah)  
**Dukungan Teknis & Remote:** Rey (Dev Lead)

---

## 📌 Ringkasan Eksekutif
Berdasarkan sesi demonstrasi sistem (*showing*) di hadapan manajemen PT Berkah Bersaudara Sentosa (BBS) pada tanggal 28 Oktober 2026, secara garis besar alur inti HRIS (struktur organisasi, presensi mobile, dan alur finalisasi payroll) telah diapresiasi dan diterima dengan baik. 

Terdapat beberapa penyesuaian aturan bisnis (*business logic*) dan permintaan fitur tambahan yang perlu diakomodasi sebelum sesi presentasi final via Zoom pada **Jumat, 6 November 2026**. Dokumen ini merangkum seluruh poin notulensi, memetakan progres yang telah diselesaikan, dan menyusun rencana kerja teknisnya.

---

## 📋 Detail Notulensi Rapat & Keputusan Bisnis

### 1. Keterlambatan Presensi & Denda (MoM Poin 3.B)
- **Aturan Pembulatan Baru:**  
  Keterlambatan **dibulatkan ke atas**. Terlambat 1 menit dihitung dan dibulatkan menjadi 30 menit (kena 1 tier denda Rp 25.000).
- **Formula Teknis:**  
  $$\text{Tier Denda} = \lceil \frac{\text{Menit Terlambat}}{30} \rceil$$
  $$\text{Total Denda} = \text{Tier Denda} \times Rp\ 25.000$$
  - *Contoh Kasus:*
    - Terlambat 1 menit s/d 30 menit $\rightarrow$ Denda Rp 25.000.
    - Terlambat 31 menit s/d 60 menit $\rightarrow$ Denda Rp 50.000.
- **Dispensasi:** Karyawan yang memiliki Izin Resmi P24 yang disetujui atasan tetap dibebaskan dari denda keterlambatan.

---

### 2. Standar Upah & Master Gaji Karyawan
- **Gaji Pokok Per-Personal:**  
  Nilai gaji pokok karyawan diatur **langsung per-personal** pada data masing-masing karyawan (`m_kary`), bukan diseragamkan kaku berdasarkan tabel grading/zona upah.
- **Fungsi Grading & Zona Gaji:**  
  Master `m_zona` dan `m_grading` tetap dipertahankan namun difungsikan murni sebagai **klasifikasi data / catatan profil karyawan**, bukan penentu mutlak angka gaji pokok.
- **Field Baru `gaji_acuan` (Dasar Iuran BPJS):**  
  Menambahkan field baru `gaji_acuan` (default mengikuti nilai **UMK Kabupaten setempat**). Field ini dipakai khusus sebagai basis pengali iuran BPJS Kesehatan dan BPJS Ketenagakerjaan.
- **Skema Staf Harian & Borongan:**  
  Model perhitungan harian berbasis kehadiran dan SPK disetujui dan dinyatakan aman.

---

### 3. Kebijakan Pajak Penghasilan (PPh 21)
- **Keputusan:**  
  Pajak PPh 21 karyawan **disubsidi / ditanggung penuh oleh perusahaan**.
- **Implementasi Sistem:**  
  Menggunakan skema *Gross-up / Tunjangan PPh 21*, di mana nilai pajak dihitung oleh sistem namun diimbangi dengan tunjangan pajak dari perusahaan, sehingga nilai *Take Home Pay* (gaji bersih) karyawan **tidak terpotong oleh PPh 21**.

---

### 4. Siklus & Tanggal Cut-Off Penggajian
- **Tanggal Cut-off Resmi:**  
  Fix ditetapkan pada **Tanggal 25** setiap bulannya.
- **Rentang Periode Penggajian Bulanan:**  
  Berjalan dari **tanggal 26 bulan sebelumnya s/d tanggal 25 bulan berjalan** (contoh: 26 September – 25 Oktober).
- **Preset Sistem:**  
  Form filter periode di modul Perhitungan Gaji (`t_perhitungan_gaji`) dan Finalisasi Gaji (`t_final_gaji`) otomatis memberikan default rentang tanggal 26 s/d 25.

---

### 5. Aturan Cuti & Dispensasi
- **Cut-off Saldo Cuti:** Ikut siklus tanggal 25.
- **Karakteristik Kuota Cuti Reguler:**  
  - Hanya ada **1 jatah kuota utama**, yaitu **12 hari per tahun** (tidak dipisah-pisah kuotanya per tipe izin umum).
  - Batas pengambilan cuti reguler: Maksimal **3 hari berturut-turut** dalam satu pengajuan (selama sisa jatah kuota masih mencukupi).
- **Izin Khusus (Di luar / Tidak Memotong Kuota 12 Hari):**
  - **Cuti Menikah:** 3 hari.
  - **Keluarga Meninggal:** 3 hari.
  - **Sakit dengan Surat Keterangan Dokter (SKD):** 3 hari.
- **Cuti Akumulasi P24:**  
  Aturan akumulasi izin jam-jaman $\ge 8$ jam memotong 1 hari kuota cuti tahunan (dan potong gaji proporsional jika kuota habis) dinyatakan aman dan disetujui.

---

### 6. Mutasi Karyawan di Tengah Periode Cut-Off
- **Keputusan:**  
  Klien memilih **Opsi 2 (Prorata Split Tanggal)** untuk mengakomodasi kebutuhan laporan pembebanan anggaran (*cost center*) per divisi.
- **Mekanisme Perhitungan:**  
  Jika karyawan dimutasi pada pertengahan periode penggajian:
  - Hari kerja sebelum tanggal efektif mutasi dibebankan ke Divisi A.
  - Hari kerja setelah tanggal efektif mutasi dibebankan ke Divisi B.
  - Nilai gaji pokok dan tunjangan dibagi secara proporsional sesuai hari kerja riil di masing-masing divisi.

---

### 7. Piutang / Kasbon Karyawan (`t_potongan`)
- **Kolom Baru pada Landing Table:**  
  Menambahkan kolom **Sisa Angsuran (Rp)** di tabel depan modul `t_potongan` agar pihak Finance dapat memonitor sisa kewajiban piutang karyawan tanpa harus klik tombol detail satu per satu.
- **Skenario Pelunasan Khusus (Karyawan Resign):**  
  Menyediakan tombol aksi **Pelunasan Manual** pada transaksi kasbon. Tombol ini digunakan saat karyawan mengundurkan diri dan melunasi sisa kasbonnya secara langsung/tunai di luar sistem pemotongan gaji rutin. Tombol ini otomatis memperbarui status menjadi `is_lunas = 1`.

---

### 8. Sinkronisasi Status Resign & Inactive Karyawan (`m_kary`)
- **Mekanisme Dua Arah (*Two-Way Trigger*):**
  1. Jika user mengisi field `tanggal_berhenti_kerja` (tanggal keluar) $\rightarrow$ sistem otomatis mengubah status karyawan menjadi **Inactive** (`is_active = false`).
  2. Sebaliknya, jika toggle switch status karyawan diubah menjadi **Inactive** $\rightarrow$ field `tanggal_berhenti_kerja` otomatis terisi dengan tanggal hari ini.

---

### 9. Transaksi Lembur & Approval (`t_lembur`) — ✅ Selesai Dikerjakan (Duta - 29 Okt 2026)
- **Penyelesaian Alur Approval (Commit `11b923f`):**  
  - Target approver pada workflow persetujuan lembur sudah divalidasi ketat di `Models/t_lembur/Custom.php`: hanya **Superadmin** dan **Atasan Langsung** (`m_kary.atasan_id == auth()->user()->m_kary_id`) yang berhak menyetujui, menolak, atau merevisi (`canApprove`).
  - Handling form saat status `IN APPROVAL` otomatis terkunci menjadi *Read-Only* (`isReadOnly`) di `Blades/t_lembur.blade.php` dan `Javascript/t_lembur.js`, dan tombol aksi `Approve`/`Reject`/`Revise` hanya muncul jika user memiliki hak akses.
- **Perbaikan UI Tombol & Format Tanggal (Commit `2dd2e97` & `ec9cf12`):**  
  - Tombol aksi **Send Approval** (`location-arrow`) sudah aktif untuk status `DRAFT`.
  - Format tanggal transaksi di menu **Notifikasi** dan tabel landing **Lembur** sudah rapi menggunakan helper `formatDate()` (`DD/MM/YYYY`).

---

## 📊 Matriks Status Pengerjaan (Update 29 Oktober 2026)

| No | Modul / Fitur | PIC | Status | Keterangan & Commit |
|---|---|---|:---:|---|
| 1 | **Alur Approval Lembur (`t_lembur`)** | Mas Duta | ✅ Selesai | Validasi approver atasan/superadmin & read-only in approval (`11b923f`) |
| 2 | **Format Tanggal Notifikasi & Lembur** | Mas Duta | ✅ Selesai | Formatter tanggal DD/MM/YYYY rapi (`2dd2e97`, `ec9cf12`) |
| 3 | **Denda Keterlambatan Pembulatan ke Atas** | Rey | ⏳ To Do | Pembulatan `ceil($menit/30) * 25000` di Cores |
| 4 | **Gaji Pokok Per-Personal & Field `gaji_acuan` (UMK BPJS)** | Rey / Tim | ⏳ To Do | Master gaji karyawan & dasar pengali BPJS |
| 5 | **Sinkronisasi Resign & Inactive Karyawan** | Rey / Tim | ⏳ To Do | Two-way trigger `tanggal_berhenti_kerja` $\leftrightarrow$ `is_active` |
| 6 | **Preset Cut-Off Payroll Tanggal 25** | Rey | ⏳ To Do | Rentang tanggal otomatis 26 s/d 25 di form gaji |
| 7 | **PPh 21 Disubsidi Perusahaan (Gross-up)** | Rey | ⏳ To Do | Tunjangan pajak agar take home pay utuh |
| 8 | **Mutasi Karyawan Prorata (Opsi 2)** | Rey | ⏳ To Do | Split perhitungan hari kerja divisi lama vs divisi baru |
| 9 | **Aturan Cuti 12 Hari & Split Izin Khusus** | Rey / Tim | ⏳ To Do | Batas 3 hari berturut-turut & izin khusus terpisah |
| 10 | **Kolom Sisa Angsuran & Pelunasan Manual Kasbon** | Rey / Tim | ⏳ To Do | Kolom nominal sisa & tombol pelunasan karyawan resign |

---

## 🎯 Rencana Kerja & Action Items (Sprint 29 Okt – 5 Nov 2026)

Berikut daftar tugas pengerjaan yang akan diselesaikan secara bertahap menuju presentasi final tanggal 6 November 2026:

### Track 0: Transaksi Lembur & Notifikasi (Dikerjakan Mas Duta)
- [x] **Task 0.1:** Perbaiki visibilitas tombol aksi Send Approval pada data lembur status DRAFT (`Javascript/t_lembur.js`).
- [x] **Task 0.2:** Kunci form lembur menjadi *Read-Only* saat berstatus `IN APPROVAL` (`isReadOnly = true`).
- [x] **Task 0.3:** Validasi wewenang approval lembur hanya untuk Superadmin dan Atasan Langsung dari karyawan terkait (`Models/t_lembur/Custom.php`).
- [x] **Task 0.4:** Rapikan format tanggal pada tabel Notifikasi dan tabel landing Lembur (`formatDate`).

### Track 1: Modul Presensi & Keterlambatan
- [ ] **Task 1.1:** Update logika denda keterlambatan di `Cores/PerhitunganGaji.php`: ubah formula denda menjadi pembulatan ke atas per kelipatan 30 menit (`ceil($menit / 30) * 25000`).
- [ ] **Task 1.2:** Verifikasi proteksi izin P24 agar tetap bebas denda saat pembulatan baru diterapkan.

### Track 2: Master Karyawan & Master Upah (`m_kary` & `m_standart_gaji`)
- [ ] **Task 2.1:** Tambahkan field `gaji_acuan` (basis UMK BPJS) pada migrasi dan model master gaji/karyawan.
- [ ] **Task 2.2:** Sesuaikan input `m_kary` agar gaji pokok per-personal menjadi prioritas utama kalkulasi gaji.
- [ ] **Task 2.3:** Buat logika sinkronisasi reaktif di `m_kary` antara toggle `is_active` dan field `tanggal_berhenti_kerja`.

### Track 3: Engine Perhitungan Gaji & Pajak (`t_perhitungan_gaji` & `Cores`)
- [ ] **Task 3.1:** Ubah default rentang periode penggajian menjadi siklus cut-off tanggal 25 (tgl 26 bulan lalu s/d tgl 25 bulan berjalan).
- [ ] **Task 3.2:** Terapkan skema PPh 21 Gross-up (tunjangan pajak ditanggung perusahaan) agar take-home pay karyawan utuh.
- [ ] **Task 3.3:** Implementasikan kalkulasi prorata mutasi divisi (Opsi 2) untuk karyawan yang mengalami mutasi di tengah periode.

### Track 4: Modul Cuti & Aturan 12 Hari (`t_cuti`)
- [ ] **Task 4.1:** Validasi batas maksimal 3 hari berturut-turut untuk cuti tahunan dari kuota 12 hari.
- [ ] **Task 4.2:** Pisahkan counter untuk izin khusus (Menikah 3 hari, Keluarga Meninggal 3 hari, Sakit 3 hari) agar tidak memotong kuota 12 hari cuti reguler.

### Track 5: Modul Piutang / Kasbon (`t_potongan`)
- [ ] **Task 5.1:** Tambahkan kolom `Sisa Angsuran` (Nominal Rp) pada tampilan tabel landing `t_potongan`.
- [ ] **Task 5.2:** Buat endpoint dan tombol aksi `Pelunasan Manual` untuk pelunasan kasbon di luar pemotongan payroll rutin.

### Track 6: Quality Assurance & Simulasi End-to-End
- [x] **Task 6.1:** Uji coba alur approval lembur bersama Mas Duta (`t_lembur` $\rightarrow$ `IN APPROVAL` $\rightarrow$ `APPROVED` $\rightarrow$ `l_spl`).
- [ ] **Task 6.2:** Simulasi payroll lengkap dengan cut-off 25, denda baru, PPh 21 subsidi, dan mutasi divisi.
- [ ] **Task 6.3:** Persiapan slide & skenario live demo Zoom Jumat, 6 November 2026.

---
*Dokumen ini disusun sebagai panduan resmi pengembangan dan tindak lanjut tim HRIS BBS Dev.*
