# 📋 Rencana Pengerjaan Modul Payroll - HRIS BBS

Dokumen ini merupakan panduan perencanaan kerja (*Work Breakdown Structure* & *Timeline*) untuk pengembangan modul **Payroll (Penggajian)** pada HRIS BBS DEV, berdasarkan **Notulensi Rapat (MoM) BBS - 7 Oktober 2026** dan arsitektur generator HRIS.

---

## 1. Latar Belakang & Ruang Lingkup (Scope)

Berdasarkan arahan manajemen dan MoM 7 Oktober 2026:
1. **Fokus Pengerjaan:** Tim Unair (3 orang) fokus penuh pada modul **Payroll & Lembur** HRIS.
2. **Karakteristik Penggajian BBS:**
   - **Metode Perhitungan:** Fleksibel berbasis harian dengan filter **rentang tanggal (*date range*)** untuk mengakomodasi penggajian **Mingguan (Staf Harian)** maupun **Bulanan (Staf Bulanan)** tanpa perlu membuat modul terpisah.
   - **Komponen Pemotongan Gaji:**
     - Denda Keterlambatan: **Rp25.000 per 30 menit**.
     - Izin Tidak Masuk: Jika masih ada cuti $\rightarrow$ potong kuota cuti. Jika cuti habis $\rightarrow$ potong gaji secara proporsional.
     - Akumulasi Izin/Pulang Cepat dalam 1 Bulan: Jika total $> 8$ jam $\rightarrow$ konversi menjadi potongan hari cuti ($8\text{ jam} = 1\text{ hari cuti}$).
     - Sakit dengan Surat Keterangan Dokter (SKD) $\rightarrow$ **tidak dipotong cuti dan tidak dipotong gaji**.
     - Piutang Karyawan (Kasbon): Diinput manual dan memotong gaji per periode.
     - BPJS Kesehatan & Ketenagakerjaan: Dihitung dari dasar pelaporan BPJS.
   - **Lembur (Overtime):**
     - Lembur **tidak masuk** ke gaji bulanan, melainkan dibayarkan mingguan tersendiri.
     - Menggunakan SPK (Surat Perintah Kerja) Lembur dengan alur approval.
     - Durasi diakui: $\min(\text{Durasi SPK}, \text{Durasi Realisasi})$.

---

## 2. Arsitektur & Pola Teknis Generator

Pengerjaan wajib mengikuti aturan kerja `AGENTS.md`:
- **Model (5 File):** Setiap tabel di `Models/<nama_model>/` memiliki `Migration.php`, `Alter.php`, `Basic.php`, `Custom.php`, dan `Test.php`.
- **Frontend SFC:** Template Blade di `Blades/<modul>.blade.php` dan logika Vue di `Javascript/<modul>.js`.
- **Core Engine:** Seluruh logika matematika dan penarikan data presensi dipusatkan di `Cores/PerhitunganGaji.php` agar model tetap bersih dan logika dapat digunakan ulang (*reusable*).

---

## 3. Pembagian Tugas (Work Breakdown Structure - 3 Orang)

Untuk menghindari tumpang tindih (*merge conflict*) dan memastikan beban kerja seimbang, tugas dibagi menjadi **3 Jalur (Track)** independen:

```mermaid
graph TD
    subgraph Track 1: Rey (Lead / Engine & Batch Run)
        T1_1[Cores/PerhitunganGaji.php]
        T1_2[t_perhitungan_gaji]
        T1_3[t_final_gaji & det & rincian]
    end

    subgraph Track 2: Rekan 1 (Master Data & Potongan Piutang)
        T2_1[m_standart_gaji & det]
        T2_2[m_pengurangan_gaji]
        T2_3[Penyesuaian m_kary]
        T2_4[t_potongan & det_bayar]
    end

    subgraph Track 3: Rekan 2 (Lembur SPK & Reporting/Slip Gaji)
        T3_1[m_lembur & t_lembur]
        T3_2[l_spl - Laporan SPK Lembur]
        T3_3[l_slip_gaji & web_report_slip_gaji]
        T3_4[l_rekap_gaji & export bank]
    end

    T2_1 --> T1_1
    T2_2 --> T1_1
    T2_4 --> T1_1
    T1_1 --> T1_2
    T1_2 --> T1_3
    T1_3 --> T3_3
    T1_3 --> T3_4
```

### Rincian Pembagian PIC:

### 👤 Jalur A (PIC: Rey) - Engine & Processing Core
*Fokus: Otak perhitungan logika gaji, kalkulasi presensi, dan batch finalisasi.*
1. **`Cores/PerhitunganGaji.php`**:
   - Fungsi kalkulasi kehadiran presensi harian.
   - Fungsi hitung menit keterlambatan $\rightarrow$ per 30 menit $\times$ Rp25.000.
   - Fungsi akumulasi izin/pulang cepat $> 8$ jam $\rightarrow$ konversi cuti / potong upah proporsional.
   - Fungsi penarikan cicilan piutang aktif dari `t_potongan`.
   - Fungsi kalkulasi BPJS Kesehatan & Ketenagakerjaan.
2. **`t_perhitungan_gaji`** (Model, Blade, JS):
   - Form UI rentang tanggal (`periode_awal` s/d `periode_akhir`) & filter divisi/dept/karyawan.
   - Action tombol *Generate Gaji* yang memanggil `Cores/PerhitunganGaji`.
3. **`t_final_gaji`**, **`t_final_gaji_det`**, **`t_final_gaji_det_rincian`** (Model, Blade, JS):
   - Form review batch payroll.
   - Action posting / kunci status (`POSTED`).
   - Penyimpanan breakdown rincian per baris komponen untuk slip gaji.

---

