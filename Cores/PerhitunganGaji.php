<?php

namespace App\Cores;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Core PerhitunganGaji
 * 
 * Engine Perhitungan Penggajian HRIS BBS
 * Berdasarkan MoM (Minutes of Meeting) 7 Oktober 2026:
 * - Master Standar Gaji & Standar Upah per Zona (m_standart_gaji & det)
 * - Denda Keterlambatan Rp 25.000 / 30 menit (MoM 3.B)
 * - Akumulasi Izin > 8 jam -> konversi 1 hari cuti (MoM 3.C)
 * - Pemotongan upah proporsional jika cuti habis (MoM 3.B)
 * - Sakit ber-surat dokter tidak potong cuti (MoM 3.D)
 * - Potongan Kasbon / Piutang Karyawan (MoM 3.E & t_potongan)
 */
class PerhitunganGaji
{
    protected $defaultHariKerja = 25; // Standar rata-rata hari kerja sebulan
    protected $tarifDendaTelatPer30Menit = 25000; // Rp 25.000 / 30 menit (MoM 3.B)

    public function __construct()
    {
        // Constructor ready for any dependencies
    }

    /**
     * Test function untuk validasi status Core
     */
    public function test()
    {
        return "Core PerhitunganGaji HRIS BBS siap digunakan!";
    }

    /**
     * 1. Ambil Standar Gaji Pokok & Tunjangan Karyawan
     * Otomatis terhubung dengan Zona (m_zona) dan Golongan (grading) karyawan.
     *
     * @param int $m_kary_id
     * @return array
     */
    public function getGajiDasar($m_kary_id)
    {
        $kary = DB::table('m_kary')->where('id', $m_kary_id)->first();
        if (!$kary) {
            return [
                'success' => false,
                'message' => "Karyawan ID {$m_kary_id} tidak ditemukan."
            ];
        }

        // Cari master standar gaji
        $standarGaji = null;
        if (!empty($kary->m_standart_gaji_id)) {
            $standarGaji = DB::table('m_standart_gaji')
                ->where('id', $kary->m_standart_gaji_id)
                ->where('is_active', 1)
                ->first();
        }

        // Fallback berdasarkan zona dan grading karyawan
        if (!$standarGaji && !empty($kary->m_zona_id) && !empty($kary->grading_id)) {
            $standarGaji = DB::table('m_standart_gaji')
                ->where('m_zona_id', $kary->m_zona_id)
                ->where('grading_id', $kary->grading_id)
                ->where('is_active', 1)
                ->first();
        }

        $gajiPokok = (float) ($standarGaji->gaji_pokok ?? 0);
        $uangMakan = (float) ($standarGaji->uang_makan ?? 0);
        $uangSaku = (float) ($standarGaji->uang_saku ?? 0);
        $tunjanganPosisi = (float) ($standarGaji->tunjangan_posisi ?? 0);
        $tunjanganTetap = (float) ($standarGaji->tunjangan_tetap ?? 0);

        // Ambil komponen detail dari m_standart_gaji_det
        $tunjanganTambahan = [];
        $potonganStandar = [];
        $totalTunjanganTambahan = 0;
        $totalPotonganStandar = 0;

        if ($standarGaji) {
            $details = DB::table('m_standart_gaji_det')
                ->where('m_standart_gaji_id', $standarGaji->id)
                ->get();

            foreach ($details as $det) {
                $nilai = (float) $det->nilai;
                if ($det->faktor === '+') {
                    $tunjanganTambahan[] = [
                        'komponen' => $det->komponen,
                        'nilai'    => $nilai,
                        'periode'  => $det->periode ?? 'Bulanan',
                    ];
                    $totalTunjanganTambahan += $nilai;
                } elseif ($det->faktor === '-') {
                    $potonganStandar[] = [
                        'komponen' => $det->komponen,
                        'nilai'    => $nilai,
                        'periode'  => $det->periode ?? 'Bulanan',
                    ];
                    $totalPotonganStandar += $nilai;
                }
            }
        }

        $totalPendapatanTetap = $gajiPokok + $uangMakan + $uangSaku + $tunjanganPosisi + $tunjanganTetap + $totalTunjanganTambahan;

        return [
            'success'               => true,
            'karyawan'              => [
                'id'           => $kary->id,
                'nik'          => $kary->nik,
                'nama'         => $kary->nama_lengkap ?? $kary->nama_depan,
                'm_zona_id'    => $kary->m_zona_id,
                'grading_id'   => $kary->grading_id,
                'sisa_cuti'    => (int) ($kary->cuti_sisa_reguler ?? 0),
            ],
            'standar_gaji_id'       => $standarGaji->id ?? null,
            'standar_gaji_kode'     => $standarGaji->kode ?? null,
            'gaji_pokok'            => $gajiPokok,
            'gaji_pokok_periode'    => $standarGaji->gaji_pokok_periode ?? 'Bulanan',
            'uang_makan'            => $uangMakan,
            'uang_saku'             => $uangSaku,
            'tunjangan_posisi'      => $tunjanganPosisi,
            'tunjangan_tetap'       => $tunjanganTetap,
            'tunjangan_tambahan'    => $tunjanganTambahan,
            'potongan_standar'      => $potonganStandar,
            'total_pendapatan_tetap'=> $totalPendapatanTetap,
            'total_potongan_standar'=> $totalPotonganStandar,
        ];
    }

