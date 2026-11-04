<?php

namespace App\Models\CustomModels;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class t_cuti extends \App\Models\BasicModels\t_cuti
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
    }

    public $fileColumns = [
        'attachment'
    ];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    private function parseCarbonDate($val)
    {
        if (!$val) return null;
        if ($val instanceof Carbon) return $val;
        $val = trim((string) $val);
        if (str_contains($val, '/')) {
            try {
                return Carbon::createFromFormat('d/m/Y', $val);
            } catch (\Exception $e) {}
        }
        try {
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function transformRowData( array $row )
    {
        $approval = generate_approval_log::where('trx_table', 't_cuti')->where('trx_id', $row['id']) ->orderBy('created_at', 'desc')->value('action_note');
        $row['approval_note'] = $approval ?? '-';
        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')->where('k.id', $row['m_kary_id'])->value('d.nama');
        }
        if (!empty($compNama)) {
            $row['m_dir.nama'] = $compNama;
        }

        foreach (['date_from', 'date_to'] as $col) {
            if (!empty($row[$col])) {
                try {
                    $row[$col] = Carbon::parse($row[$col])->format('d/m/Y');
                } catch (\Exception $e) {}
            }
        }

        return $row;
    }

    

    private function hitungHari($from , $to) {
        $dateFrom = $this->parseCarbonDate($from);
        $dateTo = $this->parseCarbonDate($to);
        if (!$dateFrom || !$dateTo) return 1;

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $tanggalMerah = \DB::table('m_libur_nasional')->pluck('tanggal')->toArray();
        $businessDays = 0;

        foreach ($period as $date) {
            if ($date->dayOfWeek != Carbon::SUNDAY && !in_array($date->format('Y-m-d'), $tanggalMerah)) {
                $businessDays++;
            }
        }
        return $businessDays;
    }

    public function custom_hitungHari($req) {
        $from = $req->date_from;
        $to = $req->date_to;
        if(app()->request->header("Source") == "mobile"){
            $dateFrom = Carbon::parse($from);
            $dateTo = Carbon::parse($to);
        }else{
             $dateFrom = Carbon::createFromFormat('d/m/Y', $from);
            $dateTo = Carbon::createFromFormat('d/m/Y', $to);
        }

        // Membuat rentang tanggal antara date_from dan date_to
        $period = CarbonPeriod::create($dateFrom, $dateTo);

        // Menghitung jumlah hari kerja
        $tanggalMerah = \DB::table('m_libur_nasional')->pluck('tanggal')->toArray();
        $businessDays = 0;

        foreach ($period as $date) {
            if ($date->dayOfWeek != Carbon::SUNDAY && !in_array($date->format('Y-m-d'), $tanggalMerah)) {
                $businessDays++;
            }
        }
        
        return $businessDays;
    }

    private function hitungMenit($from, $to) {
        $dateFrom = Carbon::parse($from);
        $dateTo = Carbon::parse($to);

        // Calculate the difference in minutes for datetime range
        $minutesDifference = $dateFrom->diffInMinutes($dateTo);

        return $minutesDifference;
    }

    private function validateCutiBusinessRules($arrayData, $interval, $interval_min, $id = null)
    {
        $karyId = $arrayData['m_kary_id'] ?? null;
        if (!$karyId) {
            return response([
                "status"  => "error",
                "message" => "Akun ini tidak tersambung dengan data karyawan manapun!",
                "errors"  => ["m_kary_id" => ["Akun ini tidak tersambung dengan data karyawan manapun!"]]
            ], 422);
        }

        // Ambil data m_general untuk alasan & tipe cuti
        $alasanObj = !empty($arrayData['alasan_id']) ? \DB::table('m_general')->where('id', $arrayData['alasan_id'])->first() : null;
        $tipeObj   = !empty($arrayData['tipe_cuti_id']) ? \DB::table('m_general')->where('id', $arrayData['tipe_cuti_id'])->first() : null;

        $checkStr = strtolower(trim(
            ($alasanObj->value ?? '') . ' ' .
            ($alasanObj->code ?? '') . ' ' .
            ($tipeObj->value ?? '') . ' ' .
            ($tipeObj->code ?? '') . ' ' .
            ($arrayData['keterangan'] ?? '')
        ));

        $isP24 = (!empty($arrayData['time_from']) && !empty($arrayData['time_to'])) || str_contains($checkStr, 'p24');
        $isSakit = str_contains($checkStr, 'sakit');
        $isMenikah = str_contains($checkStr, 'nikah') || str_contains($checkStr, 'menikah');
        $isMeninggal = str_contains($checkStr, 'meninggal') || str_contains($checkStr, 'kematian') || str_contains($checkStr, 'duka');

        if ($isP24) {
            // Izin parsial jam-jaman (P24) tidak dibatasi 3 hari harian
            return null;
        }

        $durasiHari = (int) ($interval ?? 1);

        // 1. Izin Khusus: Sakit (SKD) - maksimal 3 hari kerja & wajib upload surat dokter
        if ($isSakit) {
            if ($durasiHari > 3) {
                return response([
                    "status"  => "error",
                    "message" => "Pengajuan izin sakit dengan Surat Keterangan Dokter (SKD) maksimal 3 hari kerja per pengajuan.",
                    "errors"  => ["interval" => ["Pengajuan izin sakit dengan Surat Keterangan Dokter (SKD) maksimal 3 hari kerja per pengajuan."]]
                ], 422);
            }
            if (empty($arrayData['attachment'])) {
                return response([
                    "status"  => "error",
                    "message" => "Pengajuan izin sakit wajib melampirkan Surat Keterangan Dokter (SKD).",
                    "errors"  => ["attachment" => ["Pengajuan izin sakit wajib melampirkan Surat Keterangan Dokter (SKD)."]]
                ], 422);
            }
            return null; // Izin khusus sakit tidak memotong kuota cuti reguler
        }

        // 2. Izin Khusus: Menikah - maksimal 3 hari kerja
        if ($isMenikah) {
            if ($durasiHari > 3) {
                return response([
                    "status"  => "error",
                    "message" => "Pengajuan cuti menikah maksimal 3 hari kerja.",
                    "errors"  => ["interval" => ["Pengajuan cuti menikah maksimal 3 hari kerja."]]
                ], 422);
            }
            return null; // Izin khusus menikah tidak memotong kuota cuti reguler
        }

        // 3. Izin Khusus: Keluarga Meninggal - maksimal 3 hari kerja
        if ($isMeninggal) {
            if ($durasiHari > 3) {
                return response([
                    "status"  => "error",
                    "message" => "Pengajuan cuti duka cita / keluarga meninggal maksimal 3 hari kerja.",
                    "errors"  => ["interval" => ["Pengajuan cuti duka cita / keluarga meninggal maksimal 3 hari kerja."]]
                ], 422);
            }
            return null; // Izin khusus duka cita tidak memotong kuota cuti reguler
        }

        // 4. Cuti Reguler / Tahunan (MoM Poin 5: Maksimal 3 hari berturut-turut & potong kuota 12 hari)
        if ($durasiHari > 3) {
            return response([
                "status"  => "error",
                "message" => "Pengambilan cuti reguler maksimal 3 hari berturut-turut per pengajuan.",
                "errors"  => ["interval" => ["Pengambilan cuti reguler maksimal 3 hari berturut-turut per pengajuan."]]
            ], 422);
        }

        // Cek sisa kuota cuti tahunan karyawan
        $kary = \DB::table('m_kary')->where('id', $karyId)->first();
        if ($kary) {
            $sisaCuti = (int) ($kary->cuti_sisa_reguler ?? 12);
            if ($id) {
                $oldCuti = \DB::table('t_cuti')->where('id', $id)->first();
                if ($oldCuti && in_array($oldCuti->status, ['APPROVED', 'POSTED'])) {
                    $sisaCuti += (int) ($oldCuti->interval ?? 0);
                }
            }
            if ($durasiHari > $sisaCuti) {
                return response([
                    "status"  => "error",
                    "message" => "Sisa kuota cuti tahunan tidak mencukupi (sisa kuota: {$sisaCuti} hari, pengajuan: {$durasiHari} hari).",
                    "errors"  => ["interval" => ["Sisa kuota cuti tahunan tidak mencukupi (sisa kuota: {$sisaCuti} hari, pengajuan: {$durasiHari} hari)."]]
                ], 422);
            }
        }

        return null;
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {   
        if(!isset($arrayData['m_kary_id'])){
            return $this->helper->customResponse(
                "Akun ini tidak Tersambung dengan data karyawan manapun !",
                422
            );
        }

        $interval = null;
        $interval_min = null;

        if (isset($arrayData['date_from']) && isset($arrayData['date_to']) && (!isset($arrayData['time_from']) || $arrayData['time_from'] === null || $arrayData['time_from'] === '') && (!isset($arrayData['time_to']) || $arrayData['time_to'] === null || $arrayData['time_to'] === '')) {
            $interval = @$this->hitungHari($arrayData['date_from'], $arrayData['date_to']) ?? 1;
        }

        if (isset($arrayData['time_from']) && isset($arrayData['time_to'])) {
            $interval_min = @$this->hitungMenit($arrayData['time_from'], $arrayData['time_to']) ?? 1;
        }

        // Validasi Aturan Bisnis Cuti & Dispensasi MoM Poin 5
        $validationErr = $this->validateCutiBusinessRules($arrayData, $interval, $interval_min, $id);
        if ($validationErr) {
            return $validationErr;
        }

        $newArrayData = array_merge($arrayData, [
            "nomor" => $this->helper->generateNomor("KODE CUTI"),
            "interval" => $interval,
            "interval_min" => $interval_min
        ]);
        if (app()->request->header("Source") == "mobile") {
            $newArrayData = array_merge($newArrayData, [
                "status" => "IN APPROVAL",
                "interval" => @$interval,
                "interval_min" => @$interval_min
            ]);
        }
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }
        if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
            $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
        }

        foreach (['date_from', 'date_to'] as $dCol) {
            if (!empty($newArrayData[$dCol])) {
                $parsed = $this->parseCarbonDate($newArrayData[$dCol]);
                if ($parsed) {
                    $newArrayData[$dCol] = $parsed->format('Y-m-d');
                }
            }
        }

        return [
            "model" => $model,
            "data" => $newArrayData,
            // "errors" => ['error1']
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {

        $interval = null;
        $interval_min = null;

        if (isset($arrayData['date_from']) && isset($arrayData['date_to']) && (!isset($arrayData['time_from']) || $arrayData['time_from'] === null || $arrayData['time_from'] === '') && (!isset($arrayData['time_to']) || $arrayData['time_to'] === null || $arrayData['time_to'] === '')) {
            $interval = @$this->hitungHari($arrayData['date_from'], $arrayData['date_to']) ?? 1;
        }

        if (isset($arrayData['time_from']) && isset($arrayData['time_to'])) {
            $interval_min = @$this->hitungMenit($arrayData['time_from'], $arrayData['time_to']) ?? 1;
        }

        // Validasi Aturan Bisnis Cuti & Dispensasi MoM Poin 5
        $validationErr = $this->validateCutiBusinessRules($arrayData, $interval, $interval_min, $id);
        if ($validationErr) {
            return $validationErr;
        }

        if (app()->request->header("Source") == "mobile") {
            $data = t_cuti::where('id', $id)->first();
            if($data["status"] === 'REVISED'){
                $status = 'IN APPROVAL';
            }
        }
        $newArrayData  = array_merge( $arrayData,[
            'status' => @$status ?? @$arrayData['status'],
            "interval" =>@ $interval ?? @$arrayData['interval'],
            "interval_min" => @$interval_min ?? @$arrayData['interval_min'] 
        ]);
        if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
            $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
        }

        foreach (['date_from', 'date_to'] as $dCol) {
            if (!empty($newArrayData[$dCol])) {
                $parsed = $this->parseCarbonDate($newArrayData[$dCol]);
                if ($parsed) {
                    $newArrayData[$dCol] = $parsed->format('Y-m-d');
                }
            }
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
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
            $spd = t_cuti::find(req("id"));
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

    public function public_tes($req)
    {
        $id = "4";
        $data = t_cuti::where('id', (int)$id)->first();
        return $data;
    }

    // public function updateAfter( $model, $arrayData, $metaData, $id=null )
    // {
    //     if (app()->request->header("Source") == "mobile") {
    //         $data = t_cuti::where('id', 4)->first();
    //         if(@$data) { 
    //             dd($data);
    //             }
    //         if(@$data->status === 'REVISED'){
    //             $status = 'IN APPROVAL';
    //             $app = $this->createAppTicket($id);
    //              if (!$app) {
    //                 return $this->helper->customResponse(
    //                     "Approval tidak tersedia untuk atribut user anda",
    //                     400
    //                 );
    //             }
    //         }
    //     }
    //     $newArrayData = $arrayData;
    //     if(@$status){
    //         $newArrayData  = array_merge( $newArrayData,[
    //             'status' => $status 
    //         ]);
    //     }

    //     return [
    //         "model"  => $model,
    //         "data"   => $newArrayData,
    //         // "errors" => ['error1']
    //     ];
    // }
    

    private function createAppTicket($id)
    {
        $trx = $this->find($id);

        $conf = [
            "app_name" => "APPROVAL CUTI",
            "trx_id" => $trx->id,
            "trx_table" => $this->getTable(),
            "trx_name" => "Pengajuan Cuti",
            "form_name" => "t_cuti",
            "trx_nomor" => $trx->nomor,
            "trx_date" => Date("Y-m-d"),
            "trx_creator_id" => $trx->creator_id,
        ];

        $app = $this->helper->approvalCreateTicket($conf);
        if ($app) {
            return true;
        } else {
            return false;
        }
    }

    // public function custom_post ($request) {
    //     $data = t_cuti::find($request->$id);
    //     if (!$data) {
    //         return response()->json(['message' => 'Data not found'], 404);
    //     }
    //     if ($data->status === 'DRAFT') {
    //         // Change the status to post
    //         $data->update([
    //             "status" => "POSTED"
    //             ]);
    //         // $data->status = 'POSTED';
    //         // $data->save();
    //         return response()->json(['message' => 'DRAFT status changed to "POSTED"']);
    //     } else {
    //         // If the status is not draft, return a message
    //         return response()->json(['message' => 'POSTED status is not "DRAFT"'], 400);
    //     }
    // }

    

    public function custom_progress($req)
    {
        // Start a database transaction
        \DB::beginTransaction();

        try {
            $conf = [
                "app_id" => $req->id,
                "app_type" => $req->type, // APPROVED, REVISED, REJECTED,
                "app_note" => $req->note, // alasan approve
            ];

            $app = $this->helper->approvalProgress($conf, true);
            if ($app->status) {
                $data = $this->find($app->trx_id);
                if ($app->finish) {
                    $data->update([
                        "status" => $req->type
                    ]);

                    // Jika approval APPROVED, periksa apakah cuti reguler untuk potong kuota di m_kary
                    if ($req->type === 'APPROVED' && !empty($data->m_kary_id) && $data->interval > 0) {
                        $alasanObj = !empty($data->alasan_id) ? \DB::table('m_general')->where('id', $data->alasan_id)->first() : null;
                        $tipeObj   = !empty($data->tipe_cuti_id) ? \DB::table('m_general')->where('id', $data->tipe_cuti_id)->first() : null;
                        $checkStr  = strtolower(trim(($alasanObj->value ?? '') . ' ' . ($tipeObj->value ?? '') . ' ' . ($data->keterangan ?? '')));

                        $isKhusus = str_contains($checkStr, 'sakit') ||
                                    str_contains($checkStr, 'nikah') ||
                                    str_contains($checkStr, 'menikah') ||
                                    str_contains($checkStr, 'meninggal') ||
                                    str_contains($checkStr, 'kematian') ||
                                    str_contains($checkStr, 'duka') ||
                                    str_contains($checkStr, 'p24') ||
                                    (!empty($data->time_from) && !empty($data->time_to));

                        if (!$isKhusus) {
                            // Cuti Reguler: potong kuota sisa cuti reguler karyawan
                            \DB::table('m_kary')
                                ->where('id', $data->m_kary_id)
                                ->update([
                                    'cuti_sisa_reguler' => \DB::raw("GREATEST(0, COALESCE(cuti_sisa_reguler, 12) - {$data->interval})")
                                ]);
                        }
                    }
                } else {
                    $data->update([
                        "status" => "IN APPROVAL",
                    ]);
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
        $id = $req->id ?? 66;
        $data = $this->helper->approvalDetail($id);
        return $this->helper->customResponse("OK", 200, $data);
    }
    public function custom_log($req)
    {
        $conf = [
            "trx_id" => $req->id ?? 0,
            "trx_table" => $this->getTable(),
        ];
        $data = $this->helper->approvalLog($conf);
        return response($data);
    }
}
