<?php

namespace App\Models\CustomModels;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class t_lembur extends \App\Models\BasicModels\t_lembur
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
        $this->mergeFillable([
            'pic_id',
            'interval_min',
            'jam_mulai_realisasi',
            'jam_selesai_realisasi',
            'realisasi_min',
            'diakui_min',
            'tarif_lembur',
            'total_lembur',
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        // Isolasi Transaksi Lembur sesuai active Respo:
        // Superadmin = semua PT, HRD/Admin = PT/Divisi/Dept active Respo, User biasa = milik sendiri (+ atasan/approver saat GET single detail)
        static::addGlobalScope('respo_scope', function (Builder $builder) {
            $respoCore = getCore('Respo');
            if (!$respoCore || !$respoCore->isOperationCrudRequest('t_lembur', ['GET', 'PUT', 'PATCH', 'DELETE'])) {
                return;
            }

            $respoCore->applyActiveEmployeeTransactionScopeToQuery($builder, 't_lembur', [
                'trx_table'                => 't_lembur',
                'dir_col'                  => 't_lembur.m_dir_id',
                'divisi_col'               => null,
                'dept_col'                 => null,
                'kary_fk_col'              => 't_lembur.m_kary_id',
                'allow_approver_on_single' => true,
            ]);
        });
    }

    public $fileColumns = ["doc"];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function scopeFilter($model)
    {
        if (req("date_from") && req("date_to")) {
            $model = $model->whereBetween("tanggal", [req("date_from"), req("date_to")]);
        }
        return $model;
    }

    public function transformRowData( array $row )
    {
        try {
            $approval = \DB::table('generate_approval_log')
                ->where('trx_table', 't_lembur')
                ->where('trx_id', $row['id'] ?? 0)
                ->orderBy('created_at', 'desc')
                ->value('action_note');
        } catch (\Throwable $e) {
            $approval = '';
        }

        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')->where('k.id', $row['m_kary_id'])->value('d.nama');
        }

        // HIDE REALISASI DI SISI USER / KARYAWAN / PENGAJU:
        try {
            $userAuth = auth()->user();
            $respoCore = getCore('Respo');
            $isSuperadmin = $userAuth && $respoCore ? $respoCore->isUserSuperadmin($userAuth) : false;
            if ($userAuth && !$isSuperadmin) {
                $userKaryId = $userAuth->m_kary_id ?? null;
                $userId = $userAuth->id ?? null;

                $isApplicant = ($userKaryId && !empty($row['m_kary_id']) && $row['m_kary_id'] == $userKaryId)
                            || ($userId && !empty($row['creator_id']) && $row['creator_id'] == $userId);

                if ($isApplicant) {
                    $row['jam_mulai_realisasi'] = null;
                    $row['jam_selesai_realisasi'] = null;
                    $row['realisasi_min'] = null;
                    $row['diakui_min'] = null;
                    $row['total_lembur'] = null;
                }
            }
        } catch (\Throwable $e) {}

        return array_merge( $row, [
            'approval_note' => $approval ?? '',
            'm_dir.nama'    => $compNama ?? ($row['m_dir.nama'] ?? '-'),
        ] );
    }

    private function hitungMenit($from, $to) {
        if (!$from || !$to) return 0;
        $dateFrom = Carbon::parse($from);
        $dateTo = Carbon::parse($to);

        // Jika jam selesai lebih kecil dari jam mulai, berarti lembur lintas tengah malam
        if ($dateTo->lt($dateFrom)) {
            $dateTo->addDay();
        }

        // Calculate the difference in minutes for datetime range
        $minutesDifference = $dateFrom->diffInMinutes($dateTo);

        return $minutesDifference;
    }

    private function resolveTarifLemburByKary($kary)
    {
        if (!$kary) {
            return 0;
        }

        $dirId = !empty($kary->m_dir_id) ? intval($kary->m_dir_id) : 0;
        $divId = !empty($kary->m_divisi_id) ? intval($kary->m_divisi_id) : 0;
        $deptId = !empty($kary->m_dept_id) ? intval($kary->m_dept_id) : 0;
        $gradingId = !empty($kary->grading_id) ? intval($kary->grading_id) : 0;

        $query = \DB::table('m_lembur')
            ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))");

        $query->where(function ($q) use ($dirId) {
            if ($dirId > 0) {
                $q->where('m_dir_id', $dirId)->orWhereNull('m_dir_id');
            } else {
                $q->whereNull('m_dir_id');
            }
        });

        $query->where(function ($q) use ($divId) {
            if ($divId > 0) {
                $q->where('m_divisi_id', $divId)->orWhereNull('m_divisi_id');
            } else {
                $q->whereNull('m_divisi_id');
            }
        });

        $query->where(function ($q) use ($deptId) {
            if ($deptId > 0) {
                $q->where('m_dept_id', $deptId)->orWhereNull('m_dept_id');
            } else {
                $q->whereNull('m_dept_id');
            }
        });

        $query->where(function ($q) use ($gradingId) {
            if ($gradingId > 0) {
                $q->where('grading_id', $gradingId)->orWhereNull('grading_id');
            } else {
                $q->whereNull('grading_id');
            }
        });

        $orderDir = $dirId > 0 ? "case when m_dir_id = {$dirId} then 0 else 1 end" : "case when m_dir_id is null then 0 else 1 end";
        $orderGrade = $gradingId > 0 ? "case when grading_id = {$gradingId} then 0 else 1 end" : "case when grading_id is null then 0 else 1 end";
        $orderDept = $deptId > 0 ? "case when m_dept_id = {$deptId} then 0 else 1 end" : "case when m_dept_id is null then 0 else 1 end";
        $orderDiv = $divId > 0 ? "case when m_divisi_id = {$divId} then 0 else 1 end" : "case when m_divisi_id is null then 0 else 1 end";

        $rate = $query
            ->orderByRaw($orderDir)
            ->orderByRaw($orderGrade)
            ->orderByRaw($orderDept)
            ->orderByRaw($orderDiv)
            ->orderBy('id', 'desc')
            ->first();

        return ($rate && floatval($rate->besaran) > 0) ? floatval($rate->besaran) : 0;
    }

    /**
     * Hitung durasi SPK, durasi realisasi, dan durasi diakui sesuai MoM BBS 7 Okt 2026 Poin 3.K:
     * "Perhitungan lembur mengikuti jam realisasi lembur apabila durasi SPK lebih besar dari durasi realisasi.
     * Sebaliknya, apabila durasi realisasi lembur lebih besar dari durasi yang tercantum dalam SPK, maka perhitungan mengikuti durasi SPK."
     * Formula: min(durasi_spk, durasi_realisasi).
     */
    public function hitungDurasiDanTarif($arrayData, $existingData = null)
    {
        $jamMulaiSpk = $arrayData['jam_mulai'] ?? ($existingData->jam_mulai ?? null);
        $jamSelesaiSpk = $arrayData['jam_selesai'] ?? ($existingData->jam_selesai ?? null);
        $jamMulaiReal = $arrayData['jam_mulai_realisasi'] ?? ($existingData->jam_mulai_realisasi ?? null);
        $jamSelesaiReal = $arrayData['jam_selesai_realisasi'] ?? ($existingData->jam_selesai_realisasi ?? null);
        $mKaryId = $arrayData['m_kary_id'] ?? ($existingData->m_kary_id ?? null);

        // 1. Durasi Rencana SPK (menit)
        $durasiSpk = ($jamMulaiSpk && $jamSelesaiSpk) ? $this->hitungMenit($jamMulaiSpk, $jamSelesaiSpk) : 0;

        // 2. Durasi Realisasi Aktual (menit)
        $durasiReal = ($jamMulaiReal && $jamSelesaiReal) ? $this->hitungMenit($jamMulaiReal, $jamSelesaiReal) : null;

        // 3. Durasi Diakui (MoM 3.K: min(durasi_spk, durasi_realisasi))
        if ($durasiReal !== null && $durasiReal > 0) {
            $diakuiMin = min($durasiSpk, $durasiReal);
        } else {
            // Bila realisasi belum diisi (masih tahap rencana SPK), default diakui = durasi SPK
            $diakuiMin = $durasiSpk;
        }

        // 4. Cari Tarif Lembur dari m_lembur berdasarkan company & grading karyawan (MoM 3.J)
        $tarifLembur = 0;
        if ($mKaryId) {
            $kary = \DB::table('m_kary')->where('id', $mKaryId)->first();
            if ($kary) {
                $tarifLembur = $this->resolveTarifLemburByKary($kary);
            }
        }

        $totalLembur = round(($diakuiMin / 60) * $tarifLembur, 2);

        return [
            'interval_min'  => $durasiSpk,
            'realisasi_min' => $durasiReal,
            'diakui_min'    => $diakuiMin,
            'tarif_lembur'  => $tarifLembur,
            'total_lembur'  => $totalLembur
        ];
    }

    /**
     * Sinkronisasi Realisasi Lembur dari Presensi Absensi (Checkout Absen)
     * Mengambil jam checkout yang terhitung setelah jam selesai kerja hingga absen checkout dilakukan.
     * Untuk hari Minggu / Tanggal Merah / Libur: seluruh jam kerja dihitung sebagai lembur.
     */
    public function sinkronkanRealisasiDariAbsen($trx)
    {
        try {
            if (!$trx) return $trx;

            $id = is_array($trx) ? ($trx['id'] ?? null) : ($trx->id ?? null);
            $mKaryId = is_array($trx) ? ($trx['m_kary_id'] ?? null) : ($trx->m_kary_id ?? null);
            $tanggal = is_array($trx) ? ($trx['tanggal'] ?? null) : ($trx->tanggal ?? null);
            $statusTrx = strtoupper(trim(is_array($trx) ? ($trx['status'] ?? '') : ($trx->status ?? '')));

            if (!$id || !$mKaryId || !$tanggal) {
                return $trx;
            }

            // Realisasi HANYA disinkronkan setelah persetujuan dari Atasan (status 'HALF APPROVED' atau 'APPROVED')
            if (!in_array($statusTrx, ['HALF APPROVED', 'APPROVED'])) {
                return $trx;
            }

            $creatorId = is_array($trx) ? ($trx['creator_id'] ?? null) : ($trx->creator_id ?? null);
            $jamMulai = is_array($trx) ? ($trx['jam_mulai'] ?? null) : ($trx->jam_mulai ?? null);
            $jamSelesai = is_array($trx) ? ($trx['jam_selesai'] ?? null) : ($trx->jam_selesai ?? null);
            $intervalMin = is_array($trx) ? ($trx['interval_min'] ?? null) : ($trx->interval_min ?? null);
            $tarifLembur = is_array($trx) ? ($trx['tarif_lembur'] ?? 0) : ($trx->tarif_lembur ?? 0);

            // Cari user ID karyawan
            $userIds = \DB::table('default_users')->where('m_kary_id', $mKaryId)->pluck('id')->toArray();
            if (!empty($creatorId) && !in_array($creatorId, $userIds)) {
                $userIds[] = $creatorId;
            }

            if (empty($userIds)) {
                return $trx;
            }

            // Cari data absensi pada tanggal tersebut
            $absen = \DB::table('presensi_absensi')
                ->whereIn('default_user_id', $userIds)
                ->where('tanggal', $tanggal)
                ->orderBy('id', 'desc')
                ->first();

            if (!$absen || empty($absen->checkout_time)) {
                return $trx;
            }

            $checkoutTime = substr($absen->checkout_time, 0, 5);
            $checkinTime  = !empty($absen->checkin_time) ? substr($absen->checkin_time, 0, 5) : null;

            // Cek apakah tanggal lembur adalah Hari Minggu atau Hari Libur Nasional (Tanggal Merah)
            $tglObj = Carbon::parse($tanggal);
            $isMinggu = ($tglObj->dayOfWeek === 0);
            $isLiburNasional = \DB::table('m_libur_nasional')
                ->where('tanggal', $tanggal)
                ->where('is_active', true)
                ->exists();

            // Cek jadwal kerja karyawan untuk mencari jam selesai kerja reguler (jam pulang kerja)
            $kary = \DB::table('m_kary')->where('id', $mKaryId)->first();
            $jamSelesaiKerja = null;

            if ($kary) {
                $daysMap = [0 => 'minggu', 1 => 'senin', 2 => 'selasa', 3 => 'rabu', 4 => 'kamis', 5 => 'jumat', 6 => 'sabtu'];
                $namaHari = $daysMap[$tglObj->dayOfWeek] ?? '';
                $dayNum = $tglObj->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu

                // 1. Cek dari t_jadwal_kerja_det spesifik karyawan
                $jadwalDetKary = \DB::table('t_jadwal_kerja_det as d')
                    ->join('t_jadwal_kerja_det_hari as h', 'h.id', '=', 'd.t_jadwal_kerja_det_hari_id')
                    ->where('d.m_kary_id', $mKaryId)
                    ->where(function($q) use ($tanggal, $dayNum, $namaHari) {
                        $q->where('h.tanggal', $tanggal)
                          ->orWhere('h.day_num', $dayNum)
                          ->orWhereRaw("lower(h.day) = ?", [$namaHari]);
                    })
                    ->select('h.waktu_akhir', 'h.tipe_hari')
                    ->first();

                if ($jadwalDetKary && !empty($jadwalDetKary->waktu_akhir) && strtolower($jadwalDetKary->tipe_hari ?? '') !== 'libur') {
                    $jamSelesaiKerja = substr($jadwalDetKary->waktu_akhir, 0, 5);
                }

                // 2. Cek dari t_jadwal_kerja_id pada m_kary
                if (!$jamSelesaiKerja && !empty($kary->t_jadwal_kerja_id)) {
                    $jadwalDet = \DB::table('t_jadwal_kerja_det_hari')
                        ->where('t_jadwal_kerja_id', $kary->t_jadwal_kerja_id)
                        ->where(function($q) use ($tanggal, $dayNum, $namaHari) {
                            $q->where('tanggal', $tanggal)
                              ->orWhere('day_num', $dayNum)
                              ->orWhereRaw("lower(day) = ?", [$namaHari]);
                        })
                        ->select('waktu_akhir', 'tipe_hari')
                        ->first();

                    if ($jadwalDet && !empty($jadwalDet->waktu_akhir) && strtolower($jadwalDet->tipe_hari ?? '') !== 'libur') {
                        $jamSelesaiKerja = substr($jadwalDet->waktu_akhir, 0, 5);
                    }
                }

                // 3. Cek dari m_jam_kerja_id pada m_kary
                if (!$jamSelesaiKerja && !empty($kary->m_jam_kerja_id)) {
                    $jamKerja = \DB::table('m_jam_kerja')->where('id', $kary->m_jam_kerja_id)->first();
                    if ($jamKerja && !empty($jamKerja->waktu_akhir)) {
                        $jamSelesaiKerja = substr($jamKerja->waktu_akhir, 0, 5);
                    }
                }
            }

            // Tentukan Jam Mulai Realisasi & Jam Selesai Realisasi:
            // Sesuai aturan lembur: realisasi lembur terhitung setelah jam pulang kerja (selesai kerja normal) hingga absen checkout dilakukan.
            // Jika jam pulang kerja normal tidak ada (misal lembur di hari libur murni / Minggu tanpa shift), gunakan jam_mulai rencana SPK.
            if (!empty($jamSelesaiKerja)) {
                $jamMulaiReal = $jamSelesaiKerja;
            } elseif (!empty($jamMulai)) {
                $jamMulaiReal = substr($jamMulai, 0, 5);
            } else {
                $jamMulaiReal = '00:00';
            }

            $jamSelesaiReal = $checkoutTime;

            // Hitung durasi realisasi (menit)
            $durasiReal = $this->hitungMenit($jamMulaiReal, $jamSelesaiReal);
            $durasiSpk  = $intervalMin ?: $this->hitungMenit($jamMulai, $jamSelesai);

            // Durasi diakui: min(durasi_spk, durasi_realisasi)
            $diakuiMin = ($durasiReal > 0) ? min($durasiSpk, $durasiReal) : $durasiSpk;

            // Cari tarif lembur
            $tarif = floatval($tarifLembur ?? 0);
            if ($tarif <= 0 && $kary) {
                $tarif = $this->resolveTarifLemburByKary($kary);
            }

            $totalLembur = round(($diakuiMin / 60) * $tarif, 2);

            // Update record t_lembur di database
            \DB::table('t_lembur')->where('id', $id)->update([
                'jam_mulai_realisasi'   => $jamMulaiReal,
                'jam_selesai_realisasi' => $jamSelesaiReal,
                'interval_min'          => $durasiSpk,
                'realisasi_min'         => $durasiReal,
                'diakui_min'            => $diakuiMin,
                'tarif_lembur'          => $tarif,
                'total_lembur'          => $totalLembur,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            if (is_array($trx)) {
                $trx['jam_mulai_realisasi']   = $jamMulaiReal;
                $trx['jam_selesai_realisasi'] = $jamSelesaiReal;
                $trx['interval_min']          = $durasiSpk;
                $trx['realisasi_min']         = $durasiReal;
                $trx['diakui_min']            = $diakuiMin;
                $trx['tarif_lembur']          = $tarif;
                $trx['total_lembur']          = $totalLembur;
            } else {
                $trx->jam_mulai_realisasi   = $jamMulaiReal;
                $trx->jam_selesai_realisasi = $jamSelesaiReal;
                $trx->interval_min          = $durasiSpk;
                $trx->realisasi_min         = $durasiReal;
                $trx->diakui_min            = $diakuiMin;
                $trx->tarif_lembur          = $tarif;
                $trx->total_lembur          = $totalLembur;
            }

            return $trx;
        } catch (\Throwable $e) {
            return $trx;
        }
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $kalkulasi = $this->hitungDurasiDanTarif($arrayData);

        $newArrayData = array_merge($arrayData, [
            "nomor"                 => $this->helper->generateNomor("KODE LEMBUR"),
            "interval_min"          => $kalkulasi['interval_min'],
            "jam_mulai_realisasi"   => null,
            "jam_selesai_realisasi" => null,
            "realisasi_min"         => null,
            "diakui_min"            => $kalkulasi['interval_min'],
            "tarif_lembur"          => $kalkulasi['tarif_lembur'],
            "total_lembur"          => $kalkulasi['total_lembur'],
            "pic_id"                => @app()->request->pic_id ?? auth()->user()->id,
        ]);

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }

        $targetKary = !empty($newArrayData['m_kary_id']) ? \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->first() : null;
        if (!empty($newArrayData['m_dir_id']) && $targetKary && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
            return [
                "model" => $model,
                "data"  => $arrayData,
                "errors" => ["Company pengajuan lembur harus sesuai dengan Company karyawan terpilih."],
            ];
        }
        if (empty($newArrayData['m_dir_id']) && $targetKary && !empty($targetKary->m_dir_id)) {
            $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
        }

        // Validasi Scope Respo Aktif saat Create Lembur
        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."],
                ];
            }

            $authKaryId = (int) ($authUser->m_kary_id ?? 0);
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $isDirectSubordinate = $authKaryId > 0 && $targetKary && (int) ($targetKary->atasan_id ?? 0) === $authKaryId;

            if (!$isHrdOrAdmin) {
                if (!$targetKary) {
                    return [
                        "model" => $model,
                        "data"  => $arrayData,
                        "errors" => ["Data karyawan tidak valid."],
                    ];
                }
                $isSelf = $authKaryId > 0 && (int) $targetKary->id === $authKaryId;
                if (!$isSelf && !$isDirectSubordinate) {
                    return [
                        "model" => $model,
                        "data"  => $arrayData,
                        "errors" => ["Anda hanya dapat mengajukan lembur untuk diri sendiri atau bawahan langsung."],
                    ];
                }
            }

            $effDirId = $newArrayData['m_dir_id'] ?? ($targetKary ? $targetKary->m_dir_id : null);
            $targetDivId = $targetKary ? $targetKary->m_divisi_id : null;
            $targetDeptId = $targetKary ? $targetKary->m_dept_id : null;

            if (!$isDirectSubordinate && !$respoCore->matchesActiveCompanyScope($effDirId, $targetDivId, $targetDeptId, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Karyawan atau Perusahaan/Cabang berada di luar scope responsibility aktif."],
                ];
            }
        }

        if (app()->request->header("Source") == "mobile") {
            $newArrayData["status"] = "IN APPROVAL";
        }

        return [
            "model" => $model,
            "data"  => $newArrayData,
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $existing = \DB::table('t_lembur')->where('id', $id)->first();
        $status = $arrayData['status'] ?? ($existing->status ?? 'DRAFT');

        if (app()->request->header("Source") == "mobile") {
            if ($existing && $existing->status === 'REVISED') {
                $status = 'IN APPROVAL';
            }
        }

        $kalkulasi = $this->hitungDurasiDanTarif($arrayData, $existing);
        $statusUpper = strtoupper(trim($status));

        $newArrayData  = array_merge( $arrayData,[
            'status'                => $status,
            'interval_min'          => $kalkulasi['interval_min'],
            'jam_mulai_realisasi'   => in_array($statusUpper, ['HALF APPROVED', 'APPROVED']) ? ($arrayData['jam_mulai_realisasi'] ?? ($existing->jam_mulai_realisasi ?? null)) : null,
            'jam_selesai_realisasi' => in_array($statusUpper, ['HALF APPROVED', 'APPROVED']) ? ($arrayData['jam_selesai_realisasi'] ?? ($existing->jam_selesai_realisasi ?? null)) : null,
            'realisasi_min'         => in_array($statusUpper, ['HALF APPROVED', 'APPROVED']) ? $kalkulasi['realisasi_min'] : null,
            'diakui_min'            => in_array($statusUpper, ['HALF APPROVED', 'APPROVED']) ? $kalkulasi['diakui_min'] : $kalkulasi['interval_min'],
            'tarif_lembur'          => $kalkulasi['tarif_lembur'],
            'total_lembur'          => $kalkulasi['total_lembur'],
            'pic_id'                => @app()->request->pic_id ?? ($existing->pic_id ?? auth()->user()->id),
        ]);

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = $existing->m_comp_id ?? 1;
        }

        $targetKary = !empty($newArrayData['m_kary_id']) ? \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->first() : null;
        if (!empty($newArrayData['m_dir_id']) && $targetKary && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
            return [
                "model" => $model,
                "data"  => $arrayData,
                "errors" => ["Company pengajuan lembur harus sesuai dengan Company karyawan terpilih."],
            ];
        }
        if (empty($newArrayData['m_dir_id']) && $targetKary && !empty($targetKary->m_dir_id)) {
            $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
        }

        // Validasi Scope Respo Aktif saat Update Lembur
        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."],
                ];
            }

            $authKaryId = (int) ($authUser->m_kary_id ?? 0);
            $authUserId = (int) ($authUser->id ?? 0);
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);

            if ($existing) {
                $existKary = !empty($existing->m_kary_id) ? \DB::table('m_kary')->where('id', $existing->m_kary_id)->first() : null;
                $existDirId = $existing->m_dir_id ?? ($existKary ? $existKary->m_dir_id : null);
                $existDivId = $existKary ? $existKary->m_divisi_id : null;
                $existDeptId = $existKary ? $existKary->m_dept_id : null;
                $isExistDirectSub = $authKaryId > 0 && $existKary && (int) ($existKary->atasan_id ?? 0) === $authKaryId;

                if (!$isHrdOrAdmin) {
                    $isExistOwner = ($authKaryId > 0 && (int) ($existing->m_kary_id ?? 0) === $authKaryId)
                        || ($authUserId > 0 && (int) ($existing->creator_id ?? 0) === $authUserId);
                    if (!$isExistOwner && !$isExistDirectSub) {
                        return [
                            "model" => $model,
                            "data"  => $arrayData,
                            "errors" => ["Anda tidak diizinkan mengubah transaksi lembur milik karyawan lain."],
                        ];
                    }
                }

                if (!$isExistDirectSub && !$respoCore->matchesActiveCompanyScope($existDirId, $existDivId, $existDeptId, $authUser)) {
                    return [
                        "model" => $model,
                        "data"  => $arrayData,
                        "errors" => ["Tidak diizinkan mengubah transaksi lembur di luar scope responsibility aktif."],
                    ];
                }
            }

            $effDirId = $newArrayData['m_dir_id'] ?? ($targetKary ? $targetKary->m_dir_id : null);
            $targetDivId = $targetKary ? $targetKary->m_divisi_id : null;
            $targetDeptId = $targetKary ? $targetKary->m_dept_id : null;
            $isTargetDirectSub = $authKaryId > 0 && $targetKary && (int) ($targetKary->atasan_id ?? 0) === $authKaryId;

            if (!$isHrdOrAdmin && $targetKary) {
                $isTargetSelf = $authKaryId > 0 && (int) $targetKary->id === $authKaryId;
                if (!$isTargetSelf && !$isTargetDirectSub) {
                    return [
                        "model" => $model,
                        "data"  => $arrayData,
                        "errors" => ["Anda hanya dapat mengubah lembur untuk diri sendiri atau bawahan langsung."],
                    ];
                }
            }

            if (!$isTargetDirectSub && !$respoCore->matchesActiveCompanyScope($effDirId, $targetDivId, $targetDeptId, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Karyawan atau Perusahaan/Cabang berada di luar scope responsibility aktif."],
                ];
            }
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData,
        ];
    }

    public function createAfter($model, $arrayData, $metaData, $id = null)
    {
        if (app()->request->header("Source") == "mobile") {
            $app = $this->createAppTicket($model->id);  
        }
    }

    public function updateAfterTransaction( $newdata, $olddata, $data, $meta )
    {
        if (!empty($newdata['id']) && !empty($newdata['tanggal'])) {
            \DB::table('generate_approval')
                ->where('trx_table', 't_lembur')
                ->where('trx_id', $newdata['id'])
                ->update([
                    'trx_date' => date('Y-m-d', strtotime($newdata['tanggal']))
                ]);
            \DB::table('generate_approval_log')
                ->where('trx_table', 't_lembur')
                ->where('trx_id', $newdata['id'])
                ->update([
                    'trx_date' => date('Y-m-d', strtotime($newdata['tanggal']))
                ]);
        }

        if (app()->request->header("Source") == "mobile") {
            $app = $this->createAppTicket($newdata['id']);  
        }
    }
    
    

    // public function onRetrieved($model){
    //     // inject pic name
    //     if (app()->request->header("Source") == "mobile" || app()->request->detail) {
    //         $model['pic.nama_lengkap'] = m_kary::where('id',$model['pic.m_kary_id'])->pluck('nama_lengkap')->first();
    //     }
    // }

    public function custom_send_approval()
    {
        $id = req("id");
        $trx = $this->find($id);
        if (!$trx) {
            return $this->helper->customResponse("Data transaksi lembur tidak ditemukan", 404);
        }

        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return $this->helper->customResponse("Responsibility aktif tidak ditemukan atau sedang nonaktif.", 403);
            }

            $uid = (int) ($authUser->id ?? 0);
            $userKaryId = (int) ($authUser->m_kary_id ?? 0);
            $kary = !empty($trx->m_kary_id) ? \DB::table('m_kary')->where('id', $trx->m_kary_id)->first() : null;

            $isOwnerOrCreator = ($userKaryId > 0 && (int) ($trx->m_kary_id ?? 0) === $userKaryId)
                || ($uid > 0 && (int) ($trx->creator_id ?? 0) === $uid)
                || ($uid > 0 && (int) ($trx->pic_id ?? 0) === $uid);
            $isDirectAtasan = ($userKaryId > 0 && $kary && (int) ($kary->atasan_id ?? 0) === $userKaryId);
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $inScope = $respoCore->matchesActiveCompanyScope(
                $trx->m_dir_id ?? ($kary->m_dir_id ?? null),
                $kary->m_divisi_id ?? null,
                $kary->m_dept_id ?? null,
                $authUser
            );

            if (!$isOwnerOrCreator && !$isDirectAtasan && !($isHrdOrAdmin && $inScope)) {
                return $this->helper->customResponse("Anda tidak memiliki akses untuk mengirim approval transaksi lembur ini.", 403);
            }
        }

        $app = $this->createAppTicket($id);
        if (!$app) {
            return $this->helper->customResponse(
                "Terjadi kesalahan, coba kembali nanti",
                400
            );
        }

        if (app()->request->header("Source") != "mobile") {
            $spd = t_lembur::find($id);
            if ($spd) {
                $spd->update([
                    "status" => "IN APPROVAL",
                ]);
            }
        }

        return $this->helper->customResponse(
            "Permintaan approval berhasil dibuat"
        );
    }

    private function createAppTicket($id)
    {
        $trx = $this->find($id);
        if (!$trx) return false;

        // pengajuan lembur untuk bawahan 
        // dengan deteksi kolom pic_id terisi dan pic_id bukan user nya sendiri
        if(@$trx->pic_id != auth()->user()->id){
            $app_name = "APPROVAL LEMBUR UNTUK BAWAHAN";
        }
        $trxDate = !empty($trx->tanggal) ? date('Y-m-d', strtotime($trx->tanggal)) : Date("Y-m-d");
        $conf = [
            "app_name" => @$app_name ?? "APPROVAL LEMBUR", 
            "trx_id" => $trx->id,
            "trx_table" => $this->getTable(),
            "trx_name" => "Pengajuan Lembur",
            "form_name" => "t_lembur",
            "trx_nomor" => $trx->nomor,
            "trx_date" => $trxDate,
            "trx_creator_id" => $trx->creator_id,
            "m_kary_id" => $trx->m_kary_id,
        ];

        $app = $this->helper->approvalCreateTicket($conf);
        if ($app) {
            // Pastikan log 'MENGAJUKAN' tercatat di generate_approval_log
            $gApp = \DB::table('generate_approval')
                ->where('trx_table', $this->getTable())
                ->where('trx_id', $trx->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($gApp) {
                $hasPengajuanLog = \DB::table('generate_approval_log')
                    ->where('trx_table', $this->getTable())
                    ->where('trx_id', $trx->id)
                    ->where('action_type', 'MENGAJUKAN')
                    ->exists();

                if (!$hasPengajuanLog) {
                    $timestamp = $trx->created_at ?? date('Y-m-d H:i:s');
                    $creatorUserId = $trx->creator_id ?? (auth()->user()->id ?? null);
                    \DB::table('generate_approval_log')->insert([
                        'nomor'                     => $gApp->nomor,
                        'generate_approval_id'      => $gApp->id,
                        'generate_approval_det_id'  => null,
                        'trx_id'                    => $gApp->trx_id,
                        'trx_table'                 => $gApp->trx_table,
                        'trx_name'                  => $gApp->trx_name,
                        'trx_nomor'                 => $gApp->trx_nomor,
                        'trx_date'                  => $gApp->trx_date,
                        'trx_creator_id'            => $creatorUserId,
                        'action_type'               => 'MENGAJUKAN',
                        'action_user_id'            => $creatorUserId,
                        'creator_id'                => $creatorUserId,
                        'action_at'                 => $timestamp,
                        'action_note'               => 'Pengajuan Lembur diajukan',
                        'created_at'                => $timestamp,
                        'updated_at'                => $timestamp,
                    ]);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    public function custom_post($request)
    {
        $id = is_object($request) ? ($request->id ?? (method_exists($request, 'input') ? $request->input('id') : null)) : ($request['id'] ?? null);
        $data = $this->find($id);
        if (!$data) {
            return response()->json(["message" => "Data not found"], 404);
        }

        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return response()->json(["message" => "Responsibility aktif tidak ditemukan atau sedang nonaktif."], 403);
            }

            $kary = !empty($data->m_kary_id) ? \DB::table('m_kary')->where('id', $data->m_kary_id)->first() : null;
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $inScope = $respoCore->matchesActiveCompanyScope(
                $data->m_dir_id ?? ($kary->m_dir_id ?? null),
                $kary->m_divisi_id ?? null,
                $kary->m_dept_id ?? null,
                $authUser
            );
            if (!$isHrdOrAdmin || !$inScope) {
                return response()->json(["message" => "Anda tidak memiliki akses untuk memposting transaksi lembur ini."], 403);
            }
        }

        if ($data->status === "DRAFT") {
            // Change the status to post
            $data->update([
                "status" => "POSTED",
            ]);
            // $data->status = 'POSTED';
            // $data->save();
            return response()->json([
                "message" => 'DRAFT status changed to "POSTED"',
            ]);
        } else {
            // If the status is not draft, return a message
            return response()->json(
                ["message" => 'POSTED status is not "DRAFT"'],
                400
            );
        }
    }

    public function custom_progress($req)
    {
        \DB::beginTransaction();

        try {
            $appId = $req->id;
            $generateApproval = \DB::table('generate_approval')->where('id', $appId)->first();
            if (!$generateApproval) {
                // Jika ID yang dikirimkan adalah trx_id
                $generateApproval = \DB::table('generate_approval')
                    ->where('trx_table', $this->getTable())
                    ->where('trx_id', $appId)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($generateApproval) {
                    $appId = $generateApproval->id;
                }
            }

            // Pastikan jika ada tiket approval yang lebih baru untuk transaksi ini, gunakan tiket terbaru
            if ($generateApproval) {
                $latestGenApp = \DB::table('generate_approval')
                    ->where('trx_table', $this->getTable())
                    ->where('trx_id', $generateApproval->trx_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestGenApp && $latestGenApp->id != $generateApproval->id) {
                    $generateApproval = $latestGenApp;
                    $appId = $latestGenApp->id;
                }
            }

            if (!$generateApproval) {
                return $this->helper->customResponse("Data approval tidak ditemukan", 404);
            }

            $userAuth = auth()->user();
            $respoCore = getCore('Respo');
            $isSuperadmin = $userAuth ? $respoCore->isUserSuperadmin($userAuth) : false;
            $uid = (int) ($userAuth->id ?? 0);
            $userKaryId = (int) ($userAuth->m_kary_id ?? 0);

            $trx = $this->find($generateApproval->trx_id);
            if (!$trx) {
                return $this->helper->customResponse("Data transaksi lembur tidak ditemukan", 404);
            }

            $appType = $req->type ?? 'APPROVED';
            $appNote = $req->note ?? '-';

            if ($appType === 'REVISED') {
                $cleanedNote = trim(strval($req->note ?? ''));
                if (empty($cleanedNote) || $cleanedNote === '-' || strtolower($cleanedNote) === 'revised') {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Catatan approval wajib diisi saat memilih revisi',
                        'errors'  => ['catatan' => ['Catatan approval wajib diisi saat memilih revisi']]
                    ], 422);
                }
            }

            // Dapatkan seluruh slot approval yang belum selesai (is_done = false)
            $undoneDetails = \DB::table('generate_approval_det')
                ->leftJoin('m_role', 'm_role.id', '=', 'generate_approval_det.m_role_id')
                ->where('generate_approval_det.generate_approval_id', $generateApproval->id)
                ->where('generate_approval_det.is_done', false)
                ->select('generate_approval_det.*', 'm_role.name as role_name')
                ->orderBy('generate_approval_det.urutan_level', 'asc')
                ->orderBy('generate_approval_det.id', 'asc')
                ->get();

            if ($undoneDetails->isEmpty()) {
                return $this->helper->customResponse("Semua tahap approval untuk data ini sudah selesai.", 400);
            }

            // Cek apakah user yang login adalah karyawan pemohon lembur itu sendiri
            $isApplicant = ($userKaryId > 0 && (int) $trx->m_kary_id === $userKaryId)
                        || ($uid > 0 && (int) $trx->creator_id === $uid);

            if ($isApplicant && !$isSuperadmin) {
                return $this->helper->customResponse("Anda tidak dapat melakukan persetujuan (approval) terhadap pengajuan lembur Anda sendiri.", 403);
            }

            // Identifikasi metadata scope & relasi Atasan (m_kary.atasan_id & last_approve_id bebas company scope)
            $ticketMeta = $respoCore->getApprovalTicketScopeMetadata('t_lembur', $trx->id, $generateApproval);
            $atasanKaryId = $ticketMeta['atasan_kary_id'];
            $inCompanyScope = $userAuth
                ? $respoCore->matchesActiveCompanyScope($ticketMeta['m_dir_id'], $ticketMeta['m_divisi_id'], $ticketMeta['m_dept_id'], $userAuth)
                : false;

            $isDirectAtasan = ($atasanKaryId && $userKaryId > 0 && (int) $atasanKaryId === $userKaryId)
                || (!empty($generateApproval->last_approve_id) && $uid > 0 && (int) $generateApproval->last_approve_id === $uid);

            // Identifikasi Role & HRD melalui Core Respo
            $userRoleIds = $userAuth ? $respoCore->getEffectiveRoleIds($userAuth) : [];
            $userRoleNames = $userAuth ? $respoCore->getEffectiveRoleNames($userAuth, true) : [];

            $hasAtasanRole = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $hasAtasanRole = true;
                    break;
                }
            }

            // Direct atasan bebas company scope; fallback role 'Atasan' hanya berlaku dalam scope & tanpa mapped supervisor
            $isAtasan = $isDirectAtasan || ($inCompanyScope && $hasAtasanRole && empty($ticketMeta['has_mapped_supervisor']));

            // HRD wajib sesuai active Respo company scope
            $isHrd = $inCompanyScope && $userAuth && $respoCore->isUserHrd($userAuth);

            // Cek slot explicit default_user_id (bebas scope) & role stage-2 in-scope
            $explicitSlotStage1 = $uid > 0 ? $undoneDetails->first(function($d) use ($uid) {
                return !empty($d->default_user_id) && (int) $d->default_user_id === $uid
                    && ((int) $d->urutan_level === 1 || stripos(strtolower($d->role_name ?? ''), 'atasan') !== false);
            }) : null;

            $explicitSlotStage2 = $uid > 0 ? $undoneDetails->first(function($d) use ($uid, $respoCore) {
                return !empty($d->default_user_id) && (int) $d->default_user_id === $uid
                    && ((int) $d->urutan_level >= 2 || $respoCore->isHrdKeyword($d->role_name ?? ''));
            }) : null;

            $stage2RoleSlot = ($inCompanyScope && !empty($userRoleIds)) ? $undoneDetails->first(function($d) use ($userRoleIds) {
                if (!empty($d->default_user_id) || empty($d->m_role_id)) {
                    return false;
                }
                if (stripos(strtolower($d->role_name ?? ''), 'atasan') !== false) {
                    return false;
                }
                return (int) $d->urutan_level >= 2 && in_array((int) $d->m_role_id, $userRoleIds, true);
            }) : null;

            // Cek apakah user ini sudah pernah approve pada tiket ini sebelumnya (mencegah double-approve oleh 1 orang yang sama kecuali superadmin)
            $alreadyApprovedByMe = $uid > 0 && \DB::table('generate_approval_det')
                ->where('generate_approval_id', $generateApproval->id)
                ->where('action_user_id', $uid)
                ->where('action_type', 'APPROVED')
                ->where('is_done', true)
                ->exists();

            if ($alreadyApprovedByMe && !$isSuperadmin) {
                return $this->helper->customResponse("Anda sudah memberikan persetujuan sebelumnya untuk pengajuan ini. Tahap persetujuan berikutnya harus dilakukan oleh pihak lain (Atasan/HRD).", 403);
            }

            // Validasi peran approval berdasarkan tahap transaksi:
            // Tahap 1 (IN APPROVAL): Persetujuan Pengajuan Rencana oleh Atasan Langsung
            // Tahap 2 (HALF APPROVED): Persetujuan Realisasi Lembur oleh HRD
            if ($trx->status === 'IN APPROVAL') {
                if (!$isAtasan && !$explicitSlotStage1 && !$isSuperadmin) {
                    return $this->helper->customResponse("Persetujuan Tahap 1 (Pengajuan Rencana Lembur) hanya dapat dilakukan oleh Atasan langsung dari karyawan.", 403);
                }
                if ($appType === 'APPROVED' && (empty($appNote) || $appNote === '-')) {
                    $appNote = 'Persetujuan Pengajuan Rencana Lembur (Tahap 1 - Atasan)';
                }
            } elseif ($trx->status === 'HALF APPROVED') {
                if (!$isHrd && !$explicitSlotStage2 && !$stage2RoleSlot && !$isSuperadmin) {
                    return $this->helper->customResponse("Persetujuan Tahap 2 (Realisasi Lembur) hanya dapat dilakukan oleh HRD pada cakupan company terkait.", 403);
                }
                // Sebelum HRD approve realisasi, sinkronkan otomatis data realisasi dari checkout absensi
                $this->sinkronkanRealisasiDariAbsen($trx);
                $trx = $this->find($generateApproval->trx_id);

                if ($appType === 'APPROVED' && (empty($appNote) || $appNote === '-')) {
                    $appNote = 'Persetujuan Realisasi Lembur (Tahap 2 - HRD)';
                }
            }

            // Tentukan target slot yang diproses oleh user secara fleksibel + Respo-aware
            $targetSlot = null;

            // 0. Cek jika slot pada tahap aktif ditugaskan spesifik ke default_user_id (bebas scope)
            if ($trx->status === 'IN APPROVAL' && $explicitSlotStage1) {
                $targetSlot = $explicitSlotStage1;
            } elseif ($trx->status === 'HALF APPROVED' && $explicitSlotStage2) {
                $targetSlot = $explicitSlotStage2;
            } elseif ($uid > 0 && $trx->status !== 'IN APPROVAL' && $trx->status !== 'HALF APPROVED') {
                $targetSlot = $undoneDetails->first(function($d) use ($uid) {
                    return !empty($d->default_user_id) && (int) $d->default_user_id === $uid;
                });
            }

            // 1. Jika pada tahap 1 (IN APPROVAL) dan Atasan langsung / Superadmin
            if (!$targetSlot && $trx->status === 'IN APPROVAL' && ($isAtasan || $isSuperadmin)) {
                $targetSlot = $undoneDetails->first(function($d) {
                    if (!empty($d->default_user_id)) {
                        return false;
                    }
                    return (int) $d->urutan_level === 1 || stripos(strtolower($d->role_name ?? ''), 'atasan') !== false;
                });
            }

            // 2. Jika pada tahap 2 (HALF APPROVED) dan HRD in-scope / Superadmin
            if (!$targetSlot && $trx->status === 'HALF APPROVED' && ($isHrd || $isSuperadmin)) {
                $targetSlot = $undoneDetails->first(function($d) use ($respoCore) {
                    if (!empty($d->default_user_id)) {
                        return false;
                    }
                    $rnLower = strtolower($d->role_name ?? '');
                    return (int) $d->urutan_level >= 2 || $respoCore->isHrdKeyword($rnLower);
                });
            }

            // 3. Fallback pencocokan role dalam active company scope (sesuai tahap)
            if (!$targetSlot && $trx->status === 'HALF APPROVED' && $stage2RoleSlot) {
                $targetSlot = $stage2RoleSlot;
            }

            // 4. Fallback Superadmin: ambil slot pertama yang tersedia
            if (!$targetSlot && $isSuperadmin) {
                $targetSlot = $undoneDetails->first();
            }

            // Jika tidak memenuhi kriteria approver manapun
            if (!$targetSlot) {
                return $this->helper->customResponse("Anda tidak memiliki akses untuk melakukan approval ini. Hanya Atasan dari karyawan bersangkutan, HRD pada company terkait, atau Superadmin yang berhak.", 403);
            }

            $timestamp = date('Y-m-d H:i:s');

            // Tandai slot target menjadi done
            \DB::table('generate_approval_det')->where('id', $targetSlot->id)->update([
                'action_at' => $timestamp,
                'action_type' => $appType,
                'action_user_id' => $userAuth->id,
                'action_note' => $appNote,
                'is_done' => true,
            ]);

            // Log riwayat approval
            \DB::table('generate_approval_log')->insert([
                'nomor'                     => $generateApproval->nomor,
                'generate_approval_id'      => $generateApproval->id,
                'generate_approval_det_id'  => $targetSlot->id,
                'trx_id'                    => $generateApproval->trx_id,
                'trx_table'                 => $generateApproval->trx_table,
                'trx_name'                  => $generateApproval->trx_name,
                'trx_nomor'                 => $generateApproval->trx_nomor,
                'trx_date'                  => $generateApproval->trx_date,
                'trx_creator_id'            => $generateApproval->trx_creator_id,
                'action_type'               => $appType,
                'action_user_id'            => $userAuth->id,
                'creator_id'                => $userAuth->id,
                'action_at'                 => $timestamp,
                'action_note'               => $appNote,
                'created_at'                => $timestamp,
                'updated_at'                => $timestamp,
            ]);

            // Jika ditolak atau minta revisi -> batalkan seluruh proses
            if ($appType === 'REJECTED' || $appType === 'REVISED') {
                \DB::table('generate_approval')->where('id', $generateApproval->id)->update([
                    'status' => $appType,
                    'last_approve_id' => null,
                    'last_approve_det_id' => null,
                    'updated_at' => $timestamp
                ]);
                \DB::table('t_lembur')->where('id', $generateApproval->trx_id)->update(['status' => $appType]);
            } else {
                // Jika APPROVED -> cek tahap approval saat ini
                if ($trx->status === 'IN APPROVAL') {
                    // Tahap 1 selesai (Approved oleh Atasan) -> ubah status menjadi HALF APPROVED
                    $nextSlotId = \DB::table('generate_approval_det')
                        ->where('generate_approval_id', $generateApproval->id)
                        ->where('is_done', false)
                        ->orderBy('urutan_level', 'asc')
                        ->orderBy('id', 'asc')
                        ->value('id');

                    \DB::table('generate_approval')->where('id', $generateApproval->id)->update([
                        'status' => 'HALF APPROVED',
                        'last_approve_id' => null,
                        'last_approve_det_id' => $nextSlotId,
                        'updated_at' => $timestamp
                    ]);
                    \DB::table('t_lembur')->where('id', $generateApproval->trx_id)->update(['status' => 'HALF APPROVED']);
                } else {
                    // Tahap 2 selesai (Approved oleh HRD terhadap realisasi lembur) -> ubah status menjadi APPROVED
                    $this->sinkronkanRealisasiDariAbsen($trx);

                    // Tandai semua sisa slot approval menjadi done
                    \DB::table('generate_approval_det')
                        ->where('generate_approval_id', $generateApproval->id)
                        ->where('is_done', false)
                        ->update([
                            'action_at' => $timestamp,
                            'action_type' => 'APPROVED',
                            'action_user_id' => $userAuth->id,
                            'action_note' => $appNote,
                            'is_done' => true,
                        ]);

                    \DB::table('generate_approval')->where('id', $generateApproval->id)->update([
                        'status' => 'APPROVED',
                        'last_approve_id' => null,
                        'last_approve_det_id' => null,
                        'updated_at' => $timestamp
                    ]);
                    \DB::table('t_lembur')->where('id', $generateApproval->trx_id)->update(['status' => 'APPROVED']);
                }
            }

            \DB::commit();

            return $this->helper->customResponse("Proses approval berhasil diproses");
        } catch (\Throwable $e) {
            \DB::rollback();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            ], 500);
        }
    }

    public function custom_detail($req)
    {
        $id = $req->id ?? 0;
        try {
            $trxId = $req->trx_id ?? null;

            $app = null;
            if ($trxId) {
                $app = \DB::table('generate_approval')
                    ->where('trx_table', $this->getTable())
                    ->where('trx_id', $trxId)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($app) {
                    $id = $app->id;
                }
            } else {
                $app = \DB::table('generate_approval')->where('id', $id)->first();
                if (!$app) {
                    $app = \DB::table('generate_approval')
                        ->where('trx_table', $this->getTable())
                        ->where('trx_id', $id)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($app) {
                        $id = $app->id;
                    }
                }
            }

            // Jika tiket ditemukan tapi ada tiket approval yang lebih baru untuk transaksi ini (misalnya setelah revisi & diajukan kembali)
            if ($app) {
                $latestApp = \DB::table('generate_approval')
                    ->where('trx_table', $this->getTable())
                    ->where('trx_id', $app->trx_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestApp && $latestApp->id != $app->id) {
                    $app = $latestApp;
                    $id = $latestApp->id;
                }
            }

            if (!$app) {
                $dummyApp = (object)[
                    'id' => $id,
                    'trx_id' => $id,
                    'tahap_saat_ini' => 0,
                    'tahap_total' => 0,
                ];
                $dummyData = (object)[
                    'approval' => $dummyApp,
                    'approval_log' => [],
                    'trx' => null,
                    'can_approve' => false,
                    'is_atasan' => false,
                    'is_hrd' => false,
                    'is_superadmin' => false,
                    'already_approved' => false
                ];
                return response()->json([
                    'status' => false,
                    'message' => 'Approval tidak ditemukan',
                    'data' => $dummyData,
                    'approval' => $dummyApp,
                    'approval_log' => [],
                    'trx' => null,
                    'can_approve' => false
                ], 404);
            }

            $data = $this->helper->approvalDetail($id);

            $userAuth = auth()->user();
            $respoCore = getCore('Respo');
            $userId = (int) ($userAuth ? $userAuth->id : 0);
            $userKaryId = (int) ($userAuth ? ($userAuth->m_kary_id ?? 0) : 0);
            $isSuperadmin = $userAuth ? $respoCore->isUserSuperadmin($userAuth) : false;

            $trx = \DB::table('t_lembur')->where('id', $app->trx_id)->first();
            if ($trx) {
                // Sinkronkan realisasi otomatis dari absensi jika status sudah HALF APPROVED atau APPROVED
                $statusTrx = strtoupper(trim($trx->status ?? ''));
                if (in_array($statusTrx, ['HALF APPROVED', 'APPROVED'])) {
                    $this->sinkronkanRealisasiDariAbsen($trx);
                    $trx = \DB::table('t_lembur')->where('id', $app->trx_id)->first();
                }
                $data->trx = $trx;
                if (!empty($trx->status) && isset($data->approval)) {
                    $data->approval->status = $trx->status;
                }
            }

            $ticketMeta = $trx
                ? $respoCore->getApprovalTicketScopeMetadata('t_lembur', $trx->id, $app)
                : $respoCore->getApprovalTicketScopeMetadata('t_lembur', $app->trx_id, $app);

            $inCompanyScope = $userAuth
                ? $respoCore->matchesActiveCompanyScope($ticketMeta['m_dir_id'], $ticketMeta['m_divisi_id'], $ticketMeta['m_dept_id'], $userAuth)
                : false;

            $atasanKaryId = $ticketMeta['atasan_kary_id'];
            $isDirectAtasan = ($atasanKaryId && $userKaryId > 0 && (int) $atasanKaryId === $userKaryId)
                || (!empty($app->last_approve_id) && $userId > 0 && (int) $app->last_approve_id === $userId);

            // Cek slot yang masih tersisa
            $undoneDetails = \DB::table('generate_approval_det')
                ->leftJoin('m_role', 'm_role.id', '=', 'generate_approval_det.m_role_id')
                ->where('generate_approval_det.generate_approval_id', $app->id)
                ->where('generate_approval_det.is_done', false)
                ->select('generate_approval_det.*', 'm_role.name as role_name')
                ->orderBy('generate_approval_det.urutan_level', 'asc')
                ->orderBy('generate_approval_det.id', 'asc')
                ->get();

            $alreadyApprovedByMe = false;
            if ($userId) {
                $alreadyApprovedByMe = \DB::table('generate_approval_det')
                    ->where('generate_approval_id', $app->id)
                    ->where('action_user_id', $userId)
                    ->where('action_type', 'APPROVED')
                    ->where('is_done', true)
                    ->exists();
            }

            $userRoleIds = $userAuth ? $respoCore->getEffectiveRoleIds($userAuth) : [];
            $userRoleNames = $userAuth ? $respoCore->getEffectiveRoleNames($userAuth, true) : [];

            // Cek role Atasan (hanya fallback dalam company scope jika atasan langsung belum terpetakan ke user)
            $hasAtasanRole = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $hasAtasanRole = true;
                    break;
                }
            }

            $isAtasan = $isDirectAtasan || ($inCompanyScope && $hasAtasanRole && empty($ticketMeta['has_mapped_supervisor']));

            // Identifikasi HRD melalui Core Respo (wajib sesuai active company scope)
            $isHrd = $inCompanyScope && $userAuth && $respoCore->isUserHrd($userAuth);

            // Cek slot explicit default_user_id (bebas scope) & role stage-2 in-scope
            $explicitSlotStage1 = $userId > 0 ? $undoneDetails->first(function($d) use ($userId) {
                return !empty($d->default_user_id) && (int) $d->default_user_id === $userId
                    && ((int) $d->urutan_level === 1 || stripos(strtolower($d->role_name ?? ''), 'atasan') !== false);
            }) : null;

            $explicitSlotStage2 = $userId > 0 ? $undoneDetails->first(function($d) use ($userId, $respoCore) {
                return !empty($d->default_user_id) && (int) $d->default_user_id === $userId
                    && ((int) $d->urutan_level >= 2 || $respoCore->isHrdKeyword($d->role_name ?? ''));
            }) : null;

            $stage2RoleSlot = ($inCompanyScope && !empty($userRoleIds)) ? $undoneDetails->first(function($d) use ($userRoleIds) {
                if (!empty($d->default_user_id) || empty($d->m_role_id)) {
                    return false;
                }
                if (stripos(strtolower($d->role_name ?? ''), 'atasan') !== false) {
                    return false;
                }
                return (int) $d->urutan_level >= 2 && in_array((int) $d->m_role_id, $userRoleIds, true);
            }) : null;

            $canApprove = false;
            if ($undoneDetails->isNotEmpty() && $trx && in_array($trx->status, ['IN APPROVAL', 'HALF APPROVED'])) {
                if ($isSuperadmin) {
                    $canApprove = true;
                } elseif (!$alreadyApprovedByMe) {
                    if ($trx->status === 'IN APPROVAL') {
                        // Tahap 1: Atasan langsung atau explicit slot tahap 1
                        if ($isAtasan || $explicitSlotStage1) {
                            $canApprove = true;
                        }
                    } elseif ($trx->status === 'HALF APPROVED') {
                        // Tahap 2: HRD / role stage-2 in-scope atau explicit slot tahap 2
                        if ($isHrd || $explicitSlotStage2 || $stage2RoleSlot) {
                            $canApprove = true;
                        }
                    }
                }
            }

            // Cek apakah user yang melihat adalah karyawan pemohon lembur itu sendiri
            $isApplicant = ($userKaryId > 0 && !empty($trx->m_kary_id) && (int) $trx->m_kary_id === $userKaryId)
                        || ($userId > 0 && !empty($trx->creator_id) && (int) $trx->creator_id === $userId);

            if ($isApplicant && !$isSuperadmin) {
                // User yang melihat adalah pemohon/karyawan:
                // HIDE REALISASI dan matikan tombol approve untuk dirinya sendiri
                $isAtasan = false;
                $isHrd = false;
                $canApprove = false;
                $isApproverOrAdmin = false;
                if ($data->trx) {
                    $data->trx->jam_mulai_realisasi = null;
                    $data->trx->jam_selesai_realisasi = null;
                    $data->trx->realisasi_min = null;
                    $data->trx->diakui_min = null;
                    $data->trx->total_lembur = null;
                }
            } else {
                $isApproverOrAdmin = ($isSuperadmin || $isAtasan || $isHrd || (bool) $explicitSlotStage1 || (bool) $explicitSlotStage2 || (bool) $stage2RoleSlot);
            }

            // Jika status masih DRAFT / IN APPROVAL (menunggu persetujuan Atasan), realisasi belum diisi
            if ($trx && !in_array(strtoupper(trim($trx->status ?? '')), ['HALF APPROVED', 'APPROVED'])) {
                if ($data->trx) {
                    $data->trx->jam_mulai_realisasi = null;
                    $data->trx->jam_selesai_realisasi = null;
                    $data->trx->realisasi_min = null;
                }
            }

            $data->can_approve = $canApprove;
            $data->is_atasan = $isAtasan;
            $data->is_hrd = $isHrd;
            $data->is_superadmin = $isSuperadmin;
            $data->is_approver_or_admin = $isApproverOrAdmin;
            $data->already_approved = $alreadyApprovedByMe;

            return response()->json([
                'status'  => true,
                'message' => 'OK',
                'data'    => $data,
                'approval' => $data->approval ?? null,
                'approval_log' => $data->approval_log ?? [],
                'trx' => $data->trx ?? null,
                'can_approve' => $data->can_approve ?? false,
                'is_atasan' => $data->is_atasan ?? false,
                'is_hrd' => $data->is_hrd ?? false,
                'is_superadmin' => $data->is_superadmin ?? false,
                'is_approver_or_admin' => $data->is_approver_or_admin ?? false,
                'already_approved' => $data->already_approved ?? false,
            ], 200);
        } catch (\Throwable $e) {
            $dummyApp = (object)[
                'id' => $id,
                'trx_id' => $id,
                'tahap_saat_ini' => 0,
                'tahap_total' => 0,
            ];
            $dummyData = (object)[
                'approval' => $dummyApp,
                'approval_log' => [],
                'trx' => null,
                'can_approve' => false,
                'is_atasan' => false,
                'is_hrd' => false,
                'is_superadmin' => false,
                'already_approved' => false
            ];
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
                'data'    => $dummyData,
                'approval' => $dummyApp,
                'approval_log' => [],
                'trx' => null,
                'can_approve' => false
            ], 500);
        }
    }
    public function custom_log($req)
    {
        $id = $req->id ?? 0;
        $trxTable = $this->getTable(); // 't_lembur'

        // 1. Identifikasi transaksi t_lembur dan generate_approval
        $trx = \DB::table($trxTable)->where('id', $id)->first();
        $app = null;

        if ($trx) {
            $trxId = $trx->id;
            $app = \DB::table('generate_approval')
                ->where('trx_table', $trxTable)
                ->where('trx_id', $trxId)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $app = \DB::table('generate_approval')
                ->where('id', $id)
                ->where('trx_table', $trxTable)
                ->first();
            $trxId = $app ? $app->trx_id : $id;
            if ($trxId) {
                $trx = \DB::table($trxTable)->where('id', $trxId)->first();
            }
        }

        $appId = $app ? $app->id : 0;

        // 2. Pastikan log aksi 'MENGAJUKAN' ada di riwayat pengajuan surat
        if ($trx && $appId) {
            $hasPengajuanLog = \DB::table('generate_approval_log')
                ->where('trx_table', $trxTable)
                ->where('trx_id', $trx->id)
                ->where('action_type', 'MENGAJUKAN')
                ->exists();

            if (!$hasPengajuanLog) {
                $timestamp = $trx->created_at ?? ($app->created_at ?? date('Y-m-d H:i:s'));
                $creatorUserId = $trx->creator_id ?? ($app->creator_id ?? (auth()->user()->id ?? null));

                \DB::table('generate_approval_log')->insert([
                    'nomor'                     => $app->nomor,
                    'generate_approval_id'      => $app->id,
                    'generate_approval_det_id'  => null,
                    'trx_id'                    => $trx->id,
                    'trx_table'                 => $trxTable,
                    'trx_name'                  => $app->trx_name ?? 'Pengajuan Lembur',
                    'trx_nomor'                 => $trx->nomor,
                    'trx_date'                  => $trx->tanggal ?? ($app->trx_date ?? date('Y-m-d')),
                    'trx_creator_id'            => $creatorUserId,
                    'action_type'               => 'MENGAJUKAN',
                    'action_user_id'            => $creatorUserId,
                    'creator_id'                => $creatorUserId,
                    'action_at'                 => $timestamp,
                    'action_note'               => 'Pengajuan Lembur diajukan',
                    'created_at'                => $timestamp,
                    'updated_at'                => $timestamp,
                ]);
            }
        }

        // 3. Ambil seluruh riwayat log approval secara urut
        $data = \DB::table('generate_approval_log')
            ->selectRaw("generate_approval_log.*, (select u.name from default_users u where u.id = generate_approval_log.action_user_id) as action_user")
            ->where(function($q) use ($trxTable, $trxId, $appId) {
                $q->where(function($sq) use ($trxTable, $trxId) {
                    $sq->where('trx_table', $trxTable)
                       ->where('trx_id', $trxId);
                });
                if ($appId) {
                    $q->orWhere('generate_approval_id', $appId);
                }
            })
            ->orderBy('action_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response($data);
    }

    public function custom_sync_realisasi($req)
    {
        $id = $req->id ?? $req->trx_id;
        if (!$id) {
            return $this->helper->customResponse("ID transaksi lembur diperlukan", 400);
        }

        $trx = $this->find($id);
        if (!$trx) {
            return $this->helper->customResponse("Data transaksi lembur tidak ditemukan", 404);
        }

        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return $this->helper->customResponse("Responsibility aktif tidak ditemukan atau sedang nonaktif.", 403);
            }

            $uid = (int) ($authUser->id ?? 0);
            $userKaryId = (int) ($authUser->m_kary_id ?? 0);
            $isApplicant = ($userKaryId > 0 && (int) ($trx->m_kary_id ?? 0) === $userKaryId)
                || ($uid > 0 && (int) ($trx->creator_id ?? 0) === $uid);
            if ($isApplicant) {
                return $this->helper->customResponse("Pemohon tidak diperkenankan mengakses atau menyinkronkan data realisasi lembur.", 403);
            }

            $kary = !empty($trx->m_kary_id) ? \DB::table('m_kary')->where('id', $trx->m_kary_id)->first() : null;
            $isDirectAtasan = ($userKaryId > 0 && $kary && (int) ($kary->atasan_id ?? 0) === $userKaryId);
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $inScope = $respoCore->matchesActiveCompanyScope(
                $trx->m_dir_id ?? ($kary->m_dir_id ?? null),
                $kary->m_divisi_id ?? null,
                $kary->m_dept_id ?? null,
                $authUser
            );

            if (!$isDirectAtasan && !($isHrdOrAdmin && $inScope)) {
                return $this->helper->customResponse("Anda tidak memiliki akses untuk menyinkronkan realisasi lembur ini.", 403);
            }
        }

        $synced = $this->sinkronkanRealisasiDariAbsen($trx);

        return response()->json([
            'status'  => true,
            'message' => 'Realisasi lembur berhasil disinkronkan dari absensi',
            'data'    => $synced,
        ]);
    }
}
