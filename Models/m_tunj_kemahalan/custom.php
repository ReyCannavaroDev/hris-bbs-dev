<?php

namespace App\Models\CustomModels;

class m_tunj_kemahalan extends \App\Models\BasicModels\m_tunj_kemahalan
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
        return [
            "model"  => $model,
            "data"   => array_merge($arrayData,[
                'kode' =>  $this->helper->generateNomor('KODE TUNJANGAN KEMAHALAN'),
                //'m_dir_id' =>  $m_dir_id
            ])
        ];
    }

    public function getBesaranAttribute($value)
    {
        if ($value !== null && $value !== '') {
            $val = is_numeric($value) ? (float)$value : (float)str_replace(['.', ','], '', (string)$value);
            return number_format($val, 0, ',', '.');
        }
        return $value;
    }

    public function setBesaranAttribute($value)
    {
        if ($value !== null && $value !== '') {
            if (is_numeric($value)) {
                $this->attributes['besaran'] = (float)$value;
            } else {
                $this->attributes['besaran'] = (float)str_replace(['.', ','], '', (string)$value);
            }
        } else {
            $this->attributes['besaran'] = $value;
        }
    }

    public function transformRowData( array $row )
    {
        if (isset($row['besaran']) && $row['besaran'] !== null && $row['besaran'] !== '') {
            $row['besaran_raw'] = $row['besaran'];
            $val = is_numeric($row['besaran']) ? (float)$row['besaran'] : (float)str_replace(['.', ','], '', (string)$row['besaran']);
            $row['besaran'] = number_format($val, 0, ',', '.');
        }
        return $row;
    }
    
}