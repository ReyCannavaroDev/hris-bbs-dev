<?php

namespace App\Models\CustomModels;

class m_standart_gaji extends \App\Models\BasicModels\m_standart_gaji
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
        $details = app()->request->m_standart_gaji_det ?? [];
        if (empty($details) || count($details) === 0) {
            return [
                "model"  => $model,
                "data"   => $arrayData,
                "errors" => ['Detail komponen gaji wajib diisi minimal 1 data']
            ];
        }

        return [
            "model"  => $model,
            "data"   => array_merge($arrayData,[
                'kode' =>  app()->request->kode ?? $this->helper->generateNomor('KODE STANDART GAJI'),
                //'m_dir_id' =>  $m_dir_id
            ])
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $details = app()->request->m_standart_gaji_det ?? [];
        if (empty($details) || count($details) === 0) {
            return [
                "model"  => $model,
                "data"   => $arrayData,
                "errors" => ['Detail komponen gaji wajib diisi minimal 1 data']
            ];
        }

        return [
            "model"  => $model,
            "data"   => $arrayData
        ];
    }
    
}