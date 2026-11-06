<?php

namespace App\Models\CustomModels;

class m_respo extends \App\Models\BasicModels\m_respo
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns = [];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        if (empty($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = 1;
        }
        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }
}