    /**
     * 2. Hitung Denda Keterlambatan
     * Rumus MoM 3.B: Denda Rp 25.000 per kelipatan 30 menit keterlambatan.
     *
     * @param int $m_kary_id
     * @param string $startDate (Format: Y-m-d)
     * @param string $endDate (Format: Y-m-d)
     * @return array
     */
    public function hitungDendaKeterlambatan($m_kary_id, $startDate, $endDate)
    {
        // Hubungkan karyawan ke default_users untuk membaca presensi_absensi
        $userId = DB::table('default_users')->where('m_kary_id', $m_kary_id)->value('id');
        if (!$userId) {
            $userId = DB::table('m_pengguna')->where('m_kary_id', $m_kary_id)->value('default_user_id');
        }
        if (!$userId) {
            $userId = DB::table('default_users')->where('id', $m_kary_id)->value('id');
        }

        // Cari jam mulai kerja standar
        $kary = DB::table('m_kary')->where('id', $m_kary_id)->first();
        $waktuMulaiKerja = '08:00:00';
        if ($kary && !empty($kary->m_jam_kerja_id)) {
            $jamKerja = DB::table('m_jam_kerja')->where('id', $kary->m_jam_kerja_id)->first();
            if ($jamKerja && !empty($jamKerja->waktu_mulai)) {
                $waktuMulaiKerja = $jamKerja->waktu_mulai;
            }
        }

        $totalMenitTelat = 0;
        $totalDenda = 0;
        $rincianTelat = [];

        $presensiQuery = DB::table('presensi_absensi')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereNotNull('checkin_time');

        if ($userId) {
            $presensiQuery->where('default_user_id', $userId);
        } else {
            $presensiQuery->whereIn('default_user_id', function ($q) use ($m_kary_id) {
                $q->select('id')->from('default_users')->where('m_kary_id', $m_kary_id);
            });
        }

        $presensiList = $presensiQuery->get();

        foreach ($presensiList as $presensi) {
            // Cek jadwal kerja dinamis per hari jika ada
            $jamMulaiHariIni = $waktuMulaiKerja;
            $hariNum = Carbon::parse($presensi->tanggal)->dayOfWeek;
            $jadwalHarian = DB::table('t_jadwal_kerja_det as d')
                ->join('t_jadwal_kerja_det_hari as h', 'h.id', '=', 'd.t_jadwal_kerja_det_hari_id')
                ->where('d.m_kary_id', $m_kary_id)
                ->where('h.day_num', $hariNum)
                ->first();

            if ($jadwalHarian && !empty($jadwalHarian->waktu_mulai)) {
                $jamMulaiHariIni = $jadwalHarian->waktu_mulai;
            }

            $checkin = Carbon::parse($presensi->tanggal . ' ' . $presensi->checkin_time);
            $jadwalMulai = Carbon::parse($presensi->tanggal . ' ' . $jamMulaiHariIni);

                if ($checkin->gt($jadwalMulai)) {
                    $menit = (int) $jadwalMulai->diffInMinutes($checkin);
                    if ($menit > 0) {
                        // Formula MoM 3.B: Kelipatan 30 menit x Rp 25.000
                        $kelipatan = (int) floor($menit / 30);
                        $dendaHariIni = $kelipatan * $this->tarifDendaTelatPer30Menit;

                        $totalMenitTelat += $menit;
                        $totalDenda += $dendaHariIni;

                        $rincianTelat[] = [
                            'tanggal'      => $presensi->tanggal,
                            'checkin_time' => $presensi->checkin_time,
                            'jadwal_masuk' => $waktuMulaiKerja,
                            'menit_telat'  => $menit,
                            'kelipatan_30' => $kelipatan,
                            'denda'        => $dendaHariIni,
                        ];
                    }
                }
            }

        return [
            'total_hari_telat'  => count($rincianTelat),
            'total_menit_telat' => $totalMenitTelat,
            'total_denda'       => $totalDenda,
            'rincian'           => $rincianTelat,
        ];
    }

