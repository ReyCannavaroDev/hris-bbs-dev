# ðŸ“ NOTULENSI & REKAP HASIL SHOWING HRIS BBS
**Tanggal Pelaksanaan Showing:** Rabu, 28 Oktober 2026  
**Tanggal Penyusunan Rekap:** Kamis, 29 Oktober 2026  
**Pembaruan Status Terakhir:** Rabu, 4 November 2026  
**Target Rilis / Presentasi Lanjutan:** Jumat, 6 November 2026 (Pukul 13:00 WIB via Zoom)  
**Narasumber / Pencatat Lapangan:** Mas Duta (On-Site BBS Kupang Indah)  
**Dukungan Teknis & Remote:** Rey (Dev Lead)

---

## 📌 Ringkasan Eksekutif
Berdasarkan sesi demonstrasi sistem (*showing*) di hadapan manajemen PT Berkah Bersaudara Sentosa (BBS) pada tanggal 28 Oktober 2026, secara garis besar alur inti HRIS (struktur organisasi, presensi mobile, dan alur finalisasi payroll) telah diapresiasi dan diterima dengan baik. 

Terdapat beberapa penyesuaian aturan bisnis (*business logic*) dan permintaan fitur tambahan yang perlu diakomodasi sebelum sesi presentasi final via Zoom pada **Jumat, 6 November 2026**. Dokumen ini merangkum seluruh poin notulensi, memetakan progres yang telah diselesaikan, dan menyusun rencana kerja teknisnya.

---

## 📋 Detail Notulensi Rapat & Keputusan Bisnis

### 1. Keterlambatan Presensi & Denda (MoM Poin 3.B) — ✅ Selesai Dikerjakan (Mas Duta - 3 Nov 2026, Commit `482ea06`)
- **Aturan Pembulatan Baru:**  
  Keterlambatan **dibulatkan ke atas**. Terlambat 1 menit dihitung dan dibulatkan menjadi 30 menit (kena 1 tier denda Rp 25.000).
- **Formula Teknis:**  
  $$\text{Tier Denda} = \lceil \frac{\text{Menit Terlambat}}{30} \rceil$$
  $$\text{Total Denda} = \text{Tier Denda} \times Rp\ 25.000$$
  - *Contoh Kasus:*
    - Terlambat 1 menit s/d 30 menit $\rightarrow$ Denda Rp 25.000.
    - Terlambat 31 menit s/d 60 menit $\rightarrow$ Denda Rp 50.000.
- **Dispensasi:** Karyawan yang memiliki Izin Resmi P24 yang disetujui atasan tetap dibebaskan dari denda keterlambatan.
- **Detail Implementasi (Commit `482ea06`):**
  - Formula pembulatan ke atas `(int) ceil($menit / 30)` aktif di `Cores/PerhitunganGaji.php`.
  - Disertakan data riwayat presensi harian pada komponen denda keterlambatan.
  - Dropdown interaktif *'Rincian Keterlambatan'* ditambahkan pada modal rincian gaji di `Blades/t_perhitungan_gaji.blade.php` dan `Blades/t_final_gaji.blade.php`.

---

### 2. Standar Upah & Master Gaji Karyawan — ✅ Selesai Dikerjakan (Rey Cannavaro - 4 Nov 2026)
- **Gaji Pokok Per-Personal:**  
  Nilai gaji pokok karyawan diatur **langsung per-personal** pada data masing-masing karyawan (`m_kary`), bukan diseragamkan kaku berdasarkan tabel grading/zona upah.
- **Fungsi Grading & Zona Gaji:**  
  Master `m_zona` dan `m_grading` tetap dipertahankan namun difungsikan murni sebagai **klasifikasi data / catatan profil karyawan**, bukan penentu mutlak angka gaji pokok.
- **Field Baru `gaji_acuan` (Dasar Iuran BPJS):**  
  Menambahkan field baru `gaji_acuan` (default mengikuti nilai **UMK Kabupaten setempat**). Field ini dipakai khusus sebagai basis pengali iuran BPJS Kesehatan dan BPJS Ketenagakerjaan.
  - Untuk penggajian **Bulanan**: Iuran BPJS dipotong utuh 1 bulan penuh (Kesehatan 1%, JHT 2%, JP 1%).
  - Untuk penggajian **Mingguan**: Iuran BPJS otomatis dibagi **1/4 (prorata mingguan)** agar transparan per minggu dan pas terakumulasi 1 bulan penuh.
- **Skema Staf Harian & Borongan:**  
  Model perhitungan harian berbasis kehadiran dan SPK disetujui dan dinyatakan aman.

