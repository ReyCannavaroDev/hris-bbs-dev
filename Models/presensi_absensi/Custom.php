<?php

namespace App\Models\CustomModels;
use Illuminate\Support\Facades\Validator;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class presensi_absensi extends \App\Models\BasicModels\presensi_absensi
{
    private $helper;
    private $nullableColumnCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->casts['checkin_on_scope'] = 'boolean';
        $this->casts['checkout_on_scope'] = 'boolean';
        // Hindari join ke last_editor_id agar baris baru dengan last_editor_id NULL tidak gugur oleh INNER JOIN otomatis generator
        $this->joins = [
            "m_comp.id=presensi_absensi.m_comp_id",
            "default_users.id=presensi_absensi.default_user_id",
            "default_users.id=presensi_absensi.creator_id"
        ];
    }

    public $fileColumns = [];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    private function isColumnNullable($column)
    {
        if (array_key_exists($column, $this->nullableColumnCache)) {
            return $this->nullableColumnCache[$column];
        }

        $nullable = false;
        try {
            $colInfo = \DB::selectOne(
                "SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1",
                [$this->table, $column]
            );
            if ($colInfo && strtoupper((string)($colInfo->is_nullable ?? '')) === 'YES') {
                $nullable = true;
            }
        } catch (\Throwable $e) {
            $nullable = false;
        }

        $this->nullableColumnCache[$column] = $nullable;
        return $nullable;
    }

    private function normalizeFotoValue($val)
    {
        if ($val === null) {
            return null;
        }
        $foto = trim((string)$val);
        if ($foto === '' || strtolower($foto) === 'null' || $foto === '/') {
            return null;
        }

        $baseUrl = rtrim(url(''), '/');
        if ($baseUrl !== '' && stripos($foto, $baseUrl) === 0) {
            $foto = ltrim(substr($foto, strlen($baseUrl)), '/');
        }

        if (app()->bound('request') && app()->request) {
            $reqHost = rtrim((string)app()->request->getSchemeAndHttpHost(), '/');
            if ($reqHost !== '' && stripos($foto, $reqHost) === 0) {
                $foto = ltrim(substr($foto, strlen($reqHost)), '/');
            }
        }

        if (preg_match('#^https?://[^/]+/(uploads/.+)$#i', $foto, $m)) {
            $foto = $m[1];
        }

        $foto = trim($foto);
        return ($foto === '' || $foto === '/') ? null : $foto;
    }

    private function normalizeDateValue($val)
    {
        if ($val === null) {
            return null;
        }
        $str = trim((string)$val);
        if ($str === '' || $str === '-' || strtolower($str) === 'null') {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $str, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTimeValue($val)
    {
        if ($val === null) {
            return null;
        }
        $str = trim((string)$val);
        if ($str === '' || strtolower($str) === 'null') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $str, $m)) {
            $h = (int)$m[1];
            $i = (int)$m[2];
            $s = isset($m[3]) ? (int)$m[3] : 0;
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $i, $s);
            }
        }

        return null;
    }

    private function toBooleanValue($val, $default = false)
    {
        if ($val === null || $val === '') {
            return $default;
        }
        $parsed = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }

    public function onRetrieved($model)
    {
        if (!$model || !is_object($model)) {
            return;
        }

        // Transformasi hanya untuk GET agar PUT/POST/DELETE tidak tercemar atribut virtual company_name atau URL foto
        if (app()->bound('request') && app()->request && !app()->request->isMethod('GET')) {
            return;
        }

        $baseUrl = rtrim(url(''), '/');
        if (!empty($model->checkout_foto) && substr($model->checkout_foto, 0, 4) !== 'http') {
            $model->checkout_foto = $baseUrl.'/'.ltrim($model->checkout_foto, '/');
        } elseif (empty($model->checkout_foto) || $model->checkout_foto === $baseUrl.'/' || $model->checkout_foto === '/') {
            $model->checkout_foto = null;
        }

        if (!empty($model->checkin_foto) && substr($model->checkin_foto, 0, 4) !== 'http') {
            $model->checkin_foto = $baseUrl.'/'.ltrim($model->checkin_foto, '/');
        } elseif (empty($model->checkin_foto) || $model->checkin_foto === $baseUrl.'/' || $model->checkin_foto === '/') {
            $model->checkin_foto = null;
        }

        $user = \DB::table('default_users')
            ->leftJoin('m_dir', 'm_dir.id', '=', 'default_users.m_dir_id')
            ->leftJoin('m_kary', 'm_kary.id', '=', 'default_users.m_kary_id')
            ->leftJoin('m_dir as kary_dir', 'kary_dir.id', '=', 'm_kary.m_dir_id')
            ->where('default_users.id', $model->default_user_id)
            ->select('m_dir.nama as dir_nama', 'kary_dir.nama as kary_dir_nama')
            ->first();
        $model->company_name = $user ? ($user->dir_nama ?? $user->kary_dir_nama ?? '-') : '-';

        if (method_exists($model, 'syncOriginal')) {
            $model->syncOriginal();
        }
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $allowedColumns = $this->createable ?: $this->columns;
        $cleanData = [];
        foreach ($arrayData as $key => $val) {
            if (in_array($key, $allowedColumns)) {
                $cleanData[$key] = $val;
            }
        }

        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at'], $cleanData['company_name']);

        foreach (['checkin_foto', 'checkout_foto'] as $fotoCol) {
            $fotoVal = array_key_exists($fotoCol, $cleanData) ? $this->normalizeFotoValue($cleanData[$fotoCol]) : null;
            if ($fotoVal === null) {
                $cleanData[$fotoCol] = $this->isColumnNullable($fotoCol) ? null : '';
            } else {
                $cleanData[$fotoCol] = $fotoVal;
            }
        }

        $nullableCols = [
            'checkout_time',
            'checkout_lat',
            'checkout_long',
            'checkout_address',
            'checkout_region',
            'catatan_out',
            'catatan_in'
        ];
        foreach ($nullableCols as $col) {
            if (array_key_exists($col, $cleanData) && (trim((string)$cleanData[$col]) === '' || $cleanData[$col] === 'null')) {
                $cleanData[$col] = null;
            }
        }

        $cleanData['checkin_on_scope'] = $this->toBooleanValue($cleanData['checkin_on_scope'] ?? false, false);

        if (empty($cleanData['checkout_time'])) {
            $cleanData['checkout_time'] = null;
            $cleanData['checkout_on_scope'] = null;
        } else {
            $cleanData['checkout_time'] = $this->normalizeTimeValue($cleanData['checkout_time']);
            $cleanData['checkout_on_scope'] = $this->toBooleanValue($cleanData['checkout_on_scope'] ?? false, false);
        }

        if (!empty($cleanData['checkin_time'])) {
            $cleanData['checkin_time'] = $this->normalizeTimeValue($cleanData['checkin_time']) ?? $cleanData['checkin_time'];
        }

        if (!empty($cleanData['checkin_time']) && !empty($cleanData['checkout_time'])) {
            $cleanData['status'] = 'ATTEND';
        } elseif (!empty($cleanData['checkin_time']) && empty($cleanData['checkout_time'])) {
            $cleanData['status'] = 'WORKING';
        }

        if (!empty($cleanData['tanggal'])) {
            $parsedTanggal = $this->normalizeDateValue($cleanData['tanggal']);
            if ($parsedTanggal !== null) {
                $cleanData['tanggal'] = $parsedTanggal;
            }
        }

        if (isset($cleanData['default_user_id']) && $cleanData['default_user_id'] !== '') {
            $cleanData['default_user_id'] = (int)$cleanData['default_user_id'];
        }

        if (empty($cleanData['m_comp_id'])) {
            $cleanData['m_comp_id'] = auth()->user()->m_comp_id ?? 1;
        } else {
            $cleanData['m_comp_id'] = (int)$cleanData['m_comp_id'];
        }

        return [
            "model" => $model,
            "data"  => $cleanData
        ];
    }

    public function updateBefore($model, $arrayData, $metaData, $id = null)
    {
        $allowedColumns = $this->updateable ?: $this->columns;
        $cleanData = [];
        foreach ($arrayData as $key => $val) {
            if (in_array($key, $allowedColumns)) {
                $cleanData[$key] = $val;
            }
        }

        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at'], $cleanData['creator_id'], $cleanData['company_name']);

        if (is_object($model)) {
            if (method_exists($model, 'offsetUnset')) {
                $model->offsetUnset('company_name');
            } elseif (isset($model->company_name)) {
                unset($model->company_name);
            }
        }

        foreach (['checkin_foto', 'checkout_foto'] as $fotoCol) {
            if (array_key_exists($fotoCol, $cleanData)) {
                $fotoVal = $this->normalizeFotoValue($cleanData[$fotoCol]);
                if ($fotoVal === null) {
                    $cleanData[$fotoCol] = $this->isColumnNullable($fotoCol) ? null : '';
                } else {
                    $cleanData[$fotoCol] = $fotoVal;
                }
            }
        }

        $nullableCols = [
            'checkout_time',
            'checkout_lat',
            'checkout_long',
            'checkout_address',
            'checkout_region',
            'catatan_out',
            'catatan_in'
        ];
        foreach ($nullableCols as $col) {
            if (array_key_exists($col, $cleanData) && (trim((string)$cleanData[$col]) === '' || $cleanData[$col] === 'null')) {
                $cleanData[$col] = null;
            }
        }

        if (array_key_exists('checkin_on_scope', $cleanData)) {
            $cleanData['checkin_on_scope'] = $this->toBooleanValue($cleanData['checkin_on_scope'], false);
        }

        if (array_key_exists('checkout_time', $cleanData) || array_key_exists('checkout_on_scope', $cleanData)) {
            if (empty($cleanData['checkout_time'])) {
                $cleanData['checkout_time'] = null;
                $cleanData['checkout_on_scope'] = null;
            } else {
                $cleanData['checkout_time'] = $this->normalizeTimeValue($cleanData['checkout_time']) ?? $cleanData['checkout_time'];
                $cleanData['checkout_on_scope'] = $this->toBooleanValue($cleanData['checkout_on_scope'] ?? false, false);
            }
        }

        if (!empty($cleanData['checkin_time'])) {
            $cleanData['checkin_time'] = $this->normalizeTimeValue($cleanData['checkin_time']) ?? $cleanData['checkin_time'];
        }

        if (!empty($cleanData['checkin_time']) && !empty($cleanData['checkout_time'])) {
            $cleanData['status'] = 'ATTEND';
        } elseif (!empty($cleanData['checkin_time']) && empty($cleanData['checkout_time'])) {
            $cleanData['status'] = 'WORKING';
        }

        if (!empty($cleanData['tanggal'])) {
            $parsedTanggal = $this->normalizeDateValue($cleanData['tanggal']);
            if ($parsedTanggal !== null) {
                $cleanData['tanggal'] = $parsedTanggal;
            }
        }

        if (isset($cleanData['default_user_id']) && $cleanData['default_user_id'] !== '') {
            $cleanData['default_user_id'] = (int)$cleanData['default_user_id'];
        }

        if (array_key_exists('m_comp_id', $cleanData) && empty($cleanData['m_comp_id'])) {
            $cleanData['m_comp_id'] = auth()->user()->m_comp_id ?? 1;
        }

        return [
            "model" => $model,
            "data"  => $cleanData
        ];
    }

    public function custom_save_web($req)
    {
        $rawId = $req->input('id') ?? $req->query('id');
        $isUpdate = !empty($rawId);
        $recordId = $isUpdate ? (int)$rawId : null;

        $existing = null;
        if ($isUpdate) {
            $existing = \DB::table('presensi_absensi')->where('id', $recordId)->first();
            if (!$existing) {
                return $this->helper->customResponse("Data absensi dengan ID #{$recordId} tidak ditemukan", 404);
            }
        }

        $rawDefaultUserId = $req->input('default_user_id');
        if (($rawDefaultUserId === null || $rawDefaultUserId === '') && $existing) {
            $rawDefaultUserId = $existing->default_user_id;
        }

        $rawTanggal = $req->input('tanggal');
        if (($rawTanggal === null || trim((string)$rawTanggal) === '') && $existing) {
            $rawTanggal = $existing->tanggal;
        }
        $normalizedTanggal = $this->normalizeDateValue($rawTanggal);

        $rawCheckinTime = $req->input('checkin_time');
        $normalizedCheckinTime = $this->normalizeTimeValue($rawCheckinTime);

        $rawCheckoutTime = $req->input('checkout_time');
        $hasCheckoutTimeText = $rawCheckoutTime !== null && trim((string)$rawCheckoutTime) !== '' && strtolower(trim((string)$rawCheckoutTime)) !== 'null';
        $normalizedCheckoutTime = $hasCheckoutTimeText ? $this->normalizeTimeValue($rawCheckoutTime) : null;

        $toStrOrNull = function ($val) {
            if ($val === null) {
                return null;
            }
            $s = trim((string)$val);
            return ($s === '' || strtolower($s) === 'null') ? null : $s;
        };

        $valInput = [
            'default_user_id'   => is_numeric($rawDefaultUserId) ? (int)$rawDefaultUserId : $rawDefaultUserId,
            'tanggal'           => $normalizedTanggal,
            'checkin_time'      => $normalizedCheckinTime,
            'checkin_lat'       => $toStrOrNull($req->input('checkin_lat')),
            'checkin_long'      => $toStrOrNull($req->input('checkin_long')),
            'checkin_address'   => $toStrOrNull($req->input('checkin_address')),
            'checkin_region'    => $toStrOrNull($req->input('checkin_region')),
            'checkout_lat'      => $toStrOrNull($req->input('checkout_lat')),
            'checkout_long'     => $toStrOrNull($req->input('checkout_long')),
            'checkout_address'  => $toStrOrNull($req->input('checkout_address')),
            'checkout_region'   => $toStrOrNull($req->input('checkout_region')),
        ];

        $validator = Validator::make($valInput, [
            'default_user_id'   => 'required|integer',
            'tanggal'           => 'required|date_format:Y-m-d',
            'checkin_time'      => 'required',
            'checkin_lat'       => 'required|max:255',
            'checkin_long'      => 'required|max:255',
            'checkin_address'   => 'required|max:255',
            'checkin_region'    => 'required|max:255',
            'checkout_lat'      => 'nullable|max:255',
            'checkout_long'     => 'nullable|max:255',
            'checkout_address'  => 'nullable|max:255',
            'checkout_region'   => 'nullable|max:255',
        ], [
            'default_user_id.required' => 'Karyawan wajib dipilih',
            'default_user_id.integer'  => 'ID Karyawan tidak valid',
            'tanggal.required'         => 'Tanggal absensi wajib diisi',
            'tanggal.date_format'      => 'Format tanggal absensi tidak valid',
            'checkin_time.required'    => 'Waktu Check In wajib diisi dengan format JJ:MM',
            'checkin_lat.required'     => 'Latitude Check In wajib diisi (pilih Lokasi atau Pin Peta)',
            'checkin_long.required'    => 'Longitude Check In wajib diisi (pilih Lokasi atau Pin Peta)',
            'checkin_address.required' => 'Alamat Check In wajib diisi',
            'checkin_address.max'      => 'Alamat Check In maksimal 255 karakter',
            'checkin_region.required'  => 'Lokasi Check In wajib dipilih',
            'checkin_region.max'       => 'Lokasi Check In maksimal 255 karakter',
            'checkout_address.max'     => 'Alamat Check Out maksimal 255 karakter',
            'checkout_region.max'      => 'Lokasi Check Out maksimal 255 karakter',
        ]);

        if ($validator->fails()) {
            return $this->helper->responseValidate($validator);
        }

        if ($hasCheckoutTimeText && $normalizedCheckoutTime === null) {
            return $this->helper->customResponse('Format Waktu Check Out tidak valid (gunakan JJ:MM atau JJ:MM:DD)', 422);
        }

        $targetUser = \DB::table('default_users')->where('id', $valInput['default_user_id'])->first();
        if (!$targetUser) {
            return $this->helper->customResponse("User karyawan dengan ID #{$valInput['default_user_id']} tidak ditemukan", 422);
        }

        if ($req->has('checkin_foto')) {
            $checkinFoto = $this->normalizeFotoValue($req->input('checkin_foto'));
        } elseif ($isUpdate) {
            $checkinFoto = $this->normalizeFotoValue($existing->checkin_foto ?? null);
        } else {
            $checkinFoto = null;
        }
        if ($checkinFoto === null) {
            $checkinFoto = $this->isColumnNullable('checkin_foto') ? null : '';
        }

        if ($req->has('checkout_foto')) {
            $checkoutFoto = $this->normalizeFotoValue($req->input('checkout_foto'));
        } elseif ($isUpdate) {
            $checkoutFoto = $this->normalizeFotoValue($existing->checkout_foto ?? null);
        } else {
            $checkoutFoto = null;
        }
        if ($checkoutFoto === null && !$this->isColumnNullable('checkout_foto')) {
            $checkoutFoto = '';
        }

        $mCompId = null;
        if ($req->filled('m_comp_id') && is_numeric($req->input('m_comp_id'))) {
            $mCompId = (int)$req->input('m_comp_id');
        } elseif ($existing && !empty($existing->m_comp_id)) {
            $mCompId = (int)$existing->m_comp_id;
        } elseif (!empty($targetUser->m_comp_id)) {
            $mCompId = (int)$targetUser->m_comp_id;
        } elseif (auth()->check() && !empty(auth()->user()->m_comp_id)) {
            $mCompId = (int)auth()->user()->m_comp_id;
        } else {
            $mCompId = 1;
        }

        $checkinOnScope = $this->toBooleanValue($req->input('checkin_on_scope'), false);
        $checkoutOnScope = $normalizedCheckoutTime !== null
            ? $this->toBooleanValue($req->input('checkout_on_scope'), false)
            : null;
        $status = ($normalizedCheckinTime && $normalizedCheckoutTime) ? 'ATTEND' : 'WORKING';

        $authId = auth()->check() ? auth()->user()->id : null;
        $now = Carbon::now();

        $saveData = [
            'm_comp_id'         => $mCompId,
            'default_user_id'   => $valInput['default_user_id'],
            'tanggal'           => $valInput['tanggal'],
            'status'            => $status,
            'checkin_time'      => $normalizedCheckinTime,
            'checkin_foto'      => $checkinFoto,
            'checkin_lat'       => $valInput['checkin_lat'],
            'checkin_long'      => $valInput['checkin_long'],
            'checkin_address'   => $valInput['checkin_address'],
            'checkin_region'    => $valInput['checkin_region'],
            'checkin_on_scope'  => $checkinOnScope,
            'catatan_in'        => $toStrOrNull($req->input('catatan_in')),
            'checkout_time'     => $normalizedCheckoutTime,
            'checkout_foto'     => $checkoutFoto,
            'checkout_lat'      => $valInput['checkout_lat'],
            'checkout_long'     => $valInput['checkout_long'],
            'checkout_address'  => $valInput['checkout_address'],
            'checkout_region'   => $valInput['checkout_region'],
            'checkout_on_scope' => $checkoutOnScope,
            'catatan_out'       => $toStrOrNull($req->input('catatan_out')),
            'updated_at'        => $now,
        ];

        DB::beginTransaction();
        try {
            if ($isUpdate) {
                $saveData['last_editor_id'] = $authId;
                \DB::table('presensi_absensi')->where('id', $recordId)->update($saveData);
                $savedId = $recordId;
            } else {
                $saveData['creator_id'] = $authId;
                $saveData['created_at'] = $now;
                $savedId = \DB::table('presensi_absensi')->insertGetId($saveData);
            }

            DB::commit();
            $savedRow = \DB::table('presensi_absensi')->where('id', $savedId)->first();
            return $this->helper->customResponse('Data absensi berhasil disimpan', 200, $savedRow);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->helper->customResponse(
                'Gagal menyimpan data absensi: ' . $e->getMessage() . ' (Baris ' . $e->getLine() . ')',
                422
            );
        }
    }

    public function setTanggalAttribute($value)
    {
        if ($value) {
            $parts = preg_split('/[-\/]/', $value);
            if (count($parts) === 3 && strlen($parts[0]) <= 2 && strlen($parts[2]) === 4) {
                $this->attributes['tanggal'] = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                return;
            }
        }
        $this->attributes['tanggal'] = $value;
    }

    // public function custom_get_by_daily($req)
    // {
    //     $req->month = $req->month.'-01';
    //     $weeks = $req->weeks;
    //     $start_date = '';
    //     $end_date = '';

    //     $weeksArr = explode('/', $weeks);
    //     if(count($weeksArr) > 1){
    //         $start_date = $weeksArr[0];
    //         $end_date = $weeksArr[1];
    //         $data = \DB::select("
    //             SELECT json_agg(json_build_object(
    //                 'all_days_of_month', all_days_of_month,
    //                 'date_to_idn', date_to_idn,
    //                 'day_name_idn', day_name_idn,
    //                 'type', type,
    //                 'presentase', presentase,
    //                 'attend', attend,
    //                 'cuti', cuti,
    //                 'alpha', alpha,
    //                 'total_kary', total_kary
    //             )) AS monthly_report
    //             FROM generate_weekly_report(?,?,?,?)",[$start_date, $end_date,$req->divisi_id,$req->dept_id]);
    //     }else{
    //         $data = \DB::select("
    //             SELECT json_agg(json_build_object(
    //                 'all_days_of_month', all_days_of_month,
    //                 'date_to_idn', date_to_idn,
    //                 'day_name_idn', day_name_idn,
    //                 'type', type,
    //                 'presentase', presentase,
    //                 'attend', attend,
    //                 'cuti', cuti,
    //                 'alpha', alpha,
    //                 'total_kary', total_kary
    //             )) AS monthly_report
    //             FROM generate_monthly_report(?,?,?)",[$req->month,$req->divisi_id,$req->dept_id]);
    //     }

        
    //     if(count($data)){   
    //         return $this->helper->customResponse('OK',200,json_decode($data[0]->monthly_report));
    //     }else{
    //         return $this->helper->customResponse('OK',200,[]);
    //     }
    // }

    public function custom_get_by_daily($req)
    {
        if ($req->weeks) {
            [$startDate, $endDate] = explode("/", $req->weeks);
        } else {
            $startDate = Carbon::parse($req->month . "-01");
            $endDate = Carbon::parse($req->month . "-03");
        }

        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dates[] = $current->format("Y-m-d");
            $current->addDay();
        }

        $hariLibur = m_general::where("group", "HARI LIBUR")
            ->pluck("value")
            ->toArray();

        $cutiBersama = m_libur_nasional::pluck("tanggal")
            ->map(fn($t) => Carbon::parse($t)->format("Y-m-d"))
            ->toArray();

        $totalKary = default_users::whereHas("m_kary", function ($q) use (
            $req
        ) {
            $q->where("is_active", true);
            if ($req->filled('m_dir_id') && $req->m_dir_id !== 'null') {
                $q->where("m_dir_id", $req->m_dir_id);
            }
            if ($req->filled('divisi_id') && $req->divisi_id !== 'null') {
                $q->where("m_divisi_id", $req->divisi_id);
            }
            if ($req->filled('dept_id') && $req->dept_id !== 'null') {
                $q->where("m_dept_id", $req->dept_id);
            }
        })->count();

        $result = [];

        foreach ($dates as $index => $date) {
            $carbonDate = Carbon::parse($date);
            $dayNameIdn = $carbonDate->translatedFormat("l");
            $dayNameIdn = ucfirst($dayNameIdn);

            if (in_array($dayNameIdn, $hariLibur)) {
                $type = "Hari Libur";
            } elseif (in_array($carbonDate->format("Y-m-d"), $cutiBersama)) {
                $type = "Cuti Bersama";
            } else {
                $type = "Hari Kerja";
            }

            $hadir = default_users::whereHas("m_kary", function ($q1) use (
                $req
            ) {
                $q1->where("is_active", true);
                if ($req->filled('m_dir_id') && $req->m_dir_id !== 'null') {
                    $q1->where("m_dir_id", $req->m_dir_id);
                }
                if ($req->filled('divisi_id') && $req->divisi_id !== 'null') {
                    $q1->where("m_divisi_id", $req->divisi_id);
                }
                if ($req->filled('dept_id') && $req->dept_id !== 'null') {
                    $q1->where("m_dept_id", $req->dept_id);
                }
            })
                ->whereHas("presensi_absensi", function ($q) use ($date) {
                    $q->where("tanggal", $date);
                })
                ->count();

            $cuti = t_cuti::whereIn("status", ["APPROVED", "POSTED"])
                ->whereDate("date_from", "<=", $date)
                ->whereDate("date_to", ">=", $date)
                ->when(
                    ($req->filled('m_dir_id') && $req->m_dir_id !== 'null') ||
                    ($req->filled('divisi_id') && $req->divisi_id !== 'null') ||
                    ($req->filled('dept_id') && $req->dept_id !== 'null'),
                    function ($q) use ($req) {
                        $q->whereHas("m_kary", function ($qKary) use ($req) {
                            $qKary->where("is_active", true);
                            if ($req->filled('m_dir_id') && $req->m_dir_id !== 'null') {
                                $qKary->where("m_dir_id", $req->m_dir_id);
                            }
                            if ($req->filled('divisi_id') && $req->divisi_id !== 'null') {
                                $qKary->where("m_divisi_id", $req->divisi_id);
                            }
                            if ($req->filled('dept_id') && $req->dept_id !== 'null') {
                                $qKary->where("m_dept_id", $req->dept_id);
                            }
                        });
                    }
                )
                ->count();

            $alpha = $totalKary - $hadir - $cuti;

            $presentase = $totalKary > 0 ? ($hadir / $totalKary) * 100 : 0;

            $result[] = [
                "all_days_of_month" => $carbonDate->format("Y-m-d"),
                "date_to_idn" => $carbonDate->format("d-m-Y"),
                "day_name_idn" => $dayNameIdn,
                "type" => $type,
                "presentase" => $presentase,
                "attend" => $hadir,
                "cuti" => $cuti,
                "alpha" => $alpha,
                "total_kary" => $totalKary,
            ];
        }

        return $this->helper->customResponse("OK", 200, $result);
    }

    //     public function custom_get_by_date($req)
    // {
    //     $data = \DB::select(
    //         "
    //     SELECT json_agg(json_build_object(
    //         'm_kary_id', m_kary_id,
    //         'default_user_id', default_user_id,
    //         'kode', kode,
    //         'nama_lengkap', nama_lengkap,
    //         'dept', dept,
    //         'absensi', absensi
    //     )) AS att_report
    //     FROM get_employee_attendance_report(?,?,?)",
    //         [$req->date, $req->divisi_id, $req->dept_id]
    //     );

    //     if (!count($data)) {
    //         return $this->helper->customResponse("OK", 200, []);
    //     }

    //     $att_report = json_decode($data[0]->att_report);

    //     $cutiAll = \DB::table("t_cuti")
    //         ->select("m_kary_id", "date_from", "date_to")
    //         ->where("status", "APPROVED")
    //         ->whereDate("date_from", "<=", $req->date)
    //         ->whereDate("date_to", ">=", $req->date)
    //         ->get();

    //     // $offAll = t_libur::with([
    //     //     "t_libur_d" => function ($q) {
    //     //         $q->select("id", "t_libur_id", "m_kary_id");
    //     //     },
    //     // ])
    //     //     ->whereDate("tanggal_mulai", "<=", $req->date)
    //     //     ->whereDate("tanggal_akhir", ">=", $req->date)
    //     //     ->get();

    //     foreach ($att_report as $report) {
    //         if ($report->absensi->status === "NOT ATTEND") {
    //             $report->absensi->created_at = "-";
    //             $report->absensi->updated_at = "-";

    //             $hasCuti = $cutiAll->contains(function ($c) use ($report) {
    //                 return $c->m_kary_id == $report->m_kary_id;
    //             });

    //             // $hasOff = $offAll->contains(function ($off) use ($report) {
    //             //     return collect($off->t_libur_d)->contains(
    //             //         "m_kary_id",
    //             //         $report->m_kary_id
    //             //     );
    //             // });

    //             // if ($hasOff) {
    //             //     $report->absensi->status = "OFF";
    //             // } else
    //             if ($hasCuti) {
    //                 $report->absensi->status = "CUTI";
    //             }
    //         }
    //     }

    //     return $this->helper->customResponse("OK", 200, $att_report);
    // }

    public function custom_get_by_date($req)
    {
        $divisiId = ($req->filled('divisi_id') && $req->divisi_id !== 'null') ? $req->divisi_id : null;
        $deptId = ($req->filled('dept_id') && $req->dept_id !== 'null') ? $req->dept_id : null;

        $data = \DB::select("
            SELECT json_agg(json_build_object(
                'm_kary_id', m_kary_id,
                'default_user_id', default_user_id,
                'kode', kode,
                'nama_lengkap', nama_lengkap,
                'dept', dept,
                'absensi', absensi
            )) AS att_report
            FROM get_employee_attendance_report(?,?,?)",[$req->date, $divisiId, $deptId]);

        if (!count($data) || !$data[0]->att_report) {
            return $this->helper->customResponse('OK', 200, []);
        }

        $att_report = json_decode($data[0]->att_report);

        $karyIds = array_filter(array_column($att_report, 'm_kary_id'));
        $karyMap = \DB::table('m_kary')
            ->leftJoin('m_dir', 'm_dir.id', '=', 'm_kary.m_dir_id')
            ->whereIn('m_kary.id', $karyIds)
            ->select('m_kary.id', 'm_kary.m_dir_id', 'm_dir.nama as company_nama')
            ->get()
            ->keyBy('id');

        $cutiAll = \DB::table("t_cuti")
            ->leftJoin("m_general as al", "al.id", "=", "t_cuti.alasan_id")
            ->leftJoin("m_general as tc", "tc.id", "=", "t_cuti.tipe_cuti_id")
            ->select(
                "t_cuti.m_kary_id",
                "t_cuti.date_from",
                "t_cuti.date_to",
                "t_cuti.status",
                "t_cuti.keterangan",
                "al.value as alasan_value",
                "tc.value as tipe_cuti_value"
            )
            ->whereIn("t_cuti.status", ["APPROVED", "POSTED"])
            ->whereDate("t_cuti.date_from", "<=", $req->date)
            ->whereDate("t_cuti.date_to", ">=", $req->date)
            ->get();

        foreach ($att_report as $report) {
            $karyInfo = $karyMap->get($report->m_kary_id);
            $report->m_dir_id = $karyInfo ? $karyInfo->m_dir_id : null;
            $report->company = $karyInfo ? ($karyInfo->company_nama ?? '-') : '-';

            $currentStatus = @$report->absensi->status;
            if (!$currentStatus || $currentStatus === "NOT ATTEND" || $currentStatus === "CUTI") {
                $matchingCuti = $cutiAll->first(function ($c) use ($report) {
                    return $c->m_kary_id == $report->m_kary_id;
                });

                if ($matchingCuti) {
                    if (!isset($report->absensi)) {
                        $report->absensi = new \stdClass();
                    }
                    $ketLower = strtolower(trim(($matchingCuti->keterangan ?? '') . ' ' . ($matchingCuti->alasan_value ?? '') . ' ' . ($matchingCuti->tipe_cuti_value ?? '')));
                    $isSakit = str_contains($ketLower, 'sakit');
                    $report->absensi->status = $isSakit ? "CUTI (SAKIT)" : "CUTI";
                    if (!isset($report->absensi->created_at)) {
                        $report->absensi->created_at = "-";
                    }
                    if (!isset($report->absensi->updated_at)) {
                        $report->absensi->updated_at = "-";
                    }
                }
            }
        }

        if ($req->filled('m_dir_id') && $req->m_dir_id !== 'null') {
            $att_report = array_values(array_filter($att_report, function($report) use ($req) {
                return $report->m_dir_id == $req->m_dir_id;
            }));
        }

        return $this->helper->customResponse('OK', 200, $att_report);
    }

public function custom_checkin($req)
{
    $validator = Validator::make($req->all(), [
        "foto" => "required",
        "lat" => "required",
        "long" => "required",
        "address" => "required",
    ]);

    if ($validator->fails()) {
        return $this->helper->responseValidate($validator);
    }

    // Penentuan waktu berdasarkan zona longitude
    if (floatval($req->long) < 114) {
        $checkin_time = Carbon::now('Asia/Jakarta');
    } elseif (floatval($req->long) < 141) {
        $checkin_time = Carbon::now('Asia/Makassar');
    } else {
        $checkin_time = Carbon::now('Asia/Jayapura');
    }

    // Tentukan user sasaran
    $userAuth = $req->filled('username')
        ? default_users::where('username', $req->username)->first()
        : auth()->user();

    if (!$userAuth) {
        return $this->helper->customResponse("User tidak ditemukan", 404);
    }

    DB::beginTransaction();
    try {
        if (!$req->hasFile("foto")) {
            throw new \Exception("IMAGE NOT VALID");
        }

        $file = $req->file("foto");

        // --- STEP 1: KOMPRESI GAMBAR ---
        // Kita kompres dulu sebelum dikirim ke API Recognize agar bandwidth lebih hemat
        $rawImageData = $this->compressToWebp($file, 800, 75);
        if (!$rawImageData) {
            throw new \Exception("Gagal memproses gambar");
        }

        // --- STEP 2: HIT API FACE RECOGNITION (RECOGNIZE) ---
        // $response = Http::asMultipart()
        //     ->attach(
        //         'image',
        //         file_get_contents($file),
        //         'checkin.webp',
        //         ['Content-Type' => 'image/webp']
        //     )
        //     ->post('https://server.qqltech.com:7089/recognize');

        // $result = $response->json();

        // // --- STEP 3: VALIDASI HASIL FACE REG ---
        // if (!isset($result['data']['employee_id'])) {
        //     throw new \Exception("Wajah tidak dikenali oleh sistem");
        // }

        // // Validasi apakah wajah yang terdeteksi sesuai dengan username user sasaran
        // // Gunakan strtolower untuk menghindari error case sensitive
        // if (strtolower($result['data']['employee_id']) !== strtolower($userAuth->username)) {
        //     throw new \Exception("Wajah yang terdeteksi bukan milik " . $userAuth->username);
        // }

        // if (($result['data']['match'] ?? false) !== true) {
        //     throw new \Exception("Tingkat kecocokan wajah rendah");
        // }

        // if (($result['data']['liveness_ok'] ?? false) !== true) {
        //     throw new \Exception("Gagal verifikasi liveness (spoofing terdeteksi)");
        // }

        // --- STEP 4: LOGIKA RADIUS/DISTANCE ---
        $distance = $this->distance($req->lat, $req->long);
        $data["on_scope"] = $distance ? true : false;
        $data["region"] = $distance->nama ?? "Out Scope";
        $data["checkin_lat"] = $req->lat;
        $data["checkin_long"] = $req->long;
        $data["checkin_address"] = $req->address;
        $data["catatan_in"] = $distance ? null : ($req->catatan_in ?? null);

        // --- STEP 5: CEK DOUBLE CHECKIN ---
        $check_exists_absen = $this->where("tanggal", date("Y-m-d"))
            ->where("default_user_id", $userAuth->id)
            ->exists();

        if ($check_exists_absen) {
            return $this->helper->customResponse("Anda sudah checkin hari ini", 422);
        }

        // --- STEP 6: SIMPAN GAMBAR KE STORAGE ---
        $fileName = $userAuth->username . ":::" . md5(time()) . ".webp";
        $subfolder = date('Y/m/d');
        $destinationPath = public_path("uploads/presensi/" . $subfolder);

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        file_put_contents($destinationPath . '/' . $fileName, $rawImageData);

        // --- STEP 7: SIMPAN DATA KE DATABASE ---
        $this->create([
            "tanggal" => date("Y-m-d"),
            "checkin_time" => $checkin_time->format("H:i:s"),
            "checkin_foto" => "uploads/presensi/$subfolder/$fileName",
            "checkin_lat" => $data["checkin_lat"],
            "checkin_long" => $data["checkin_long"],
            "checkin_address" => $data["checkin_address"],
            "checkin_region" => $data["region"],
            "checkin_on_scope" => $data["on_scope"],
            "catatan_in" => $data["catatan_in"],
            "default_user_id" => $userAuth->id,
            "creator_id" => $userAuth->id,
            "status" => "WORKING"
        ]);

        DB::commit();
        return $this->helper->customResponse("Checkin berhasil", 200, $data);

    } catch (\Exception $e) {
        DB::rollback();
        return $this->helper->customResponse("Checkin gagal: " . $e->getMessage(), 400);
    }
}

    public function custom_checkout($req)
    {
        $validator = Validator::make($req->all(), [
            "foto" => "required",
            "lat" => "required",
            "long" => "required",
            "address" => "required",
        ]);
        if ($validator->fails()) return $this->helper->responseValidate($validator);

        //$checkout_time = $req->checkout_time;

        if(floatval($req->long) < 114){
            $checkout_time = Carbon::now('Asia/Jakarta');
        }elseif(floatval($req->long) < 141){
            $checkout_time = Carbon::now('Asia/Makassar');
        }else{
            $checkout_time = Carbon::now('Asia/Jayapura');
        }

        $user = $req->filled('username')
        ? default_users::where('username', $req->username)->first()
        : auth()->user();

        DB::beginTransaction();
        try {
            $distance = $this->distance($req->lat, $req->long);
            if ($distance) {
                $data["on_scope"] = true;
                $data["region"] = $distance->nama;
                $data["checkout_lat"] = $req->lat;
                $data["checkout_long"] = $req->long;
                $data["checkout_address"] = $req->address;
                $data["catatan_out"] = null;
            } else {
                $data["on_scope"] = false;
                $data["region"] = "Out Scope";
                $data["checkout_lat"] = $req->lat;
                $data["checkout_long"] = $req->long;
                $data["checkout_address"] = $req->address;
                $data["catatan_out"] = $req->catatan_out ?? null;
            }

            $check_exists_absen = $this->where("tanggal", date("Y-m-d"))
                ->where("default_user_id", $user->id)
                ->where("status", "ATTEND")
                ->exists();
            if ($check_exists_absen) 
                return $this->helper->customResponse("Anda sudah checkout hari ini", 422);
            
            $check_not_exists_checkin = $this->where("tanggal", date("Y-m-d"))
                ->where("default_user_id", $user->id)
                ->where("status", "WORKING")->exists();
             if ($check_exists_absen) 
                return $this->helper->customResponse("Anda belum checkin hari ini", 422);

            if ($req->hasFile("foto")) {
                $file = $req->file("foto");
                $fileName =
                    $user->username .
                    ":::" .
                    md5(time()) .
                    "." .
                    $file->getClientOriginalExtension();
                $file->move(public_path("uploads/presensi"), $fileName);
            } else {
                trigger_error("IMAGE NOT VALID");
            }

            $this->where("tanggal", date("Y-m-d"))
                ->where("default_user_id", $user->id)
                ->where("status", "WORKING")
                ->update([
                    "checkout_time" => $checkout_time ?? date("H:i:s"),
                    "checkout_foto" => "uploads/presensi/$fileName",
                    "checkout_lat" => $data["checkout_lat"],
                    "checkout_long" => $data["checkout_long"],
                    "checkout_address" => $data["checkout_address"],
                    "checkout_region" => $data["region"],
                    "checkout_on_scope" => $data["on_scope"],
                    "catatan_out" => $data["catatan_out"],
                    "status" => "ATTEND",
                ]);
            DB::commit();
       } catch (\Exception $e) {
            DB::rollback();
            return $this->helper->customResponse("Checkout gagal, coba kembali nanti - ", 422);
        }
        return $this->helper->customResponse("Checkout berhasil", 200, $data);
    }

    private function distance($lat, $long)
    {
        $distance = DB::select("select distance_location(?,?)", [$lat, $long]);
        if (count($distance)) {
            $location = json_decode($distance[0]->distance_location);
            return @$location[0] ?? false;
        } else {
            return false;
        }
    }

    public function custom_distance_check($req)
    {
        $distance = $this->distance($req->lat, $req->long);
        if ($distance) {
            $data["on_scope"] = true;
            $data["region"] = $distance->nama;
            $data["lat"] = $distance->lat;
            $data["long"] = $distance->long;
            $data["address"] = $req->address;
            $data["office"] = $distance->nama;
        } else {
            $data["on_scope"] = false;
            $data["region"] = "Out Scope";
            $data["lat"] = $req->lat;
            $data["long"] = $req->long;
            $data["address"] = $req->address;
            $data["office"] = null;
        }
        return $this->helper->customResponse("OK", 200, $data);
    }

    public function custom_status($model)
    {
        $data = [
            'status' => $this->where('tanggal', date('Y-m-d'))->where('default_user_id', auth()->user()->id ?? 0)->pluck('status')->first() ?? 'NOT ATTEND'
        ];
        return $this->helper->customResponse("OK", 200, $data);
    }

    public function custom_status_get_jadwal_kerja(){
        $karyId = auth()->user()->m_kary_id;
        $getTodayNum = Carbon::today()->dayOfWeek;
        $startOfWeek = Carbon::today()->startOfWeek();
        $endOfweek = Carbon::today()->endOfweek();
        $now = Carbon::now();

        $t_jadwal_kerja_det = t_jadwal_kerja_det::where('m_kary_id',$karyId)->whereHas('t_jadwal_kerja_det_hari',function($query) {
            $query->whereIn('day_num',range(1,7));
        })->with(['t_jadwal_kerja_det_hari.m_jam_kerja' => function($select){
            $select->select('id','m_jam_kerja.is_hari_berikutnya');
        }])->get([
            'id','m_kary_id','t_jadwal_kerja_det_hari_id'
        ]);


        $t_jadwal_kerja_det = $t_jadwal_kerja_det->transform(function ($item){
            $tanggal = Carbon::today()->startOfWeek()->addDays($item->t_jadwal_kerja_det_hari->day_num - 1)->toDateString();
            $item->t_jadwal_kerja_det_hari->tanggal = $tanggal;
            $waktu_mulai = $item->t_jadwal_kerja_det_hari->waktu_mulai;
            $waktu_akhir = $item->t_jadwal_kerja_det_hari->waktu_akhir;
            $item->start_work = Carbon::parse("$tanggal $waktu_mulai")->subHours(2)->toDateTimeString();
            if($item->t_jadwal_kerja_det_hari->m_jam_kerja->is_hari_berikutnya == true){
                $item->end_work = Carbon::parse("$tanggal $waktu_akhir")->addDay()->addHours(2)->toDateTimeString();
            }else{
                 $item->end_work = Carbon::parse("$tanggal $waktu_akhir")->toDateTimeString();
            }
            return $item;
        });
        
        $startDayBeforeWeekend = $t_jadwal_kerja_det[6]['start_work'];
        $EndDayBeforeWeekend = $t_jadwal_kerja_det[6]['end_work'];

        $day_before = [
        "start_work" => Carbon::parse($startDayBeforeWeekend)->subWeek()->toDateTimeString(),
        "end_work" => Carbon::parse($EndDayBeforeWeekend)->subWeek()->toDateTimeString(),
        ];

        $t_jadwal_kerja_det->prepend($day_before);
        
        $getData = $t_jadwal_kerja_det->where('start_work', '<=', $now)
                            ->where('end_work', '>=', $now)
                            ->first();
        if($getData){
            $isPresent = $this->whereBetween('created_at', [$getData['start_work'],$getData['end_work']])->where('default_user_id', auth()->user()->id ?? 0)->pluck('status')->first() ?? 'NOT ATTEND';
            $data = [
                'status' => $isPresent 
            ];
        }else{
            $data = [
                'status' => "ATTEND" 
            ];
        }
        return $this->helper->customResponse("OK", 200, $data);
    }


    public function scopeFilter($model)
    {
        if(req('date_from') && req('date_to')){
            return $model->whereBetween('tanggal',[req('date_from'),req('date_to')])->where('default_user_id', auth()->user()->id ?? 0);
        }
    }

    public function custom_get_absen($req)
    {
        $periode = ($req->periode ?? date('Y-m')).'-1';
        $m_kary_id = auth()->user()->m_kary_id;

        $data = \DB::select("
            select * from employee_attendance_detail(?, ?);
        ", [$periode,$m_kary_id ?? 0]);

        // transform object for mobile
        foreach($data as $dt){
            $dt->status = @json_decode($dt->absensi)->status ?? null;
            $dt->tanggal = @json_decode($dt->absensi)->tanggal ?? null;
            $dt->catatan_in = @json_decode($dt->absensi)->catatan_in ?? null;
            $dt->catatan_out = @json_decode($dt->absensi)->catatan_out ?? null;
            $dt->checkin_lat = @json_decode($dt->absensi)->checkin_lat ?? null;
            $dt->checkin_foto = ($inPic=@json_decode($dt->absensi)->checkin_foto) ? (str_contains($inPic,'http')?$inPic:url($inPic)) : null;
            $dt->checkin_long = @json_decode($dt->absensi)->checkin_long ?? null;
            $dt->checkin_time = @json_decode($dt->absensi)->checkin_time ?? null;
            $dt->checkout_lat = @json_decode($dt->absensi)->checkout_lat ?? null;
            $dt->checkout_foto = ($outPic=@json_decode($dt->absensi)->checkout_foto) ? (str_contains($outPic,'http')?$outPic:url($outPic)) : null;
            $dt->checkout_long = @json_decode($dt->absensi)->checkout_long ?? null;
            $dt->checkout_time = @json_decode($dt->absensi)->checkout_time ?? null;
            $dt->checkin_region = @json_decode($dt->absensi)->checkin_region ?? null;
            $dt->checkin_address = @json_decode($dt->absensi)->checkin_address ?? null;
            $dt->checkout_region = @json_decode($dt->absensi)->checkout_region ?? null;
            $dt->checkin_on_scope = @json_decode($dt->absensi)->checkin_on_scope ?? null;
            $dt->checkout_address = @json_decode($dt->absensi)->checkout_address ?? null;
            $dt->checkout_on_scope = @json_decode($dt->absensi)->checkout_on_scope ?? null;
            $dt->presensi_absensi_id = @json_decode($dt->absensi)->presensi_absensi_id ?? null;
        }

        return $this->helper->customResponse("OK", 200, $data);
    }


    //for face recog

    public function public_status($model)
    {
        $username = app()->request->employee_id;
        $checkId = default_users::whereRaw(
                'LOWER(username) = ?',
                [strtolower($username)]
            )->first()?->id;

        if(!$checkId){
            throw new \Exception("Akun karyawan tidak ditemukan");
        }

        $data = [
            'status' => $this->where('tanggal', date('Y-m-d'))->where('default_user_id', $checkId ?? 0)->pluck('status')->first() ?? 'NOT ATTEND'
        ];
        return $this->helper->customResponse("OK", 200, $data);
    }

    
    public function public_checkin($req)
    {
        $validator = Validator::make($req->all(), [
            "foto" => "required",
            "lat" => "required",
            "long" => "required",
            "address" => "required",
        ]);
        
        if ($validator->fails()) 
            return $this->helper->responseValidate($validator);
        
        // Penentuan waktu berdasarkan zona longitude
        if(floatval($req->long) < 114){
            $checkin_time = Carbon::now('Asia/Jakarta');
        } elseif(floatval($req->long) < 141){
            $checkin_time = Carbon::now('Asia/Makassar');
        } else {
            $checkin_time = Carbon::now('Asia/Jayapura');
        }

        DB::beginTransaction();
        try {
            $distance = $this->distance($req->lat, $req->long);

            $data["on_scope"] = $distance ? true : false;
            $data["region"]   = $distance->nama ?? "Out Scope";
            $data["checkin_lat"] = $req->lat;
            $data["checkin_long"] = $req->long;
            $data["checkin_address"] = $req->address;
            $data["catatan_in"] = $distance ? null : ($req->catatan_in ?? null);

            if (!$req->hasFile("foto")) {
                throw new \Exception("IMAGE NOT VALID");
            }

            // --- PROSES KOMPRESI ---
            $file = $req->file("foto");
            $rawImageData = $this->compressToWebp($file, 800, 75);

            if (!$rawImageData) {
                throw new \Exception("Gagal mengolah gambar");
            }

            // --- HIT API RECOGNIZE DENGAN DATA KOMPRESI ---
            $response = Http::asMultipart()
                ->attach(
                    'image',
                    file_get_contents($file),
                    'checkface.webp',
                    ['Content-Type' => 'image/webp']
                )
                ->post('https://server.qqltech.com:7089/recognize');

            $result = $response->json();

            if (!isset($result['data']['employee_id'])) {
                throw new \Exception("Wajah tidak dikenali oleh sistem");
            }

            $employeeUsername = $result['data']['employee_id'];
            $user = default_users::whereRaw(
                'LOWER(username) = ?',
                [strtolower($employeeUsername)]
            )->first();
            
            if(!$user){
                throw new \Exception("Akun karyawan tidak ditemukan di database");
            }
            
            $employeeId = $user->id;

            if (($result['data']['match'] ?? false) !== true) {
                throw new \Exception("Wajah tidak cocok");
            }

            if (($result['data']['liveness_ok'] ?? false) !== true) {
                throw new \Exception("Gagal verifikasi liveness (spoofing terdeteksi)");
            }

            // --- VALIDASI DOUBLE CHECKIN ---
            $check_exists_absen = $this->where("tanggal", date("Y-m-d"))
                ->where("default_user_id", $employeeId)
                ->exists();

            if ($check_exists_absen) 
                return $this->helper->customResponse("Anda sudah checkin hari ini", 422);

            // --- SIMPAN GAMBAR KOMPRESI KE DISK ---
            $fileName = $employeeId . ":::" . md5(time()) . ".webp";
            $subfolder = date('Y/m/d');
            $destinationPath = public_path("uploads/presensi/" . $subfolder);

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Simpan file dari memory ke disk
            file_put_contents($destinationPath . '/' . $fileName, $rawImageData);

            // --- CREATE ABSENSI ---
            $this->create([
                "tanggal" => date("Y-m-d"),
                "checkin_time" => $checkin_time->format("H:i:s"),
                "checkin_foto" => "uploads/presensi/$subfolder/$fileName",
                "checkin_lat" => $data["checkin_lat"],
                "checkin_long" => $data["checkin_long"],
                "checkin_address" => $data["checkin_address"],
                "checkin_region" => $data["region"],
                "checkin_on_scope" => $data["on_scope"],
                "catatan_in" => $data["catatan_in"],
                "default_user_id" => $employeeId,
                "creator_id" => $employeeId,
                "status" => "WORKING", // Menambahkan status awal agar bisa di-checkout nanti
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            return $this->helper->customResponse(
                "Checkin gagal: " . $e->getMessage(),
                400
            );
        }

        return $this->helper->customResponse("Checkin berhasil", 200, $data);
    }


    public function public_checkout($req)
    {
        $validator = Validator::make($req->all(), [
            "foto"      => "required",
            "lat"       => "required",
            "long"      => "required",
            "address"   => "required",
        ]);
        if ($validator->fails()) return $this->helper->responseValidate($validator);

        // Penentuan waktu berdasarkan zona longitude
        if (floatval($req->long) < 114) {
            $checkout_time = Carbon::now('Asia/Jakarta');
        } elseif (floatval($req->long) < 141) {
            $checkout_time = Carbon::now('Asia/Makassar');
        } else {
            $checkout_time = Carbon::now('Asia/Jayapura');
        }

        DB::beginTransaction();
        try {
            $distance = $this->distance($req->lat, $req->long);

            $data["on_scope"] = $distance ? true : false;
            $data["region"]   = $distance->nama ?? "Out Scope";
            $data["checkout_lat"]      = $req->lat;
            $data["checkout_long"]     = $req->long;
            $data["checkout_address"]  = $req->address;
            $data["catatan_out"]       = $distance ? null : ($req->catatan_out ?? null);

            if (!$req->hasFile("foto")) {
                throw new \Exception("IMAGE NOT VALID");
            }

            // --- PROSES KOMPRESI ---
            // Panggil fungsi private yang sudah kita buat sebelumnya
            $file = $req->file("foto");
            $rawImageData = $this->compressToWebp($file, 800, 75);

            if (!$rawImageData) {
                throw new \Exception("Gagal mengolah gambar");
            }

            // --- HIT API RECOGNIZE DENGAN DATA KOMPRESI ---
            $response = Http::asMultipart()
                ->attach(
                    'image',
                    file_get_contents($file),
                    'checkface.webp',
                    ['Content-Type' => 'image/webp']
                )
                ->post('https://server.qqltech.com:7089/recognize');

            $result = $response->json();

            // Validasi Response API
            if (!isset($result['data']['employee_id'])) {
                throw new \Exception("Wajah tidak dikenali oleh sistem");
            }

            $employeeUsername = $result['data']['employee_id'];
            $user = default_users::where('username', $employeeUsername)->first();
            
            if (!$user) {
                throw new \Exception("Akun karyawan tidak ditemukan");
            }

            if (($result['data']['match'] ?? false) !== true) {
                throw new \Exception("Wajah tidak cocok");
            }

            if (($result['data']['liveness_ok'] ?? false) !== true) {
                throw new \Exception("Gagal verifikasi liveness (spoofing terdeteksi)");
            }

            $employeeId = $user->id;

            // --- VALIDASI DOUBLE CHECKOUT & CHECKIN STATUS ---
            $today = date("Y-m-d");

            $alreadyCheckout = $this->where("tanggal", $today)
                ->where("default_user_id", $employeeId)
                ->where("status", "ATTEND")
                ->exists();

            if ($alreadyCheckout) {
                return $this->helper->customResponse("Anda sudah checkout hari ini", 422);
            }

            $hasCheckin = $this->where("tanggal", $today)
                ->where("default_user_id", $employeeId)
                ->where("status", "WORKING")
                ->first();

            if (!$hasCheckin) {
                return $this->helper->customResponse("Anda belum checkin atau data checkin tidak ditemukan", 422);
            }

            // --- SIMPAN GAMBAR KOMPRESI KE DISK ---
            $fileName = $employeeId . "::" . md5(time()) . ".webp";
            $subfolder = date('Y/m/d');
            $destinationPath = public_path("uploads/presensi/" . $subfolder);

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Simpan file dari memory ke disk
            file_put_contents($destinationPath . '/' . $fileName, $rawImageData);

            // --- UPDATE DATABASE ---
            $hasCheckin->update([
                "checkout_time"     => $checkout_time->format("H:i:s"),
                "checkout_foto"     => "uploads/presensi/$subfolder/$fileName",
                "checkout_lat"      => $data["checkout_lat"],
                "checkout_long"     => $data["checkout_long"],
                "checkout_address"  => $data["checkout_address"],
                "checkout_region"   => $data["region"],
                "checkout_on_scope" => $data["on_scope"],
                "catatan_out"       => $data["catatan_out"],
                "status"            => "ATTEND",
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            return $this->helper->customResponse(
                "Checkout gagal: ".$e->getMessage(),
                422
            );
        }

        return $this->helper->customResponse("Checkout berhasil", 200, $data);
    }

    public function public_registerFace($req)
    {
        $validator = Validator::make($req->all(), [
            "foto" => "required|image|max:5120", // Tambahkan validasi tipe & ukuran
            "username" => "required",
            "employee_id" => "required",
        ]);

        if ($validator->fails()) {
            return $this->helper->responseValidate($validator);
        }

        if ($req->hasFile('foto')) {
            $rawImageData = $this->compressToWebp($req->file('foto'), 800, 75);

            if (!$rawImageData) {
                return response()->json(['message' => 'Gagal memproses gambar / Format tidak didukung'], 400);
            }

            try {
                $response = \Illuminate\Support\Facades\Http::asMultipart()
                    ->attach(
                        'image', 
                        file_get_contents($file), 
                        'face.webp', 
                        ['Content-Type' => 'image/webp']
                    )
                    ->post('https://server.qqltech.com:7089/register', [
                        'employee_id' => $req->employee_id,
                        'name'        => $req->username,
                    ]);

                return $response->json();

            } catch (\Exception $e) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal menghubungi server Face Recognition: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json(['message' => 'IMAGE NOT VALID'], 400);
    }

    private function compressToWebp($file, $maxWidth = 800, $quality = 75)
    {
        $ext = strtolower($file->getClientOriginalExtension());
        list($width, $height) = getimagesize($file->getRealPath());

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height / $width) * $maxWidth;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        switch ($ext) {
            case 'jpeg':
            case 'jpg': $src = imagecreatefromjpeg($file->getRealPath()); break;
            case 'png': $src = imagecreatefrompng($file->getRealPath()); break;
            default: return null;
        }

        $tmp = imagecreatetruecolor($newWidth, $newHeight);
        if ($ext == 'png') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 255, 255, 255, 127));
        }

        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($tmp, null, $quality);
        $rawImageData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($tmp);

        return $rawImageData;
    }


}