    /**
     * 3. Hitung Akumulasi Izin & Pemotongan Cuti / Gaji Proporsional
     * Berdasarkan MoM 3.B, 3.C, dan 3.D:
     * - Akumulasi izin > 8 jam dikonversi jadi hari cuti (8 jam = 1 hari) (MoM 3.C)
     * - Jika kuota cuti habis, dilakukan pemotongan gaji secara proporsional (MoM 3.B)
     * - Sakit dengan surat dokter tidak dipotong cuti / upah (MoM 3.D)
     *
     * @param int $m_kary_id
     * @param string $startDate
     * @param string $endDate
     * @param float $tarifUpahHarian
     * @return array
     */
    public function hitungAkumulasiIzinDanPotonganCuti($m_kary_id, $startDate, $endDate, $tarifUpahHarian)
    {
        $kary = DB::table('m_kary')->where('id', $m_kary_id)->first();
        $sisaCutiAwal = (int) ($kary->cuti_sisa_reguler ?? 0);

        // Ambil permohonan izin/cuti yang disetujui dalam rentang tanggal
        $daftarCuti = DB::table('t_cuti')
            ->where('m_kary_id', $m_kary_id)
            ->whereDate('date_from', '>=', $startDate)
            ->whereDate('date_to', '<=', $endDate)
            ->whereIn('status', ['APPROVED', 'POSTED'])
            ->get();

        $totalMenitIzinParsial = 0;
        $totalHariCutiPenuh = 0;
        $rincianIzin = [];

        foreach ($daftarCuti as $cuti) {
            $keteranganLower = strtolower($cuti->keterangan ?? '');

            // MoM 3.D: Sakit dengan surat dokter dikecualikan
            if (str_contains($keteranganLower, 'sakit')) {
                $rincianIzin[] = [
                    'nomor'      => $cuti->nomor,
                    'tipe'       => 'Sakit (Bebas Potongan)',
                    'date_from'  => $cuti->date_from,
                    'date_to'    => $cuti->date_to,
                    'keterangan' => $cuti->keterangan,
                    'status'     => 'Tidak memotong cuti/upah',
                ];
                continue;
            }

            // Jika izin parsial jam-jaman (pulang cepat / datang telat)
            if (!empty($cuti->time_from) && !empty($cuti->time_to)) {
                $timeFrom = Carbon::parse($cuti->time_from);
                $timeTo = Carbon::parse($cuti->time_to);
                $durasiMenit = max(0, $timeFrom->diffInMinutes($timeTo));
                $totalMenitIzinParsial += $durasiMenit;

                $rincianIzin[] = [
                    'nomor'        => $cuti->nomor,
                    'tipe'         => 'Izin Parsial (Jam)',
                    'date_from'    => $cuti->date_from,
                    'durasi_menit' => $durasiMenit,
                    'durasi_jam'   => round($durasiMenit / 60, 2),
                    'keterangan'   => $cuti->keterangan,
                ];
            } else {
                // Izin atau Cuti seharian penuh
                $dFrom = Carbon::parse($cuti->date_from);
                $dTo = Carbon::parse($cuti->date_to);
                $hari = $dFrom->diffInDays($dTo) + 1;
                $totalHariCutiPenuh += $hari;

                $rincianIzin[] = [
                    'nomor'      => $cuti->nomor,
                    'tipe'       => 'Cuti/Izin Harian',
                    'date_from'  => $cuti->date_from,
                    'date_to'    => $cuti->date_to,
                    'jumlah_hari'=> $hari,
                    'keterangan' => $cuti->keterangan,
                ];
            }
        }

        // MoM 3.C: Akumulasi izin > 8 jam (480 menit) dikonversi: 8 jam = 1 hari cuti
        $totalJamIzinParsial = round($totalMenitIzinParsial / 60, 2);
        $konversiHariCuti = 0;
        if ($totalJamIzinParsial >= 8) {
            $konversiHariCuti = (int) floor($totalJamIzinParsial / 8);
        }

        $totalKebutuhanHariCuti = $totalHariCutiPenuh + $konversiHariCuti;

        // MoM 3.B: Cek sisa kuota cuti vs pemotongan gaji proporsional
        $cutiTerpakai = 0;
        $hariPotongGaji = 0;
        $nominalPotonganGaji = 0;

        if ($sisaCutiAwal >= $totalKebutuhanHariCuti) {
            // Kuota cuti mencukupi, tidak ada potongan uang rupiah
            $cutiTerpakai = $totalKebutuhanHariCuti;
            $hariPotongGaji = 0;
            $nominalPotonganGaji = 0;
        } else {
            // Kuota cuti habis atau kurang -> kelebihannya potong upah harian
            $cutiTerpakai = max(0, $sisaCutiAwal);
            $hariPotongGaji = $totalKebutuhanHariCuti - $cutiTerpakai;
            $nominalPotonganGaji = round($hariPotongGaji * $tarifUpahHarian, 2);
        }

        return [
            'total_menit_izin_jam'       => $totalMenitIzinParsial,
            'total_jam_izin_jam'         => $totalJamIzinParsial,
            'konversi_hari_cuti'         => $konversiHariCuti,
            'total_hari_cuti_penuh'      => $totalHariCutiPenuh,
            'total_kebutuhan_cuti'       => $totalKebutuhanHariCuti,
            'sisa_cuti_awal'             => $sisaCutiAwal,
            'cuti_terpakai'              => $cutiTerpakai,
            'sisa_cuti_akhir'            => max(0, $sisaCutiAwal - $cutiTerpakai),
            'hari_potong_gaji'           => $hariPotongGaji,
            'tarif_upah_harian'          => $tarifUpahHarian,
            'nominal_potongan_proporsional' => $nominalPotonganGaji,
            'rincian'                    => $rincianIzin,
        ];
    }

