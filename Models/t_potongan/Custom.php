<?php

namespace App\Models\CustomModels;

use Illuminate\Database\Eloquent\Builder;

class t_potongan extends \App\Models\BasicModels\t_potongan
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }

    protected static function boot()
    {
        parent::boot();

        // Isolasi Transaksi Potongan sesuai active Respo:
        // Superadmin = semua PT, HRD/Admin = PT/Divisi/Dept active Respo, User biasa = milik sendiri (+ approver saat GET single detail)
        static::addGlobalScope('respo_scope', function (Builder $builder) {
            $respoCore = getCore('Respo');
            if (!$respoCore || !$respoCore->isOperationCrudRequest('t_potongan', ['GET', 'PUT', 'PATCH', 'DELETE'])) {
                return;
            }

            $respoCore->applyActiveEmployeeTransactionScopeToQuery($builder, 't_potongan', [
                'trx_table'                => 't_potongan',
                'dir_col'                  => 't_potongan.m_dir_id',
                'divisi_col'               => null,
                'dept_col'                 => null,
                'kary_fk_col'              => 't_potongan.m_kary_id',
                'allow_approver_on_single' => true,
            ]);
        });
    }
    
    public $fileColumns    = ['doc'];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData  = array_merge( $arrayData,[
            'nomor' => $this->helper->generateNomor('KODE POTONGAN')
        ]);
        if (!empty($newArrayData['is_all_kary'])) {
            $newArrayData['m_kary_id'] = null;
        }
        $targetKary = !empty($newArrayData['m_kary_id']) ? \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->first() : null;
        if (!empty($newArrayData['m_kary_id']) && !$targetKary) {
            return [
                "model" => $model,
                "data"  => $arrayData,
                "errors" => ["Data karyawan tidak ditemukan."],
            ];
        }
        if ($targetKary && empty($newArrayData['is_all_kary'])) {
            if (!empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Company transaksi potongan harus sesuai dengan Company karyawan terpilih."],
                ];
            }
            if (empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id)) {
                $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
            }
            if (empty($newArrayData['m_comp_id']) && !empty($targetKary->m_comp_id)) {
                $newArrayData['m_comp_id'] = (int) $targetKary->m_comp_id;
            }
        }
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }

        // Validasi Role & Scope Respo Aktif saat Create Potongan
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

            if (!$respoCore->isUserHrdOrAdmin($authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Hanya HRD/Admin atau Superadmin yang berhak membuat transaksi potongan."],
                ];
            }

            if (!empty($newArrayData['is_all_kary']) && empty($newArrayData['m_dir_id']) && empty($scope['is_unrestricted']) && !empty($scope['m_dir_id'])) {
                $newArrayData['m_dir_id'] = (int) $scope['m_dir_id'];
            }

            $effDirId = $newArrayData['m_dir_id'] ?? ($targetKary ? $targetKary->m_dir_id : null);
            $targetDivId = $targetKary ? $targetKary->m_divisi_id : null;
            $targetDeptId = $targetKary ? $targetKary->m_dept_id : null;

            if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetDivId, $targetDeptId, $authUser)) {
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

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $existing = \DB::table('t_potongan')->where('id', $id)->first();
        if (!$existing) {
            return [
                "model" => $model,
                "data"  => $arrayData,
                "errors" => ["Data transaksi potongan tidak ditemukan."],
            ];
        }
        $newArrayData = $arrayData;
        $isAllKary = array_key_exists('is_all_kary', $newArrayData) ? !empty($newArrayData['is_all_kary']) : !empty($existing->is_all_kary);
        if ($isAllKary) {
            $newArrayData['m_kary_id'] = null;
        }
        $targetKaryId = array_key_exists('m_kary_id', $newArrayData) ? $newArrayData['m_kary_id'] : $existing->m_kary_id;
        $targetKary = !empty($targetKaryId) ? \DB::table('m_kary')->where('id', $targetKaryId)->first() : null;
        if (!empty($targetKaryId) && !$targetKary) {
            return [
                "model" => $model,
                "data"  => $arrayData,
                "errors" => ["Data karyawan tidak ditemukan."],
            ];
        }
        if ($targetKary && !$isAllKary) {
            if (!empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Company transaksi potongan harus sesuai dengan Company karyawan terpilih."],
                ];
            }
            if (empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id)) {
                $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
            }
            if (empty($newArrayData['m_comp_id']) && !empty($targetKary->m_comp_id)) {
                $newArrayData['m_comp_id'] = (int) $targetKary->m_comp_id;
            }
        }
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = $existing->m_comp_id ?? 1;
        }

        // Validasi Role & Scope Respo Aktif saat Update Potongan
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

            if (!$respoCore->isUserHrdOrAdmin($authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Hanya HRD/Admin atau Superadmin yang berhak mengubah transaksi potongan."],
                ];
            }

            $existKary = !empty($existing->m_kary_id) ? \DB::table('m_kary')->where('id', $existing->m_kary_id)->first() : null;
            $existDirId = $existing->m_dir_id ?? ($existKary ? $existKary->m_dir_id : null);
            $existDivId = $existKary ? $existKary->m_divisi_id : null;
            $existDeptId = $existKary ? $existKary->m_dept_id : null;

            if (!$respoCore->matchesActiveCompanyScope($existDirId, $existDivId, $existDeptId, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Tidak diizinkan mengubah transaksi potongan di luar scope responsibility aktif."],
                ];
            }

            $effDirId = array_key_exists('m_dir_id', $newArrayData)
                ? ($newArrayData['m_dir_id'] ?? ($targetKary ? $targetKary->m_dir_id : null))
                : ($existing->m_dir_id ?? ($targetKary ? $targetKary->m_dir_id : null));
            $targetDivId = $targetKary ? $targetKary->m_divisi_id : null;
            $targetDeptId = $targetKary ? $targetKary->m_dept_id : null;

            if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetDivId, $targetDeptId, $authUser)) {
                return [
                    "model" => $model,
                    "data"  => $arrayData,
                    "errors" => ["Target karyawan atau Perusahaan/Cabang berada di luar scope responsibility aktif."],
                ];
            }
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData,
        ];
    }

    public function transformRowData( array $row )
    {
        $totalBayar = \DB::table('t_potongan_det_bayar')
            ->where('t_potongan_id', $row['id'])
            ->sum('nilai');

        $nilaiTotal = (float) ($row['nilai'] ?? 0);
        $isLunas = !empty($row['is_lunas']) ? 1 : ($totalBayar >= $nilaiTotal && $nilaiTotal > 0 ? 1 : 0);
        $sisa = $isLunas ? 0 : max(0, $nilaiTotal - (float) $totalBayar);
        $statusBayar = $row['status_bayar'] ?? ($isLunas ? 'LUNAS' : ($totalBayar > 0 ? 'SEBAGIAN' : 'BELUM BAYAR'));

        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')->where('k.id', $row['m_kary_id'])->value('d.nama');
        }
        if (empty($compNama) && !empty($row['is_all_kary']) && empty($row['m_dir_id'])) {
            $compNama = 'Semua Company';
        }

        return array_merge( $row, [
            'm_dir.nama'    => $compNama ?? ($row['m_dir.nama'] ?? '-'),
            'total_bayar'   => (float) $totalBayar,
            'sisa_angsuran' => (float) $sisa,
            'is_lunas'      => (bool) $isLunas,
            'status_bayar'  => $statusBayar,
        ]);
    }

    private function canAccessPotongan($potongan, bool $requireHrdManage = false)
    {
        $respoCore = getCore('Respo');
        if (!$respoCore) {
            return null;
        }
        $authUser = app()->bound('auth') ? app('auth')->user() : (auth()->check() ? auth()->user() : null);
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }
        if ($respoCore->isUserSuperadmin($authUser)) {
            return null;
        }

        $scope = $respoCore->getActiveCompanyScope($authUser);
        if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
            return response()->json(['error' => 'Responsibility aktif tidak ditemukan atau sedang nonaktif.'], 403);
        }

        $kary = !empty($potongan->m_kary_id) ? \DB::table('m_kary')->where('id', $potongan->m_kary_id)->first() : null;
        $effDirId = !empty($potongan->m_dir_id) ? (int) $potongan->m_dir_id : ($kary ? (int) ($kary->m_dir_id ?? 0) : null);
        $effDivId = $kary ? ($kary->m_divisi_id ?? null) : null;
        $effDeptId = $kary ? ($kary->m_dept_id ?? null) : null;
        $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
        $inScope = $respoCore->matchesActiveCompanyScope($effDirId, $effDivId, $effDeptId, $authUser);

        if ($requireHrdManage) {
            if (!$isHrdOrAdmin || !$inScope) {
                return response()->json(['error' => 'Anda tidak berwenang mengelola transaksi potongan di luar scope responsibility aktif.'], 403);
            }
            return null;
        }

        $userKaryId = (int) ($authUser->m_kary_id ?? 0);
        $uid = (int) ($authUser->id ?? 0);
        $isOwner = ($userKaryId > 0 && (int) ($potongan->m_kary_id ?? 0) === $userKaryId)
            || ($uid > 0 && (int) ($potongan->creator_id ?? 0) === $uid);
        $isAtasan = ($kary && $userKaryId > 0 && !empty($kary->atasan_id) && (int) $kary->atasan_id === $userKaryId);

        if ($isOwner || $isAtasan || ($isHrdOrAdmin && $inScope)) {
            return null;
        }

        return response()->json(['error' => 'Anda tidak berwenang mengakses transaksi potongan ini.'], 403);
    }

    public function custom_pelunasanManual($request)
    {
        $id = $request->id ?? $request->input('id');
        $potongan = t_potongan::find($id);

        if (!$potongan) {
            return response()->json(['error' => 'Data piutang tidak ditemukan.'], 404);
        }

        $accessErr = $this->canAccessPotongan($potongan, true);
        if ($accessErr) {
            return $accessErr;
        }

        if (strtoupper($potongan->status ?? '') !== 'POSTED') {
            return response()->json(['error' => 'Piutang harus berstatus POSTED untuk dapat dilunasi.'], 422);
        }

        if ($potongan->is_lunas) {
            return response()->json(['message' => 'Piutang ini sudah berstatus LUNAS.'], 200);
        }

        // Tanggal pelunasan manual (adjustable date dari request)
        $paidAtRaw = $request->paid_at ?? $request->input('paid_at');
        $paidAt = \Carbon\Carbon::now();
        if (!empty($paidAtRaw)) {
            try {
                $paidAt = \Carbon\Carbon::parse($paidAtRaw);
            } catch (\Exception $e) {
                $paidAt = \Carbon\Carbon::now();
            }
        }

        try {
            \DB::beginTransaction();

            $totalBayar = \DB::table('t_potongan_det_bayar')
                ->where('t_potongan_id', $potongan->id)
                ->sum('nilai');

            $nilaiTotal = (float) ($potongan->nilai ?? 0);
            $sisaPelunasan = max(0, $nilaiTotal - (float) $totalBayar);

            if ($sisaPelunasan > 0) {
                \DB::table('t_potongan_det_bayar')->insert([
                    'm_comp_id'       => $potongan->m_comp_id ?? 1,
                    't_potongan_id'   => $potongan->id,
                    't_final_gaji_id' => null, // Pelunasan manual di luar payroll rutin
                    'percentage'      => $potongan->percentage ?? 0,
                    'nilai'           => $sisaPelunasan,
                    'paid_at'         => $paidAt,
                    'creator_id'      => auth()->user()->id ?? null,
                    'created_at'      => \Carbon\Carbon::now(),
                    'updated_at'      => \Carbon\Carbon::now(),
                ]);
            }

            $potongan->update([
                'is_lunas'     => 1,
                'status_bayar' => 'LUNAS MANUAL'
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Piutang berhasil dilunasi secara manual (Sisa Rp ' . number_format($sisaPelunasan, 0, ',', '.') . ', Tanggal: ' . $paidAt->format('d/m/Y') . ').'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Gagal melunasi piutang: ' . $e->getMessage()], 500);
        }
    }

    public function custom_getHistoryBayar($request)
    {
        try {
            $req = $request instanceof \Illuminate\Http\Request ? $request : app()->request;
            $id = $req->id ?? ($req->input ? $req->input('id') : null) ?? $req->query('id');
            $potongan = $this->find($id);

            if (!$potongan) {
                return response()->json(['error' => 'Data piutang tidak ditemukan.'], 404);
            }

            $accessErr = $this->canAccessPotongan($potongan, false);
            if ($accessErr) {
                return $accessErr;
            }

            $history = \DB::table('t_potongan_det_bayar as pdb')
                ->leftJoin('t_final_gaji as fg', 'fg.id', '=', 'pdb.t_final_gaji_id')
                ->where('pdb.t_potongan_id', $potongan->id)
                ->select([
                    'pdb.id',
                    'pdb.t_potongan_id',
                    'pdb.t_final_gaji_id',
                    'pdb.nilai',
                    'pdb.paid_at',
                    'pdb.created_at',
                    'fg.nomor as nomor_final_gaji',
                    'fg.desc as desc_final_gaji'
                ])
                ->orderBy('pdb.paid_at', 'asc')
                ->orderBy('pdb.id', 'asc')
                ->get();

            $totalBayar = 0;
            $items = [];
            $no = 1;

            foreach ($history as $row) {
                $nilai = (float) ($row->nilai ?? 0);
                $totalBayar += $nilai;
                $isPayroll = !empty($row->t_final_gaji_id);
                $metode = $isPayroll ? 'POTONG GAJI' : 'PELUNASAN MANUAL';
                $keterangan = $isPayroll 
                    ? ('Pemotongan Payroll (' . ($row->nomor_final_gaji ?: 'Final Gaji #' . $row->t_final_gaji_id) . ')')
                    : 'Pelunasan Tunai / Luar Sistem';

                $items[] = [
                    'no'               => $no++,
                    'id'               => $row->id,
                    'paid_at'          => !empty($row->paid_at) ? \Carbon\Carbon::parse($row->paid_at)->format('d/m/Y H:i') : '-',
                    'paid_date'        => !empty($row->paid_at) ? \Carbon\Carbon::parse($row->paid_at)->format('d/m/Y') : '-',
                    'nilai'            => $nilai,
                    'nilai_formatted'  => 'Rp ' . number_format($nilai, 0, ',', '.'),
                    'metode'           => $metode,
                    'keterangan'       => $keterangan,
                    'nomor_final_gaji' => $row->nomor_final_gaji ?? null,
                ];
            }

            $nilaiTotal = (float) ($potongan->nilai ?? 0);
            $sisa = max(0, $nilaiTotal - $totalBayar);
            $isLunas = $potongan->is_lunas || ($totalBayar >= $nilaiTotal && $nilaiTotal > 0);

            return response()->json([
                'potongan' => [
                    'id'                    => $potongan->id,
                    'nomor'                 => $potongan->nomor,
                    'nilai'                 => $nilaiTotal,
                    'nilai_formatted'       => 'Rp ' . number_format($nilaiTotal, 0, ',', '.'),
                    'total_bayar'           => $totalBayar,
                    'total_bayar_formatted' => 'Rp ' . number_format($totalBayar, 0, ',', '.'),
                    'sisa_angsuran'         => $sisa,
                    'sisa_formatted'        => 'Rp ' . number_format($sisa, 0, ',', '.'),
                    'is_lunas'              => (bool) $isLunas,
                    'status'                => $potongan->status,
                    'status_bayar'          => $potongan->status_bayar ?? ($isLunas ? 'LUNAS' : ($totalBayar > 0 ? 'SEBAGIAN' : 'BELUM BAYAR')),
                ],
                'history'  => $items,
                'count'    => count($items)
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal memuat riwayat pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function custom_postData($request)
    {
        $id = $request->id ?? $request->input('id');
        $data = t_potongan::find($id);

        if (!$data) {
            return response()->json(['error' => 'Data tidak ditemukan.'], 404);
        }

        $accessErr = $this->canAccessPotongan($data, true);
        if ($accessErr) {
            return $accessErr;
        }

        try {
            $update = $data->update([
                'status' => "POSTED"
            ]);

            if ($update) {
                return response()->json(['message' => 'Data berhasil diposting.']);
            } else {
                return response()->json(['error' => 'Gagal memperbarui status.'], 500);
            }
        } catch (\Exception $e) {
            // Handle exception, log error messages, etc.
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    
}