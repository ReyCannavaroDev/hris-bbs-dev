<?php

namespace App\Models\CustomModels;

class t_perhitungan_gaji extends \App\Models\BasicModels\t_perhitungan_gaji
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id", "last_editor_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];


    protected $factorAdded = [];

    private function factorSalary($standart_gaji, $kary = null, $periode = null, $periodeAkhir = null)
    {
        $startDate = null;
        $endDate = null;

        if ($periode) {
            if (strlen($periode) === 10) {
                $startDate = $periode;
                $endDate = ($periodeAkhir && strlen($periodeAkhir) === 10) ? $periodeAkhir : $periode;
            } elseif (strlen($periode) === 7) {
                $startDate = "$periode-01";
                if ($periodeAkhir && strlen($periodeAkhir) === 7) {
                    $endDate = date('Y-m-t', strtotime("$periodeAkhir-01"));
                } else {
                    $date = new \DateTime($startDate);
                    $date->modify('last day of this month');
                    $endDate = $date->format('Y-m-d');
                }
            } else {
                $time = strtotime($periode);
                if ($time) {
                    $startDate = date('Y-m-d', $time);
                    $endDate = $periodeAkhir ? date('Y-m-d', strtotime($periodeAkhir)) : $startDate;
                }
            }
        }

        if (!$startDate) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        $firstDayOfMonth = $startDate;
        $lastDayOfMonth = $endDate;

        $defaultColumns = [
            [
                'name'  => 'gaji_pokok',
                'type'  => 'gaji_pokok_periode'
            ],
            [
                'name'  => 'uang_saku',
                'type'  => 'uang_saku_periode'
            ],
            [
                'name'  => 'tunjangan_posisi',
                'type'  => 'tunjangan_posisi_periode'
            ],
            [
                'name'  => 'tunjangan_kemahalan_id',
                'table' => 'm_tunj_kemahalan',
                'type'  => 'tunjangan_kemahalan_periode'
            ],
            [
                'name'  => 'uang_makan',
                'type'  => 'uang_makan'
            ],
            [
                'name'  => 'tunjangan_tetap',
                'type'  => 'tunjangan_tetap'
            ]
        ];

        foreach($defaultColumns as $idx => $key ){
            $defaultColumns[$idx]['label']  = $this->helper->snakeCaseToCapitalize($key['name']);
            $defaultColumns[$idx]['factor'] = '+'; // pasti tambah karena default kolom tunjangan

            // dynamic 
            if(@!$key['table']) {
                    $defaultColumns[$idx]['value']  = (float)$standart_gaji[$key['name']] ?? 0;
                    $defaultColumns[$idx]['type']   = $standart_gaji[$key['type']];
              
            }else{
                if($key['table'] == 'm_tunj_kemahalan'){
                    $defaultColumns[$idx]['label']  = "Tunjangan Kemahalan";
                    $defaultColumns[$idx]['value']  = (float)\DB::table($key['table'])->where('id', $standart_gaji[$key['name']])->pluck('besaran')->first() ?? 0;
                    $defaultColumns[$idx]['type']   = $standart_gaji[$key['type']];
                }
            }
            $defaultColumns[$idx]['can_adjust'] = 1;
            if ($defaultColumns[$idx]['value'] == 0) {
                unset($defaultColumns[$idx]);
            }
        }

        // faktor lain dari table m_standart_gaji_det
        $standart_gaji_det = m_standart_gaji_det::where('m_standart_gaji_id', $standart_gaji->id ?? 0)->get();
        foreach($standart_gaji_det as $d){
            $defaultColumns[] = [
                'label'    => $d->komponen,
                'factor'   => $d->faktor,
                'value'    => $d->nilai,
                'type'     => $d->periode,
                'can_adjust' => 1
                
            ];
        }

        if(!$kary) return $defaultColumns;

        // tunjangan masa kerja
        $general_masa_kerja = m_general::where('group', 'TUNJANGAN MASA KERJA')->where('key','01')->pluck('value')->first();
        if($general_masa_kerja && $kary->tgl_masuk) {
            $general_masa_kerja = (float)$general_masa_kerja;
            $date_from = \DateTime::createFromFormat('Y-m-d', $kary->tgl_masuk);
            $date_to = \DateTime::createFromFormat('Y-m-d', date('Y-m-d'));
            $interval = @$date_from->diff($date_to) ?? 0;
            $jumlah_tahun = floor($interval->days / 365);

            $total_tunjangan = $general_masa_kerja * pow(2, $jumlah_tahun);
            if($total_tunjangan > 0){
                $defaultColumns[] = [
                    'label'    => "Tunjangan Masa Kerja ($jumlah_tahun)",
                    'factor'   => '+',
                    'value'    => $total_tunjangan,
                    'type'     => 'Bulanan',
                    'can_adjust' => 1
                ];
            }
        }

        // faktor lain :Potongan
        $t_potongan = t_potongan::where(function($q) use ($kary) {
                $q->where('m_kary_id', @$kary->id ?? 0)
                  ->orWhere('is_all_kary', true);
            })
            ->where(function($q) {
                $q->where('is_lunas', 0)->orWhereNull('is_lunas');
            })
            ->where(function($q) use ($firstDayOfMonth, $lastDayOfMonth) {
                $q->whereRaw("date_from >= ? and date_to <= ?", [$firstDayOfMonth, $lastDayOfMonth])
                  ->orWhereRaw("date_from <= ? and date_to >= ?", [$lastDayOfMonth, $firstDayOfMonth]);
            })
            ->get();
        if(count($t_potongan)) {
            foreach($t_potongan as $d){
                $nilai_netto = ((float)$d->nilai * (float)$d->percentage)/100;
                $defaultColumns[] = [
                    'label'    => "Potongan - $d->nomor",
                    'factor'   => '-',
                    'value'    => $nilai_netto,
                    'type'     => 'Bulanan',
                    'can_adjust' => 1,
                    't_potongan_id' => $d->id
                ];
            }
        }

        // check kehadiran & denda/potongan presensi karyawan (MoM BBS 7 Oktober 2026)
        $gaji_pokok = (float) (@$standart_gaji['gaji_pokok'] ?? ($standart_gaji->gaji_pokok ?? 0));
        $gajiPokokPeriode = strtolower(trim($standart_gaji->gaji_pokok_periode ?? (@$standart_gaji['gaji_pokok_periode'] ?? 'Bulanan')));

        $periodeGajiKary = null;
        if (!empty($kary->periode_text)) {
            $periodeGajiKary = strtolower(trim($kary->periode_text));
        } elseif (!empty($kary->periode_gaji_id)) {
            $val = \DB::table('m_general')->where('id', $kary->periode_gaji_id)->value('value');
            $periodeGajiKary = strtolower(trim($val ?? ''));
        }

        // Ambil daftar tanggal merah / libur nasional dalam rentang periode
        $liburNasional = [];
        try {
            $liburNasional = \DB::table('m_libur_nasional')
                ->where('is_active', true)
                ->whereBetween('tanggal', [$firstDayOfMonth, $lastDayOfMonth])
                ->pluck('tanggal')
                ->map(function($t) { return date('Y-m-d', strtotime($t)); })
                ->toArray();
        } catch (\Throwable $e) {
            $liburNasional = [];
        }

        // Ambil konfigurasi nama hari libur mingguan dari m_general (default: Minggu)
        $hariLiburNama = ['minggu', 'sunday'];
        try {
            $generalLibur = \DB::table('m_general')
                ->where('group', 'HARI LIBUR')
                ->where('is_active', true)
                ->pluck('value')
                ->map(function($v) { return strtolower(trim($v)); })
                ->toArray();
            if (count($generalLibur)) {
                $hariLiburNama = array_unique(array_merge($hariLiburNama, $generalLibur));
            }
        } catch (\Throwable $e) {
            // default tetap ['minggu', 'sunday']
        }

        // Hitung total hari kalender, hari minggu, tanggal merah, dan hari kerja efektif
        $diffKalender = 0;
        $totalHariLibur = 0;
        $hariKerjaEfektif = 0;

        if ($firstDayOfMonth && $lastDayOfMonth) {
            $currDate = new \DateTime($firstDayOfMonth);
            $endDateObj = new \DateTime($lastDayOfMonth);

            $daysMapIdn = [
                0 => 'minggu',
                1 => 'senin',
                2 => 'selasa',
                3 => 'rabu',
                4 => 'kamis',
                5 => 'jumat',
                6 => 'sabtu',
            ];

            while ($currDate <= $endDateObj) {
                $diffKalender++;
                $tglStr = $currDate->format('Y-m-d');
                $dayOfWeek = (int) $currDate->format('w'); // 0 = Minggu
                $namaHariIdn = $daysMapIdn[$dayOfWeek] ?? '';

                $isHariLiburMingguan = ($dayOfWeek === 0 || in_array($namaHariIdn, $hariLiburNama));
                $isTanggalMerah = in_array($tglStr, $liburNasional);

                if ($isHariLiburMingguan || $isTanggalMerah) {
                    $totalHariLibur++;
                } else {
                    $hariKerjaEfektif++;
                }

                $currDate->modify('+1 day');
            }
        } else {
            $diffKalender = 25;
            $hariKerjaEfektif = 25;
        }

        if ($hariKerjaEfektif <= 0) {
            $hariKerjaEfektif = max(1, $diffKalender - $totalHariLibur);
        }

        $isMingguan = ($periodeGajiKary === 'mingguan' || str_contains($periodeGajiKary ?? '', 'minggu') || $diffKalender <= 7);
        $isHarian = ($gajiPokokPeriode === 'harian' || $periodeGajiKary === 'harian');

        if ($isHarian && !$isMingguan) {
            // Standar gaji harian murni
            $gaji_per_hari = $gaji_pokok;
        } elseif ($isMingguan) {
            // Periode mingguan: bagi berdasarkan hari kerja efektif (di luar hari Minggu dan tanggal merah)
            $pembagiHari = ($hariKerjaEfektif > 0) ? $hariKerjaEfektif : 6;
            $gaji_per_hari = $gaji_pokok > 0 ? ($gaji_pokok / $pembagiHari) : 0;
        } else {
            // Standar bulanan BBS (25 hari kerja)
            $jml_hari_sebulan = 25;
            $gaji_per_hari = $gaji_pokok > 0 ? ($gaji_pokok / $jml_hari_sebulan) : 0;
        }

        $tipePeriodeKolom = $isMingguan ? 'Mingguan' : ($isHarian ? 'Harian' : 'Bulanan');

        try {
            $coreGaji = new \App\Cores\PerhitunganGaji();

            // 1. Denda Keterlambatan (MoM 3.B: Rp 25.000 / 30 menit)
            $dendaTelat = $coreGaji->hitungDendaKeterlambatan($kary->id, $firstDayOfMonth, $lastDayOfMonth);
            if (!empty($dendaTelat['total_denda']) && $dendaTelat['total_denda'] > 0) {
                $defaultColumns[] = [
                    'label'      => "Denda Keterlambatan ({$dendaTelat['total_menit_telat']} Menit)",
                    'factor'     => '-',
                    'value'      => (float) $dendaTelat['total_denda'],
                    'type'       => $tipePeriodeKolom,
                    'can_adjust' => 1
                ];
            }

            // 2. Akumulasi Izin & Pemotongan Cuti / Gaji Proporsional (MoM 3.B, 3.C, 3.D)
            $potonganIzin = $coreGaji->hitungAkumulasiIzinDanPotonganCuti($kary->id, $firstDayOfMonth, $lastDayOfMonth, $gaji_per_hari);
            if (!empty($potonganIzin['nominal_potongan_proporsional']) && $potonganIzin['nominal_potongan_proporsional'] > 0) {
                $defaultColumns[] = [
                    'label'      => "Potongan Izin & Cuti Habis ({$potonganIzin['hari_potong_gaji']} Hari)",
                    'factor'     => '-',
                    'value'      => (float) $potonganIzin['nominal_potongan_proporsional'],
                    'type'       => $tipePeriodeKolom,
                    'can_adjust' => 1
                ];
            }
        } catch (\Throwable $e) {
            // Safe fallback jika table presensi belum ada data
        }

        // Cek kehadiran karyawan melalui stored procedure PostgreSQL
        try {
            $attendance = null;
            $isRange = ($firstDayOfMonth && $lastDayOfMonth && $firstDayOfMonth !== $lastDayOfMonth);
            $isFullMonth = false;
            if ($isRange) {
                $isFullMonth = (date('Y-m-01', strtotime($firstDayOfMonth)) === $firstDayOfMonth
                    && date('Y-m-t', strtotime($firstDayOfMonth)) === $lastDayOfMonth);
            }

            // Jika periode merupakan rentang tertentu (seperti mingguan / 7 hari), gunakan employee_attendance_range
            if ($isRange && !$isFullMonth) {
                try {
                    $attendance = \DB::select("select public.employee_attendance_range(?,?,?) as employee_attendance", [
                        $firstDayOfMonth,
                        $lastDayOfMonth,
                        @$kary->id ?? 0
                    ]);
                } catch (\Throwable $e) {
                    $attendance = null;
                }
            }

            // Fallback ke employee_attendance bulanan jika range tidak tersedia atau periode 1 bulan penuh
            if (!$attendance || !count($attendance)) {
                $attendance = \DB::select("select public.employee_attendance(?,?) as employee_attendance", [
                    $firstDayOfMonth,
                    @$kary->id ?? 0
                ]);
            }

            if (count($attendance)) {
                $att = $attendance[0]->employee_attendance;
                $att = json_decode($att);

                $rawNotPresent = (int) ($att->work_not_present ?? 0);
                $hadir = (int) ($att->work_present ?? 0);
                $cutiTerpakai = (int) ($att->cuti_terpakai ?? 0);
                $sisa_cuti = (int) ((@$att->sisa_cuti_reguler ?? 0) + (@$att->sisa_cuti_masa_kerja ?? 0));

                if ($isRange && !$isFullMonth) {
                    // Hari Minggu dan Tanggal Merah / Libur Nasional TIDAK dihitung kerja dan TIDAK dipotong
                    if (isset($att->work_present)) {
                        // Dihitung dari Hari Kerja Efektif dikurangi kehadiran dan cuti resmi
                        $tidak_masuk_kerja = max(0, $hariKerjaEfektif - $hadir - $cutiTerpakai);
                    } else {
                        // Fallback: kurangi total hari libur (Minggu + tanggal merah) dari not present stored procedure
                        $tidak_masuk_kerja = max(0, $rawNotPresent - $totalHariLibur);
                    }
                } else {
                    $tidak_masuk_kerja = $rawNotPresent;
                }

                if (($sisa_cuti - $tidak_masuk_kerja) < 0) {
                    $hariDipotong = $tidak_masuk_kerja - max(0, $sisa_cuti);
                    $value = round($gaji_per_hari * $hariDipotong, 2);
                    $defaultColumns[] = [
                        'label'      => "Potongan Tidak Masuk Kerja ($tidak_masuk_kerja)",
                        'factor'     => '-',
                        'value'      => $value,
                        'type'       => $tipePeriodeKolom,
                        'can_adjust' => 1
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika function public.employee_attendance tidak ada di server
        }
        

        // faktor lain :Cuti
        // $t_cuti = t_cuti::where('m_kary_id', @$kary->id ?? 0)->whereRaw("status = 'APPROVED' and date_from >= ? and date_to <= ?",[$firstDayOfMonth,$lastDayOfMonth])->get();
        // if(count($t_cuti)) {
        //     $sisa_cuti = m_kary::where('id', @$kary->id ?? 0)->pluck('cuti_sisa_reguler')->first() ?? 0;
        //     $count = t_cuti::where('m_kary_id', @$kary->id ?? 0)->whereRaw("attachment is not null and status = 'APPROVED' and date_from >= ? and date_to <= ?",[$firstDayOfMonth,$lastDayOfMonth])->count();
        //     foreach($t_cuti as $d){
        //         $date_from = \DateTime::createFromFormat('Y-m-d', $d->date_from);
        //         $date_to = \DateTime::createFromFormat('Y-m-d', $d->date_to);
        //         $interval = @$date_from->diff($date_to) ?? 0;
        //         $jumlah_hari = $interval->days;
        //         if($sisa_cuti > 0){
        //             $jumlah_hari = $jumlah_hari - $sisa_cuti;
        //         }

        //         $gaji_per_hari = 0;
        //         $makan_per_hari = 0;
        //         $gaji_pokok = @$standart_gaji['gaji_pokok'] ?? 0;
        //         if($gaji_pokok){
        //             $gaji_per_hari = $gaji_pokok / (int)date('t');
        //         }
        //         $makan_per_hari = @$standart_gaji['uang_makan'] / (int)date('t');
        //         $potongan_cuti = $gaji_per_hari*$jumlah_hari;
        //         $potongan_makan = $makan_per_hari * $jumlah_hari;

        //         if($count > 7){
        //             $defaultColumns[] = [
        //                 'label'    => "Potongan Cuti ($jumlah_hari)",
        //                 'factor'   => '-',
        //                 'value'    => $potongan_cuti,
        //                 'type'     => 'Bulanan',
        //                 'can_adjust' => 1,
        //                 't_cuti_id' => $d->id
        //             ];
                    
        //         }else{
        //             $defaultColumns[] = [
        //                 'label'    => "Potongan Cuti (Uang Makan) ($jumlah_hari)",
        //                 'factor'   => '-',
        //                 'value'    => $potongan_cuti,
        //                 'type'     => 'Bulanan',
        //                 'can_adjust' => 1,
        //                 't_cuti_id' => $d->id
        //             ];
        //         }
                
        //     }
        // }

        return $defaultColumns;
    }

    private function countPPH21($kary, $netto = 0)
    {
        $getBasicSalary = [];
        // pengurangan dari perhitungan pph21
        // ------------------------- contoh perhitungan ---------------------------
        // Penghasilan Neto dalam setahun Rp9.400.000 x 12	    = Rp112.800.000
        // PTKP Status Lajang	                                = Rp54.000.000 (-)
        // Pendapatan Kena Pajak (PKP):	
        // PKP setahun Rp112.800.000 – Rp54.000.000	            = Rp58.800.000

        $tanggungan = m_general::find($kary->tanggungan_id);
        if($tanggungan){

            // persentase pajak <= Rp50.000.000                 = 5%
            // persentase pajak > Rp50.000.000  – Rp250.000.000 = 15%
            // persentase pajak > Rp250.000.000 – Rp500.000.000 = 25%
            // persentase pajak > Rp250.000.000 – Rp500.000.000 = 30%

            $nilaiTanggungan = @$tanggungan->value_2 ?? 0;
            $nettoYear = $netto*12;
            $nettoPTKP = $nettoYear-$nilaiTanggungan;

            // hentikan fungsi ketika gaji masih dibawah jumlah tanggungan 
            if($nettoPTKP <= 0) return $getBasicSalary;

            $percent   = 0;
            if($nettoPTKP <= 50000000){
                $before_value = 0;
                $before_percent = $percent;
                $percent = 5;
            }elseif(
                $nettoPTKP > 50000000 
                && $nettoPTKP <= 250000000
            ){
                $before_value = 50000000;
                $before_percent = $percent;
                $percent = 15;
        
            }elseif($nettoPTKP > 250000000 && $nettoPTKP <= 500000000){ 
                $before_value = 250000000;
                $before_percent = $percent;
                $percent = 25;
              
            }elseif($nettoPTKP > 500000000){
                $before_value = 500000000;
                $before_percent = $percent;
                $percent = 30;
            }
            $getBasicSalary = $this->countTaxDetail(
                $tanggungan, 
                $nettoPTKP, 
                $before_value, 
                $before_percent, 
                $percent, 
                $getBasicSalary
            );
        }
        return $getBasicSalary;
    }

    private function countTaxDetail(
        $tanggungan, 
        $nettoPTKP, 
        $before_value, 
        $before_percent, 
        $percent, 
        $mergingArr
    )
    {
        $outstanding = $nettoPTKP-$before_value;
        $tax1 = $before_percent*$before_value/100;
        $tax2 = $percent*$outstanding/100;
        $total_tax = $tax1+$tax2;
        // insert dari kondisi gaji sebelumnya sebelumnya
        // ex: 5% x 50.000.000
        // ex: 15% x 800.0000
        $detail = [];
        if($before_percent != 0){
            // jika netto / before value memiliki sisa
            $detail = [
                [
                    'label'    => "$before_percent% x $before_value",
                    'factor'   => '+',
                    'value'    => $tax1,
                    'type'     => 'Tahunan'
                ],
                [
                    'label'    => "$percent% x $outstanding",
                    'factor'   => '+',
                    'value'    => $tax2,
                    'type'     => 'Tahunan'
                ]
            ];
        }else{
            // jika netto / before value tidak memiliki sisa (konidisi pertama)
            $detail = [
                [
                    'label'    => "$percent% x $nettoPTKP",
                    'factor'   => '+',
                    'value'    => $tax2,
                    'type'     => 'Tahunan',
                ]
            ];
        }

        $mergingArr[] = [
            'label'    => "PTKP $tanggungan->value (perbulan)",
            'factor'   => '-',
            'value'    => $total_tax/12,
            'type'     => 'Bulanan',
            'can_adjust' => 0,
            'detail'   => $detail
        ];
        return $mergingArr;
    }

    private function summarySubSalary($arrConfig) 
    {   
        return array_reduce($arrConfig, function ($carry, $item) {
            if(is_numeric($item['value'])){
                $value = (float)$item['value'];
                if($value != 0){
                    if($item['factor'] == '+'){
                        $carry = $carry + $item['value'];
                    }elseif($item['factor'] == '-'){
                        $carry = $carry - $item['value'];
                    }
                }
            }
            return $carry;
        }, 0);
    }



    public function salaryOfKary($id, $periode = null, $periodeAkhir = null)
    {
        try{
            $m_kary_id = $id;
            $kary = m_kary::find($m_kary_id);
            if (!$kary) {
                return [
                    'm_kary_id'  => $m_kary_id,
                    'total_gaji' => 0,
                    'total_tax'  => 0,
                    'netto'      => 0,
                    'detail'     => []
                ];
            }

            $m_standart_gaji = null;
            if (@$kary->m_standart_gaji_id) {
                $m_standart_gaji = m_standart_gaji::find($kary->m_standart_gaji_id);
            }
            if (!$m_standart_gaji && @$kary->m_zona_id && @$kary->grading_id) {
                $m_standart_gaji = m_standart_gaji::where('m_zona_id', $kary->m_zona_id)
                    ->where('grading_id', $kary->grading_id)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$m_standart_gaji) return [
                'm_kary_id'  => $m_kary_id,
                'total_gaji' => 0,
                'total_tax'  => 0,
                'netto'      => 0,
                'detail'     => []
            ];

            // default summary salary
            $getBasicSalary = $this->factorSalary($m_standart_gaji, $kary, $periode, $periodeAkhir);
            $netto          = $this->summarySubSalary($getBasicSalary);
            $getBasicSalary    = array_merge($getBasicSalary, [
                [
                    'label'    => 'Total Gaji',
                    'factor'   => '=',
                    'value'    => $netto,
                    'type'     => '-'
                ]
            ]);

            $nettoFinish    = $this->summarySubSalary($getBasicSalary);

            // default summary tax
            $arrPPH         = $this->countPPH21($kary, $netto);
            $totalTax = @$arrPPH[0]['value'];
            if(count($arrPPH)){
                $getBasicSalary = array_merge($getBasicSalary, $arrPPH);
                $nettoFinish    = $this->summarySubSalary($getBasicSalary);
                $getBasicSalary    = array_merge($getBasicSalary, [
                    [
                        'label'    => 'Total Gaji (Setelah PPH 21)',
                        'factor'   => '=',
                        'value'    => $nettoFinish,
                        'type'     => '-'
                    ]
                ]);
            }

            return [
                'm_kary_id'  => $m_kary_id,
                'total_gaji' => $netto,
                'total_tax'  => $totalTax,
                'netto'      => $nettoFinish,
                'detail'     => $getBasicSalary
            ];
        } catch (\Exception $e) {
            return $this->helper->responseCatch($e);
        }
    }

    public function generateSalary()
    {
        try{
            $req =  app()->request;
            $kary = m_kary::selectRaw("m_kary.*,m_general.value periode_text, m_dir.nama dir, m_divisi.nama divisi, m_dept.nama dept")
                ->leftJoin('m_dir','m_dir.id','m_kary.m_dir_id')
                ->leftJoin('m_divisi','m_divisi.id','m_kary.m_divisi_id')
                ->leftJoin('m_dept','m_dept.id','m_kary.m_dept_id')
                ->leftJoin('m_general','m_general.id','m_kary.periode_gaji_id')
                ->where('m_kary.is_active', true)
                ->where(function ($q) {
                    $q->whereRaw('m_kary.m_standart_gaji_id in(select s.id from m_standart_gaji s where s.is_active = true)')
                      ->orWhereExists(function ($sq) {
                          $sq->select(\DB::raw(1))
                             ->from('m_standart_gaji as s')
                             ->whereColumn('s.m_zona_id', 'm_kary.m_zona_id')
                             ->whereColumn('s.grading_id', 'm_kary.grading_id')
                             ->where('s.is_active', true);
                      });
                });

            if($req->m_dir_id) $kary = $kary->where('m_kary.m_dir_id', $req->m_dir_id);
            if($req->m_divisi_id) $kary = $kary->where('m_kary.m_divisi_id', $req->m_divisi_id);
            if($req->m_dept_id) $kary = $kary->where('m_kary.m_dept_id', $req->m_dept_id);
            if($req->periode_gaji_id) $kary = $kary->where('m_kary.periode_gaji_id', $req->periode_gaji_id);
            if($req->m_kary_id) $kary = $kary->where('m_kary.id', $req->m_kary_id);

            $kary = $kary->get();
            
            $rawAwal = $req->periode_awal;
            $rawAkhir = $req->periode_akhir;

            if (!$rawAwal || !$rawAkhir) {
                return $this->helper->customResponse('Periode awal dan periode akhir wajib diisi', 422);
            }

            // Normalisasi format input (mendukung Y-m-d maupun Y-m)
            if (strlen($rawAwal) === 7) {
                $strDateFrom = $rawAwal . '-01';
            } else {
                $strDateFrom = date('Y-m-d', strtotime($rawAwal));
            }

            if (strlen($rawAkhir) === 7) {
                $strDateTo = date('Y-m-t', strtotime($rawAkhir . '-01'));
            } else {
                $strDateTo = date('Y-m-d', strtotime($rawAkhir));
            }

            $labelAwal = date('d/m/Y', strtotime($strDateFrom));
            $labelAkhir = date('d/m/Y', strtotime($strDateTo));
            $periodeLabel = ($labelAwal === $labelAkhir) ? $labelAwal : ($labelAwal . ' - ' . $labelAkhir);

            $data = [];
            foreach ($kary as $key) {
                $gaji = $this->salaryOfKary($key->id, $strDateFrom, $strDateTo);
                $nikVal = $key->nik ?: $key->kode;
                $data[] = [
                    'm_kary_id'         => $key->id,
                    'm_kary.nik'        => $nikVal,
                    'm_kary_dir_id'     => $key->m_dir_id,
                    'm_kary_dir.nama'   => $key->dir,
                    'm_kary_divisi_id'  => $key->m_divisi_id,
                    'm_kary_divisi.nama'=> $key->divisi,
                    'm_kary_dept_id'    => $key->m_dept_id,
                    'm_kary_dept.nama'  => $key->dept,
                    'nik'               => $nikVal,
                    'kode'              => $key->kode,
                    'nama_lengkap'      => $key->nama_lengkap,
                    'periode'           => $periodeLabel,
                    'periode_in_date'   => $strDateFrom,
                    'periode_id'        => $key->periode_gaji_id,
                    'periode_text'      => $key->periode_text,
                    'total_tax'         => $gaji['total_tax'] ?? 0,
                    'total_gaji'        => $gaji['total_gaji'],
                    'netto'             => $gaji['netto'],
                    'detail_gaji'       => $gaji['detail'],
                ];
            }

            return $this->helper->customResponse('OK', 200, $data);
        } catch (\Exception $e) {
            return $this->helper->responseCatch($e);
        }
    }

    public function public_generate()
    {
        $data =  $this->salaryOfKary(app()->request->id ?? 8, '2024-03');
       
        return response(['msg'=>$data]);
    }

    public function custom_generate()
    {
        return $this->generateSalary();
    }

    public function custom_generatePPH($req)
    {
        $netto = $req->netto;
        $kary = m_kary::find($req->m_kary_id);
        return $this->countPPH21($kary, $netto);
    }

    public function custom_save($req)
    {
        $counter = count($req->detail);
        if($counter){
            $nomor = $this->helper->generateNomor('KODE PERHITUNGAN GAJI');
            foreach($req->detail as $key){
                $checkAndDelete = $this->where('m_kary_id', @$key['m_kary_id'])->where('periode', @$key['periode'])->delete();
                $key['nomor'] =  $nomor;
                $key['detail_gaji'] = json_encode($key['detail_gaji']);
                $hdr = $this->create($key);
            }
        }
        return $this->helper->customResponse("$counter Data berhasil disimpan");
    }
    
       
    public function scopeGenerateForFinal($model)
    {
        $req = app()->request;
        $rawAwal = $req->periode_awal;
        $rawAkhir = $req->periode_akhir;

        if ($rawAwal && $rawAkhir) {
            $strDateFrom = (strlen($rawAwal) === 7) ? $rawAwal . '-01' : date('Y-m-d', strtotime($rawAwal));
            $strDateTo = (strlen($rawAkhir) === 7) ? date('Y-m-t', strtotime($rawAkhir . '-01')) : date('Y-m-d', strtotime($rawAkhir));

            $labelAwal = date('d/m/Y', strtotime($strDateFrom));
            $labelAkhir = date('d/m/Y', strtotime($strDateTo));
            $expectedPeriode = ($labelAwal === $labelAkhir) ? $labelAwal : ($labelAwal . ' - ' . $labelAkhir);

            // Filter agar HANYA mengambil data perhitungan gaji yang sesuai persis dengan periode yang dipilih,
            // atau yang periode_in_date-nya sama persis dan memuat label tanggal akhir
            $model = $model->where(function($q) use ($expectedPeriode, $strDateFrom, $labelAwal, $labelAkhir) {
                $q->where('t_perhitungan_gaji.periode', $expectedPeriode)
                  ->orWhere(function($sub) use ($strDateFrom, $labelAkhir) {
                      $sub->where('t_perhitungan_gaji.periode_in_date', $strDateFrom)
                          ->where('t_perhitungan_gaji.periode', 'like', '%' . $labelAkhir . '%');
                  })
                  ->orWhere(function($sub2) use ($labelAwal, $labelAkhir) {
                      $sub2->where('t_perhitungan_gaji.periode', 'like', '%' . $labelAwal . '%')
                           ->where('t_perhitungan_gaji.periode', 'like', '%' . $labelAkhir . '%');
                  });
            });
        } elseif ($rawAwal) {
            $strDateFrom = (strlen($rawAwal) === 7) ? $rawAwal . '-01' : date('Y-m-d', strtotime($rawAwal));
            $labelAwal = date('d/m/Y', strtotime($strDateFrom));
            $model = $model->where(function($q) use ($strDateFrom, $labelAwal) {
                $q->where('t_perhitungan_gaji.periode_in_date', '>=', $strDateFrom)
                  ->orWhere('t_perhitungan_gaji.periode', 'like', '%' . $labelAwal . '%');
            });
        } elseif ($rawAkhir) {
            $strDateTo = (strlen($rawAkhir) === 7) ? date('Y-m-t', strtotime($rawAkhir . '-01')) : date('Y-m-d', strtotime($rawAkhir));
            $labelAkhir = date('d/m/Y', strtotime($strDateTo));
            $model = $model->where(function($q) use ($strDateTo, $labelAkhir) {
                $q->where('t_perhitungan_gaji.periode_in_date', '<=', $strDateTo)
                  ->where('t_perhitungan_gaji.periode', 'like', '%' . $labelAkhir . '%');
            });
        }

        if($req->m_dir_id) $model = $model->where('t_perhitungan_gaji.m_kary_dir_id', $req->m_dir_id);
        if($req->m_divisi_id) $model = $model->where('t_perhitungan_gaji.m_kary_divisi_id', $req->m_divisi_id);
        if($req->m_dept_id) $model = $model->where('t_perhitungan_gaji.m_kary_dept_id', $req->m_dept_id);
        if($req->periode_gaji_id) $model = $model->where('t_perhitungan_gaji.periode_id', $req->periode_gaji_id);
        if($req->m_kary_id) $model = $model->where('t_perhitungan_gaji.m_kary_id', $req->m_kary_id);

        // Proteksi anti-double finalize: data perhitungan gaji yang sudah masuk ke finalisasi berstatus POSTED tidak ditarik ulang
        $model = $model->whereNotIn('t_perhitungan_gaji.id', function($q) {
            $q->select('t_perhitungan_gaji_id')
              ->from('t_final_gaji_det')
              ->join('t_final_gaji', 't_final_gaji.id', '=', 't_final_gaji_det.t_final_gaji_id')
              ->where('t_final_gaji.status', 'POSTED')
              ->whereNotNull('t_final_gaji_det.t_perhitungan_gaji_id');
        });

        return $model;
    }
}