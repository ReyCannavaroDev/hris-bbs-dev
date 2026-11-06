<?php

namespace App\Models\CustomModels;

class m_respo_d extends \App\Models\BasicModels\m_respo_d
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns = [];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];
}

