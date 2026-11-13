<?php

namespace App\Models\CustomModels;

class t_final_gaji_det_rincian extends \App\Models\BasicModels\t_final_gaji_det_rincian
{    
    public function __construct()
    {
        parent::__construct();
        // Kosongkan $joins agar generator tidak melakukan INNER JOIN ke t_potongan dan t_cuti
        // yang menyebabkan baris rincian komponen gaji biasa tereliminasi
        $this->joins = [];
        $this->details = [];
        $this->detailsChild = [];
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

        $cleanData['name'] = $cleanData['name'] ?? $cleanData['label'] ?? '-';
        $cleanData['label'] = !empty($cleanData['label']) ? $cleanData['label'] : '-';
        $cleanData['type'] = !empty($cleanData['type']) ? $cleanData['type'] : 'Bulanan';
        $cleanData['factor'] = !empty($cleanData['factor']) ? $cleanData['factor'] : '+';
        $cleanData['value'] = (float)($cleanData['value'] ?? 0);
        $cleanData['value_ref'] = isset($cleanData['value_ref']) && $cleanData['value_ref'] !== '' && $cleanData['value_ref'] !== null ? (float)$cleanData['value_ref'] : $cleanData['value'];
        $cleanData['seq'] = (float)($cleanData['seq'] ?? 1);
        $cleanData['can_adjust'] = isset($cleanData['can_adjust']) ? (bool)$cleanData['can_adjust'] : true;
        $cleanData['status'] = $cleanData['status'] ?? 'DRAFT';

        if (isset($cleanData['detail']) && is_array($cleanData['detail'])) {
            $cleanData['detail'] = json_encode($cleanData['detail']);
        } elseif (empty($cleanData['detail'])) {
            $cleanData['detail'] = null;
        }

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

        if (array_key_exists('label', $cleanData) && empty($cleanData['label'])) $cleanData['label'] = '-';
        if (array_key_exists('type', $cleanData) && empty($cleanData['type'])) $cleanData['type'] = 'Bulanan';
        if (array_key_exists('factor', $cleanData) && empty($cleanData['factor'])) $cleanData['factor'] = '+';
        if (array_key_exists('value', $cleanData)) $cleanData['value'] = (float)($cleanData['value'] ?? 0);
        if (array_key_exists('seq', $cleanData)) $cleanData['seq'] = (float)($cleanData['seq'] ?? 1);

        if (isset($cleanData['detail']) && is_array($cleanData['detail'])) {
            $cleanData['detail'] = json_encode($cleanData['detail']);
        } elseif (array_key_exists('detail', $cleanData) && empty($cleanData['detail'])) {
            $cleanData['detail'] = null;
        }

        return [
            "model"  => $model,
            "data"   => $cleanData,
        ];
    }
}