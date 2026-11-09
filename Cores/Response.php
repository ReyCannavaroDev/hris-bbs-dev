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
            $req = app()->request;
            $source = $req ? ($req->header('Source') ?? $req->Source ?? null) : null;
            $userId = $model['id'] ?? (@auth()->user()->id ?? 0);

            $respoCore = getCore('Respo');
            $respoCore->resetCache($userId);

            $userObj = $userId ? \DB::table('default_users')->where('id', $userId)->first() : null;
            $effectiveUser = $userObj ?: $model;
            $isSuperadmin = $respoCore->isUserSuperadmin($effectiveUser);
            $canViewSalary = $respoCore->canViewSalary($effectiveUser);
            $hasRespo = $userId ? $respoCore->userHasAssignedRespo($userId) : false;
            $activeRespo = $hasRespo ? $respoCore->checkRespoActive($userId) : null;
            $effectiveRoles = $respoCore->getEffectiveRoles($effectiveUser);
            $roles = array_values(array_map(function ($r) {
                return $r->name;
            }, $effectiveRoles));

            if (strtolower((string)$source) !== 'mobile') {
                $model['is_superadmin'] = $isSuperadmin;
            }
            $model['roles'] = $roles;
            $model['role_names'] = implode(', ', $roles);
            $model['can_view_salary'] = $canViewSalary;
            $model['has_respo'] = $hasRespo;
            $model['active_respo'] = $activeRespo;

            $dirId = $activeRespo ? $activeRespo->m_dir_id : ($model['m_dir_id'] ?? null);
            $compId = ($activeRespo && !empty($activeRespo->m_comp_id)) ? $activeRespo->m_comp_id : ($model['m_comp_id'] ?? null);

            $model['m_dir_id'] = $dirId;
            if ($compId) {
                $model['m_comp_id'] = $compId;
            }

            if(!$dirId){
                $model['direktorat'] = $activeRespo ? 'Holding (Semua Company)' : 'ADMIN INSTANSI';
            }else{
                $model['direktorat'] = ($activeRespo && !empty($activeRespo->dir_nama))
                    ? $activeRespo->dir_nama
                    : m_dir::where('id', $dirId)->pluck('nama')->first();
            }
            $model['company'] = m_comp::where('id', @$compId ?? 9)->pluck('nama')->first();
            $model['avatar'] =  m_general::where('group','SETTING')->where('code','BRAND-LOGO-SMALL')->pluck('value')->first();
          
        }
        return $model;
    }
}