    /**
     * 4. Tarik Potongan Kasbon / Piutang Karyawan
     * Berdasarkan MoM 3.E & transaksi tabel t_potongan
     *
     * @param int $m_kary_id
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function tarikPotonganKasbon($m_kary_id, $startDate, $endDate)
    {
        $potonganList = DB::table('t_potongan')
            ->where(function ($q) use ($m_kary_id) {
                $q->where('m_kary_id', $m_kary_id)
                  ->orWhere('is_all_kary', 1);
            })
            ->where('status', 'POSTED')
            ->where('is_lunas', 0)
            ->whereDate('date_from', '<=', $endDate)
            ->whereDate('date_to', '>=', $startDate)
            ->get();

        $totalPotonganKasbon = 0;
        $rincianKasbon = [];

        foreach ($potonganList as $pot) {
            $totalNilaiPinjaman = (float) $pot->nilai;

            // Hitung total angsuran yang sudah dibayar sebelumnya di t_potongan_det_bayar
            $totalSudahBayar = (float) DB::table('t_potongan_det_bayar')
                ->where('t_potongan_id', $pot->id)
                ->sum('nilai');

            $sisaHutang = max(0, $totalNilaiPinjaman - $totalSudahBayar);

            if ($sisaHutang <= 0) {
                // Tandai lunas jika sudah habis
                DB::table('t_potongan')->where('id', $pot->id)->update(['is_lunas' => 1]);
                continue;
            }

            // Hitung cicilan bulan ini berdasarkan percentage atau sisa
            $percentage = (float) ($pot->percentage ?? 0);
            if ($percentage > 0) {
                $angsuranBulanIni = round($totalNilaiPinjaman * ($percentage / 100), 2);
            } else {
                $angsuranBulanIni = $sisaHutang;
            }

            // Angsuran tidak boleh melebihi sisa hutang pokok
            $potongFix = min($angsuranBulanIni, $sisaHutang);

            $totalPotonganKasbon += $potongFix;
            $rincianKasbon[] = [
                't_potongan_id'  => $pot->id,
                'nomor'          => $pot->nomor,
                'keterangan'     => $pot->keterangan ?? 'Potongan Kasbon/Piutang',
                'total_pinjaman' => $totalNilaiPinjaman,
                'sudah_dibayar'  => $totalSudahBayar,
                'sisa_hutang'    => $sisaHutang,
                'percentage'     => $percentage,
                'angsuran_payroll' => $potongFix,
            ];
        }

        return [
            'total_potongan_kasbon' => $totalPotonganKasbon,
            'rincian'               => $rincianKasbon,
        ];
    }

    /**
     * 5. Hitung Keseluruhan Gaji Karyawan (Single Employee Payroll Run)
     * Menggabungkan Hak Upah, Denda Telat, Potongan Izin, dan Kasbon.
     *
     * @param int $m_kary_id
     * @param string $startDate (Format: Y-m-d)
     * @param string $endDate (Format: Y-m-d)
     * @param array $options
     * @return array
     */
    public function hitungGajiKaryawan($m_kary_id, $startDate, $endDate, $options = [])
    {
        $hariKerja = $options['hari_kerja'] ?? $this->defaultHariKerja;

        // 1. Gaji Pokok & Master Allowance
        $gajiDasar = $this->getGajiDasar($m_kary_id);
        if (!$gajiDasar['success']) {
            return $gajiDasar;
        }

        $gajiPokok = $gajiDasar['gaji_pokok'];
        $tarifHarian = $hariKerja > 0 ? ($gajiPokok / $hariKerja) : 0;

        // 2. Denda Keterlambatan (MoM 3.B: Rp 25.000 / 30 menit)
        $dendaTelat = $this->hitungDendaKeterlambatan($m_kary_id, $startDate, $endDate);

        // 3. Potongan Izin & Cuti Habis (MoM 3.B, 3.C, 3.D)
        $potonganIzin = $this->hitungAkumulasiIzinDanPotonganCuti($m_kary_id, $startDate, $endDate, $tarifHarian);

        // 4. Potongan Kasbon / Piutang (MoM 3.E)
        $potonganKasbon = $this->tarikPotonganKasbon($m_kary_id, $startDate, $endDate);

        // 5. Total Pendapatan Kotor (Gross)
        $totalPendapatanKotor = $gajiDasar['total_pendapatan_tetap'];

        // 6. Total Potongan
        $totalPotongan = 
            $dendaTelat['total_denda'] + 
            $potonganIzin['nominal_potongan_proporsional'] + 
            $potonganKasbon['total_potongan_kasbon'] + 
            $gajiDasar['total_potongan_standar'];

        // 7. Take Home Pay (Net Salary)
        $thp = max(0, $totalPendapatanKotor - $totalPotongan);

        return [
            'success'               => true,
            'karyawan'              => $gajiDasar['karyawan'],
            'periode'               => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'hari_kerja' => $hariKerja,
            ],
            'gaji_dasar'            => $gajiDasar,
            'denda_keterlambatan'   => $dendaTelat,
            'potongan_izin_cuti'    => $potonganIzin,
            'potongan_kasbon'       => $potonganKasbon,
            'ringkasan'             => [
                'total_pendapatan'  => $totalPendapatanKotor,
                'total_potongan'    => $totalPotongan,
                'take_home_pay'     => $thp,
            ],
            // Rincian siap cetak untuk slip gaji
            'slip_details'          => [
                'pendapatan' => array_merge([
                    ['nama' => 'Gaji Pokok', 'nilai' => $gajiPokok],
                    ['nama' => 'Uang Makan', 'nilai' => $gajiDasar['uang_makan']],
                    ['nama' => 'Uang Saku', 'nilai' => $gajiDasar['uang_saku']],
                    ['nama' => 'Tunjangan Jabatan', 'nilai' => $gajiDasar['tunjangan_posisi']],
                    ['nama' => 'Tunjangan Tetap', 'nilai' => $gajiDasar['tunjangan_tetap']],
                ], array_map(fn($t) => ['nama' => $t['komponen'], 'nilai' => $t['nilai']], $gajiDasar['tunjangan_tambahan'])),
                'potongan'   => array_merge([
                    ['nama' => "Denda Terlambat ({$dendaTelat['total_menit_telat']} menit)", 'nilai' => $dendaTelat['total_denda']],
                    ['nama' => "Potongan Izin ({$potonganIzin['hari_potong_gaji']} hari)", 'nilai' => $potonganIzin['nominal_potongan_proporsional']],
                    ['nama' => "Potongan Kasbon / Piutang", 'nilai' => $potonganKasbon['total_potongan_kasbon']],
                ], array_map(fn($p) => ['nama' => $p['komponen'], 'nilai' => $p['nilai']], $gajiDasar['potongan_standar'])),
            ]
        ];
    }

    /**
     * 6. Generate Batch Penggajian (Seluruh Karyawan Aktif)
     * Digunakan oleh transaksi t_perhitungan_gaji / t_final_gaji.
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $filter (misal: m_divisi_id, m_dept_id, m_zona_id)
     * @return array
     */
    public function generateBatchPayroll($startDate, $endDate, $filter = [])
    {
        $query = DB::table('m_kary')->where('is_active', 1);

        if (!empty($filter['m_divisi_id'])) {
            $query->where('m_divisi_id', $filter['m_divisi_id']);
        }
        if (!empty($filter['m_dept_id'])) {
            $query->where('m_dept_id', $filter['m_dept_id']);
        }
        if (!empty($filter['m_zona_id'])) {
            $query->where('m_zona_id', $filter['m_zona_id']);
        }

        $karyawanList = $query->get(['id', 'nik', 'nama_depan', 'nama_lengkap']);

        $batchResults = [];
        $grandTotalPendapatan = 0;
        $grandTotalPotongan = 0;
        $grandTotalTHP = 0;

        foreach ($karyawanList as $k) {
            $calc = $this->hitungGajiKaryawan($k->id, $startDate, $endDate, $filter);
            if ($calc['success']) {
                $batchResults[] = $calc;
                $grandTotalPendapatan += $calc['ringkasan']['total_pendapatan'];
                $grandTotalPotongan += $calc['ringkasan']['total_potongan'];
                $grandTotalTHP += $calc['ringkasan']['take_home_pay'];
            }
        }

        return [
            'total_karyawan'         => count($batchResults),
            'periode_awal'           => $startDate,
            'periode_akhir'          => $endDate,
            'grand_total_pendapatan' => $grandTotalPendapatan,
            'grand_total_potongan'   => $grandTotalPotongan,
            'grand_total_thp'        => $grandTotalTHP,
            'data'                   => $batchResults,
        ];
    }
}
