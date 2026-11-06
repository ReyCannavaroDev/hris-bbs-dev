<?php
namespace App\Cores;

use App\Models\CustomModels\m_dir;
use App\Models\CustomModels\m_comp;
use App\Models\CustomModels\m_pengguna;
use App\Models\CustomModels\m_general;

class Response
{
    public function store($model){

        if(isset($model['platform'])){
            if(!app()->request->Source == 'Mobile'){
                $is_superadmin = \DB::select("
                        select mr.is_superadmin from m_role mr 
                        join m_role_access mra on mr.id = mra.m_role_id
                        join default_users u on u.id = mra.user_id 
                        where mra.user_id = ? and mr.is_superadmin = true
                    ", [auth()->user()->id]);
                    $model['is_superadmin'] = @count($is_superadmin) ? true : false;
            }
            $roles = \DB::table('m_role')
                ->join('m_role_access', 'm_role.id', '=', 'm_role_access.m_role_id')
                ->where('m_role_access.user_id', auth()->user()->id)
                ->pluck('m_role.name')
                ->toArray();
            $model['roles'] = $roles;
            $model['role_names'] = implode(', ', $roles);

            if(!$model['m_dir_id']){
                $model['direktorat'] = 'ADMIN INSTANSI';
            }else{
                $model['direktorat'] = m_dir::where('id', @$model['m_dir_id'] ?? 9)->pluck('nama')->first();
            }
            $model['company'] = m_comp::where('id', @$model['m_comp_id'] ?? 9)->pluck('nama')->first();
            $model['avatar'] =  m_general::where('group','SETTING')->where('code','BRAND-LOGO-SMALL')->pluck('value')->first();
          
        }
        return $model;
    }
}