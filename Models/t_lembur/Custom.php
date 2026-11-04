<?php

namespace App\Models\CustomModels;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

    public $fileColumns = ["doc"];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function scopeFilter($model)
    {
     

        if(app()->request->header('Source') === 'mobile') {
            // $model->where
        }
        
        if (req("date_from") && req("date_to")) {
            return $model
                ->whereBetween("tanggal", [req("date_from"), req("date_to")])
                ->whereRaw(
                    "t_lembur.m_dir_id in(select u.m_dir_id from default_users u where u.id = ?)",
                    [auth()->user()->id ?? 0]
                );
        }
    }

    public function transformRowData( array $row )
    {
        $approval = generate_approval_log::where('trx_table', 't_lembur')->where('trx_id', $row['id'])->orderBy('created_at', 'desc')->value('action_note');
        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')->where('k.id', $row['m_kary_id'])->value('d.nama');
        }
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

        // 4. Cari Tarif Lembur dari m_lembur berdasarkan grading karyawan (MoM 3.J)
        $tarifLembur = 0;
        if ($mKaryId) {
            $kary = \DB::table('m_kary')->where('id', $mKaryId)->first();
            if ($kary) {
                $rate = \DB::table('m_lembur')
                    ->where('is_active', 1)
                    ->where(function($q) use ($kary) {
                        $q->where('grading_id', $kary->grading_id)
                          ->orWhereNull('grading_id');
                    })
                    ->orderByRaw("case when grading_id = " . intval($kary->grading_id ?? 0) . " then 0 else 1 end")
                    ->orderBy('id', 'desc')
                    ->first();
                if ($rate) {
                    $tarifLembur = floatval($rate->besaran);
                }
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

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $kalkulasi = $this->hitungDurasiDanTarif($arrayData);

        $newArrayData = array_merge($arrayData, [
            "nomor"                 => $this->helper->generateNomor("KODE LEMBUR"),
            "interval_min"          => $kalkulasi['interval_min'],
            "realisasi_min"         => $kalkulasi['realisasi_min'],
            "diakui_min"            => $kalkulasi['diakui_min'],
            "tarif_lembur"          => $kalkulasi['tarif_lembur'],
            "total_lembur"          => $kalkulasi['total_lembur'],
            "pic_id"                => @app()->request->pic_id ?? auth()->user()->id,
        ]);

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }
        if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
            $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
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

        $newArrayData  = array_merge( $arrayData,[
            'status'                => $status,
            'interval_min'          => $kalkulasi['interval_min'],
            'realisasi_min'         => $kalkulasi['realisasi_min'],
            'diakui_min'            => $kalkulasi['diakui_min'],
            'tarif_lembur'          => $kalkulasi['tarif_lembur'],
            'total_lembur'          => $kalkulasi['total_lembur'],
            'pic_id'                => @app()->request->pic_id ?? ($existing->pic_id ?? auth()->user()->id),
        ]);

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = $existing->m_comp_id ?? 1;
        }
        if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
            $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
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
        $app = $this->createAppTicket(req("id"));
        if (!$app) {
            return $this->helper->customResponse(
                "Terjadi kesalahan, coba kembali nanti",
                400
            );
        }

        if (app()->request->header("Source") != "mobile") {
            $spd = t_lembur::find(req("id"));
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
        $conf = [
            "app_name" => @$app_name ?? "APPROVAL LEMBUR", 
            "trx_id" => $trx->id,
            "trx_table" => $this->getTable(),
            "trx_name" => "Pengajuan Lembur",
            "form_name" => "t_lembur",
            "trx_nomor" => $trx->nomor,
            "trx_date" => Date("Y-m-d"),
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
        $data = $this->find($request->$id);
        if (!$data) {
            return response()->json(["message" => "Data not found"], 404);
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
            $isSuperadmin = (bool) ($userAuth->is_superadmin ?? false);

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
                ->get();

            if ($undoneDetails->isEmpty()) {
                return $this->helper->customResponse("Semua tahap approval untuk data ini sudah selesai.", 400);
            }

            // Identifikasi relasi Atasan (dari m_kary.atasan_id)
            $atasanKaryId = \DB::table('m_kary')->where('id', $trx->m_kary_id)->value('atasan_id');
            $isAtasan = ($atasanKaryId && !empty($userAuth->m_kary_id) && $atasanKaryId == $userAuth->m_kary_id);

            // Identifikasi Role user
            $userRoleIds = \DB::table('m_role_access')->where('user_id', $userAuth->id)->pluck('m_role_id')->toArray();
            $userRoleNames = \DB::table('m_role')->whereIn('id', $userRoleIds)->pluck('name')->map(function($n) {
                return strtolower($n);
            })->toArray();

            // Cek apakah user ber-role Atasan
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $isAtasan = true;
                    break;
                }
            }

            // Identifikasi HRD: via ROLE ATAU JABATAN (m_posisi) ATAU DIVISI (m_divisi)
            $isHrd = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'hr') !== false || stripos($rn, 'personalia') !== false || stripos($rn, 'sdm') !== false || stripos($rn, 'human capital') !== false) {
                    $isHrd = true;
                    break;
                }
            }

            // Jika belum terdeteksi dari role, periksa dari Jabatan (Posisi) dan Divisi Karyawan
            if (!$isHrd && !empty($userAuth->m_kary_id)) {
                $userKary = \DB::table('m_kary')
                    ->leftJoin('m_posisi', 'm_posisi.id', '=', 'm_kary.m_posisi_id')
                    ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                    ->where('m_kary.id', $userAuth->m_kary_id)
                    ->select('m_posisi.desc_kerja as posisi_nama', 'm_divisi.nama as divisi_nama')
                    ->first();
                if ($userKary) {
                    $posNama = strtolower($userKary->posisi_nama ?? '');
                    $divNama = strtolower($userKary->divisi_nama ?? '');
                    if (stripos($posNama, 'hr') !== false || stripos($posNama, 'personalia') !== false || stripos($posNama, 'sdm') !== false || stripos($posNama, 'human capital') !== false ||
                        stripos($divNama, 'hr') !== false || stripos($divNama, 'personalia') !== false || stripos($divNama, 'sdm') !== false || stripos($divNama, 'human capital') !== false) {
                        $isHrd = true;
                    }
                }
            }

            // Cek apakah user ini sudah pernah approve pada tiket ini sebelumnya (mencegah double-approve oleh 1 orang yang sama kecuali superadmin)
            $alreadyApprovedByMe = \DB::table('generate_approval_det')
                ->where('generate_approval_id', $generateApproval->id)
                ->where('action_user_id', $userAuth->id)
                ->where('action_type', 'APPROVED')
                ->where('is_done', true)
                ->exists();

            if ($alreadyApprovedByMe && !$isSuperadmin) {
                return $this->helper->customResponse("Anda sudah memberikan persetujuan sebelumnya untuk pengajuan ini. Tahap persetujuan kedua harus dilakukan oleh pihak lain (Atasan/HRD).", 403);
            }

            // Tentukan target slot yang diproses oleh user secara fleksibel / paralel
            $targetSlot = null;

            // 0. Cek jika slot ditugaskan spesifik ke default_user_id
            $targetSlot = $undoneDetails->first(function($d) use ($userAuth) {
                return !empty($d->default_user_id) && $d->default_user_id == $userAuth->id;
            });

            // 1. Jika Atasan langsung, cari slot Atasan (urutan_level 1 atau nama role atasan)
            if (!$targetSlot && $isAtasan) {
                $targetSlot = $undoneDetails->first(function($d) {
                    return $d->urutan_level == 1 || stripos(strtolower($d->role_name ?? ''), 'atasan') !== false;
                });
            }

            // 2. Jika HRD atau role cocok, cari slot HRD / urutan_level >= 2
            if (!$targetSlot && ($isHrd || !empty(array_intersect($userRoleIds, $undoneDetails->pluck('m_role_id')->toArray())))) {
                $targetSlot = $undoneDetails->first(function($d) use ($userRoleIds) {
                    $rnLower = strtolower($d->role_name ?? '');
                    $nameMatch = (stripos($rnLower, 'hr') !== false) || (stripos($rnLower, 'personalia') !== false) || (stripos($rnLower, 'sdm') !== false);
                    $roleIdMatch = in_array($d->m_role_id, $userRoleIds);
                    return $nameMatch || $roleIdMatch || $d->urutan_level >= 2;
                });
            }

            // 3. Jika status HALF APPROVED dan user bukan approver tahap 1, ambil slot level >= 2
            if (!$targetSlot && $trx->status === 'HALF APPROVED' && ($isHrd || !empty($userRoleIds))) {
                $targetSlot = $undoneDetails->first(function($d) {
                    return $d->urutan_level >= 2;
                });
            }

            // 4. Jika approver umum yang cocok dengan m_role_id slot
            if (!$targetSlot) {
                foreach ($undoneDetails as $d) {
                    if (in_array($d->m_role_id, $userRoleIds)) {
                        $targetSlot = $d;
                        break;
                    }
                }
            }

            // 5. Fallback Superadmin: ambil slot pertama yang tersedia
            if (!$targetSlot && $isSuperadmin) {
                $targetSlot = $undoneDetails->first();
            }

            // Jika tidak memenuhi kriteria approver manapun
            if (!$targetSlot) {
                return $this->helper->customResponse("Anda tidak memiliki akses untuk melakukan approval ini. Hanya Atasan dari karyawan bersangkutan, HRD, atau Superadmin yang berhak.", 403);
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
                // Jika APPROVED -> cek sisa slot yang belum selesai
                $remainingCount = \DB::table('generate_approval_det')
                    ->where('generate_approval_id', $generateApproval->id)
                    ->where('is_done', false)
                    ->count();

                if ($remainingCount > 0) {
                    // Baru 1 approval (masih ada sisa) -> ubah status transaksi menjadi HALF APPROVED
                    $nextSlotId = \DB::table('generate_approval_det')
                        ->where('generate_approval_id', $generateApproval->id)
                        ->where('is_done', false)
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
                    // Sudah 2 approval (semua slot selesai) -> ubah status transaksi menjadi APPROVED
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
            $userId = $userAuth ? $userAuth->id : 0;
            $userKaryId = $userAuth ? ($userAuth->m_kary_id ?? null) : null;
            $isSuperadmin = (bool) ($userAuth->is_superadmin ?? false);

            $trx = \DB::table('t_lembur')->where('id', $app->trx_id)->first();
            if ($trx && !empty($trx->status) && isset($data->approval)) {
                $data->approval->status = $trx->status;
            }
            $isAtasan = false;
            if ($trx && $trx->m_kary_id) {
                $atasanKaryId = \DB::table('m_kary')->where('id', $trx->m_kary_id)->value('atasan_id');
                if ($atasanKaryId && !empty($userKaryId) && $atasanKaryId == $userKaryId) {
                    $isAtasan = true;
                }
            }

            // Cek slot yang masih tersisa
            $undoneDetails = \DB::table('generate_approval_det')
                ->leftJoin('m_role', 'm_role.id', '=', 'generate_approval_det.m_role_id')
                ->where('generate_approval_det.generate_approval_id', $app->id)
                ->where('generate_approval_det.is_done', false)
                ->select('generate_approval_det.*', 'm_role.name as role_name')
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

            $userRoleIds = [];
            $userRoleNames = [];
            if ($userId) {
                $userRoleIds = \DB::table('m_role_access')->where('user_id', $userId)->pluck('m_role_id')->toArray();
                $userRoleNames = \DB::table('m_role')->whereIn('id', $userRoleIds)->pluck('name')->map(function($n) {
                    return strtolower($n);
                })->toArray();
            }

            // Cek role Atasan
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $isAtasan = true;
                    break;
                }
            }

            // Identifikasi HRD: via Role ATAU Jabatan/Divisi
            $isHrd = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'hr') !== false || stripos($rn, 'personalia') !== false || stripos($rn, 'sdm') !== false || stripos($rn, 'human capital') !== false) {
                    $isHrd = true;
                    break;
                }
            }
            if (!$isHrd && !empty($userKaryId)) {
                $userKary = \DB::table('m_kary')
                    ->leftJoin('m_posisi', 'm_posisi.id', '=', 'm_kary.m_posisi_id')
                    ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                    ->where('m_kary.id', $userKaryId)
                    ->select('m_posisi.desc_kerja as posisi_nama', 'm_divisi.nama as divisi_nama')
                    ->first();
                if ($userKary) {
                    $posNama = strtolower($userKary->posisi_nama ?? '');
                    $divNama = strtolower($userKary->divisi_nama ?? '');
                    if (stripos($posNama, 'hr') !== false || stripos($posNama, 'personalia') !== false || stripos($posNama, 'sdm') !== false || stripos($posNama, 'human capital') !== false ||
                        stripos($divNama, 'hr') !== false || stripos($divNama, 'personalia') !== false || stripos($divNama, 'sdm') !== false || stripos($divNama, 'human capital') !== false) {
                        $isHrd = true;
                    }
                }
            }

            $canApprove = false;
            if ($undoneDetails->isNotEmpty()) {
                if ($isSuperadmin) {
                    $canApprove = true;
                } elseif (!$alreadyApprovedByMe) {
                    // 1. Cek jika slot ditugaskan secara spesifik ke default_user_id
                    $hasAssignedSlot = $undoneDetails->contains(function($d) use ($userId) {
                        return !empty($d->default_user_id) && $d->default_user_id == $userId;
                    });

                    // 2. Cek jika slot Atasan masih belum selesai dan user adalah Atasan
                    $hasAtasanSlot = $undoneDetails->contains(function($d) {
                        return $d->urutan_level == 1 || stripos(strtolower($d->role_name ?? ''), 'atasan') !== false;
                    });

                    // 3. Cek jika role user cocok dengan salah satu slot yang belum selesai
                    $hasRoleMatch = !empty(array_intersect($userRoleIds, $undoneDetails->pluck('m_role_id')->toArray()));

                    // 4. Cek jika slot HRD / Level 2+ belum selesai dan user adalah HRD
                    $hasHrdSlot = $undoneDetails->contains(function($d) {
                        $rn = strtolower($d->role_name ?? '');
                        return $d->urutan_level >= 2 || stripos($rn, 'hr') !== false || stripos($rn, 'personalia') !== false || stripos($rn, 'sdm') !== false;
                    });

                    if ($hasAssignedSlot) {
                        $canApprove = true;
                    } elseif ($isAtasan && $hasAtasanSlot) {
                        $canApprove = true;
                    } elseif (($isHrd || $hasRoleMatch) && $hasHrdSlot) {
                        $canApprove = true;
                    } elseif ($trx && in_array($trx->status, ['HALF APPROVED', 'IN APPROVAL']) && ($isHrd || $hasRoleMatch || !empty($userRoleIds))) {
                        $canApprove = true;
                    }
                }
            }

            $data->can_approve = $canApprove;
            $data->is_atasan = $isAtasan;
            $data->is_hrd = $isHrd;
            $data->is_superadmin = $isSuperadmin;
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
}
