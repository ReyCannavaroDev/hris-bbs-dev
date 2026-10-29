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
        $approval = generate_approval_log::where('trx_table', 't_lembur')->where('trx_id', $row['id']) ->orderBy('created_at', 'desc')->value('action_note');
        return array_merge( $row, [
            'approval_note' => $approval ?? ''
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
                    ->where('status', 'PROGRESS')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($generateApproval) {
                    $appId = $generateApproval->id;
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

            // Validasi apakah user berhak approve: Superadmin atau Atasan dari karyawan di lembur
            $atasanKaryId = \DB::table('m_kary')->where('id', $trx->m_kary_id)->value('atasan_id');
            $isAtasan = ($atasanKaryId && $userAuth->m_kary_id && $atasanKaryId == $userAuth->m_kary_id);

            if (!$isSuperadmin && !$isAtasan) {
                return $this->helper->customResponse("Anda tidak memiliki akses untuk melakukan approval ini. Hanya atasan dari karyawan bersangkutan atau Superadmin yang dapat menyetujui/menolak/merevisi.", 403);
            }

            $conf = [
                "app_id" => $appId,
                "app_type" => $req->type, // APPROVED, REVISED, REJECTED,
                "app_note" => $req->note ?? '-', // alasan approve
            ];

            $app = $this->helper->approvalProgress($conf);
            if ($app->status) {
                $data = $this->find($app->trx_id);
                if ($app->finish) {
                    $data->update([
                        "status" => $req->type,
                    ]);
                } else {
                    if ($req->type != 'APPROVED') {
                        $data->update([
                            "status" => $req->type,
                        ]);
                    } else {
                        $data->update([
                            "status" => "IN APPROVAL",
                        ]);
                    }
                }
            }

            \DB::commit();

            return $this->helper->customResponse("Proses approval berhasil");
        } catch (\Exception $e) {
            \DB::rollback();

            return $this->helper->responseCatch($e);
        }
    }

    public function custom_detail($req)
    {
        $id = $req->id ?? 0;
        $trxId = $req->trx_id ?? null;

        $app = null;
        if ($trxId) {
            $app = \DB::table('generate_approval')
                ->where('trx_table', $this->getTable())
                ->where('trx_id', $trxId)
                ->where('status', 'PROGRESS')
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
                    ->where('status', 'PROGRESS')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($app) {
                    $id = $app->id;
                }
            }
        }

        if (!$app) {
            return $this->helper->customResponse("Approval tidak ditemukan", 404);
        }

        $data = $this->helper->approvalDetail($id);

        $userAuth = auth()->user();
        $isSuperadmin = (bool) ($userAuth->is_superadmin ?? false);

        $trx = \DB::table('t_lembur')->where('id', $app->trx_id)->first();
        $isAtasan = false;
        if ($trx && $trx->m_kary_id) {
            $atasanKaryId = \DB::table('m_kary')->where('id', $trx->m_kary_id)->value('atasan_id');
            if ($atasanKaryId && $userAuth->m_kary_id && $atasanKaryId == $userAuth->m_kary_id) {
                $isAtasan = true;
            }
        }

        $data->can_approve = ($isSuperadmin || $isAtasan);
        $data->is_atasan = $isAtasan;
        $data->is_superadmin = $isSuperadmin;

        return $this->helper->customResponse("OK", 200, $data);
    }
    public function custom_log($req)
    {
        $id = $req->id ?? 0;
        // Cek jika ID yang dikirim adalah generate_approval.id, dapatkan trx_id
        $app = \DB::table('generate_approval')->where('id', $id)->first();
        $trxId = $app ? $app->trx_id : $id;

        $conf = [
            "trx_id" => $trxId,
            "trx_table" => $this->getTable(),
        ];
        $data = $this->helper->approvalLog($conf);
        return response($data);
    }
}
