<?php

namespace App\Models\CustomModels;

use DB;

class default_users extends \App\Models\BasicModels\default_users
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        // SELECT * FROM default_users
    }

    protected $hidden = ["password"];

    public $fileColumns = [
        /*file_column*/
    ];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function presensi_absensi() :\HasMany
    {
        return $this->HasMany('App\Models\BasicModels\presensi_absensi', 'default_user_id', 'id');
    }

    public function transformRowData( array $row )
    {
        $object = [];
        $req = app()->request;
        if($req->from == 'role_access'){
            $roles = m_role_access::join('m_role as r','r.id','m_role_access.m_role_id')
                ->where('m_role_access.user_id', $row['id'])
                ->pluck('r.name')
                ->toArray();
            $object['role_names'] = !empty($roles) ? implode(', ', $roles) : '-';
            $object['is_superadmin'] = 
                m_role_access::join('m_role as r','r.id','m_role_access.m_role_id')
                ->where('m_role_access.user_id', $row['id'])
                ->pluck('is_superadmin')->first();
            if($req->detail)
                $object['detail'] =  m_role_access::join('m_role as r','r.id','m_role_access.m_role_id')
                ->select("r.*")
                ->where('m_role_access.user_id', $row['id'])
                ->get();

            // Sediakan nama company secara konsisten (dari m_dir, m_comp, atau relasi m_kary)
            $companyName = $row['m_dir.nama'] ?? ($row['m_comp.nama'] ?? null);
            if (empty($companyName) && !empty($row['m_dir_id'])) {
                $companyName = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
            }
            if (empty($companyName) && !empty($row['m_comp_id'])) {
                $companyName = \DB::table('m_comp')->where('id', $row['m_comp_id'])->value('nama');
            }
            if (empty($companyName) && !empty($row['m_kary_id'])) {
                $kary = \DB::table('m_kary')->where('id', $row['m_kary_id'])->first();
                if ($kary) {
                    if (!empty($kary->m_dir_id)) {
                        $companyName = \DB::table('m_dir')->where('id', $kary->m_dir_id)->value('nama');
                    }
                    if (empty($companyName) && !empty($kary->m_comp_id)) {
                        $companyName = \DB::table('m_comp')->where('id', $kary->m_comp_id)->value('nama');
                    }
                }
            }
            $object['company'] = $companyName ?? '-';
        }
        if($req->withKary){
            $kary = m_kary::find($row['m_kary_id']);
            $object['nama_lengkap'] = @$kary->nama_lengkap;
            $object['m_kary_id'] = @$kary->id;
            $object['kode'] = @$kary->kode;
            $object['nik'] = @$kary->nik;
            $object['divisi'] = @m_divisi::find($kary->m_divisi_id)->nama;
            $object['dept'] = @m_dept::find($kary->m_dept_id)->nama;
        }
        $object['atasan'] = m_kary::where('id',@$row['m_kary.atasan_id']??0)->pluck('nama_lengkap')->first();

        if(app()->request->header('Source') === 'mobile'){
            $data = \DB::select("select public.employee_attendance(?,?)",[Date('Y-m-d'),$row['m_kary_id'] ??0]);
            $data = json_decode($data[0]->employee_attendance);
            $object['m_kary.sisa_cuti_satu_hari'] = $data->sisa_cuti_satu_hari ?? 0;
            $object['m_kary.sisa_cuti_setengah_hari'] = $data->sisa_cuti_setengah_hari ?? 0;
            $object['m_kary.cuti_satu_hari'] = $data->cuti_satu_hari ?? 0;
            $object['m_kary.cuti_setengah_hari'] = $data->cuti_setengah_hari ?? 0;
            $object['info_cuti'] = $data;
            
        }
        $object['profil_image'] = url('').'/'.$row['profil_image'];

        if (!empty($row['id'])) {
            $object['default_users_respo'] = \DB::table('default_users_respo as dur')
                ->join('m_respo as r', 'r.id', '=', 'dur.m_respo_id')
                ->leftJoin('m_dir as d', 'd.id', '=', 'r.m_dir_id')
                ->leftJoin('m_divisi as div', 'div.id', '=', 'r.m_divisi_id')
                ->leftJoin('m_dept as dept', 'dept.id', '=', 'r.m_dept_id')
                ->where('dur.default_users_id', $row['id'])
                ->select([
                    'dur.id',
                    'dur.default_users_id',
                    'dur.m_respo_id',
                    'dur.is_primary',
                    'dur.is_active',
                    'dur.seq',
                    'r.nama as respo_nama',
                    'r.kode as respo_kode',
                    'r.m_comp_id',
                    'r.m_dir_id',
                    'd.nama as company_nama',
                    'r.m_divisi_id',
                    'div.nama as divisi_nama',
                    'r.m_dept_id',
                    'dept.nama as dept_nama',
                ])
                ->orderBy('dur.seq', 'asc')
                ->orderBy('dur.id', 'asc')
                ->get();
        }
        
        return array_merge( $row, $object );
    }

    // public function onRetrieved($model)
    // {
    //     $req = app()->request;
    //     if($req->from == 'role_access'){
    //         $model->is_superadmin = 
    //             m_role_access::join('m_role as r','r.id','m_role_access.m_role_id')
    //             ->where('m_role_access.user_id', $model->id)
    //             ->pluck('is_superadmin')->first();
    //         if($req->detail)
    //             $model->detail =  m_role_access::join('m_role as r','r.id','m_role_access.m_role_id')
    //             ->select("r.*")
    //             ->where('m_role_access.user_id', $model->id)
    //             ->get();
    //     }
    //     if($req->withKary){
    //         $kary = m_kary::find($model->m_kary_id);
    //         $model->nama_lengkap = @$kary->nama_lengkap;
    //         $model->m_kary_id = @$kary->id;
    //         $model->kode = @$kary->kode;
    //         $model->nik = @$kary->nik;
    //         $model->divisi = @m_divisi::find($kary->m_divisi_id)->nama;
    //         $model->dept = @m_dept::find($kary->m_dept_id)->nama;
    //     }
    //     $model->atasan = m_kary::where('id',@$model['m_kary.atasan_id']??0)->pluck('nama_lengkap')->first();

    //     if(app()->request->header('Source') === 'mobile'){
    //         $data = \DB::select("select public.employee_attendance(?,?)",[Date('Y-m-d'),$model['m_kary_id'] ??0]);
    //         $data = json_decode($data[0]->employee_attendance);
    //         $model['m_kary.sisa_cuti_satu_hari'] = $data->sisa_cuti_satu_hari ?? 0;
    //         $model['m_kary.sisa_cuti_setengah_hari'] = $data->sisa_cuti_setengah_hari ?? 0;
    //         $model['m_kary.cuti_satu_hari'] = $data->cuti_satu_hari ?? 0;
    //         $model['m_kary.cuti_setengah_hari'] = $data->cuti_setengah_hari ?? 0;
    //         $model['info_cuti'] = $data;
            
    //     }
    // }

    public function custom_getHBDKaryawan($request){
        try {
                $today = \Carbon::today();

                $karyawanHariIni = m_kary::leftJoin('default_users', 'm_kary.id', '=', 'default_users.m_kary_id')
                    ->whereMonth('m_kary.tgl_lahir', $today->month)
                    ->whereDay('m_kary.tgl_lahir', '>=', $today->day)
                    ->select('m_kary.*', 'default_users.name as default_users_nama')
                    ->get();
                
                $karyawanHariIni->each(function($karyawan) use ($today) {
                    $tanggalLahir = \Carbon::parse($karyawan->tgl_lahir);

                    $ulangTahunTahunIni = $tanggalLahir->copy()->year($today->year);

                    $countdown = $today->diffInDays($ulangTahunTahunIni, false);

                    $karyawan->countdown = $countdown;
                });



                return response()->json([
                    'data' => $karyawanHariIni,
                    'message' => 'Daftar karyawan yang ulang tahun hari ini',
                ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mendapatkan data karyawan yang ulang tahun hari ini',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $check = $model->where("username", req("username"))->exists();
        if ($check && req("username")) {
            return ["errors" => ["Username sudah dipakai"]];
        }

        $check = $model->where("email", req("email"))->exists();
        if ($check && req("email")) {
            return ["errors" => ["Email sudah dipakai"]];
        }

        if (req("password") && !req("password_confirm")) {
            return response([
                "status" => "error",
                "message" => "Masukkan password Konfirmasi",
                "errors" => ["password_confirm" => ["Masukkan password Konfirmasi"]]
            ], 422);
        }

        if (req("password") && req("password") != req("password_confirm")) {
            return response([
                "status" => "error",
                "message" => "Konfirmasi password salah",
                "errors" => ["password_confirm" => ["Konfirmasi password salah"]]
            ], 422);
        }

        if (!empty($arrayData['m_kary_id'])) {
            $kary = \DB::table('m_kary')->where('id', $arrayData['m_kary_id'])->first();
            if ($kary) {
                if (empty($arrayData['m_dir_id']) && !empty($kary->m_dir_id)) {
                    $arrayData['m_dir_id'] = $kary->m_dir_id;
                }
                if (empty($arrayData['m_comp_id']) && !empty($kary->m_comp_id)) {
                    $arrayData['m_comp_id'] = $kary->m_comp_id;
                }
            }
        }

        $hasher = app()->make("hash");
        return [
            "model" => $model,
            "data" => array_merge($arrayData, [
                "password" => $hasher->make(req("password")),
            ]),
        ];
    }

    public function createAfter($model, $arrayData, $metaData, $id = null)
    {
        $userId = $model->id ?? $id;
        $this->syncUserRespo($userId, $arrayData);
    }


    

    public function custom_update_foto_profil($req)
    {
        $validator = \Validator::make($req->all(), [
            "profil_image" => "required",
            "id" => "required",
        ]);
        if ($validator->fails()) {
            return $this->helper->responseValidate($validator);
        }

        DB::beginTransaction();
        try {
            if ($req->hasFile("profil_image")) {
                $file = $req->file("profil_image");
                $fileName =
                    auth()->user()->username .
                    ":::" .
                    md5(time()) .
                    "." .
                    $file->getClientOriginalExtension();
                $file->move(public_path("uploads/profile"), $fileName);
            } else {
                trigger_error("IMAGE NOT VALID");
            }

            $this->where("id", $req->id)->update([
                "profil_image" => "uploads/profile/$fileName",
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->helper->customResponse(
                "Update Foto Profil gagal, coba kembali nanti",
                400
            );
        }
        return $this->helper->customResponse(
            "Update Foto Profil berhasil",
            200,
            $this->where("id", $req->id)->first()
        );
    }

    public function public_generate()
    {
        $kary = m_kary::whereRaw("m_kary.id not in(select u.m_kary_id from default_users u where u.m_kary_id is not null)")->limit(200)->get();
        
        \DB::beginTransaction();
        try{
              $hasher = app()->make("hash");
            foreach($kary as $k) {
                if($k->kode){
                    $this->create([
                        'username' => $k->kode,
                        'name' => $k->nama_lengkap,
                        'email' => $k->kode."@hris.com",
                        'password' => $hasher->make($k->kode),
                        'm_kary_id' => $k->id
                    ]);

                }
            }
            \DB::commit();
        }catch(\Exception $e) {
            return response(['m'=>$e->getMessage().' - '.$e->getLine()], 400);
        }
        
        return response(['m'=>$kary]);
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $hasher = app()->make("hash");
        if (req("password")) {
            $arrayData['password'] = $hasher->make(req("password"));
        }
        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }

    public function updateAfter($model, $arrayData, $metaData, $id = null)
    {
        $userId = $model->id ?? $id;
        $this->syncUserRespo($userId, $arrayData);
        return [
            "model" => $model,
            "data"  => array_merge($arrayData, [
                "is_sync" => false,
            ]),
        ];
    }
    

    public function custom_reset_password($req)
    {
        if (req("password") && !req("password_confirm")) {
            return response([
                "status" => "error",
                "message" => "Masukkan password Konfirmasi",
                "errors" => ["password_confirm" => ["Masukkan password Konfirmasi"]]
            ], 422);
        }

        if (req("password") && req("password") != req("password_confirm")) {
            return response([
                "status" => "error",
                "message" => "Konfirmasi password salah",
                "errors" => ["password_confirm" => ["Konfirmasi password salah"]]
            ], 422);
        }

        $hasher = app()->make("hash");
        $userId = $req->id_user ?? auth()->user()->id;

        $updateData = [];
        if ($req->email && $req->username) {
            $updateData['username'] = $req->username;
            $updateData['email'] = $req->email;
        }
        if (isset($req->is_active)) {
            $updateData['is_active'] = $req->is_active ? 1 : 0;
        }
        if (!empty($req->m_dir_id)) {
            $updateData['m_dir_id'] = $req->m_dir_id;
        }
        if (!empty($req->m_comp_id)) {
            $updateData['m_comp_id'] = $req->m_comp_id;
        }
        if (!empty($updateData)) {
            \DB::table('default_users')->where('id', $userId)->update($updateData);
        }

        if (req("password")) {
            \DB::table('default_users')->where('id', $userId)->update([
                'password' => $hasher->make(req("password"))
            ]);
        }

        // Sinkronkan daftar responsibility milik user
        $this->syncUserRespo($userId, $req);

        return response([
            'message' => 'Update pengguna berhasil'
        ]);
    }

    /**
     * Sinkronkan daftar responsibility ke tabel default_users_respo
     */
    private function syncUserRespo($userId, $req)
    {
        if (!$userId) return;

        $respoList = null;
        if (is_object($req) && method_exists($req, 'input')) {
            $respoList = $req->input('default_users_respo') ?? $req->default_users_respo;
        } elseif (is_object($req)) {
            $respoList = $req->default_users_respo ?? null;
        } elseif (is_array($req)) {
            $respoList = $req['default_users_respo'] ?? null;
        }
        if ($respoList === null && app()->request) {
            $respoList = app()->request->input('default_users_respo') ?? app()->request->default_users_respo;
        }

        if (!is_array($respoList)) {
            return;
        }

        \DB::table('default_users_respo')->where('default_users_id', $userId)->delete();

        $now = \Carbon\Carbon::now();
        $authId = auth()->user()->id ?? $userId;
        $primaryRespoId = null;

        $seq = 1;
        foreach ($respoList as $item) {
            $row = is_array($item) ? $item : (array)$item;
            $mRespoId = !empty($row['m_respo_id']) ? (int)$row['m_respo_id'] : null;
            if (!$mRespoId) continue;

            $isPrimary = !empty($row['is_primary']) && ($row['is_primary'] === true || $row['is_primary'] === 1 || $row['is_primary'] === '1' || $row['is_primary'] === 'true');
            $isActive = isset($row['is_active']) ? ($row['is_active'] === true || $row['is_active'] === 1 || $row['is_active'] === '1' || $row['is_active'] === 'true') : true;

            if ($isPrimary) {
                $primaryRespoId = $mRespoId;
            }

            \DB::table('default_users_respo')->insert([
                'default_users_id' => $userId,
                'm_respo_id'       => $mRespoId,
                'seq'              => $row['seq'] ?? $seq++,
                'is_primary'       => $isPrimary ? 1 : 0,
                'is_active'        => $isActive ? 1 : 0,
                'creator_id'       => $authId,
                'last_editor_id'   => $authId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // Jika belum ada yang primary tapi list ada isinya, jadikan item pertama sebagai primary
        if (!$primaryRespoId && count($respoList) > 0) {
            $first = is_array($respoList[0]) ? $respoList[0] : (array)$respoList[0];
            $primaryRespoId = !empty($first['m_respo_id']) ? (int)$first['m_respo_id'] : null;
            if ($primaryRespoId) {
                \DB::table('default_users_respo')
                    ->where('default_users_id', $userId)
                    ->where('m_respo_id', $primaryRespoId)
                    ->update(['is_primary' => 1]);
            }
        }

        // Sinkronkan m_dir_id dan m_comp_id pada default_users sesuai primary respo
        if ($primaryRespoId) {
            $respoData = \DB::table('m_respo')->where('id', $primaryRespoId)->first();
            if ($respoData) {
                $updateUser = [];
                if (!empty($respoData->m_dir_id)) {
                    $updateUser['m_dir_id'] = $respoData->m_dir_id;
                }
                if (!empty($respoData->m_comp_id)) {
                    $updateUser['m_comp_id'] = $respoData->m_comp_id;
                }
                if (!empty($updateUser)) {
                    \DB::table('default_users')->where('id', $userId)->update($updateUser);
                }
            }
        }
    }


    public function scopePic($model){

        $kary_id = default_users::where('id', auth()->user()->id)->pluck('m_kary_id')->first();
        return $model->whereRaw("default_users.id = ? or default_users.m_kary_id in (select k.id from default_users u join m_kary k on k.id = u.m_kary_id where k.atasan_id = ?)", [auth()->user()->id ?? 0, $kary_id]);
    }

    public function public_phpinfo(){
        return phpinfo();
    }
}
