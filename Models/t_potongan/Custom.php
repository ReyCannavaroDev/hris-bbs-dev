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
        $statusBayar = $row['status_bayar'] ?? ($isLunas ? 'LUNAS' : ($totalBayar > 0 ? 'SEBAGIAN' : 'BELUM BAYAR'));

        return array_merge( $row, [
            'total_bayar'   => (float) $totalBayar,
            'sisa_angsuran' => (float) $sisa,
            'is_lunas'      => (bool) $isLunas,
            'status_bayar'  => $statusBayar,
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
        $id = $request->id ?? $request->input('id');
        $potongan = t_potongan::find($id);

        if (!$potongan) {
            return response()->json(['error' => 'Data piutang tidak ditemukan.'], 404);
        }

        $history = \DB::table('t_potongan_det_bayar as pdb')
            ->leftJoin('t_final_gaji as fg', 'fg.id', '=', 'pdb.t_final_gaji_id')
            ->leftJoin('default_users as u', 'u.id', '=', 'pdb.creator_id')
            ->where('pdb.t_potongan_id', $potongan->id)
            ->select([
                'pdb.id',
                'pdb.t_potongan_id',
                'pdb.t_final_gaji_id',
                'pdb.nilai',
                'pdb.paid_at',
                'pdb.created_at',
                'fg.nomor as nomor_final_gaji',
                'fg.keterangan as ket_final_gaji',
                'u.name as creator_name'
            ])
            ->orderBy('pdb.paid_at', 'asc')
            ->orderBy('pdb.id', 'asc')
            ->get();

        $totalBayar = 0;
        $items = [];
        $no = 1;

        foreach ($history as $row) {
            $nilai = (float) $row->nilai;
            $totalBayar += $nilai;
            $metode = !empty($row->t_final_gaji_id) ? 'POTONG GAJI' : 'PELUNASAN MANUAL';
            $keterangan = !empty($row->t_final_gaji_id) 
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
                'creator_name'     => $row->creator_name ?? 'Sistem',
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