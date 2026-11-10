<?php

namespace App\Models\CustomModels;

use Illuminate\Database\Eloquent\Builder;

class t_jadwal_kerja extends \App\Models\BasicModels\t_jadwal_kerja
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->fillable = array_merge($this->fillable, ['m_comp_id', 'm_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->createable = array_merge($this->createable, ['m_comp_id', 'm_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->updateable = array_merge($this->updateable, ['m_comp_id', 'm_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->searchable = array_merge($this->searchable, ['m_comp_id', 'm_dir_id', 'm_divisi_id', 'm_dept_id']);
        // Batasi joins hanya ke tabel yang pasti ada agar data dengan m_divisi_id/m_dept_id/last_editor_id NULL tidak tereliminasi oleh INNER JOIN otomatis generator
        $this->joins = [
            "m_comp.id=t_jadwal_kerja.m_comp_id",
            "m_dir.id=t_jadwal_kerja.m_dir_id",
            "m_general.id=t_jadwal_kerja.tipe_jam_kerja_id"
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Isolasi Jadwal Kerja sesuai active Respo (m_dir_id):
        // Superadmin = semua PT, HRD/Admin/User = PT sesuai active Respo (Holding = semua PT)
        static::addGlobalScope('respo_scope', function (Builder $builder) {
            $respoCore = getCore('Respo');
            if (!$respoCore || !$respoCore->isOperationCrudRequest('t_jadwal_kerja', ['GET', 'PUT', 'PATCH', 'DELETE'])) {
                return;
            }

            $respoCore->applyActiveOrgScopeToQuery($builder, 't_jadwal_kerja', [
                'dir_col'    => 't_jadwal_kerja.m_dir_id',
                'divisi_col' => null,
                'dept_col'   => null,
            ]);
        });
    }

    public function comp() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_comp', 'm_comp_id', 'id');
    }

    public function dir() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dir', 'm_dir_id', 'id');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        // validasi
        $tipe_jam_kerja = m_general::where('id', $arrayData['tipe_jam_kerja_id'] ?? null)->pluck('code')->first();

        if(strtolower($tipe_jam_kerja ?? '') == 'shift'){
            foreach(@app()->request->t_jadwal_kerja_det_hari ?? [] as $idx => $d){
                if ((@$d['tipe_hari'] ?? '') === 'LIBUR') {
                    continue;
                }
                $no = $idx+1;
                if(!@$d['waktu_mulai']){
                    return [
                        "errors" => ["Detail baris $no, waktu mulai wajib di isi"]
                    ];
                }
                if(!@$d['waktu_akhir']){
                    return [
                        "errors" => ["Detail baris $no, waktu akhir wajib di isi"]
                    ];
                }
            }
        }

        // Validasi Scope Respo Aktif saat Create Jadwal Kerja
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

            // Autofill m_dir_id dari active respo bila belum terisi
            if (empty($arrayData['m_dir_id']) && !empty($scope['m_dir_id'])) {
                $arrayData['m_dir_id'] = $scope['m_dir_id'];
            }

            $effDirId = $arrayData['m_dir_id'] ?? null;
            if (!$respoCore->matchesActiveCompanyScope($effDirId, null, null, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Perusahaan/Cabang berada di luar scope responsibility aktif."],
                ];
            }
        }

        $m_comp_id = $arrayData['m_comp_id'] ?? (auth()->check() ? auth()->user()->m_comp_id : 1) ?? 1;

        $newArrayData  = array_merge( $arrayData,[
            'nomor' => $this->helper->generateNomor('KODE JADWAL KERJA'),
            'm_comp_id' => $m_comp_id,
            'status' => 'DRAFT',
        ]);
        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
        ];
    }

    private function checkJadwalCompanyScope($dirId = null, $divId = null, $deptId = null)
    {
        $respoCore = getCore('Respo');
        if (!$respoCore) {
            return null;
        }
        $authUser = app()->bound('auth') ? app('auth')->user() : (auth()->check() ? auth()->user() : null);
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized.', 'message' => 'Unauthorized.'], 401);
        }
        if ($respoCore->isUserSuperadmin($authUser)) {
            return null;
        }
        $scope = $respoCore->getActiveCompanyScope($authUser);
        if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
            return response()->json([
                'error'   => 'Responsibility aktif tidak ditemukan atau sedang nonaktif.',
                'message' => 'Responsibility aktif tidak ditemukan atau sedang nonaktif.',
            ], 403);
        }
        if (!$respoCore->matchesActiveCompanyScope($dirId, $divId, $deptId, $authUser)) {
            return response()->json([
                'error'   => 'Data jadwal kerja berada di luar scope responsibility aktif.',
                'message' => 'Data jadwal kerja berada di luar scope responsibility aktif.',
            ], 403);
        }
        return null;
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
         // validasi
        $tipe_jam_kerja = m_general::where('id', $arrayData['tipe_jam_kerja_id'] ?? null)->pluck('code')->first();

        // khusus edit tambahkan pengecualian untuk setting jadwal detail karyawan
        if(strtolower($tipe_jam_kerja ?? '') == 'shift' && app()->request->from != 'setting_jadwal'){
            foreach(@app()->request->t_jadwal_kerja_det_hari ?? [] as $idx => $d){
                if ((@$d['tipe_hari'] ?? '') === 'LIBUR') {
                    continue;
                }
                $no = $idx+1;
                if(!@$d['waktu_mulai']){
                    return [
                        "errors" => ["Detail baris $no, waktu mulai wajib di isi"]
                    ];
                }
                if(!@$d['waktu_akhir']){
                    return [
                        "errors" => ["Detail baris $no, waktu akhir wajib di isi"]
                    ];
                }
                $check = t_jadwal_kerja_det_hari::join('t_jadwal_kerja as t','t.id','t_jadwal_kerja_det_hari.t_jadwal_kerja_id')
                    ->whereRaw("((waktu_mulai <= ? and waktu_akhir >= ?) or (waktu_mulai <= ? and waktu_akhir >= ?)) and t.status = 'POSTED'", [$d['waktu_mulai'],$d['waktu_mulai'], $d['waktu_akhir'],$d['waktu_akhir']])   
                    ->exists();
            }
        }

        // Validasi Scope Respo Aktif saat Update Jadwal Kerja
        $respoCore = getCore('Respo');
        $authUser = app()->bound('auth') ? app('auth')->user() : (auth()->check() ? auth()->user() : null);
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."],
                ];
            }

            $existing = \DB::table('t_jadwal_kerja')->where('id', $id)->first();
            if (!$existing) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Data jadwal kerja tidak ditemukan."],
                ];
            }

            if (!$respoCore->matchesActiveCompanyScope($existing->m_dir_id, null, null, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Data jadwal kerja berada di luar scope responsibility aktif."],
                ];
            }

            $targetDirId = array_key_exists('m_dir_id', $arrayData) ? $arrayData['m_dir_id'] : $existing->m_dir_id;
            if (!$respoCore->matchesActiveCompanyScope($targetDirId, null, null, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Perubahan Perusahaan/Cabang berada di luar scope responsibility aktif."],
                ];
            }
        }

        $newArrayData  = array_merge( $arrayData,[] );
        if (!empty($arrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = $arrayData['m_comp_id'];
        }
        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
        ];
    }
    

    public function custom_generate(){
        $validator = \Validator::make(app()->request->all(), [
            'grup_kerja_id' => 'required'
        ]);
        if($validator->fails()) return $this->helper->responseValidate($validator);

        $grup_kerja = t_grup_kerja::find(req('grup_kerja_id') ?? 0);

        if(!$grup_kerja) return $this->helper->customResponse("Grup kerja id tersbut tidak ditemukan", 422);

        $scopeErr = $this->checkJadwalCompanyScope($grup_kerja->m_dir_id ?? null, $grup_kerja->m_divisi_id ?? null, $grup_kerja->m_dept_id ?? null);
        if ($scopeErr) {
            return $this->helper->customResponse("Grup kerja berada di luar scope responsibility aktif.", 403);
        }

        $date_from = $grup_kerja->date_from;
        $date_to = $grup_kerja->date_to;

        $date_from = new \DateTime($grup_kerja->date_from);
        $date_to = new \DateTime($grup_kerja->date_to);

        // Menghitung jumlah hari antara tanggal_awal dan tanggal_akhir
        $interval = $date_from->diff($date_to);
        $jumlah_hari = $interval->days;

        $data = [];
        for ($i = 0; $i <= $jumlah_hari; $i++) {
            $date = $date_from->format('Y-m-d');
            // Menambahkan satu hari untuk iterasi berikutnya
            $date_from->add(new \DateInterval('P1D'));

            $karyQuery = m_kary::selectRaw("m_kary.*, m_posisi.desc_kerja posisi, m_jam_kerja.kode jam_kerja")
                    ->where('m_dept_id', $grup_kerja->m_dept_id)
                    ->join('m_posisi', 'm_posisi.id','m_kary.m_posisi_id')
                    ->join('m_jam_kerja', 'm_jam_kerja.id','m_kary.m_jam_kerja_id');

            $respoCore = getCore('Respo');
            if ($respoCore) {
                $respoCore->applyActiveOrgScopeToQuery($karyQuery, 'm_kary', [
                    'dir_col'    => 'm_kary.m_dir_id',
                    'divisi_col' => 'm_kary.m_divisi_id',
                    'dept_col'   => 'm_kary.m_dept_id',
                ]);
            }

            $kary = $karyQuery->get();
            foreach($kary as $d){
                $data[] = [
                    'tanggal'   => $date,
                    'm_kary_id' => $d->id,
                    'nik' => $d->nik,
                    'nama_lengkap' => $d->nama_lengkap,
                    'm_posisi_id' => $d->m_posisi_id,
                    'posisi.nama' => $d->m_posisi_id,
                    'm_jam_kerja_id' => $d->m_jam_kerja_id,
                    'status'    => 'DRAFT'
                ];
            }
        }

        return $this->helper->customResponse('OK', 200, $data);
    }

    public function custom_get_jam_kerja_default()
    {
        $data = \DB::table('m_jam_kerja as k')->join('m_general as g','g.id','k.tipe_jam_kerja_id')
            ->whereRaw("lower(g.value) = 'office'")->selectRaw('k.*')->first();
        return $this->helper->customResponse('OK', 200, $data, true);
    }

     public function custom_get_jadwal_office($req)
    {
        $query = \DB::table('t_jadwal_kerja as t')->selectRaw("t.*")->join('m_general as g','g.id','t.tipe_jam_kerja_id')->where('g.value','OFFICE')
                ->where('status','POSTED');

        $respoCore = getCore('Respo');
        if ($respoCore) {
            $respoCore->applyActiveOrgScopeToQuery($query, 't', [
                'dir_col'    => 't.m_dir_id',
                'divisi_col' => null,
                'dept_col'   => null,
            ]);
        }

        $data = $query->first();
        return $this->helper->customResponse('OK', 200, $data, true);
    }

     public function custom_post($request)
    {
        $id = $request->id ?? $request->input('id');
        $data = $this->find($id);
        if (!$data) {
            return response()->json(
                ["error" => "Data tidak ditemukan.", "message" => "Data tidak ditemukan."],
                404
            );
        }

        $scopeErr = $this->checkJadwalCompanyScope($data->m_dir_id ?? null);
        if ($scopeErr) {
            return $scopeErr;
        }

        try {
            // Begin a database transaction
            \DB::beginTransaction();

            $tipe_jam_kerja = m_general::where('id', $data->tipe_jam_kerja_id)->pluck('value')->first();

            $update = $data->update([
                "status" => "POSTED",
            ]);

            if(strtolower($tipe_jam_kerja ?? '') == 'office'){
                $updateQ = $this->where('id', '!=', $data->id)->where('tipe_jam_kerja_id', $data->tipe_jam_kerja_id);
                if (!empty($data->m_dir_id)) {
                    $updateQ->where('m_dir_id', $data->m_dir_id);
                } else {
                    $updateQ->whereNull('m_dir_id');
                }
                $update = $updateQ->update([
                    "status" => "EXPIRED",
                ]);
            }

            \DB::commit();
            return response()->json(["message" => "Data berhasil diposting."]);
        } catch (\Exception $e) {
            // Handle exception, log error messages, etc.

            // Rollback the transaction in case of any exception
            \DB::rollBack();

            return response()->json(
                ["error" => "Terjadi kesalahan: " . $e->getMessage(), "message" => "Terjadi kesalahan: " . $e->getMessage()],
                500
            );
        }
    }

    public function custom_expired($request)
    {
        $id = $request->id ?? $request->input('id');
        $data = $this->find($id);
        if (!$data) {
            return response()->json(
                ["error" => "Data tidak ditemukan.", "message" => "Data tidak ditemukan."],
                404
            );
        }

        $scopeErr = $this->checkJadwalCompanyScope($data->m_dir_id ?? null);
        if ($scopeErr) {
            return $scopeErr;
        }

        try {
            // Begin a database transaction
            \DB::beginTransaction();

            $update = $data->update([
                "status" => "EXPIRED",
            ]);

            \DB::commit();
            return response()->json(["message" => "Jadwal kerja berhasil dinonaktifkan."]);
        } catch (\Exception $e) {
            // Handle exception, log error messages, etc.

            // Rollback the transaction in case of any exception
            \DB::rollBack();

            return response()->json(
                ["error" => "Terjadi kesalahan: " . $e->getMessage(), "message" => "Terjadi kesalahan: " . $e->getMessage()],
                500
            );
        }
    }

    public function custom_generate_det_kary($req)
    {
        $query = m_kary::selectRaw("m_kary.id m_kary_id, m_kary.nama_lengkap, d.nama \"m_dept.nama\", dv.nama \"m_divisi.nama\",m_kary.m_dir_id,m_kary.m_divisi_id,m_kary.m_dept_id")
            ->leftJoin('m_dept as d','d.id','m_kary.m_dept_id')
            ->leftJoin('m_divisi as dv','dv.id','m_kary.m_divisi_id')
            ->leftJoin('m_general as g','g.id','m_kary.tipe_jam_kerja_id')
            ->where(function($q) {
                $q->whereNull('m_kary.tipe_jam_kerja_id')
                  ->orWhere(function($sub) {
                      $sub->whereRaw("lower(coalesce(g.code, '')) != 'office'")
                          ->whereRaw("lower(coalesce(g.value, '')) != 'office'");
                  });
            })
            ->whereRaw("m_kary.id not in(select d.m_kary_id from t_jadwal_kerja_det d 
                join t_jadwal_kerja t on t.id = d.t_jadwal_kerja_id where t.status = 'POSTED' and d.m_kary_id is not null)")
            ->where('m_kary.is_active', true);

        $respoCore = getCore('Respo');
        if ($respoCore) {
            $respoCore->applyActiveOrgScopeToQuery($query, 'm_kary', [
                'dir_col'    => 'm_kary.m_dir_id',
                'divisi_col' => 'm_kary.m_divisi_id',
                'dept_col'   => 'm_kary.m_dept_id',
            ]);
        }

        $data = $query->get();
        return $this->helper->customResponse('OK', 200, $data);
    }

    public function custom_post_det_kary($req)
    {
        try {
            \DB::beginTransaction();
                
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(
                ["error" => "Terjadi kesalahan: " . $e->getMessage()],
                500
            );
        }
    }

    public function custom_save_jadwal($req){
        $data = collect($req);
        $validatedRows = [];
        foreach($data as $single){
            $head = !empty($single['t_jadwal_kerja_id']) ? \DB::table('t_jadwal_kerja')->where('id', $single['t_jadwal_kerja_id'])->first() : null;
            if (!$head) {
                return $this->helper->customResponse('Header jadwal kerja tidak ditemukan.', 404);
            }
            $headErr = $this->checkJadwalCompanyScope($head->m_dir_id ?? null);
            if ($headErr) {
                return $headErr;
            }
            $kary = !empty($single['m_kary_id']) ? \DB::table('m_kary')->where('id', $single['m_kary_id'])->first() : null;
            if (!$kary) {
                return $this->helper->customResponse('Data karyawan tidak ditemukan.', 404);
            }
            $karyErr = $this->checkJadwalCompanyScope($kary->m_dir_id ?? null, $kary->m_divisi_id ?? null, $kary->m_dept_id ?? null);
            if ($karyErr) {
                return $karyErr;
            }
            $validatedRows[] = [
                'single' => $single,
                'kary'   => $kary,
            ];
        }

        foreach($validatedRows as $row){
            $single = $row['single'];
            $kary = $row['kary'];
            t_jadwal_kerja_det::firstOrCreate([
                't_jadwal_kerja_det_hari_id' => $single['t_jadwal_kerja_det_hari_id'],
                't_jadwal_kerja_id' => $single['t_jadwal_kerja_id'],
                'm_kary_id' => $single['m_kary_id']
            ],[
                'm_dir_id' => $single['m_dir_id'] ?? $kary->m_dir_id,
                'm_divisi_id' => $single['m_divisi_id'] ?? $kary->m_divisi_id,
                'm_dept_id' => $single['m_dept_id'] ?? $kary->m_dept_id,
            ]);
        }
        return $this->helper->customResponse('Success', 201);        
    }

    public function custom_delete_jadwal($req){
        $t_jadwal_det_head = $req;
        $getData = t_jadwal_kerja_det::where('t_jadwal_kerja_det_hari_id',$t_jadwal_det_head['t_jadwal_kerja_det_hari_id'])
        ->where('t_jadwal_kerja_id',$t_jadwal_det_head['t_jadwal_kerja_id'])
        ->where('m_kary_id',$t_jadwal_det_head['m_kary_id'])->first();
        if($getData){
            $head = !empty($getData->t_jadwal_kerja_id) ? \DB::table('t_jadwal_kerja')->where('id', $getData->t_jadwal_kerja_id)->first() : null;
            if ($head) {
                $headErr = $this->checkJadwalCompanyScope($head->m_dir_id ?? null);
                if ($headErr) {
                    return $headErr;
                }
            }
            $kary = !empty($getData->m_kary_id) ? \DB::table('m_kary')->where('id', $getData->m_kary_id)->first() : null;
            $scopeErr = $this->checkJadwalCompanyScope(
                $getData->m_dir_id ?? ($kary->m_dir_id ?? null),
                $getData->m_divisi_id ?? ($kary->m_divisi_id ?? null),
                $getData->m_dept_id ?? ($kary->m_dept_id ?? null)
            );
            if ($scopeErr) {
                return $scopeErr;
            }
            $getData->delete();
            return $this->helper->customResponse('Success', 200);
        }else{
            return $this->helper->customResponse('Not Found', 404);
        }
    }

    public function custom_delete_jadwal_bulk($req){
        $data = collect($req['items'] ?? [])->pluck('id')->filter()->values()->toArray();
        if (empty($data)) {
            return $this->helper->customResponse('Success', 200);
        }

        $targets = t_jadwal_kerja_det::whereIn('id', $data)->get();
        foreach ($targets as $det) {
            $head = !empty($det->t_jadwal_kerja_id) ? \DB::table('t_jadwal_kerja')->where('id', $det->t_jadwal_kerja_id)->first() : null;
            if ($head) {
                $headErr = $this->checkJadwalCompanyScope($head->m_dir_id ?? null);
                if ($headErr) {
                    return $headErr;
                }
            }
            $kary = !empty($det->m_kary_id) ? \DB::table('m_kary')->where('id', $det->m_kary_id)->first() : null;
            $scopeErr = $this->checkJadwalCompanyScope(
                $det->m_dir_id ?? ($kary->m_dir_id ?? null),
                $det->m_divisi_id ?? ($kary->m_divisi_id ?? null),
                $det->m_dept_id ?? ($kary->m_dept_id ?? null)
            );
            if ($scopeErr) {
                return $scopeErr;
            }
        }

        try{
            $check = t_jadwal_kerja_det::whereIn('id',$data)->delete();
            return $this->helper->customResponse('Success', 200);
        }catch(\Exception $e){
            return $e->getMessage();
        }

    }
}