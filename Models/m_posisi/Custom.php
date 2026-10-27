<?php

namespace App\Models\CustomModels;

class m_posisi extends \App\Models\BasicModels\m_posisi
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->required = [
            "desc_kerja",
            "min_pengalaman",
            "min_pendidikan_id",
            "min_gaji_pokok",
            "max_gaji_pokok",
            "potongan_bpjs",
            "tipe_bpjs_id",
            "desc",
            "is_active",
        ];
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    private function validatePayload($arrayData)
    {
        $errors = [];
        if (empty($arrayData['desc_kerja'])) {
            $errors[] = 'Deskripsi wajib diisi.';
        }
        if (!isset($arrayData['min_pengalaman']) || $arrayData['min_pengalaman'] === '' || $arrayData['min_pengalaman'] === null) {
            $errors[] = 'Minimal pengalaman wajib diisi.';
        }
        if (empty($arrayData['min_pendidikan_id'])) {
            $errors[] = 'Minimal pendidikan wajib diisi.';
        }
        if (!isset($arrayData['min_gaji_pokok']) || $arrayData['min_gaji_pokok'] === '' || $arrayData['min_gaji_pokok'] === null) {
            $errors[] = 'Minimal gaji pokok wajib diisi.';
        }
        if (!isset($arrayData['max_gaji_pokok']) || $arrayData['max_gaji_pokok'] === '' || $arrayData['max_gaji_pokok'] === null) {
            $errors[] = 'Maksimal gaji pokok wajib diisi.';
        }
        if (
            isset($arrayData['min_gaji_pokok']) && isset($arrayData['max_gaji_pokok']) &&
            $arrayData['min_gaji_pokok'] !== '' && $arrayData['max_gaji_pokok'] !== '' &&
            (float)$arrayData['max_gaji_pokok'] < (float)$arrayData['min_gaji_pokok']
        ) {
            $errors[] = 'Maksimal gaji pokok tidak boleh lebih kecil dari minimal gaji pokok.';
        }
        if (!isset($arrayData['potongan_bpjs']) || $arrayData['potongan_bpjs'] === '' || $arrayData['potongan_bpjs'] === null) {
            $errors[] = 'Potongan BPJS wajib diisi.';
        }
        if (empty($arrayData['tipe_bpjs_id'])) {
            $errors[] = 'Tipe BPJS wajib diisi.';
        }
        if (empty($arrayData['desc'])) {
            $errors[] = 'Keterangan wajib diisi.';
        }

        return $errors;
    }

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $errors = $this->validatePayload($arrayData);
        if (!empty($errors)) {
            return [
                "model" => $model,
                "data" => $arrayData,
                "errors" => $errors,
            ];
        }

        $newArrayData  = array_merge( $arrayData,[
            'kode' =>  $this->helper->generateNomor('KODE POSISI'),
            'comp_id' => auth()->user()->comp_id ?? 0,
            'm_comp_id' => auth()->user()->comp_id ?? 1,
        ] );

        return [
            "model"  => $model,
            "data"   => $newArrayData,
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $errors = $this->validatePayload($arrayData);
        if (!empty($errors)) {
            return [
                "model" => $model,
                "data" => $arrayData,
                "errors" => $errors,
            ];
        }

        return [
            "model"  => $model,
            "data"   => $arrayData,
        ];
    }

    public function custom_seeder(){
        
    }
    
}