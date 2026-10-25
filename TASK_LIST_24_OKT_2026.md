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

- [x] **Task 1.1: Update UI Form Perhitungan Gaji ([`Blades/t_perhitungan_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/t_perhitungan_gaji.blade.php))**
  - ✅ Ditambahkan `<FieldSelect>` untuk `Direktorat / Cabang` (`values.m_dir_id`) via API `/operation/m_dir`.
  - ✅ Dropdown Divisi & Departemen otomatis di-*reset* saat Direktorat berubah (`values.m_divisi_id = null`, `values.m_dept_id = null`, `detailArr = []`).
  - ✅ Dropdown Divisi memfilter dinamis `where: values.m_dir_id ? 'this.m_dir_id=' + values.m_dir_id + ' and this.is_active=\'true\'' : 'this.is_active=\'true\''`.
  - ✅ Dropdown Departemen memfilter dinamis berdasarkan Divisi / Direktorat terpilih. Tanda `*` wajib dihilangkan agar fleksibel.
  - ✅ Kolom `Direktorat` ditambahkan pada tabel preview rincian karyawan (`item['m_kary_dir.nama']`).

- [x] **Task 1.2: Update Logika Frontend ([`Javascript/t_perhitungan_gaji.js`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Javascript/t_perhitungan_gaji.js))**
  - ✅ Ditambahkan `m_dir_id: null` pada objek reactive `values`.
  - ✅ Payload `generatePerhitungan()` meneruskan `m_dir_id` ke endpoint `/operation/t_perhitungan_gaji/generate`.
  - ✅ Ditambahkan kolom `m_kary_dir.nama` (Direktorat) dan `m_kary_divisi.nama` (Divisi) pada tabel landing page.

- [x] **Task 1.3: Update Backend Generator ([`Models/t_perhitungan_gaji/Custom.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Models/t_perhitungan_gaji/Custom.php))**
  - ✅ Pada fungsi `generateSalary()`, ditambahkan filter query karyawan:
    ```php
    if ($req->m_dir_id) $kary = $kary->where('m_kary.m_dir_id', $req->m_dir_id);
    ```
  - ✅ Pada `scopeGenerateForFinal()`, ditambahkan filter draft perhitungan gaji:
    ```php
    if ($req->m_dir_id) $model = $model->where('t_perhitungan_gaji.m_kary_dir_id', $req->m_dir_id);
    ```

- [x] **Task 1.4: Update UI & Backend Finalisasi Gaji ([`Blades/t_final_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/t_final_gaji.blade.php), [`Javascript/t_final_gaji.js`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Javascript/t_final_gaji.js), [`Models/t_final_gaji/Custom.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Models/t_final_gaji/Custom.php), [`Models/t_final_gaji/Alter.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Models/t_final_gaji/Alter.php))**
  - ✅ Ditambahkan dropdown `Direktorat / Cabang` pada form filter batch Finalisasi Gaji dengan cascading reset ke Divisi & Departemen.
  - ✅ Ditambahkan `m_dir_id: null` pada reactive `values` & parameter query `GenerateForFinal` di `Javascript/t_final_gaji.js`.
  - ✅ Ditambahkan `m_dir_id` ke `$this->fillable`, `$this->createable`, `$this->updateable`, dan `$this->searchable` di `Models/t_final_gaji/Custom.php`.
  - ✅ Ditambahkan relasi `public function m_dir()` ke model `App\Models\BasicModels\m_dir`.
  - ✅ Ditambahkan kolom `m_dir_id` secara kondisional di `Models/t_final_gaji/Alter.php`.
  - ✅ Kolom `Direktorat` ditambahkan pada tabel preview batch dan detail mapping.

---

### 2. Pengujian Simulasi Alur End-to-End (Dry-Run Pra-Demo)
> **Tujuan:** Memvalidasi bahwa seluruh aturan bisnis MoM 7 Oktober 2026 terhitung akurat dan tidak ada kendala teknis saat live demo.

- [x] **Task 2.1: Skenario Denda Keterlambatan vs Izin P24 (MoM 3.B)**
  - ✅ Terverifikasi pada `App\Cores\PerhitunganGaji::hitungDendaKeterlambatan()`:
    - Keterlambatan dihitung per kelipatan 30 menit x Rp 25.000 (`floor($menit / 30) * 25000`).
    - Pengecekan izin P24 (`t_cuti` dengan `time_to` dan status `APPROVED`/`POSTED`): jam masuk toleran disesuaikan dengan batas akhir izin sehingga terbebas dari denda.

- [x] **Task 2.2: Skenario Akumulasi Izin & Pemotongan Cuti (MoM 3.C & 3.D)**
  - ✅ Terverifikasi pada `App\Cores\PerhitunganGaji::hitungAkumulasiIzinDanPotonganCuti()`:
    - Akumulasi izin parsial jam-jaman $\ge$ 8 jam (480 menit) otomatis dikonversi menjadi 1 hari kuota cuti tahunan (`floor($totalJam / 8)`).
    - Jika sisa kuota cuti karyawan habis, kelebihannya otomatis memotong upah harian secara proporsional ($gaji\_pokok / 25$).
    - Izin sakit dengan surat dokter (keterangan/alasan 'sakit') secara otomatis dikecualikan dari pemotongan kuota cuti maupun upah.

- [x] **Task 2.3: Skenario Kasbon / Piutang Karyawan (`t_potongan`)**
  - ✅ Terverifikasi pada `App\Cores\PerhitunganGaji::tarikPotonganKasbon()` & `Models/t_perhitungan_gaji/Custom.php`:
    - Transaksi pinjaman kasbon `t_potongan` (`status = 'POSTED'`, `is_lunas = 0`) otomatis ditarik sebagai komponen potongan gaji (`factor = '-'`).
    - Pada saat Finalisasi Gaji diposting (`custom_postData`), sistem otomatis membuat catatan cicilan di `t_potongan_det_bayar` dan meng-update `is_lunas = 1` ketika total bayar $\ge$ nilai pinjaman.

- [x] **Task 2.4: Skenario Fleksibilitas Rentang Tanggal (MoM 3.G)**
  - ✅ Normalisasi rentang tanggal fleksibel (mendukung format `Y-m-d` harian/mingguan maupun `Y-m` bulanan) aktif di `Models/t_perhitungan_gaji/Custom.php`.
  - ✅ Label periode dinamis (contoh: `12/10/2026 - 18/10/2026` untuk mingguan atau `01/10/2026 - 31/10/2026` untuk bulanan).

---

### 3. Pemeriksaan Kualitas Output Laporan & Cetak (*Demo Assets*)
> **Tujuan:** Memastikan dokumen fisik dan unduhan yang akan dilihat langsung oleh klien memiliki tampilan yang rapi dan profesional.

- [x] **Task 3.1: Validasi Slip Gaji Karyawan ([`web_report_slip_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_slip_gaji.blade.php))**
  - ✅ Header menampilkan identitas lengkap karyawan, NIK, Direktorat, Divisi, Departemen, No. Final Gaji, dan Catatan.
  - ✅ Komponen gaji bersih, tunjangan (+), potongan (-), dan take-home pay (=) ditata dengan zebra striping rapi, font seragam, dan format mata uang Rupiah.
  - ✅ Null-safe saat data kosong dan siap cetak 1 lembar A4.

