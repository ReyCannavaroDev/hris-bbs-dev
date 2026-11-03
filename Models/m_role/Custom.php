<?php

namespace App\Models\CustomModels;

class m_role extends \App\Models\BasicModels\m_role
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        if (empty($arrayData['name']) || trim((string)$arrayData['name']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama Role wajib diisi",
                "errors" => ["name" => ["Kolom Nama Role wajib diisi"]]
            ];
        }

        $details = $arrayData['m_role_det'] ?? [];
        if (empty($details) || count($details) === 0) {
            return [
                "status" => "error",
                "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
            ];
        }

        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }

    public function updateBefore($model, $arrayData, $metaData, $id = null)
    {
        if (isset($arrayData['name']) && trim((string)$arrayData['name']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama Role wajib diisi",
                "errors" => ["name" => ["Kolom Nama Role wajib diisi"]]
            ];
        }

        if (isset($arrayData['m_role_det'])) {
            $details = $arrayData['m_role_det'];
            if (empty($details) || count($details) === 0) {
                return [
                    "status" => "error",
                    "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                    "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
                ];
            }
        }

        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }
}