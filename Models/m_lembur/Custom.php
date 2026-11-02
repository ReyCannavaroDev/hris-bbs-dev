<?php

namespace App\Models\CustomModels;

class m_lembur extends \App\Models\BasicModels\m_lembur
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData  = array_merge( $arrayData,[
        'm_dir_id' => $arrayData['m_dir_id'] ?? (auth()->user()->m_dir_id ?? null),
        'kode'     => !empty($arrayData['kode']) ? $arrayData['kode'] : ('LMB-' . str_pad(($model->max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT)),
        'grading'  => !empty($arrayData['grading_id']) ? (\DB::table('m_general')->where('id', $arrayData['grading_id'])->value('value') ?? '') : (!empty($arrayData['grading']) ? $arrayData['grading'] : 'Semua Grading')
      ] );
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;
      if (array_key_exists('grading_id', $arrayData)) {
        $newArrayData['grading'] = !empty($arrayData['grading_id']) ? (\DB::table('m_general')->where('id', $arrayData['grading_id'])->value('value') ?? '') : 'Semua Grading';
      }
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }
}