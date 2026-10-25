<?php

namespace App\Models\CustomModels;

class t_final_gaji extends \App\Models\BasicModels\t_final_gaji
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->fillable = array_merge($this->fillable, ['m_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->createable = array_merge($this->createable, ['m_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->updateable = array_merge($this->updateable, ['m_dir_id', 'm_divisi_id', 'm_dept_id']);
        $this->searchable = array_merge($this->searchable, ['m_dir_id', 'm_divisi_id', 'm_dept_id']);
        // Kosongkan $joins agar generator tidak melakukan INNER JOIN otomatis
        // yang menyebabkan data dengan m_divisi_id, m_dept_id, atau last_editor_id NULL tereliminasi dari landing
        $this->joins = [];
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
        $req = app()->request;
        $newArrayData  = array_merge( $arrayData,[
            'nomor' => $this->helper->generateNomor('KODE FINAL GAJI'),
            'm_comp_id' => $arrayData['m_comp_id'] ?? (auth()->check() ? auth()->user()->m_comp_id : 1) ?? 1,
            'status' => $arrayData['status'] ?? 'DRAFT'
        ]);
       
        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
        ];
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