# 📌 Rencana Kerja & Daftar Tugas Harian (Task List)
**Tanggal:** Sabtu, 24 Oktober 2026  
**Fokus Utama:** Penyempurnaan Hirarki Organisasi (`m_dir`) & Finalisasi Alur Penggajian Menuju Demo Klien (Rabu, 28 Oktober 2026)  
**PIC:** Rey Cannavaro & Tim Pengembang HRIS BBS  

---

## 🎯 Target Utama Hari Ini
Menyelesaikan integrasi entitas operasional (`m_dir` / Direktorat / Cabang) ke seluruh modul transaksi penggajian dan memastikan alur operasional dari hulu ke hilir siap 100% untuk demo di hadapan klien.

---

## 📋 Rincian Daftar Tugas (Action Items)

### 1. Penyelarasan Dropdown Filter `m_dir` (Direktorat / Cabang)
> **Tujuan:** Mengimplementasikan keputusan arsitektur dual-layer agar transaksi Perhitungan Gaji & Final Gaji memiliki filter berjenjang yang sama persis dengan Laporan Rekap Gaji (*Direktorat $\rightarrow$ Divisi $\rightarrow$ Departemen*).

- [ ] **Task 1.1: Update UI Form Perhitungan Gaji ([`Blades/t_perhitungan_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/t_perhitungan_gaji.blade.php))**
  - Tambahkan komponen `<FieldSelect>` untuk `Direktorat` (`values.m_dir_id`) yang memanggil API `/operation/m_dir`.
  - Pasang event `@update:valueFull` agar saat Direktorat berubah, dropdown Divisi dan Departemen otomatis di-*reset*.
  - Buat dropdown Divisi menyaring hanya divisi milik direktorat terpilih (`where: m_dir_id = values.m_dir_id`).

- [ ] **Task 1.2: Update Logika Frontend ([`Javascript/t_perhitungan_gaji.js`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Javascript/t_perhitungan_gaji.js))**
  - Tambahkan properti `m_dir_id: null` pada objek reactive `values`.
  - Teruskan parameter `m_dir_id` di payload `generatePerhitungan()`.

- [ ] **Task 1.3: Update Backend Generator ([`Models/t_perhitungan_gaji/Custom.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Models/t_perhitungan_gaji/Custom.php))**
  - Pada fungsi `generateSalary()`, tambahkan query penyaringan karyawan:
    ```php
    if ($req->m_dir_id) $kary = $kary->where('m_kary.m_dir_id', $req->m_dir_id);
    ```
  - Pada `scopeGenerateForFinal()`, tambahkan filter draft perhitungan gaji:
    ```php
    if ($req->m_dir_id) $model = $model->where('t_perhitungan_gaji.m_kary_dir_id', $req->m_dir_id);
    ```

- [ ] **Task 1.4: Update UI & Backend Finalisasi Gaji ([`Blades/t_final_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/t_final_gaji.blade.php) & [`Models/t_final_gaji/Custom.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Models/t_final_gaji/Custom.php))**
  - Tambahkan dropdown `Direktorat` pada form filter batch Finalisasi Gaji.
  - Tambahkan `m_dir_id` ke fillable `$this->fillable` dan simpan nilai direktorat pada header final gaji.

---

### 2. Pengujian Simulasi Alur End-to-End (Dry-Run Pra-Demo)
> **Tujuan:** Memvalidasi bahwa seluruh aturan bisnis MoM 7 Oktober 2026 terhitung akurat dan tidak ada kendala teknis saat live demo.

- [ ] **Task 2.1: Skenario Denda Keterlambatan vs Izin P24 (MoM 3.B)**
  - Uji coba karyawan masuk terlambat tanpa dispensasi $\rightarrow$ denda Rp25.000 per 30 menit muncul di rincian potongan.
  - Uji coba karyawan dengan izin resmi P24 (izin jam-jaman) yang sudah *approved* $\rightarrow$ jam masuk toleran bebas denda.

- [ ] **Task 2.2: Skenario Akumulasi Izin & Pemotongan Cuti (MoM 3.C & 3.D)**
  - Uji coba akumulasi izin > 8 jam $\rightarrow$ otomatis memotong 1 hari kuota cuti tahunan.
  - Uji coba saat kuota cuti habis $\rightarrow$ sistem memotong gaji secara proporsional ($gaji\_pokok / 25$).
  - Uji coba izin sakit dengan surat dokter (SKD) $\rightarrow$ bebas potongan upah dan cuti.