---

### 3. Kebijakan Pajak Penghasilan (PPh 21) — ✅ Selesai Dikerjakan (Rey Cannavaro - 4 Nov 2026)
- **Keputusan:**  
  Pajak PPh 21 karyawan **disubsidi / ditanggung penuh oleh perusahaan**.
- **Implementasi Sistem:**  
  Menggunakan skema *Gross-up / Tunjangan PPh 21*, di mana nilai pajak dihitung oleh sistem namun diimbangi dengan tunjangan pajak dari perusahaan, sehingga nilai *Take Home Pay* (gaji bersih) karyawan **tidak terpotong oleh PPh 21**.

---

### 4. Siklus & Tanggal Cut-Off Penggajian — ✅ Selesai Dikerjakan (Mas Duta - 3 Nov 2026, Commit `d0ba545`)
- **Tanggal Cut-off Resmi:**  
  Fix ditetapkan pada **Tanggal 25** setiap bulannya.
- **Rentang Periode Penggajian Bulanan & Mingguan:**  
  - Bulanan: Berjalan dari **tanggal 26 bulan sebelumnya s/d tanggal 25 bulan berjalan** (contoh: 26 September – 25 Oktober).
  - Mingguan: Auto-set durasi 1 minggu di modul perhitungan.
- **Preset Sistem & Fleksibilitas Penggajian:**  
  - Form filter periode di modul Perhitungan Gaji (`t_perhitungan_gaji`) dan Finalisasi Gaji (`t_final_gaji`) otomatis memberikan default rentang tanggal 26 s/d 25 untuk bulanan atau 1 minggu untuk mingguan dengan format rapi `DD/MM/YYYY`.
  - Penambahan opsi tipe penggajian khusus **'LEMBUR SAJA'** yang terintegrasi langsung dengan `t_perhitungan_lembur`.
  - Filter tipe penggajian (Bulanan/Mingguan) disempurnakan agar presisi, dan auto-set rentang tanggal dibebaskan jika dibutuhkan skenario rangkap gaji.
  - Perbaikan layout form: mengatasi floating label overlapping dengan placeholder, serta menangani bug blank screen dan `value is not defined`.

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

### 7. Piutang / Kasbon Karyawan (`t_potongan`) — ✅ Selesai Dikerjakan (Mas Duta - 3 Nov 2026, Commit `482ea06`)
- **Kolom Baru pada Landing Table:**  
  Menambahkan kolom **Sisa Angsuran (Rp)** di tabel depan modul `t_potongan` agar pihak Finance dapat memonitor sisa kewajiban piutang karyawan tanpa harus klik tombol detail satu per satu.
- **Skenario Pelunasan Khusus (Karyawan Resign):**  
  Menyediakan tombol aksi **Pelunasan Manual** pada transaksi kasbon. Tombol ini digunakan saat karyawan mengundurkan diri dan melunasi sisa kasbonnya secara langsung/tunai di luar sistem pemotongan gaji rutin. Tombol ini otomatis memperbarui status menjadi `is_lunas = 1`.
- **Detail Implementasi (Commit `482ea06`):**
  - Penambahan kolom `sisa_angsuran` via `transformRowData` di `Models/t_potongan/Custom.php` dan kolom tabel di `Blades/t_potongan.blade.php` serta `Javascript/t_potongan.js`.
  - Endpoint `custom_pelunasanManual` di `Models/t_potongan/Custom.php` dengan pencatatan otomatis ke `t_potongan_det_bayar` (`status_bayar = 'LUNAS MANUAL'`).

---

### 8. Sinkronisasi Status Resign & Inactive Karyawan (`m_kary`) — ✅ Selesai Dikerjakan (Rey Cannavaro - 3 Nov 2026)
- **Mekanisme Dua Arah (*Two-Way Trigger*):**
  1. Jika user mengisi field `tanggal_berhenti_kerja` (tanggal keluar) $\rightarrow$ sistem otomatis mengubah status karyawan menjadi **Inactive** (`is_active = false` / `0`).
  2. Sebaliknya, jika toggle switch status karyawan diubah menjadi **Inactive** $\rightarrow$ field `tanggal_berhenti_kerja` otomatis terisi dengan tanggal hari ini.
  3. Jika status diaktifkan kembali menjadi **Active** (rehire) $\rightarrow$ field `tanggal_berhenti_kerja` otomatis dibersihkan (`null`).
