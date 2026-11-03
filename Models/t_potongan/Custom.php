<?php

namespace App\Models\CustomModels;

class t_potongan extends \App\Models\BasicModels\t_potongan
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = ['doc'];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData  = array_merge( $arrayData,[
            'nomor' => $this->helper->generateNomor('KODE POTONGAN')
        ]);
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }
       
        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
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

        return array_merge( $row, [
            'total_bayar'   => (float) $totalBayar,
            'sisa_angsuran' => (float) $sisa,
            'is_lunas'      => (bool) $isLunas,
        ]);
    }

    public function custom_pelunasanManual($request)
    {
        $id = $request->id ?? $request->input('id');
        $potongan = t_potongan::find($id);

        if (!$potongan) {
            return response()->json(['error' => 'Data piutang tidak ditemukan.'], 404);
        }

        if (strtoupper($potongan->status ?? '') !== 'POSTED') {
            return response()->json(['error' => 'Piutang harus berstatus POSTED untuk dapat dilunasi.'], 422);
        }

        if ($potongan->is_lunas) {
            return response()->json(['message' => 'Piutang ini sudah berstatus LUNAS.'], 200);
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
                    'paid_at'         => \Carbon\Carbon::now(),
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
                'message' => 'Piutang berhasil dilunasi secara manual (Sisa Rp ' . number_format($sisaPelunasan, 0, ',', '.') . ').'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Gagal melunasi piutang: ' . $e->getMessage()], 500);
        }
    }

    public function custom_postData($request)
    {
        $data = t_potongan::find($request->id);

        if (!$data) {
            return response()->json(['error' => 'Data tidak ditemukan.'], 404);
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