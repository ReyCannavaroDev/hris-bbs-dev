<?php

namespace App\Models\CustomModels;

class m_zona extends \App\Models\BasicModels\m_zona
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData = array_merge($arrayData, [
            'kode' => $this->helper->generateNomor('KODE ZONA'),
        ]);
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = 1;
        }
        if (empty($newArrayData['m_dir_id']) && auth()->check() && !empty(auth()->user()->m_dir_id)) {
            $newArrayData['m_dir_id'] = auth()->user()->m_dir_id;
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData
        ];
    }
    
    
}