- **Penyederhanaan Form & Standardisasi Format Tanggal (`DD/MM/YYYY`):**
  1. **Holding Company dihapus:** Kolom *Holding Company* dihilangkan dari form karena sudah terwakili oleh pilihan *Company* (otomatis mengambil `m_comp_id` dari relasi `m_dir`).
  2. **Penyederhanaan Label:** Label diubah dari `Company / Cabang` menjadi cukup `Company` (baik pada form informasi, popup atasan, maupun kolom tabel landing).
  3. **Penataan Layout Grid & Switch Toggle:** Menghapus div dummy spacer serta menyelaraskan field `Can Outscope` (kiri) dan `Status` (kanan) ke dalam grid simetris 2 kolom (`col-span-8 md:col-span-6`) dengan komponen *toggle switch* yang seragam (Tidak/Ya dan Inactive/Active).
  4. **Standardisasi Format Tanggal (`DD/MM/YYYY`):** Semua input tanggal (`tgl_masuk`, `tgl_berhenti`, `tgl_lahir`, `npwp_tgl_berlaku`) dan kolom landing table diseragamkan dengan format tampilan `DD/MM/YYYY`, serta disinkronkan secara aman dengan format `YYYY-MM-DD` saat komunikasi database/API.
- **Detail Implementasi:**
  - Penambahan helper `onChangeStatusKaryawan`, `onChangeTanggalBerhenti`, `parseDate`, `formatDateDisplay`, dan `formatDateApi` serta reactive `watch` dua arah di `Javascript/m_karyawan.js` dan `Blades/m_karyawan.blade.php`.
  - Penambahan konversi dua arah tanggal pada backend `createBefore`, `updateBefore`, dan `transformRowData` di `Models/m_kary/Custom.php`.
  - Sinkronisasi otomatis ke akun pengguna di `updateAfter`: jika status karyawan menjadi inaktif, user login (`default_users`) terkait otomatis dinonaktifkan (`is_active = 0`).

---

### 9. Transaksi Lembur, Approval & Dokumen SPK (`t_lembur`) — ✅ Selesai Dikerjakan (Mas Duta - 29 Okt & 3 Nov 2026, Commit `11b923f` & `d0ba545`)
- **Penyelesaian Alur Approval (Commit `11b923f`):**  
  - Target approver pada workflow persetujuan lembur sudah divalidasi ketat di `Models/t_lembur/Custom.php`: hanya **Superadmin** dan **Atasan Langsung** (`m_kary.atasan_id == auth()->user()->m_kary_id`) yang berhak menyetujui, menolak, atau merevisi (`canApprove`).
  - Handling form saat status `IN APPROVAL` otomatis terkunci menjadi *Read-Only* (`isReadOnly`) di `Blades/t_lembur.blade.php` dan `Javascript/t_lembur.js`, dan tombol aksi `Approve`/`Reject`/`Revise` hanya muncul jika user memiliki hak akses.
  - Penyesuaian tampilan dan cetak dokumen SPK Lembur (`l_spl`).
- **Perbaikan UI Tombol & Format Tanggal (Commit `2dd2e97`, `ec9cf12`, & `d0ba545`):**  
  - Tombol aksi **Send Approval** (`location-arrow`) sudah aktif untuk status `DRAFT`.
  - Format tanggal transaksi di menu **Notifikasi**, tabel landing **Lembur**, dan **Perhitungan Lembur** sudah rapi menggunakan helper `formatDate()` (`DD/MM/YYYY`).
  - Modul **Perhitungan Lembur** (`t_perhitungan_lembur`) otomatis mengatur durasi filter ke rentang 1 minggu.

---

## 📊 Matriks Status Pengerjaan (Update 4 November 2026)

