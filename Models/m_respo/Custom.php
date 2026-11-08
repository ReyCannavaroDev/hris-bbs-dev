<?php

namespace App\Models\CustomModels;

class m_respo extends \App\Models\BasicModels\m_respo
{    
    public function __construct()
    {
        parent::__construct();
        $this->required = ["nama"];
        $this->details  = ["m_respo_d"];
    }
    
    public $fileColumns = [];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        if (empty($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = 1;
        }
        if (empty($arrayData['kode'])) {
            try {
                $helper = getCore('Helper');
                if ($helper && method_exists($helper, 'generateNomor')) {
                    $arrayData['kode'] = $helper->generateNomor('KODE RESPONSIBILITY') ?: ('RSP-' . date('ymd') . '-' . rand(100, 999));
                } else {
                    $arrayData['kode'] = 'RSP-' . date('ymd') . '-' . rand(100, 999);
                }
            } catch (\Throwable $e) {
                $arrayData['kode'] = 'RSP-' . date('ymd') . '-' . rand(100, 999);
            }
        }
        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }

    public function createAfter($model, $arrayData, $metaData, $id = null)
    {
        $respoId = $model->id ?? $id;
        $this->syncDetails($respoId, $arrayData);
    }

    public function updateAfter($model, $arrayData, $metaData, $id = null)
    {
        $respoId = $model->id ?? $id;
        $this->syncDetails($respoId, $arrayData);
    }

    private function syncDetails($respoId, $arrayData)
    {
        if (!$respoId) return;

        $req = app()->request;
        $details = $arrayData['m_respo_d'] 
            ?? ($req ? $req->m_respo_d : null) 
            ?? ($req ? $req->input('m_respo_d') : null) 
            ?? null;

        if (is_array($details)) {
            \DB::table('m_respo_d')->where('m_respo_id', $respoId)->delete();
            $now = \Carbon\Carbon::now();
            $userId = auth()->user()->id ?? null;

            foreach ($details as $d) {
                $row = is_array($d) ? $d : (array)$d;
                $roleId = !empty($row['m_role_id']) ? (int)$row['m_role_id'] : null;
                if ($roleId) {
                    \DB::table('m_respo_d')->insert([
                        'm_respo_id'     => $respoId,
                        'm_role_id'      => $roleId,
                        'desc'           => $row['desc'] ?? null,
                        'creator_id'     => $userId,
                        'last_editor_id' => $userId,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                }
            }
        }
    }
}


