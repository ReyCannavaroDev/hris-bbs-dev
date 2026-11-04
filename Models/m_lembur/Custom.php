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
      // Generate auto sequential kode LMB-xxxx
      $maxId = (int) ($model->max('id') ?? 0);
      $nextSeq = $maxId + 1;
      do {
          $candidate = 'LMB-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
          $exists = \DB::table('m_lembur')->where('kode', $candidate)->exists();
          if (!$exists) {
              $autoKode = $candidate;
              break;
          }
          $nextSeq++;
      } while (true);

      // Jika kode kosong atau kode yang dikirim sudah pernah ada di database (misal akibat copy data), pakai auto-generate baru
      $finalKode = (!empty($arrayData['kode']) && !\DB::table('m_lembur')->where('kode', $arrayData['kode'])->exists())
          ? $arrayData['kode']
          : $autoKode;

      $newArrayData  = array_merge( $arrayData,[
        'm_dir_id' => $arrayData['m_dir_id'] ?? (auth()->user()->m_dir_id ?? null),
        'kode'     => $finalKode,
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