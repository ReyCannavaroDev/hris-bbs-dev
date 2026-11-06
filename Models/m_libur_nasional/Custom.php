<?php

namespace App\Models\CustomModels;

class m_libur_nasional extends \App\Models\BasicModels\m_libur_nasional
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    private function normalizeDateDb($val)
    {
        if (empty($val)) return null;
        $val = trim((string)$val);
        if (str_contains($val, '/')) {
            $parts = explode('/', $val);
            if (count($parts) === 3) {
                return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
            }
        }
        return date('Y-m-d', strtotime($val));
    }

    public function transformRowData(array $row)
    {
        if (!empty($row['tanggal'])) {
            $row['tanggal_raw'] = $row['tanggal'];
            $row['tanggal_formatted'] = date('d/m/Y', strtotime($row['tanggal']));
        }
        $isCuti = !empty($row['is_cuti_bersama']) && ($row['is_cuti_bersama'] == 1 || $row['is_cuti_bersama'] === true || $row['is_cuti_bersama'] === '1');
        $row['tipe_libur'] = $isCuti ? 'Cuti Bersama' : 'Libur Nasional';
        $row['potong_cuti_label'] = $isCuti ? 'Potong Cuti' : 'Bebas Cuti';
        return $row;
    }

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        if (!empty($arrayData['tanggal'])) {
            $arrayData['tanggal'] = $this->normalizeDateDb($arrayData['tanggal']);
        }
        $isCuti = !empty($arrayData['is_cuti_bersama']) && ($arrayData['is_cuti_bersama'] == 1 || $arrayData['is_cuti_bersama'] === true || $arrayData['is_cuti_bersama'] === '1');
        $arrayData['is_cuti_bersama'] = $isCuti ? 1 : 0;
        $arrayData['is_potong_cuti']  = $isCuti ? 1 : 0;

        if (empty($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = auth()->user()->m_comp_id ?? 1;
        }

        return [
            "model"  => $model,
            "data"   => array_merge($arrayData,[
                'kode' =>  $this->helper->generateNomor('KODE LIBUR NASIONAL'),
            ])
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        if (!empty($arrayData['tanggal'])) {
            $arrayData['tanggal'] = $this->normalizeDateDb($arrayData['tanggal']);
        }
        if (array_key_exists('is_cuti_bersama', $arrayData)) {
            $isCuti = !empty($arrayData['is_cuti_bersama']) && ($arrayData['is_cuti_bersama'] == 1 || $arrayData['is_cuti_bersama'] === true || $arrayData['is_cuti_bersama'] === '1');
            $arrayData['is_cuti_bersama'] = $isCuti ? 1 : 0;
            $arrayData['is_potong_cuti']  = $isCuti ? 1 : 0;
        }
        return [
            "model"  => $model,
            "data"   => $arrayData
        ];
    }
}
