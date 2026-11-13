<?php

namespace App\Models\CustomModels;

class t_final_gaji_det extends \App\Models\BasicModels\t_final_gaji_det
{    
    public function __construct()
    {
        parent::__construct();
        // Kosongkan $joins agar generator tidak melakukan INNER JOIN otomatis
        // yang menyebabkan data detail dengan foreign key nullable tereliminasi
        $this->joins = [];
        $this->details = [];
        $this->detailsChild = [];
    }

    public function t_final_gaji_det_rincian(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\Models\CustomModels\t_final_gaji_det_rincian', 't_final_gaji_det_id', 'id');
    }

    public function transformRowData( $row )
    {
        $rowArr = is_array($row) ? $row : (array) $row;
        $karyId = $rowArr['m_kary_id'] ?? null;
        $dirId = $rowArr['m_kary_dir_id'] ?? null;
        $divisiId = $rowArr['m_kary_divisi_id'] ?? null;
        $deptId = $rowArr['m_kary_dept_id'] ?? null;

        $nik = '';
        $namaKary = '';
        if (!empty($karyId)) {
            try {
                $kary = \DB::table('m_kary')->where('id', $karyId)->select('nik', 'nama_lengkap')->first();
                if ($kary) {
                    $nik = $kary->nik ?? '';
                    $namaKary = $kary->nama_lengkap ?? '';
                }
            } catch (\Throwable $e) {}
        }

        $dirNama = '-';
        if (!empty($dirId)) {
            try {
                $dirNama = \DB::table('m_dir')->where('id', $dirId)->value('nama') ?? '-';
            } catch (\Throwable $e) {}
        }
        $divisiNama = '-';
        if (!empty($divisiId)) {
            try {
                $divisiNama = \DB::table('m_divisi')->where('id', $divisiId)->value('nama') ?? '-';
            } catch (\Throwable $e) {}
        }
        $deptNama = '-';
        if (!empty($deptId)) {
            try {
                $deptNama = \DB::table('m_dept')->where('id', $deptId)->value('nama') ?? '-';
            } catch (\Throwable $e) {}
        }

        $rowArr['m_kary.nik'] = $nik;
        $rowArr['m_kary.nama_lengkap'] = $namaKary;
        $rowArr['karyawan'] = $namaKary;
        $rowArr['m_kary_dir.nama'] = $dirNama;
        $rowArr['m_kary_divisi.nama'] = $divisiNama;
        $rowArr['m_kary_dept.nama'] = $deptNama;
        $rowArr['total_tax'] = $rowArr['total_tax'] ?? 0;
        $rowArr['total_gaji'] = $rowArr['total_gaji'] ?? 0;
        $rowArr['netto'] = $rowArr['netto'] ?? 0;

        return is_array($row) ? $rowArr : (object) $rowArr;
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id", "last_editor_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $allowedColumns = $this->createable ?: $this->columns;
        $cleanData = [];
        foreach ($arrayData as $key => $val) {
            if (in_array($key, $allowedColumns)) {
                $cleanData[$key] = $val;
            }
        }
        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at']);

        $cleanData['total_gaji'] = (float)($cleanData['total_gaji'] ?? 0);
        $cleanData['total_tax'] = (float)($cleanData['total_tax'] ?? 0);
        $cleanData['netto'] = (float)($cleanData['netto'] ?? 0);
        $cleanData['periode'] = !empty($cleanData['periode']) ? $cleanData['periode'] : '-';
        $cleanData['status'] = $cleanData['status'] ?? 'DRAFT';

        return [
            "model"  => $model,
            "data"   => $cleanData,
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $allowedColumns = $this->updateable ?: $this->columns;
        $cleanData = [];
        foreach ($arrayData as $key => $val) {
            if (in_array($key, $allowedColumns)) {
                $cleanData[$key] = $val;
            }
        }
        unset($cleanData['id'], $cleanData['created_at'], $cleanData['updated_at'], $cleanData['creator_id']);

        if (array_key_exists('total_gaji', $cleanData)) $cleanData['total_gaji'] = (float)($cleanData['total_gaji'] ?? 0);
        if (array_key_exists('total_tax', $cleanData)) $cleanData['total_tax'] = (float)($cleanData['total_tax'] ?? 0);
        if (array_key_exists('netto', $cleanData)) $cleanData['netto'] = (float)($cleanData['netto'] ?? 0);
        if (array_key_exists('periode', $cleanData) && empty($cleanData['periode'])) $cleanData['periode'] = '-';

        return [
            "model"  => $model,
            "data"   => $cleanData,
        ];
    }

    
}