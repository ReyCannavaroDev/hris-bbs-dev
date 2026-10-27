<?php
namespace App\Cores;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Middleware
{
    public function handle( Request $request, Closure $next ) 
    {
        @$modelName = $request->route()[2]['modelname'];
        if(!$modelName) return $next($request);
        $modelInstance = @app("App\\Models\\CustomModels\\$modelName");
        $user = auth()->user();
        $requestData = $request->all();

        // Normalisasi filter is_active agar support 'Active' dan 'Inactive'
        if (isset($requestData['filter_is_active'])) {
            $val = strtolower(trim((string)$requestData['filter_is_active']));
            if ($val === 'active') {
                $requestData['filter_is_active'] = 'true';
            } elseif ($val === 'inactive') {
                $requestData['filter_is_active'] = 'false';
            }
            if ($request->query) {
                $request->query->set('filter_is_active', $requestData['filter_is_active']);
            }
            $_GET['filter_is_active'] = $requestData['filter_is_active'];
        }
        if (isset($requestData['filter_status']) && $modelInstance && in_array('is_active', $modelInstance->columns ?? []) && !in_array('status', $modelInstance->columns ?? [])) {
            $val = strtolower(trim((string)$requestData['filter_status']));
            if ($val === 'active') {
                $requestData['filter_is_active'] = 'true';
            } elseif ($val === 'inactive') {
                $requestData['filter_is_active'] = 'false';
            }
            if ($request->query) {
                $request->query->set('filter_is_active', $requestData['filter_is_active']);
                $request->query->remove('filter_status');
            }
            $_GET['filter_is_active'] = $requestData['filter_is_active'];
            unset($_GET['filter_status']);
            unset($requestData['filter_status']);
        }
        if (isset($requestData['search'])) {
            $searchVal = strtolower(trim((string)$requestData['search']));
            if ($searchVal === 'active' || $searchVal === 'inactive') {
                if ($modelInstance && in_array('is_active', $modelInstance->columns ?? [])) {
                    $requestData['filter_is_active'] = ($searchVal === 'active') ? 'true' : 'false';
                    if ($request->query) {
                        $request->query->set('filter_is_active', $requestData['filter_is_active']);
                        $request->query->remove('search');
                    }
                    $_GET['filter_is_active'] = $requestData['filter_is_active'];
                    unset($_GET['search']);
                    unset($requestData['search']);
                }
            }
        }

        if(app()->request->header('Source') === 'mobile') {
            $modelInstance->addGlobalScope('global', function (Builder $builder) use($modelName, $modelInstance, $user) {
                if(in_array('creator_id', $modelInstance->columns) && !in_array(@$modelInstance->getTable(),['generate_approval','generate_approval_det','m_kary','default_users','m_general','m_dir','m_zona','m_lokasi','m_divisi','m_dept','m_posisi','m_spd','presensi_app_version','t_pengumuman'])){
                    $builder = $builder->where("$modelName.creator_id", $user->id);
                    // inject auto relation for mobile with column assigned to pic_id
                    if(in_array('pic_id', $modelInstance->columns) ){
                        $builder = $builder->orWhere("$modelName.pic_id", $user->id);
                    }
                    return $builder;
                }
                if(in_array('pic_id', $modelInstance->columns) && !in_array(@$modelInstance->getTable(),['generate_approval','generate_approval_det','m_kary','default_users','m_general','m_dir','m_zona','m_lokasi','m_divisi','m_dept','m_posisi','m_spd','presensi_app_version'])){
                    return $builder->where("$modelName.creator_id", $user->id);
                }
                if(in_array('tanggal', $modelInstance->columns)){
                    if(req('date_from') && req('date_to')){
                        return $builder->whereBetween('tanggal',[req('date_from'),req('date_to')]);
                    }
                }
                if(in_array('date_from', $modelInstance->columns)){
                    if(req('date_from') && req('date_to')){
                        return $builder->whereRaw("$modelName.date_from >= ? or $modelName.date_to <= ?",[req('date_from'), req('date_to')]);
                    }
                }
            });
            if(in_array('m_kary_id', $modelInstance->columns)) {
                $requestData['m_kary_id'] = @$user->m_kary_id;
            }
        }
        $request->replace($requestData);

        return $next($request);
    }
}