- [x] **Task 3.2: Validasi Rekapitulasi Gaji per Divisi ([`web_report_rekap_gaji.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_rekap_gaji.blade.php))**
  - ✅ Filter aktif di pojok kiri atas (Direktorat, Divisi, Departemen, Periode) tampil tepat dan akurat.
  - ✅ Menampilkan rincian Gaji, PPH, dan Netto per karyawan dengan subtotal dan grand total di baris paling bawah.

- [x] **Task 3.3: Validasi File Payroll Bank Mandiri ([`web_report_payroll.blade.php`](file:///c:/Users/Rey%20Cannavaro/hris-bbs-dev/Blades/web_report_payroll.blade.php))**
  - ✅ Header menampilkan ringkasan Rekening Pengirim, Total Karyawan, dan Total Nominal Netto.
  - ✅ Kolom tabel sesuai format batch payroll Mandiri: No, NIK, Nama Karyawan (BPK/IBU), Direktorat, Divisi, Departemen, Posisi, IDR, Netto Gaji, Bank MANDIRI, Cabang MADIUN, Catatan.

---

### 4. Penyusunan Skenario & Alur Cerita Demo (*Demo Storyline*)
> **Tujuan:** Menyiapkan alur presentasi sistematis agar demo di hadapan klien berjalan percaya diri, runtut, dan terstruktur.

- [x] **Task 4.1: Susun Urutan Alur Presentasi (Rabu, 28 Oktober 2026)**
  1. **Mastering Organisasi:** Tunjukkan struktur Direktorat (`BBS CABANG A`), Divisi, dan Departemen, serta integrasi Standar Upah per Zona.
  2. **Operasional Harian & MoM:** Peragakan input Izin P24 (bebas denda telat), input izin > 8 jam (konversi cuti), dan pengajuan Kasbon Karyawan.
  3. **Drafting (Perhitungan Gaji):** Tunjukkan fleksibilitas filter Direktorat $\rightarrow$ Divisi $\rightarrow$ Departemen, klik *Generate*, dan buka modal *Rincian Gaji* karyawan.
  4. **Review & Approval (Finalisasi Gaji):** Buka batch finalisasi, lakukan penyesuaian (*Adjustment*) jika ada bonus/lembur tambahan, lalu klik *Posted Data* (kasbon otomatis terpotong).
  5. **Executive Reporting:** Tampilkan Slip Gaji individu, Laporan Rekapitulasi Divisi, dan File Payroll Bank Mandiri siap transfer.

---

## ⏱️ Estimasi Waktu Kerja Hari Ini
| No | Blok Tugas | Estimasi Waktu | Status |
|---|---|---|---|
| 1 | Penyelarasan Dropdown Filter `m_dir` di Form Transaksi Gaji | 1 - 2 Jam | ✅ Selesai 100% |
| 2 | Pengujian Simulasi Alur End-to-End (Dry-Run 4 Skenario MoM) | 2 Jam | ✅ Selesai 100% |
| 3 | Pemeriksaan Kerapian Tampilan Laporan (Slip, Rekap, Payroll) | 1 Jam | ✅ Selesai 100% |
| 4 | Penyusunan Skenario Alur Cerita Demo Klien | 1 Jam | ✅ Selesai 100% |

---
*Dokumen ini diperbarui secara berkala sebagai panduan kerja mandiri tim pengembang HRIS BBS.*
