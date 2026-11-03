<?php

namespace App\Models\CustomModels;

class m_role_access extends \App\Models\BasicModels\m_role_access
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        return [
            "model"  => $model,
            "data"   => array_merge($arrayData,[
                //'m_dir_id' =>  $m_dir_id
            ])
        ];
    }

    public function custom_store($req)
    {
        $id = (int)$req->pengguna_id;
        if (empty($id)) {
            return response([
                'status' => 'error',
                'message' => 'User ID tidak valid',
                'errors' => ['pengguna_id' => ['User ID tidak valid']]
            ], 422);
        }

        $details = $req->detail ?? [];
        if (empty($details) || count($details) === 0) {
            return response([
                'status' => 'error',
                'message' => 'Detail hak akses wajib diisi minimal 1 role',
                'errors' => ['detail' => ['Detail hak akses wajib diisi minimal 1 role']]
            ], 422);
        }

        $this->where('user_id', $id)->delete();
        foreach($details as $d){
            $this->create([
                'user_id'   => $id,
                'm_role_id' => $d['m_role_id'],
            ]);
        }

        return response(['message'=>'Pengaturan role akses berhasil']);
    }
}