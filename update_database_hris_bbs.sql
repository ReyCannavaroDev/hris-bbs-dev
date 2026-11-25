-- ==============================================================================
-- SCRIPT UPDATE / MIGRATE DATABASE HRIS BBS (POSTGRESQL)
-- Keterangan:
-- Script ini aman dijalankan langsung pada database server BBS yang sudah berjalan 
-- karena menggunakan 'IF NOT EXISTS' sehingga tidak akan menghapus atau menimpa 
-- data yang sudah ada sebelumnya.
-- ==============================================================================

BEGIN;

-- ==============================================================================
-- 1. TABEL BARU (HANYA DIBUAT JIKA BELUM ADA DI DATABASE)
-- ==============================================================================

-- 1.1 Master Responsibility (m_respo)
CREATE TABLE IF NOT EXISTS m_respo (
    id BIGSERIAL PRIMARY KEY,
    kode VARCHAR(50),
    nama VARCHAR(150) NOT NULL,
    m_comp_id BIGINT DEFAULT 1,
    m_dir_id BIGINT,
    m_divisi_id BIGINT,
    m_dept_id BIGINT,
    "desc" TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    creator_id BIGINT,
    last_editor_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 1.2 Detail Responsibility - Role Mapping (m_respo_d)
CREATE TABLE IF NOT EXISTS m_respo_d (
    id BIGSERIAL PRIMARY KEY,
    m_respo_id BIGINT,
    m_role_id BIGINT,
    "desc" TEXT,
    creator_id BIGINT,
    last_editor_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 1.3 Pivot User Responsibility (default_users_respo)
CREATE TABLE IF NOT EXISTS default_users_respo (
    id BIGSERIAL PRIMARY KEY,
    default_users_id BIGINT,
    m_respo_id BIGINT,
    seq INTEGER,
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    creator_id BIGINT,
    last_editor_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deletor_id BIGINT,
    deleted_at TIMESTAMP
);

-- 1.4 Transaksi Kalkulasi / Perhitungan Lembur (t_perhitungan_lembur)
CREATE TABLE IF NOT EXISTS t_perhitungan_lembur (
    id BIGSERIAL PRIMARY KEY,
    nomor VARCHAR(50),
    m_comp_id BIGINT DEFAULT 1,
    m_dir_id BIGINT,
    m_kary_id BIGINT,
    m_kary_dir_id BIGINT,
    m_kary_divisi_id BIGINT,
    m_kary_dept_id BIGINT,
    periode VARCHAR(100),
    periode_awal DATE,
    periode_akhir DATE,
    total_spk_min INTEGER DEFAULT 0,
    total_realisasi_min INTEGER DEFAULT 0,
    total_diakui_min INTEGER DEFAULT 0,
    total_jam_diakui NUMERIC(8, 2) DEFAULT 0,
    tarif_lembur NUMERIC(12, 2) DEFAULT 0,
    total_lembur NUMERIC(22, 2) DEFAULT 0,
    detail_lembur JSONB,
    deskripsi TEXT,
    status VARCHAR(50) DEFAULT 'DRAFT',
    creator_id BIGINT,
    last_editor_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);


-- ==============================================================================
-- 2. ALTER PENAMBAHAN KOLOM PADA TABEL EXISTING
-- ==============================================================================

-- 2.1 Master Karyawan (m_kary)
ALTER TABLE m_kary ADD COLUMN IF NOT EXISTS can_outscope BOOLEAN DEFAULT FALSE;
ALTER TABLE m_kary ADD COLUMN IF NOT EXISTS gaji_pokok NUMERIC(12, 2);
ALTER TABLE m_kary ADD COLUMN IF NOT EXISTS gaji_acuan NUMERIC(12, 2);

-- 2.2 Transaksi Mutasi (t_mutasi)
ALTER TABLE t_mutasi ADD COLUMN IF NOT EXISTS gaji_pokok_lama NUMERIC(12, 2);
ALTER TABLE t_mutasi ADD COLUMN IF NOT EXISTS gaji_pokok_baru NUMERIC(12, 2);
ALTER TABLE t_mutasi ADD COLUMN IF NOT EXISTS gaji_acuan_lama NUMERIC(12, 2);
ALTER TABLE t_mutasi ADD COLUMN IF NOT EXISTS gaji_acuan_baru NUMERIC(12, 2);

-- 2.3 Transaksi Lembur (t_lembur)
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS interval_min INTEGER;
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS jam_mulai_realisasi TIME;
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS jam_selesai_realisasi TIME;
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS realisasi_min INTEGER;
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS diakui_min INTEGER;
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS tarif_lembur NUMERIC(12, 2);
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS total_lembur NUMERIC(14, 2);
ALTER TABLE t_lembur ADD COLUMN IF NOT EXISTS pic_id BIGINT;

-- 2.4 Transaksi Finalisasi Gaji (t_final_gaji)
ALTER TABLE t_final_gaji ADD COLUMN IF NOT EXISTS m_dir_id BIGINT;

-- 2.5 Transaksi Pengumuman (t_pengumuman)
ALTER TABLE t_pengumuman ADD COLUMN IF NOT EXISTS m_divisi_id BIGINT;

-- 2.6 Master Role (m_role)
ALTER TABLE m_role ADD COLUMN IF NOT EXISTS can_view_salary BOOLEAN DEFAULT FALSE;
ALTER TABLE m_role ALTER COLUMN m_comp_id DROP NOT NULL;

-- 2.7 Master Company / Direktorat (m_dir)
ALTER TABLE m_dir ADD COLUMN IF NOT EXISTS kode VARCHAR(50);

-- 2.8 Presensi / Absensi (presensi_absensi)
ALTER TABLE presensi_absensi ALTER COLUMN checkin_foto TYPE VARCHAR(255);
ALTER TABLE presensi_absensi ALTER COLUMN checkout_foto TYPE VARCHAR(255);

COMMIT;
