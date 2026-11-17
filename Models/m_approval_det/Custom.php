<?php

namespace App\Models\CustomModels;

class m_approval_det extends \App\Models\BasicModels\m_approval_det
{    
    public function __construct()
    {
        parent::__construct();
        $this->casts = array_merge($this->casts, [
            'is_full_approve' => 'boolean',
            'is_skippable' => 'boolean',
        ]);
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id" => "auth:id"];
    //public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    
}