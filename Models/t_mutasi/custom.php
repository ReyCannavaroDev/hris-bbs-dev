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
        if (empty($karyId)) {
            return [
                "status" => "error",
                "message" => "NIK / Karyawan wajib dipilih.",
                "errors" => ["m_kary_id" => ["NIK / Karyawan wajib dipilih."]]
            ];
        }

        // Cek apakah karyawan masih memiliki transaksi mutasi aktif berstatus DRAFT
        $existingDraft = \DB::table('t_mutasi')
            ->where('m_kary_id', $karyId)
            ->where('status', 'DRAFT')
            ->first();
        if ($existingDraft) {
            return [
                "status" => "error",
                "message" => "Karyawan ini masih memiliki transaksi mutasi aktif berstatus DRAFT (Nomor: " . ($existingDraft->nomor ?: '-') . "). Selesaikan atau batalkan transaksi tersebut terlebih dahulu sebelum membuat mutasi baru.",
                "errors" => ["m_kary_id" => ["Karyawan ini masih memiliki transaksi mutasi aktif berstatus DRAFT."]]
            ];
        }

        $mDirId = $arrayData['m_dir_id'] ?? null;
        $mCompId = $arrayData['m_comp_id'] ?? null;
        $kary = \DB::table('m_kary')->where('id', $karyId)->first();

        if ($kary) {
            $mDirId = $mDirId ?: $kary->m_dir_id;
            $mCompId = $mCompId ?: ($kary->m_comp_id ?: 1);
        }

        // Ambil data divisi, departemen, posisi, dan gaji aktual dari master karyawan
        $divisiLamaId   = $kary ? $kary->m_divisi_id : ($arrayData['m_divisi_lama_id'] ?? null);
        $deptLamaId     = $kary ? $kary->m_dept_id : ($arrayData['m_dept_lama_id'] ?? null);
        $posisiLamaId   = $kary ? $kary->m_posisi_id : ($arrayData['m_posisi_lama_id'] ?? null);
        $standartLamaId = $kary ? $kary->m_standart_gaji_id : ($arrayData['m_standart_posisi_id'] ?? null);
        $gajiPokokLama  = $kary ? ($kary->gaji_pokok ?: 0) : ($arrayData['gaji_pokok_lama'] ?? 0);
        $gajiAcuanLama  = $kary ? ($kary->gaji_acuan ?: 0) : ($arrayData['gaji_acuan_lama'] ?? 0);

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
            "m_divisi_lama_id" => $divisiLamaId,
            "m_dept_lama_id" => $deptLamaId,
            "m_posisi_lama_id" => $posisiLamaId,
            "m_standart_posisi_id" => $standartLamaId,
            "gaji_pokok_lama" => (float)$gajiPokokLama,
            "gaji_pokok_baru" => (float)$gajiPokokBaru,
            "gaji_acuan_lama" => (float)$gajiAcuanLama,
            "gaji_acuan_baru" => (float)$gajiAcuanBaru,
        ]);

        unset(
            $newArrayData['id'],
            $newArrayData['created_at'],
            $newArrayData['updated_at']
        );

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
                "status" => "error",
                "message" => "Data transaksi yang sudah berstatus POSTED tidak dapat diubah.",
                "errors" => ["status" => ["Data transaksi yang sudah berstatus POSTED tidak dapat diubah."]],
            ];
        }

        $karyId = $arrayData['m_kary_id'] ?? ($existing ? $existing->m_kary_id : null);
        if ($karyId) {
            // Cek apakah ada draft mutasi lain untuk karyawan ini selain ID transaksi ini
            $otherDraft = \DB::table('t_mutasi')
                ->where('m_kary_id', $karyId)
                ->where('status', 'DRAFT')
                ->where('id', '!=', $id)
                ->first();
            if ($otherDraft) {
                return [
                    "status" => "error",
                    "message" => "Terdapat transaksi mutasi DRAFT lain untuk karyawan ini (Nomor: " . ($otherDraft->nomor ?: '-') . ").",
                    "errors" => ["m_kary_id" => ["Terdapat transaksi mutasi DRAFT lain untuk karyawan ini."]]
                ];
            }

            // Validasi integritas: pastikan data aktual karyawan saat ini di m_kary masih cocok dengan data lama pada transaksi ini
            $kary = \DB::table('m_kary')->where('id', $karyId)->first();
            if ($kary) {
                $divisiLama = $arrayData['m_divisi_lama_id'] ?? ($existing ? $existing->m_divisi_lama_id : null);
                $deptLama   = $arrayData['m_dept_lama_id'] ?? ($existing ? $existing->m_dept_lama_id : null);
                $posisiLama = $arrayData['m_posisi_lama_id'] ?? ($existing ? $existing->m_posisi_lama_id : null);

                $isDivisiBeda = (int)($kary->m_divisi_id ?? 0) !== (int)($divisiLama ?? 0);
                $isDeptBeda   = (int)($kary->m_dept_id ?? 0) !== (int)($deptLama ?? 0);
                $isPosisiBeda = (int)($kary->m_posisi_id ?? 0) !== (int)($posisiLama ?? 0);

                if ($isDivisiBeda || $isDeptBeda || $isPosisiBeda) {
                    return [
                        "status" => "error",
                        "message" => "Data transaksi mutasi ini sudah tidak valid karena data divisi, departemen, atau posisi aktual karyawan saat ini telah berubah (telah dimutasi pada transaksi lain). Transaksi ini tidak dapat disimpan.",
                        "errors" => ["m_divisi_lama_id" => ["Divisi/Departemen/Posisi lama sudah tidak sesuai dengan data aktual karyawan saat ini."]]
                    ];
                }
            }
        }

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

            $data = \DB::table('t_mutasi')->where('id', $request->id)->first();
            if (!$data) {
                \DB::rollBack();
                return response()->json(
                    ["error" => "Data tidak ditemukan."],
                    404
                );
            }

            if (strtoupper($data->status) === 'POSTED') {
                \DB::rollBack();
                return response()->json(
                    ["error" => "Data transaksi mutasi ini sudah berstatus POSTED."],
                    422
                );
            }

            $karyawan = \DB::table('m_kary')->where('id', $data->m_kary_id)->first();
            if (!$karyawan) {
                \DB::rollBack();
                return response()->json(
                    ["error" => "Data karyawan tidak ditemukan."],
                    404
                );
            }

            // Validasi integritas: pastikan kondisi divisi, departemen, dan posisi karyawan saat ini di m_kary masih sama dengan data lama di transaksi ini
            $isDivisiBeda = (int)($karyawan->m_divisi_id ?? 0) !== (int)($data->m_divisi_lama_id ?? 0);
            $isDeptBeda   = (int)($karyawan->m_dept_id ?? 0) !== (int)($data->m_dept_lama_id ?? 0);
            $isPosisiBeda = (int)($karyawan->m_posisi_id ?? 0) !== (int)($data->m_posisi_lama_id ?? 0);

            if ($isDivisiBeda || $isDeptBeda || $isPosisiBeda) {
                \DB::rollBack();
                return response()->json(
                    ["error" => "Data transaksi mutasi ini sudah tidak valid karena karyawan bersangkutan telah dimutasi sebelumnya (Divisi/Departemen/Posisi lama pada data ini sudah tidak sesuai dengan data aktual karyawan saat ini). Transaksi ini tidak dapat diposting."],
                    422
                );
            }

            $update = \DB::table('t_mutasi')->where('id', $data->id)->update([
                "status" => "POSTED",
                "updated_at" => date('Y-m-d H:i:s'),
            ]);

            $updateKaryPayload = [
                "m_divisi_id" => $data->m_devisi_baru_id,
                "m_dept_id" => $data->m_dept_baru_id,
                "m_posisi_id" => $data->m_posisi_baru_id,
                "m_standart_gaji_id" => $data->m_standart_baru_id,
                "updated_at" => date('Y-m-d H:i:s'),
            ];
            if ($data->m_dir_id) {
                $updateKaryPayload["m_dir_id"] = $data->m_dir_id;
            }
            if ($data->m_comp_id) {
                $updateKaryPayload["m_comp_id"] = $data->m_comp_id;
            }
            // Sinkronisasi gaji pokok baru dan gaji acuan baru ke master karyawan
            if (isset($data->gaji_pokok_baru) && (float)$data->gaji_pokok_baru > 0) {
                $updateKaryPayload["gaji_pokok"] = (float)$data->gaji_pokok_baru;
            }
            if (isset($data->gaji_acuan_baru) && (float)$data->gaji_acuan_baru > 0) {
                $updateKaryPayload["gaji_acuan"] = (float)$data->gaji_acuan_baru;
            }

            $updateKary = \DB::table('m_kary')->where('id', $karyawan->id)->update($updateKaryPayload);

            if ($update !== false && $updateKary !== false) {
                // If both updates are successful, commit the transaction
                \DB::commit();

                return response()->json([
                    "message" => "Data mutasi berhasil diposting. Master karyawan dan perhitungan gaji prorata otomatis diperbarui.",
                ]);
            } else {
                // If any update fails, rollback the transaction
                \DB::rollBack();

                return response()->json(
                    ["error" => "Gagal memperbarui status transaksi atau data master karyawan."],
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