### 👤 Jalur B (PIC: Rekan 1) - Master Data & Transaksi Potongan
*Fokus: Struktur master standar upah, parameter denda, dan modul kasbon/piutang.*
1. **`m_standart_gaji`** & **`m_standart_gaji_det`** (Model, Blade, JS):
   - Master standar upah per jabatan/grade (Gaji Pokok, Uang Makan, Tunjangan Jabatan, Tunjangan Tetap).
   - Detail komponen fleksibel/dinamis.
2. **`m_pengurangan_gaji`** (Model, Blade, JS):
   - Master konfigurasi aturan denda telat (Rp25k/30 menit) dan batas toleransi izin.
3. **Penyesuaian `m_kary`**:
   - Update tab pembayaran/gaji: mapping `m_standart_gaji_id`, tipe periode (Harian/Bulanan), dasar pelaporan BPJS, dan dasar PPh 21.
4. **`t_potongan`** & **`t_potongan_det_bayar`** (Model, Blade, JS):
   - Form transaksi penginputan manual piutang/kasbon karyawan.
   - Pencatatan jumlah pinjaman, cicilan per periode, dan status lunas.

---

### 👤 Jalur C (PIC: Rekan 2) - Lembur Mingguan & Reporting/Slip Gaji
*Fokus: Alur SPK lembur mingguan dan template laporan/slip gaji siap cetak.*
1. **`m_lembur`** & **`t_lembur`** (Model, Blade, JS):
   - Form transaksi pengajuan SPK Lembur + alur approval.
   - Form pencatatan jam realisasi lembur.
   - Perhitungan durasi lembur: $\min(\text{Durasi SPK}, \text{Durasi Realisasi})$ dibayarkan mingguan.
2. **`l_spl`** (Blade, JS):
   - Laporan rekapitulasi Surat Perintah Lembur mingguan.
3. **`l_slip_gaji`** & **`web_report_slip_gaji`** (Blade, JS):
   - Tampilan slip gaji web/cetak PDF per karyawan yang membaca data `t_final_gaji_det_rincian`.
4. **`l_rekap_gaji`** & **`web_report_rekap_gaji`** (Blade, JS):
   - Laporan tabel rekapitulasi penggajian per divisi/departemen untuk accounting & transfer payroll bank.

---

## 4. Jadwal Pelaksanaan (Timeline 4 Sprint)

| Sprint | Periode | Target Output | PIC |
|---|---|---|---|
| **Sprint 1** | 12 Okt – 16 Okt 2026 | • Master Standar Gaji (`m_standart_gaji` & det)<br>• Master Pengurangan Gaji (`m_pengurangan_gaji`)<br>• Transaksi SPK Lembur Mingguan (`t_lembur`)<br>• Kerangka Engine `Cores/PerhitunganGaji.php` | Rekan 1<br>Rekan 1<br>Rekan 2<br>Rey |
| **Sprint 2** | 19 Okt – 23 Okt 2026 | • Transaksi Piutang/Kasbon Manual (`t_potongan`)<br>• Form & Controller Perhitungan Gaji (`t_perhitungan_gaji`)<br>• Logic Kalkulasi Presensi, Telat Rp25k & Cuti di Cores<br>• Laporan SPK Lembur (`l_spl`) | Rekan 1<br>Rey<br>Rey<br>Rekan 2 |
| **Sprint 3** | 26 Okt – 30 Okt 2026 | • Finalisasi Batch Payroll (`t_final_gaji` & rincian)<br>• Template Slip Gaji (`l_slip_gaji` & `web_report`)<br>• Laporan Rekap Gaji per Divisi (`l_rekap_gaji`) | Rey<br>Rekan 2<br>Rekan 2 |
| **Sprint 4** | 02 Nov – 06 Nov 2026 | • Integrasi End-to-End & UAT<br>• Verifikasi formula denda keterlambatan dan kuota cuti<br>• Dokumentasi & Serah Terima ke Mbak Diani / Pak Hen | Rey, Rekan 1, Rekan 2 |

---

## 5. Matriks Integrasi Data Antar-Modul

```
[ Master Standar Gaji ] ──┐
[ Master Denda Telat ]  ──┼──> [ Cores/PerhitunganGaji ] ──> [ t_perhitungan_gaji ]
[ Transaksi Piutang ]   ──┤             ▲                           │
[ Presensi & Cuti ]     ──┘             │ (Kalkulasi)               ▼
                                        │                   [ t_final_gaji ]
                                        │                           │
                                        │                           ▼
                                        └───────────── [ t_final_gaji_det_rincian ]
                                                                    │
                                                      ┌─────────────┴─────────────┐
                                                      ▼                           ▼
                                              [ l_slip_gaji ]             [ l_rekap_gaji ]
```

---

## 6. Checklist & Standar Kualitas Koding

Setiap anggota tim wajib mematuhi standar berikut saat mengerjakan modul masing-masing:
1. **Preserve Naming Conventions:** Gunakan penamaan file dan tabel sesuai generator (`m_*` untuk master, `t_*` untuk transaksi, `l_*` untuk laporan, `web_report_*` untuk view cetak).
2. **5-Bagian Model:** Setiap penambahan model baru harus memiliki 5 file lengkap (`Migration.php`, `Alter.php`, `Basic.php`, `Custom.php`, `Test.php`).
3. **No Direct Business Logic in Basic:** Jangan mengubah file `Basic.php` untuk logika bisnis. Seluruh query, hook, dan custom endpoint harus berada di `Custom.php` atau `Cores/`.
4. **Sanitisasi Data:** Jangan memasukkan credential, token, atau NIK/data riil karyawan ke dalam repository.
5. **Review Sebelum Commit:** Sebelum commit ke git, pastikan syntax PHP valid dan tidak ada typo kurung kurawal atau variabel undefined.
