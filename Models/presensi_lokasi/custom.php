<?php

namespace App\Models\CustomModels;

class presensi_lokasi extends \App\Models\BasicModels\presensi_lokasi
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function listRoleCheck()
    {
        $request = app()->request;
        if ($request && $request->has('filter_is_active')) {
            $val = strtolower(trim((string)$request->filter_is_active));
            if ($val === 'active') {
                $request->merge(['filter_is_active' => 'true']);
                if ($request->query) {
                    $request->query->set('filter_is_active', 'true');
                }
                $_GET['filter_is_active'] = 'true';
            } elseif ($val === 'inactive') {
                $request->merge(['filter_is_active' => 'false']);
                if ($request->query) {
                    $request->query->set('filter_is_active', 'false');
                }
                $_GET['filter_is_active'] = 'false';
            }
        }
        return true;
    }
}