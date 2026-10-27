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
            $boolVal = in_array($val, ['active', 'true', '1'], true) ? 'true' : (in_array($val, ['inactive', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_active=$boolVal";
                $where = $request->where;
                if (!empty($where)) {
                    if (preg_match('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $where)) {
                        $where = preg_replace('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $condition, $where);
                    } else {
                        $where .= " and $condition";
                    }
                } else {
                    $where = $condition;
                }
                $request->merge(['where' => $where]);
                if ($request->query) {
                    $request->query->set('where', $where);
                    $request->query->remove('filter_is_active');
                }
                $_GET['where'] = $where;
                unset($_GET['filter_is_active']);
            }
        }
        return true;
    }
}