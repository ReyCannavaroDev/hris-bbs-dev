<?php

namespace App\Models\CustomModels;

class t_final_gaji extends \App\Models\BasicModels\t_final_gaji
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        // Kosongkan $joins agar generator tidak melakukan INNER JOIN otomatis
        // yang menyebabkan data dengan last_editor_id NULL tereliminasi dari landing
        $this->joins = [];
        // Kosongkan $details dan $detailsChild agar generator tidak memproses eager loading hasMany
        // yang memicu HTTP 500 / CORS error di core generator backend
        $this->details = [];
        $this->detailsChild = [];
    }

    public function t_final_gaji_det(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\Models\CustomModels\t_final_gaji_det', 't_final_gaji_id', 'id');
    }

    private function normalizeDateValue($val)
    {
        if ($val === null) return null;
        $str = trim((string)$val);
        if ($str === '' || $str === '-' || strtolower($str) === 'null') return null;

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $str, $m)) {
            $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function transformRowData( $row )
    {
        $rowArr = is_array($row) ? $row : (array) $row;
        $id = $rowArr['id'] ?? null;

        $dirNama = '-';
        $divisiNama = '-';
        $deptNama = '-';

        if ($id) {
            try {
                $det = \DB::table('t_final_gaji_det')
                    ->leftJoin('m_dir', 'm_dir.id', '=', 't_final_gaji_det.m_kary_dir_id')
                    ->leftJoin('m_divisi', 'm_divisi.id', '=', 't_final_gaji_det.m_kary_divisi_id')
                    ->leftJoin('m_dept', 'm_dept.id', '=', 't_final_gaji_det.m_kary_dept_id')
                    ->where('t_final_gaji_det.t_final_gaji_id', $id)
                    ->select('m_dir.nama as dir_nama', 'm_divisi.nama as divisi_nama', 'm_dept.nama as dept_nama')
                    ->first();
                if ($det) {
                    if (!empty($det->dir_nama)) $dirNama = $det->dir_nama;
                    if (!empty($det->divisi_nama)) $divisiNama = $det->divisi_nama;
                    if (!empty($det->dept_nama)) $deptNama = $det->dept_nama;
                }
            } catch (\Throwable $e) {}
        }

        $rowArr['m_dir.nama'] = $dirNama;
        $rowArr['m_divisi.nama'] = $divisiNama;
        $rowArr['m_dept.nama'] = $deptNama;

        $rowArr['company'] = $dirNama;
        $rowArr['divisi'] = $divisiNama;
        $rowArr['dept'] = $deptNama;

        return is_array($row) ? $rowArr : (object) $rowArr;
    }

    public function m_dir() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dir', 'm_dir_id', 'id');
    }

    public function m_divisi() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_divisi', 'm_divisi_id', 'id');
    }

    public function m_dept() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dept', 'm_dept_id', 'id');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id", "last_editor_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $cleanData = $arrayData;
        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at'], $cleanData['m_dir_id'], $cleanData['m_divisi_id'], $cleanData['m_dept_id'], $cleanData['t_final_gaji_det']);

        // Normalisasi format tanggal
        if (isset($cleanData['periode_awal'])) {
            $cleanData['periode_awal'] = $this->normalizeDateValue($cleanData['periode_awal']);
        }
        if (isset($cleanData['periode_akhir'])) {
            $cleanData['periode_akhir'] = $this->normalizeDateValue($cleanData['periode_akhir']);
        }

        // Field NOT NULL diberi default aman
        if (!isset($cleanData['total_pengeluaran_gaji']) || $cleanData['total_pengeluaran_gaji'] === '' || $cleanData['total_pengeluaran_gaji'] === null) {
            $cleanData['total_pengeluaran_gaji'] = 0;
        } else {
            $cleanData['total_pengeluaran_gaji'] = (float)$cleanData['total_pengeluaran_gaji'];
        }

        if (!isset($cleanData['desc']) || $cleanData['desc'] === null || trim((string)$cleanData['desc']) === '') {
            $cleanData['desc'] = '-';
        }

        $cleanData['nomor'] = !empty($cleanData['nomor']) ? $cleanData['nomor'] : $this->helper->generateNomor('KODE FINAL GAJI');
        $cleanData['m_comp_id'] = $cleanData['m_comp_id'] ?? (auth()->check() ? auth()->user()->m_comp_id : 1) ?? 1;
        $cleanData['status'] = $cleanData['status'] ?? 'DRAFT';

        return [
            "model"  => $model,
            "data"   => $cleanData,
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $cleanData = $arrayData;
        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at'], $cleanData['creator_id'], $cleanData['m_dir_id'], $cleanData['m_divisi_id'], $cleanData['m_dept_id'], $cleanData['t_final_gaji_det']);

        // Normalisasi format tanggal
        if (isset($cleanData['periode_awal'])) {
            $cleanData['periode_awal'] = $this->normalizeDateValue($cleanData['periode_awal']);
        }
        if (isset($cleanData['periode_akhir'])) {
            $cleanData['periode_akhir'] = $this->normalizeDateValue($cleanData['periode_akhir']);
        }

        // Field NOT NULL diberi default aman
        if (array_key_exists('total_pengeluaran_gaji', $cleanData)) {
            if ($cleanData['total_pengeluaran_gaji'] === '' || $cleanData['total_pengeluaran_gaji'] === null) {
                $cleanData['total_pengeluaran_gaji'] = 0;
            } else {
                $cleanData['total_pengeluaran_gaji'] = (float)$cleanData['total_pengeluaran_gaji'];
            }
        }

        if (array_key_exists('desc', $cleanData)) {
            if ($cleanData['desc'] === null || trim((string)$cleanData['desc']) === '') {
                $cleanData['desc'] = '-';
            }
        }

        return [
            "model"  => $model,
            "data"   => $cleanData,
        ];
    }

    public function createAfter($model, $arrayData, $metaData, $id = null)
    {
        $finalGajiId = $id ?? ($model->id ?? ($arrayData['id'] ?? null));
        $this->saveDetails($finalGajiId, $arrayData);
    }

    public function updateAfter($model, $arrayData, $metaData, $id = null)
    {
        $finalGajiId = $id ?? ($model->id ?? ($arrayData['id'] ?? null));
        $this->saveDetails($finalGajiId, $arrayData);
    }

    private function saveDetails($finalGajiId, $arrayData)
    {
        if (!$finalGajiId) {
            return;
        }

        $details = $arrayData['t_final_gaji_det'] ?? (request()->input('t_final_gaji_det') ?? []);
        if (empty($details) || !is_array($details)) {
            $raw = request()->getContent();
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['t_final_gaji_det'])) {
                    $details = $decoded['t_final_gaji_det'];
                }
            }
        }

        if (empty($details) || !is_array($details)) {
            return;
        }

        $dirId = $arrayData['m_dir_id'] ?? (request()->input('m_dir_id') ?? ($details[0]['m_kary_dir_id'] ?? null));
        $divisiId = $arrayData['m_divisi_id'] ?? (request()->input('m_divisi_id') ?? ($details[0]['m_kary_divisi_id'] ?? null));
        $deptId = $arrayData['m_dept_id'] ?? (request()->input('m_dept_id') ?? ($details[0]['m_kary_dept_id'] ?? null));

        $existingDetIds = \DB::table('t_final_gaji_det')->where('t_final_gaji_id', $finalGajiId)->pluck('id')->toArray();
        $sentDetIds = [];

        foreach ($details as $d) {
            $detData = [
                't_final_gaji_id'       => $finalGajiId,
                't_perhitungan_gaji_id' => $d['t_perhitungan_gaji_id'] ?? null,
                'm_kary_id'             => $d['m_kary_id'] ?? null,
                'm_kary_dir_id'         => $d['m_kary_dir_id'] ?? $dirId,
                'm_kary_divisi_id'      => $d['m_kary_divisi_id'] ?? $divisiId,
                'm_kary_dept_id'        => $d['m_kary_dept_id'] ?? $deptId,
                'periode'               => $d['periode'] ?? '-',
                'periode_in_date'       => !empty($d['periode_in_date']) ? $this->normalizeDateValue($d['periode_in_date']) : null,
                'total_gaji'            => (float)($d['total_gaji'] ?? 0),
                'total_tax'             => (float)($d['total_tax'] ?? 0),
                'netto'                 => (float)($d['netto'] ?? 0),
                'periode_id'            => $d['periode_id'] ?? null,
                'deskripsi'             => $d['deskripsi'] ?? '',
                'status'                => $d['status'] ?? 'DRAFT',
                'updated_at'            => \Carbon\Carbon::now(),
            ];

            if (!empty($d['id']) && in_array($d['id'], $existingDetIds)) {
                \DB::table('t_final_gaji_det')->where('id', $d['id'])->update($detData);
                $detId = $d['id'];
            } else {
                $detData['created_at'] = \Carbon\Carbon::now();
                $detData['creator_id'] = auth()->check() ? auth()->id() : 1;
                $detId = \DB::table('t_final_gaji_det')->insertGetId($detData);
            }
            $sentDetIds[] = $detId;

            // Simpan child rincian komponen gaji (t_final_gaji_det_rincian)
            $rincianList = $d['t_final_gaji_det_rincian'] ?? ($d['detail_gaji'] ?? []);
            if (!empty($rincianList) && is_array($rincianList)) {
                \DB::table('t_final_gaji_det_rincian')->where('t_final_gaji_det_id', $detId)->delete();
                $insertRincian = [];
                foreach ($rincianList as $idx => $r) {
                    $insertRincian[] = [
                        't_final_gaji_det_id' => $detId,
                        'seq'                 => (float)($r['seq'] ?? ($idx + 1)),
                        'name'                => $r['name'] ?? ($r['label'] ?? '-'),
                        'label'               => $r['label'] ?? ($r['name'] ?? '-'),
                        'type'                => $r['type'] ?? 'Bulanan',
                        'factor'              => $r['factor'] ?? '+',
                        'value_ref'           => isset($r['value_ref']) ? (float)$r['value_ref'] : (float)($r['value'] ?? 0),
                        'value'               => (float)($r['value'] ?? 0),
                        'can_adjust'          => isset($r['can_adjust']) ? (bool)$r['can_adjust'] : true,
                        'detail'              => !empty($r['detail']) ? (is_array($r['detail']) ? json_encode($r['detail']) : $r['detail']) : null,
                        'deskripsi'           => $r['deskripsi'] ?? null,
                        'status'              => $r['status'] ?? 'DRAFT',
                        't_potongan_id'       => $r['t_potongan_id'] ?? null,
                        't_cuti_id'           => $r['t_cuti_id'] ?? null,
                        'creator_id'          => auth()->check() ? auth()->id() : 1,
                        'created_at'          => \Carbon\Carbon::now(),
                        'updated_at'          => \Carbon\Carbon::now(),
                    ];
                }
                if (!empty($insertRincian)) {
                    \DB::table('t_final_gaji_det_rincian')->insert($insertRincian);
                }
            }
        }

        $deletedDetIds = array_diff($existingDetIds, $sentDetIds);
        if (!empty($deletedDetIds)) {
            \DB::table('t_final_gaji_det_rincian')->whereIn('t_final_gaji_det_id', $deletedDetIds)->delete();
            \DB::table('t_final_gaji_det')->whereIn('id', $deletedDetIds)->delete();
        }
    }

    public function custom_getDetail($request)
    {
        $id = $request->id ?? ($request->t_final_gaji_id ?? null);
        if (!$id) {
            return response()->json(['data' => []]);
        }

        try {
            $dets = \DB::table('t_final_gaji_det')
                ->leftJoin('m_kary', 'm_kary.id', '=', 't_final_gaji_det.m_kary_id')
                ->leftJoin('m_dir', 'm_dir.id', '=', 't_final_gaji_det.m_kary_dir_id')
                ->leftJoin('m_divisi', 'm_divisi.id', '=', 't_final_gaji_det.m_kary_divisi_id')
                ->leftJoin('m_dept', 'm_dept.id', '=', 't_final_gaji_det.m_kary_dept_id')
                ->where('t_final_gaji_det.t_final_gaji_id', $id)
                ->select(
                    't_final_gaji_det.*',
                    'm_kary.nik as kary_nik',
                    'm_kary.nama_lengkap as kary_nama',
                    'm_dir.nama as dir_nama',
                    'm_divisi.nama as divisi_nama',
                    'm_dept.nama as dept_nama'
                )
                ->orderBy('t_final_gaji_det.id')
                ->get();

            foreach ($dets as $d) {
                $d->{'m_kary.nik'} = $d->kary_nik ?? '';
                $d->{'m_kary.nama_lengkap'} = $d->kary_nama ?? '';
                $d->karyawan = $d->kary_nama ?? '';
                $d->{'m_kary_dir.nama'} = $d->dir_nama ?? '-';
                $d->{'m_kary_divisi.nama'} = $d->divisi_nama ?? '-';
                $d->{'m_kary_dept.nama'} = $d->dept_nama ?? '-';

                $rincian = \DB::table('t_final_gaji_det_rincian')
                    ->where('t_final_gaji_det_id', $d->id)
                    ->orderBy('seq')
                    ->get();
                $d->t_final_gaji_det_rincian = $rincian;
                $d->detail_gaji = $rincian;
            }

            return response()->json([
                'data' => $dets
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'error' => $e->getMessage()
            ], 200);
        }
    }

    public function custom_tes()
    {
        $det = t_final_gaji_det::with(['t_final_gaji_det_rincian' => function ($query) {
        $query->where('t_potongan_id', '!=', null);
        }])
        ->where('t_final_gaji_id', 1)
        ->get();


        return response()->json($det);
    }
    
    public function custom_postData($request)
    {
        \DB::beginTransaction();
        $data = t_final_gaji::find($request->id);

        if (!$data) {
            return response()->json(['error' => 'Data tidak ditemukan.'], 404);
        }

        try {

            $update = $data->update([
                'status' => "POSTED"
            ]);
            $create = true;
            $det = t_final_gaji_det::with(['t_final_gaji_det_rincian' => function ($query) {
            $query->where('t_potongan_id', '!=', null);
            }])
            ->where('t_final_gaji_id', $request->id)
            ->get();
            foreach($det as $dt){
                foreach($dt['t_final_gaji_det_rincian'] as $dt1){
                    $potongan = t_potongan::where('id', $dt1['t_potongan_id'])->first();
                    if ($potongan) {
                        $create = t_potongan_det_bayar::create([
                            't_potongan_id'   => $dt1['t_potongan_id'],
                            't_final_gaji_id' => $request->id,
                            'percentage'      => $potongan->percentage ?? 0,
                            'nilai'           => $dt1['value'],
                            'paid_at'         => \Carbon\Carbon::now()
                        ]) && $create;

                        // Periksa apakah total pembayaran sudah melunasi nilai pinjaman
                        $totalBayar = t_potongan_det_bayar::where('t_potongan_id', $potongan->id)->sum('nilai');
                        if ($totalBayar >= (float) $potongan->nilai) {
                            $potongan->update(['is_lunas' => 1]);
                        }
                    }
                }
            }

            if ($update && $create) {
                 \DB::commit(); 
                return response()->json(['message' => 'Data berhasil diposting.']);
            } else {
                \DB::rollback();
                return response()->json(['error' => 'Gagal memperbarui status.'], 500);
            }
        } catch (\Exception $e) {
            \DB::rollBack();
            // Handle exception, log error messages, etc.
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }   
}