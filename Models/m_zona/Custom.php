<?php

namespace App\Models\CustomModels;

class m_zona extends \App\Models\BasicModels\m_zona
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData = array_merge($arrayData, [
            'kode' => $this->helper->generateNomor('KODE ZONA'),
        ]);

        if (!empty($newArrayData['m_dir_id'])) {
            $dir = \DB::table('m_dir')->where('id', $newArrayData['m_dir_id'])->first();
            if ($dir && !empty($dir->m_comp_id)) {
                $newArrayData['m_comp_id'] = $dir->m_comp_id;
            }
        }

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }

        if (empty($newArrayData['m_dir_id']) && auth()->check() && !empty(auth()->user()->m_dir_id)) {
            $newArrayData['m_dir_id'] = auth()->user()->m_dir_id;
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData = array_merge($arrayData, []);

        if (!empty($newArrayData['m_dir_id'])) {
            $dir = \DB::table('m_dir')->where('id', $newArrayData['m_dir_id'])->first();
            if ($dir && !empty($dir->m_comp_id)) {
                $newArrayData['m_comp_id'] = $dir->m_comp_id;
            }
        }

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData
        ];
    }

    public function scopeFilter($model)
    {
        $status = request('filter_is_active') ?? request('filter_status');
        if ($status !== null) {
            $val = strtolower(trim((string)$status));
            if (in_array($val, ['active', 'true', '1'])) {
                return $model->where('m_zona.is_active', true);
            } elseif (in_array($val, ['inactive', 'false', '0'])) {
                return $model->where('m_zona.is_active', false);
            }
        }
    }
}