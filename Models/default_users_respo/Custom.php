<?php

namespace App\Models\CustomModels;

class default_users_respo extends \App\Models\BasicModels\default_users_respo
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns = [];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function scopeWithRespoDetails($model)
    {
        $userId = auth()->user()->id ?? request('user_id');
        $model
            ->join('m_respo', 'm_respo.id', '=', 'default_users_respo.m_respo_id')
            ->leftJoin('m_dir', 'm_dir.id', '=', 'm_respo.m_dir_id')
            ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_respo.m_divisi_id')
            ->leftJoin('m_dept', 'm_dept.id', '=', 'm_respo.m_dept_id')
            ->select([
                'default_users_respo.*',
                'm_respo.kode as respo_kode',
                'm_respo.nama as respo_nama',
                'm_respo.m_comp_id',
                'm_respo.m_dir_id',
                'm_respo.m_divisi_id',
                'm_respo.m_dept_id',
                'm_dir.nama as dir_nama',
                'm_divisi.nama as divisi_nama',
                'm_dept.nama as dept_nama'
            ]);
            
        if ($userId) {
            $model->where('default_users_respo.default_users_id', $userId);
        }
        
        return $model;
    }

    /**
     * Endpoint API: Mendapatkan daftar responsibility milik user login
     * GET /operation/default_users_respo/getMyRespo
     */
    public function custom_getMyRespo($request)
    {
        $userId = auth()->user()->id ?? 0;
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $list = \DB::table('default_users_respo as dur')
            ->join('m_respo as r', 'r.id', '=', 'dur.m_respo_id')
            ->leftJoin('m_dir as d', 'd.id', '=', 'r.m_dir_id')
            ->leftJoin('m_divisi as div', 'div.id', '=', 'r.m_divisi_id')
            ->leftJoin('m_dept as dept', 'dept.id', '=', 'r.m_dept_id')
            ->where('dur.default_users_id', $userId)
            ->where('dur.is_active', true)
            ->where('r.is_active', true)
            ->select([
                'dur.id as user_respo_id',
                'dur.m_respo_id',
                'dur.is_primary',
                'dur.seq',
                'r.nama as respo_nama',
                'r.kode as respo_kode',
                'r.m_comp_id',
                'r.m_dir_id',
                'd.nama as dir_nama',
                'r.m_divisi_id',
                'div.nama as divisi_nama',
                'r.m_dept_id',
                'dept.nama as dept_nama'
            ])
            ->orderBy('dur.seq', 'asc')
            ->orderBy('dur.id', 'asc')
            ->get();

        $activeRespo = $list->firstWhere('is_primary', true) ?: $list->first();

        return response()->json([
            'success'      => true,
            'list'         => $list,
            'active_respo' => $activeRespo,
            'count'        => count($list)
        ]);
    }

    /**
     * Endpoint API: Switch active responsibility user login
     * POST /operation/default_users_respo/switchRespo
     * Payload: { m_respo_id: 123 } atau { user_respo_id: 456 }
     */
    public function custom_switchRespo($request)
    {
        $userId = auth()->user()->id ?? 0;
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $req = $request instanceof \Illuminate\Http\Request ? $request : app()->request;
        $mRespoId = $req->m_respo_id ?? $req->input('m_respo_id');
        $userRespoId = $req->user_respo_id ?? $req->input('user_respo_id');

        $query = \DB::table('default_users_respo')
            ->where('default_users_id', $userId)
            ->where('is_active', true);

        if ($mRespoId) {
            $target = (clone $query)->where('m_respo_id', $mRespoId)->first();
        } elseif ($userRespoId) {
            $target = (clone $query)->where('id', $userRespoId)->first();
        } else {
            return response()->json(['error' => 'Parameter m_respo_id atau user_respo_id wajib diisi.'], 422);
        }

        if (!$target) {
            return response()->json(['error' => 'Responsibility tidak ditemukan atau tidak aktif untuk akun Anda.'], 404);
        }

        try {
            \DB::beginTransaction();

            // Reset semua respo user menjadi non-primary
            \DB::table('default_users_respo')
                ->where('default_users_id', $userId)
                ->update(['is_primary' => false]);

            // Set respo terpilih menjadi primary
            \DB::table('default_users_respo')
                ->where('id', $target->id)
                ->update([
                    'is_primary'     => true,
                    'last_editor_id' => $userId,
                    'updated_at'     => \Carbon\Carbon::now()
                ]);

            // Ambil data respo & direktorat terpilih
            $respo = \DB::table('m_respo as r')
                ->leftJoin('m_dir as d', 'd.id', '=', 'r.m_dir_id')
                ->where('r.id', $target->m_respo_id)
                ->select(['r.*', 'd.nama as dir_nama'])
                ->first();

            // Opsional: sinkronkan m_dir_id pada tabel default_users agar konsisten dengan query lama
            if (!empty($respo->m_dir_id)) {
                \DB::table('default_users')
                    ->where('id', $userId)
                    ->update([
                        'm_dir_id'   => $respo->m_dir_id,
                        'm_comp_id'  => $respo->m_comp_id ?? 1,
                        'updated_at' => \Carbon\Carbon::now()
                    ]);
            }

            \DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Berhasil berpindah ke responsibility: ' . ($respo->nama ?? '-'),
                'active_respo' => $respo
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Gagal berpindah responsibility: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint API: Mendapatkan daftar responsibility milik user tertentu (untuk form m_pengguna)
     * GET /operation/default_users_respo/getUserRespo?user_id=123
     */
    public function custom_getUserRespo($request)
    {
        $req = $request instanceof \Illuminate\Http\Request ? $request : app()->request;
        $userId = $req->user_id ?? $req->default_users_id ?? request('user_id');
        if (!$userId) {
            return response()->json(['data' => []]);
        }

        $list = \DB::table('default_users_respo as dur')
            ->join('m_respo as r', 'r.id', '=', 'dur.m_respo_id')
            ->leftJoin('m_dir as d', 'd.id', '=', 'r.m_dir_id')
            ->leftJoin('m_divisi as div', 'div.id', '=', 'r.m_divisi_id')
            ->leftJoin('m_dept as dept', 'dept.id', '=', 'r.m_dept_id')
            ->where('dur.default_users_id', $userId)
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

        return response()->json(['data' => $list]);
    }
}

