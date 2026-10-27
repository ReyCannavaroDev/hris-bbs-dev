<?php

namespace App\Models\CustomModels;

class t_jadwal_kerja_det_hari extends \App\Models\BasicModels\t_jadwal_kerja_det_hari
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id=null)
    {
        if (strtoupper($arrayData['tipe_hari'] ?? '') === 'LIBUR') {
            $arrayData['m_jam_kerja_id'] = null;
            $arrayData['waktu_mulai'] = !empty($arrayData['waktu_mulai']) ? $arrayData['waktu_mulai'] : '00:00:00';
            $arrayData['waktu_akhir'] = !empty($arrayData['waktu_akhir']) ? $arrayData['waktu_akhir'] : '00:00:00';
        }
        return [
            "model" => $model,
            "data" => $arrayData
        ];
    }

    public function updateBefore($model, $arrayData, $metaData, $id=null)
    {
        if (strtoupper($arrayData['tipe_hari'] ?? '') === 'LIBUR') {
            $arrayData['m_jam_kerja_id'] = null;
            $arrayData['waktu_mulai'] = !empty($arrayData['waktu_mulai']) ? $arrayData['waktu_mulai'] : '00:00:00';
            $arrayData['waktu_akhir'] = !empty($arrayData['waktu_akhir']) ? $arrayData['waktu_akhir'] : '00:00:00';
        }
        return [
            "model" => $model,
            "data" => $arrayData
        ];
    }
}