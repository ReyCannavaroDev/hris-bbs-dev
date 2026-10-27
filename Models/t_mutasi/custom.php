<?php

namespace App\Models\CustomModels;

class t_mutasi extends \App\Models\BasicModels\t_mutasi
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
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

        if ($karyId && (!$mDirId || !$mCompId)) {
            $kary = \DB::table('m_kary')->where('id', $karyId)->select('m_dir_id', 'm_comp_id')->first();
            if ($kary) {
                $mDirId = $mDirId ?: $kary->m_dir_id;
                $mCompId = $mCompId ?: ($kary->m_comp_id ?: 1);
            }
        }

        $newArrayData = array_merge($arrayData, [
            "nomor" => $this->helper->generateNomor("KODE MUTASI"),
            "status" => "DRAFT",
            "m_dir_id" => $mDirId,
            "m_comp_id" => $mCompId ?: 1,
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
            $kary = \DB::table('m_kary')->where('id', $karyId)->select('m_dir_id', 'm_comp_id')->first();
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

            $updateKary = $karyawan->update($updateKaryPayload);

            if ($update && $updateKary) {
                // If both updates are successful, commit the transaction
                \DB::commit();

                return response()->json([
                    "message" => "Data berhasil diposting.",
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
