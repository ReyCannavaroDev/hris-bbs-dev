<?php

namespace App\Models\CustomModels;

class m_jam_kerja extends \App\Models\BasicModels\m_jam_kerja
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->fillable = array_merge($this->fillable, ['m_comp_id', 'm_dir_id']);
        $this->createable = array_merge($this->createable, ['m_comp_id', 'm_dir_id']);
        $this->updateable = array_merge($this->updateable, ['m_comp_id', 'm_dir_id']);
        $this->searchable = array_merge($this->searchable, ['m_comp_id', 'm_dir_id']);
        // Batasi joins hanya ke tabel yang pasti ada agar data dengan m_dir_id/last_editor_id NULL tidak tereliminasi oleh INNER JOIN otomatis generator
        $this->joins = [
            "m_comp.id=m_jam_kerja.m_comp_id",
            "m_general.id=m_jam_kerja.tipe_jam_kerja_id"
        ];
    }
    
    public function comp() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_comp', 'm_comp_id', 'id');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $m_comp_id = $arrayData['m_comp_id'] ?? (auth()->check() ? auth()->user()->m_comp_id : 1) ?? 1;
        return [
            "model"  => $model,
            "data"   => array_merge($arrayData,[
                'kode' =>  $this->helper->generateNomor('KODE JAM KERJA'),
                'm_comp_id' => $m_comp_id,
            ])
        ];
    }
}