| No | Modul / Fitur | PIC | Status | Keterangan & Commit |
|---|---|---|:---:|---|
| 1 | **Alur Approval Lembur & SPK (`t_lembur`)** | Mas Duta | ✅ Selesai | Validasi approver atasan/superadmin, read-only in approval, & format SPK (`11b923f`, `d0ba545`) |
| 2 | **Format Tanggal Notifikasi & Lembur** | Mas Duta | ✅ Selesai | Formatter tanggal DD/MM/YYYY rapi di Notifikasi, Lembur, & SPL (`2dd2e97`, `ec9cf12`, `d0ba545`) |
| 3 | **Preset Cut-Off Payroll Tanggal 25 & Fleksibilitas Gaji** | Mas Duta | ✅ Selesai | Auto-set dinamis 26 s/d 25 bulanan, 1 minggu lembur, opsi 'LEMBUR SAJA', & fix label overlapping (`d0ba545`) |
| 4 | **Denda Keterlambatan Pembulatan ke Atas** | Mas Duta | ✅ Selesai | Formula `ceil($menit/30) * 25000`, history presensi harian, & dropdown rincian denda (`482ea06`) |
| 5 | **Kolom Sisa Angsuran & Pelunasan Manual Kasbon (`t_potongan`)** | Mas Duta | ✅ Selesai | Kolom Sisa Angsuran di landing table & tombol/endpoint Pelunasan Manual (`482ea06`) |
| 6 | **Gaji Pokok Per-Personal & Field `gaji_acuan` (UMK BPJS)** | Rey / Tim | ✅ Selesai | Input gaji pokok personal prioritas di m_kary, auto-fill standar gaji, & field gaji_acuan BPJS |
| 7 | **Sinkronisasi Resign & Inactive Karyawan** | Rey / Tim | ✅ Selesai | Two-way reactive trigger `tgl_berhenti` $\leftrightarrow$ `is_active` & auto-deactivate `default_users` |
| 8 | **PPh 21 Disubsidi Perusahaan (Gross-up)** | Rey | ✅ Selesai | Tunjangan PPh 21 subsidi perusahaan penyeimbang potongan pajak agar take home pay utuh |
| 9 | **Mutasi Karyawan Prorata (Opsi 2)** | Rey Cannavaro | ✅ Selesai | Prorata split tanggal divisi lama vs baru, snapshot gaji pokok/acuan di t_mutasi, & sinkronisasi m_kary |
| 10 | **Aturan Cuti 12 Hari, Validasi SKD & Self-Approval (`t_cuti`)** | Rey Cannavaro | ✅ Selesai | Batas 3 hari berturut-turut, wajib lampiran SKD, company field, & izin self-approval superadmin (`431784f`) |
| 11 | **Approval Lembur Siklus 2 & Catatan Revisi (`t_lembur`)** | Duta & Rey | ✅ Selesai | Fix tombol aksi Approver 2 status HALF APPROVED siklus 2 & mandatory revisi note frontend-backend (`d9f0f2f`) |
| 12 | **Validasi Duplikasi Tarif Lembur & Reset Copy (`m_lembur`)** | Rey Cannavaro | ✅ Selesai | Cek duplikasi company-divisi-dept, reset kode saat copy data, & toggle status filter (`c1d7fc4`, `92ab949`) |
| 13 | **Perbaikan Form Master Pengguna (`m_pengguna`)** | Rey Cannavaro | ✅ Selesai | Fix teks tumpang tindih saat pilih NIK, bersihkan placeholder company, & sinkronisasi status aktif (`2f91940`) |
| 14 | **Penyempurnaan Master Karyawan (`m_kary` / QA Feedback)** | Rey Cannavaro | ✅ Selesai | Fix NIK vs No KTP, edit/delete interaktif baris detail, preview ijazah/referensi, & fix blank form create (`e747651`, `3a97c8c`, `569dc6c`) |
| 15 | **Refactor Grid Layout Form Gaji (`t_perhitungan_gaji`)** | Duta & Rey | ✅ Selesai | Pindahkan dropdown Tipe Penggajian ke samping periode tanggal (grid simetris 2 kolom) (`3398589`) |

---

## 🎯 Rencana Kerja & Action Items (Sprint 29 Okt – 5 Nov 2026)

Berikut daftar tugas pengerjaan yang akan diselesaikan secara bertahap menuju presentasi final tanggal 6 November 2026:

### Track 0: Transaksi Lembur, Notifikasi & Finalisasi Gaji (Dikerjakan Mas Duta)
- [x] **Task 0.1:** Perbaiki visibilitas tombol aksi Send Approval pada data lembur status DRAFT (`Javascript/t_lembur.js`).
- [x] **Task 0.2:** Kunci form lembur menjadi *Read-Only* saat berstatus `IN APPROVAL` (`isReadOnly = true`).
- [x] **Task 0.3:** Validasi wewenang approval lembur hanya untuk Superadmin dan Atasan Langsung dari karyawan terkait (`Models/t_lembur/Custom.php`, `Models/generate_approval/custom.php`).
- [x] **Task 0.4:** Rapikan format tanggal pada tabel Notifikasi, tabel landing Lembur, dan Surat Perintah Lembur (`formatDate` `DD/MM/YYYY`).
- [x] **Task 0.5:** Auto-set rentang durasi 1 minggu pada modul Perhitungan Lembur (`Javascript/t_perhitungan_lembur.js`).
- [x] **Task 0.6:** Tambahkan opsi tipe penggajian khusus **'LEMBUR SAJA'** pada Finalisasi Gaji terintegrasi dengan perhitungan lembur (`Javascript/t_final_gaji.js`).
- [x] **Task 0.7:** Selesaikan bug layar blank saat create & 'value is not defined' saat view pada Finalisasi Gaji serta atasi overlapping label form.

