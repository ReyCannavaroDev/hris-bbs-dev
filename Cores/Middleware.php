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

        // Normalisasi filter is_active agar support 'Active' dan 'Inactive' via query where
        if (isset($requestData['filter_is_active'])) {
            $val = strtolower(trim((string)$requestData['filter_is_active']));
            $boolVal = in_array($val, ['active', 'true', '1'], true) ? 'true' : (in_array($val, ['inactive', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_active=$boolVal";
                if (!empty($requestData['where'])) {
                    if (preg_match('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                        $requestData['where'] = preg_replace('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                    } else {
                        $requestData['where'] .= " and $condition";
                    }
                } else {
                    $requestData['where'] = $condition;
                }
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_is_active');
                }
                $_GET['where'] = $requestData['where'];
                unset($_GET['filter_is_active']);
                unset($requestData['filter_is_active']);
            }
        }
        if (isset($requestData['filter_status']) && $modelInstance && in_array('is_active', $modelInstance->columns ?? []) && !in_array('status', $modelInstance->columns ?? [])) {
            $val = strtolower(trim((string)$requestData['filter_status']));
            $boolVal = in_array($val, ['active', 'true', '1'], true) ? 'true' : (in_array($val, ['inactive', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_active=$boolVal";
                if (!empty($requestData['where'])) {
                    if (preg_match('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                        $requestData['where'] = preg_replace('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                    } else {
                        $requestData['where'] .= " and $condition";
                    }
                } else {
                    $requestData['where'] = $condition;
                }
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_status');
                }
                $_GET['where'] = $requestData['where'];
                unset($_GET['filter_status']);
                unset($requestData['filter_status']);
            }
        }
        if (isset($requestData['search'])) {
            $searchVal = strtolower(trim((string)$requestData['search']));
            if ($searchVal === 'active' || $searchVal === 'inactive') {
                if ($modelInstance && in_array('is_active', $modelInstance->columns ?? [])) {
                    $boolVal = ($searchVal === 'active') ? 'true' : 'false';
                    $condition = "this.is_active=$boolVal";
                    if (!empty($requestData['where'])) {
                        if (preg_match('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                            $requestData['where'] = preg_replace('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                        } else {
                            $requestData['where'] .= " and $condition";
                        }
                    } else {
                        $requestData['where'] = $condition;
                    }
                    if ($request->query) {
                        $request->query->set('where', $requestData['where']);
                        $request->query->remove('search');
                    }
                    $_GET['where'] = $requestData['where'];
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
