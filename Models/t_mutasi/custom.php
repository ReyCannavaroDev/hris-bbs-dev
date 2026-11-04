<?php

namespace App\Models\CustomModels;

class t_mutasi extends \App\Models\BasicModels\t_mutasi
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
        $newCols = ['gaji_pokok_lama', 'gaji_pokok_baru', 'gaji_acuan_lama', 'gaji_acuan_baru'];
        $this->fillable    = array_merge($this->fillable, $newCols);
        $this->columns     = array_merge($this->columns, $newCols);
        $this->columnsFull = array_merge($this->columnsFull, [
            'gaji_pokok_lama:decimal',
            'gaji_pokok_baru:decimal',
            'gaji_acuan_lama:decimal',
            'gaji_acuan_baru:decimal'
        ]);
        $this->createable  = array_merge($this->createable, $newCols);
        $this->updateable  = array_merge($this->updateable, $newCols);
        $this->searchable  = array_merge($this->searchable, $newCols);
    }

    public $fileColumns = [
        'file_dokumen'
    ];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $karyId = $arrayData['m_kary_id'] ?? null;
        $mDirId = $arrayData['m_dir_id'] ?? null;
        $mCompId = $arrayData['m_comp_id'] ?? null;
        $kary = null;

        if ($karyId) {
            $kary = \DB::table('m_kary')->where('id', $karyId)->first();
            if ($kary) {
                $mDirId = $mDirId ?: $kary->m_dir_id;
                $mCompId = $mCompId ?: ($kary->m_comp_id ?: 1);
            }
        }

        // Ambil nilai gaji pokok & acuan lama dari master karyawan
        $gajiPokokLama = $arrayData['gaji_pokok_lama'] ?? ($kary ? ($kary->gaji_pokok ?: 0) : 0);
        $gajiAcuanLama = $arrayData['gaji_acuan_lama'] ?? ($kary ? ($kary->gaji_acuan ?: 0) : 0);
        $gajiPokokBaru = $arrayData['gaji_pokok_baru'] ?? $gajiPokokLama;
        $gajiAcuanBaru = $arrayData['gaji_acuan_baru'] ?? $gajiAcuanLama;

        // Jika standar baru dipilih dan gaji baru masih kosong/0, default dari standar baru
        if ((empty($gajiPokokBaru) || (float)$gajiPokokBaru == 0) && !empty($arrayData['m_standart_baru_id'])) {
            $standartBaru = \DB::table('m_standart_gaji')->where('id', $arrayData['m_standart_baru_id'])->first();
            if ($standartBaru) {
                $gajiPokokBaru = $standartBaru->gaji_pokok ?: $gajiPokokBaru;
                $gajiAcuanBaru = $standartBaru->gaji_pokok ?: $gajiAcuanBaru;
            }
        }

        $newArrayData = array_merge($arrayData, [
            "nomor" => $this->helper->generateNomor("KODE MUTASI"),
            "status" => "DRAFT",
            "m_dir_id" => $mDirId,
            "m_comp_id" => $mCompId ?: 1,
            "gaji_pokok_lama" => (float)$gajiPokokLama,
            "gaji_pokok_baru" => (float)$gajiPokokBaru,
            "gaji_acuan_lama" => (float)$gajiAcuanLama,
            "gaji_acuan_baru" => (float)$gajiAcuanBaru,
        ]);

        return [
            "model" => $model,
            "data" => $newArrayData,
        ];
    }

    public function updateBefore($model, $arrayData, $metaData, $id = null)
    {
        $existing = \DB::table('t_mutasi')->where('id', $id)->first();
        if ($existing && strtoupper($existing->status) === 'POSTED') {
            return [
                "model" => $model,
                "data" => $arrayData,
                "errors" => ["Data transaksi yang sudah berstatus POSTED tidak dapat diubah."],
            ];
        }

        $karyId = $arrayData['m_kary_id'] ?? ($existing ? $existing->m_kary_id : null);
        $mDirId = $arrayData['m_dir_id'] ?? ($existing ? $existing->m_dir_id : null);
        $mCompId = $arrayData['m_comp_id'] ?? ($existing ? $existing->m_comp_id : null);

        if ($karyId && (!$mDirId || !$mCompId)) {
            $kary = \DB::table('m_kary')->where('id', $karyId)->select('m_dir_id', 'm_comp_id', 'gaji_pokok', 'gaji_acuan')->first();
            if ($kary) {
                $mDirId = $mDirId ?: $kary->m_dir_id;
                $mCompId = $mCompId ?: ($kary->m_comp_id ?: 1);
            }
        }

        $newArrayData = array_merge($arrayData, [
            "m_dir_id" => $mDirId,
            "m_comp_id" => $mCompId ?: 1,
        ]);

        return [
            "model" => $model,
            "data" => $newArrayData,
        ];
    }

    public function custom_postData($request)
    {
        try {
            // Begin a database transaction
            \DB::beginTransaction();

            $data = t_mutasi::find($request->id);
            if (!$data) {
                return response()->json(
                    ["error" => "Data tidak ditemukan."],
                    404
                );
            }

            if (strtoupper($data->status) === 'POSTED') {
                return response()->json(
                    ["error" => "Data transaksi mutasi ini sudah berstatus POSTED."],
                    422
                );
            }

            $karyawan = \App\Models\CustomModels\m_kary::find($data["m_kary_id"]);
            if (!$karyawan) {
                return response()->json(
                    ["error" => "Data karyawan tidak ditemukan."],
                    404
                );
            }

            // Validasi integritas: pastikan kondisi karyawan saat ini di m_kary masih sama dengan data lama di transaksi ini
            if (
                $karyawan->m_divisi_id != $data["m_divisi_lama_id"] ||
                ($data["m_dept_lama_id"] && $karyawan->m_dept_id != $data["m_dept_lama_id"]) ||
                $karyawan->m_posisi_id != $data["m_posisi_lama_id"]
            ) {
                \DB::rollBack();
                return response()->json(
                    ["error" => "Data karyawan saat ini sudah berubah atau telah dimutasi sebelumnya. Divisi/Departemen/Posisi lama pada data ini sudah tidak sesuai dengan data karyawan saat ini, sehingga tidak dapat diposting."],
                    422
                );
            }

            $update = $data->update([
                "status" => "POSTED",
            ]);

            $updateKaryPayload = [
                "m_divisi_id" => $data["m_devisi_baru_id"],
                "m_dept_id" => $data["m_dept_baru_id"],
                "m_posisi_id" => $data["m_posisi_baru_id"],
                "m_standart_gaji_id" => $data["m_standart_baru_id"],
            ];
            if ($data["m_dir_id"]) {
                $updateKaryPayload["m_dir_id"] = $data["m_dir_id"];
            }
            if ($data["m_comp_id"]) {
                $updateKaryPayload["m_comp_id"] = $data["m_comp_id"];
            }
            // Sinkronisasi gaji pokok baru dan gaji acuan baru ke master karyawan
            if (isset($data["gaji_pokok_baru"]) && (float)$data["gaji_pokok_baru"] > 0) {
                $updateKaryPayload["gaji_pokok"] = (float)$data["gaji_pokok_baru"];
            }
            if (isset($data["gaji_acuan_baru"]) && (float)$data["gaji_acuan_baru"] > 0) {
                $updateKaryPayload["gaji_acuan"] = (float)$data["gaji_acuan_baru"];
            }

            $updateKary = $karyawan->update($updateKaryPayload);

            if ($update && $updateKary) {
                // If both updates are successful, commit the transaction
                \DB::commit();

                return response()->json([
                    "message" => "Data mutasi berhasil diposting. Master karyawan dan perhitungan gaji prorata otomatis diperbarui.",
                ]);
            } else {
                // If any update fails, rollback the transaction
                \DB::rollBack();

                return response()->json(
                    ["error" => "Gagal memperbarui status."],
                    500
                );
            }
        } catch (\Exception $e) {
            // Rollback the transaction in case of any exception
            \DB::rollBack();

            return response()->json(
                ["error" => "Terjadi kesalahan: " . $e->getMessage()],
                500
            );
        }
    }
}