### Track 1: Modul Presensi & Keterlambatan
- [x] **Task 1.1:** Update logika denda keterlambatan di `Cores/PerhitunganGaji.php`: ubah formula denda menjadi pembulatan ke atas per kelipatan 30 menit (`ceil($menit / 30) * 25000`).
- [x] **Task 1.2:** Verifikasi proteksi izin P24 agar tetap bebas denda saat pembulatan baru diterapkan.

### Track 2: Master Karyawan & Master Upah (`m_kary` & `m_standart_gaji`) — ✅ Selesai Dikerjakan (Rey Cannavaro - 4 Nov 2026)
- [x] **Task 2.1:** Tambahkan field `gaji_acuan` (basis UMK BPJS) pada migrasi, model master, dan kalkulasi potongan resmi BPJS (Kesehatan 1%, JHT 2%, JP 1%).
- [x] **Task 2.2:** Sesuaikan input `m_kary` agar gaji pokok per-personal menjadi prioritas utama kalkulasi gaji.
- [x] **Task 2.3:** Buat logika sinkronisasi reaktif di `m_kary` antara toggle `is_active` dan field `tanggal_berhenti_kerja`.

### Track 3: Engine Perhitungan Gaji & Pajak (`t_perhitungan_gaji` & `Cores`)
- [x] **Task 3.1:** Ubah default rentang periode penggajian menjadi siklus cut-off tanggal 25 (tgl 26 bulan lalu s/d tgl 25 bulan berjalan) dan mingguan dinamis (`Javascript/t_perhitungan_gaji.js`, `d0ba545`).
- [x] **Task 3.2:** Terapkan skema PPh 21 Gross-up (tunjangan pajak ditanggung perusahaan) agar take-home pay karyawan utuh (`Models/t_perhitungan_gaji/Custom.php`, `Javascript/t_final_gaji.js`, `Blades/t_final_gaji.blade.php`).
- [x] **Task 3.3:** Implementasikan kalkulasi prorata mutasi divisi (Opsi 2) untuk karyawan yang mengalami mutasi di tengah periode di `t_mutasi`, `Cores/PerhitunganGaji.php`, dan `Models/t_perhitungan_gaji/Custom.php`.

### Track 4: Modul Cuti & Aturan 12 Hari (`t_cuti`) — ✅ Selesai Dikerjakan (Rey Cannavaro - 3 Nov 2026)
- [x] **Task 4.1:** Validasi batas maksimal 3 hari berturut-turut untuk cuti tahunan dari kuota 12 hari di `Models/t_cuti/Custom.php` dan `Javascript/t_cuti.js`.
- [x] **Task 4.2:** Pisahkan counter untuk izin khusus (Menikah 3 hari, Keluarga Meninggal 3 hari, Sakit SKD 3 hari) agar tidak memotong kuota 12 hari cuti reguler di `Models/m_kary/Custom.php`, `Cores/PerhitunganGaji.php`, dan `Models/t_cuti/Custom.php`.

### Track 5: Modul Piutang / Kasbon (`t_potongan`)
- [x] **Task 5.1:** Tambahkan kolom `Sisa Angsuran` (Nominal Rp) pada tampilan tabel landing `t_potongan`.
- [x] **Task 5.2:** Buat endpoint dan tombol aksi `Pelunasan Manual` untuk pelunasan kasbon di luar pemotongan payroll rutin.

### Track 6: Quality Assurance & Simulasi End-to-End
- [x] **Task 6.1:** Uji coba alur approval lembur bersama Mas Duta (`t_lembur` $\rightarrow$ `IN APPROVAL` $\rightarrow$ `APPROVED` $\rightarrow$ `l_spl`).
- [x] **Task 6.2:** Simulasi payroll lengkap dengan cut-off 25, denda baru, PPh 21 subsidi, dan mutasi divisi (Kalkulasi diverifikasi akurat & diserahkan ke Tim QA untuk testing end-to-end menyeluruh).
- [ ] **Task 6.3:** Persiapan slide & skenario live demo Zoom Jumat, 6 November 2026.

---
*Dokumen ini disusun sebagai panduan resmi pengembangan dan tindak lanjut tim HRIS BBS Dev.*
