<?php

namespace App\Models\CustomModels;

class t_cuti_adjustment extends \App\Models\BasicModels\t_cuti_adjustment
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

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;
      if (empty($newArrayData['m_comp_id'])) {
        $newArrayData['m_comp_id'] = 1;
      }
      if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
        $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
      }
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;
      if (empty($newArrayData['m_dir_id']) && !empty($newArrayData['m_kary_id'])) {
        $newArrayData['m_dir_id'] = \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->value('m_dir_id');
      }
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }
}