- [ ] **Task 2.3: Skenario Kasbon / Piutang Karyawan (`t_potongan`)**
  - Buat draft transaksi pinjaman kasbon di `t_potongan` dengan status `POSTED`.
  - Generate perhitungan gaji $\rightarrow$ pastikan potongan cicilan kasbon muncul otomatis di rincian gaji.
  - Lakukan posting pada Finalisasi Gaji $\rightarrow$ pastikan catatan pembayaran otomatis terbuat di `t_potongan_det_bayar` dan status lunas ter-update jika nilai sudah tercukupi.

- [ ] **Task 2.4: Skenario Fleksibilitas Rentang Tanggal (MoM 3.G)**
  - Uji coba generate gaji bulanan reguler (contoh: `01/10/2026` s/d `31/10/2026`).
  - Uji coba generate gaji mingguan staf harian dengan tanggal kustom (contoh: `12/10/2026` s/d `18/10/2026`).

---

### 3. Pemeriksaan Kualitas Output Laporan & Cetak (*Demo Assets*)
> **Tujuan:** Memastikan dokumen fisik dan unduhan yang akan dilihat langsung oleh klien memiliki tampilan yang rapi dan profesional.

- [ ] **Task 3.1: Validasi Slip Gaji Karyawan ([`web_report_slip_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_slip_gaji.blade.php))**
  - Pastikan logo, header perusahaan, rincian komponen pendapatan, dan potongan tampil bersih.
  - Uji tombol cetak PDF dan pastikan layout pas 1 lembar tanpa terpotong.

- [ ] **Task 3.2: Validasi Rekapitulasi Gaji per Divisi ([`web_report_rekap_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_rekap_gaji.blade.php))**
  - Pastikan ringkasan filter aktif di pojok kiri atas (Direktorat, Divisi, Periode) tampil tepat.
  - Cek formula subtotal dan total transfer pada ekspor Excel.

- [ ] **Task 3.3: Validasi File Payroll Bank Mandiri ([`web_report_payroll.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_payroll.blade.php))**
  - Pastikan kolom nomor rekening, nama karyawan, dan nominal transfer netto sesuai format *batch payroll* perbankan.

---

### 4. Penyusunan Skenario & Alur Cerita Demo (*Demo Storyline*)
> **Tujuan:** Menyiapkan alur presentasi sistematis agar demo di hadapan klien berjalan percaya diri, runtut, dan terstruktur.

- [ ] **Task 4.1: Susun Urutan Alur Presentasi**
  1. **Mastering:** Pengenalan struktur organisasi (`BBS CABANG A` $\rightarrow$ Divisi $\rightarrow$ Departemen) & Standar Upah per Zona.
  2. **Operasional:** Input izin jam-jaman P24 dan SPK Lembur mingguan.
  3. **Drafting (Perhitungan Gaji):** Simulasi klik tombol *Generate Gaji* dengan filter fleksibel.
  4. **Review & Approval (Finalisasi Gaji):** Penguncian data (*Lock/POSTED*) dan pemotongan kasbon otomatis.
  5. **Reporting:** Pembuktian Slip Gaji karyawan, Rekap Divisi, dan File Payroll Bank Mandiri.

---

## ⏱️ Estimasi Waktu Kerja Hari Ini
| No | Blok Tugas | Estimasi Waktu | Status |
|---|---|---|---|
| 1 | Penyelarasan Dropdown Filter `m_dir` di Form Transaksi Gaji | 1 - 2 Jam | Siap Dikerjakan |
| 2 | Pengujian Simulasi Alur End-to-End (Dry-Run 4 Skenario MoM) | 2 Jam | Menunggu Task 1 |
| 3 | Pemeriksaan Kerapian Tampilan Laporan (Slip, Rekap, Payroll) | 1 Jam | Menunggu Task 2 |
| 4 | Penyusunan Skenario Alur Cerita Demo Klien | 1 Jam | Sore / Sesi Akhir |

---
*Dokumen ini dibuat otomatis sebagai panduan kerja mandiri hari ini. Setiap task yang selesai dapat langsung dicentang `[x]`.*
