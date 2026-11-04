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
        @$modelName = (is_array($request->route()) && isset($request->route()[2]))
            ? ($request->route()[2]['modelname'] ?? $request->route()[2]['model'] ?? $request->route()[2]['name'] ?? $request->route()[2]['table'] ?? null)
            : ((is_object($request->route()) && method_exists($request->route(), 'parameter'))
                ? ($request->route()->parameter('modelname') ?? $request->route()->parameter('model') ?? $request->route()->parameter('name'))
                : null);

        if (!$modelName && preg_match('#/operation/([^/?]+)#', $request->getRequestUri(), $m)) {
            $modelName = $m[1];
        }

        if(!$modelName) return $next($request);

        $modelInstance = null;
        $customClass = "\\App\\Models\\CustomModels\\$modelName";
        $basicClass = "\\App\\Models\\BasicModels\\$modelName";
        if (class_exists($customClass)) {
            $modelInstance = new $customClass();
        } elseif (class_exists($basicClass)) {
            $modelInstance = new $basicClass();
        } else {
            $modelInstance = @app("App\\Models\\CustomModels\\$modelName") ?: @app("App\\Models\\BasicModels\\$modelName");
        }

        $user = auth()->user();
        $requestData = $request->all();

        // Normalisasi filter is_active dan status agar support 'Active' dan 'Inactive' via query where
        if (!empty($requestData['where']) && $modelInstance && in_array('is_active', $modelInstance->columns ?? [])) {
            $requestData['where'] = preg_replace_callback(
                '/(?:this\.)?(?:status|is_active)\s*(?:=|ilike|like)\s*[\'"]?%?(active|inactive)%?[\'"]?/i',
                function ($m) {
                    $bool = strtolower($m[1]) === 'active' ? 'true' : 'false';
                    return "this.is_active=$bool";
                },
                $requestData['where']
            );
            $request->merge(['where' => $requestData['where']]);
            if ($request->query) {
                $request->query->set('where', $requestData['where']);
            }
            $_GET['where'] = $requestData['where'];
        }

        // Normalisasi filter is_hari_berikutnya dan hari_lain agar support 'Ada', 'Tidak Ada', 'true', 'false' via query where
        if (!empty($requestData['where']) && $modelInstance && in_array('is_hari_berikutnya', $modelInstance->columns ?? [])) {
            $requestData['where'] = preg_replace_callback(
                '/(?:this\.)?(?:hari_lain|is_hari_berikutnya)\s*(?:=|ilike|like)\s*[\'"]?%?(ada|tidak ada|yes|no|true|false|1|0)%?[\'"]?/i',
                function ($m) {
                    $val = strtolower($m[1]);
                    $bool = in_array($val, ['ada', 'yes', 'true', '1'], true) ? 'true' : 'false';
                    return "this.is_hari_berikutnya=$bool";
                },
                $requestData['where']
            );
            $request->merge(['where' => $requestData['where']]);
            if ($request->query) {
                $request->query->set('where', $requestData['where']);
            }
            $_GET['where'] = $requestData['where'];
        }

        // Normalisasi filter is_superadmin dan superadmin agar support 'Yes', 'No', 'true', 'false' via query where
        if (!empty($requestData['where']) && $modelInstance && in_array('is_superadmin', $modelInstance->columns ?? [])) {
            $requestData['where'] = preg_replace_callback(
                '/(?:this\.)?(?:superadmin|is_superadmin)\s*(?:=|ilike|like)\s*[\'"]?%?(yes|no|true|false|1|0)%?[\'"]?/i',
                function ($m) {
                    $val = strtolower($m[1]);
                    $bool = in_array($val, ['yes', 'true', '1'], true) ? 'true' : 'false';
                    return "this.is_superadmin=$bool";
                },
                $requestData['where']
            );
            $request->merge(['where' => $requestData['where']]);
            if ($request->query) {
                $request->query->set('where', $requestData['where']);
            }
            $_GET['where'] = $requestData['where'];
        }

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
                $request->merge(['where' => $requestData['where']]);
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_is_active');
                }
                if ($request->request) {
                    $request->request->remove('filter_is_active');
                }
                $request->offsetUnset('filter_is_active');
                $_GET['where'] = $requestData['where'];
                $_REQUEST['where'] = $requestData['where'];
                unset($_GET['filter_is_active'], $_REQUEST['filter_is_active']);
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
                $request->merge(['where' => $requestData['where']]);
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_status');
                }
                if ($request->request) {
                    $request->request->remove('filter_status');
                }
                $request->offsetUnset('filter_status');
                $_GET['where'] = $requestData['where'];
                $_REQUEST['where'] = $requestData['where'];
                unset($_GET['filter_status'], $_REQUEST['filter_status']);
                unset($requestData['filter_status']);
            }
        }

        // Normalisasi filter_is_superadmin dan filter_superadmin
        if (isset($requestData['filter_is_superadmin']) || isset($requestData['filter_superadmin'])) {
            $rawVal = $requestData['filter_is_superadmin'] ?? $requestData['filter_superadmin'];
            $val = strtolower(trim((string)$rawVal));
            $boolVal = in_array($val, ['yes', 'true', '1'], true) ? 'true' : (in_array($val, ['no', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_superadmin=$boolVal";
                if (!empty($requestData['where'])) {
                    if (preg_match('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                        $requestData['where'] = preg_replace('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                    } else {
                        $requestData['where'] .= " and $condition";
                    }
                } else {
                    $requestData['where'] = $condition;
                }
                $request->merge(['where' => $requestData['where']]);
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_is_superadmin');
                    $request->query->remove('filter_superadmin');
                }
                if ($request->request) {
                    $request->request->remove('filter_is_superadmin');
                    $request->request->remove('filter_superadmin');
                }
                $request->offsetUnset('filter_is_superadmin');
                $request->offsetUnset('filter_superadmin');
                $_GET['where'] = $requestData['where'];
                $_REQUEST['where'] = $requestData['where'];
                unset($_GET['filter_is_superadmin'], $_GET['filter_superadmin']);
                unset($_REQUEST['filter_is_superadmin'], $_REQUEST['filter_superadmin']);
                unset($requestData['filter_is_superadmin'], $requestData['filter_superadmin']);
            }
        }

        // Normalisasi filter_is_hari_berikutnya dan filter_hari_lain
        if (isset($requestData['filter_is_hari_berikutnya']) || isset($requestData['filter_hari_lain'])) {
            $rawVal = $requestData['filter_is_hari_berikutnya'] ?? $requestData['filter_hari_lain'];
            $val = strtolower(trim((string)$rawVal));
            $boolVal = in_array($val, ['ada', 'yes', 'true', '1'], true) ? 'true' : (in_array($val, ['tidak ada', 'no', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_hari_berikutnya=$boolVal";
                if (!empty($requestData['where'])) {
                    if (preg_match('/\bthis\.is_hari_berikutnya\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                        $requestData['where'] = preg_replace('/\bthis\.is_hari_berikutnya\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                    } else {
                        $requestData['where'] .= " and $condition";
                    }
                } else {
                    $requestData['where'] = $condition;
                }
                $request->merge(['where' => $requestData['where']]);
                if ($request->query) {
                    $request->query->set('where', $requestData['where']);
                    $request->query->remove('filter_is_hari_berikutnya');
                    $request->query->remove('filter_hari_lain');
                }
                if ($request->request) {
                    $request->request->remove('filter_is_hari_berikutnya');
                    $request->request->remove('filter_hari_lain');
                }
                $request->offsetUnset('filter_is_hari_berikutnya');
                $request->offsetUnset('filter_hari_lain');
                $_GET['where'] = $requestData['where'];
                $_REQUEST['where'] = $requestData['where'];
                unset($_GET['filter_is_hari_berikutnya'], $_GET['filter_hari_lain']);
                unset($_REQUEST['filter_is_hari_berikutnya'], $_REQUEST['filter_hari_lain']);
                unset($requestData['filter_is_hari_berikutnya'], $requestData['filter_hari_lain']);
            }
        }

        // Normalisasi filter jam_kerja via query where agar support pencarian waktu mulai & waktu akhir
        if (!empty($requestData['where']) && $modelInstance && in_array('waktu_mulai', $modelInstance->columns ?? [])) {
            $requestData['where'] = preg_replace_callback(
                '/(?:this\.|m_jam_kerja\.)?jam_kerja\s*(?:=|ilike|like)\s*[\'\"]?%?([^%\'\"]*?)%?[\'\"]?/i',
                function ($m) {
                    $raw = trim($m[1]);
                    $raw = str_replace('.', ':', $raw);
                    if ($raw === '') {
                        return "1=1";
                    }
                    if (str_contains($raw, '-')) {
                        $parts = explode('-', $raw);
                        $start = trim($parts[0]);
                        $end = trim($parts[1]);
                        if ($start !== '' && $end !== '') {
                            return "(m_jam_kerja.waktu_mulai::text ilike '%$start%' and m_jam_kerja.waktu_akhir::text ilike '%$end%')";
                        } elseif ($start !== '') {
                            return "m_jam_kerja.waktu_mulai::text ilike '%$start%'";
                        } elseif ($end !== '') {
                            return "m_jam_kerja.waktu_akhir::text ilike '%$end%'";
                        }
                    }
                    return "(m_jam_kerja.waktu_mulai::text ilike '%$raw%' or m_jam_kerja.waktu_akhir::text ilike '%$raw%')";
                },
                $requestData['where']
            );
            $request->merge(['where' => $requestData['where']]);
            if ($request->query) {
                $request->query->set('where', $requestData['where']);
            }
            $_GET['where'] = $requestData['where'];
            $_REQUEST['where'] = $requestData['where'];
        }

        // Normalisasi filter_jam_kerja: pertahankan parameter agar diproses oleh scopeFilter di Custom.php
        if (isset($requestData['filter_jam_kerja']) && $modelInstance && in_array('waktu_mulai', $modelInstance->columns ?? [])) {
            $val = trim((string)$requestData['filter_jam_kerja']);
            $val = str_replace('.', ':', $val);
            if ($val !== '') {
                $request->merge(['filter_jam_kerja' => $val]);
                if ($request->query) {
                    $request->query->set('filter_jam_kerja', $val);
                }
                $_GET['filter_jam_kerja'] = $val;
                $_REQUEST['filter_jam_kerja'] = $val;
            }
        }

        if (!empty($requestData['where'])) {
            $requestData['where'] = str_replace('this.waktu_mulai', 'm_jam_kerja.waktu_mulai', $requestData['where']);
            $requestData['where'] = str_replace('this.waktu_akhir', 'm_jam_kerja.waktu_akhir', $requestData['where']);
            $requestData['where'] = str_replace('this.jam_kerja', 'm_jam_kerja.waktu_mulai', $requestData['where']);
            $request->merge(['where' => $requestData['where']]);
            if ($request->query) {
                $request->query->set('where', $requestData['where']);
            }
            $_GET['where'] = $requestData['where'];
            $_REQUEST['where'] = $requestData['where'];
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
                    $request->merge(['where' => $requestData['where']]);
                    if ($request->query) {
                        $request->query->set('where', $requestData['where']);
                        $request->query->remove('search');
                    }
                    $_GET['where'] = $requestData['where'];
                    unset($_GET['search']);
                    unset($requestData['search']);
                }
            } elseif ($searchVal === 'ada' || $searchVal === 'tidak ada') {
                if ($modelInstance && in_array('is_hari_berikutnya', $modelInstance->columns ?? [])) {
                    $boolVal = ($searchVal === 'ada') ? 'true' : 'false';
                    $condition = "this.is_hari_berikutnya=$boolVal";
                    if (!empty($requestData['where'])) {
                        if (preg_match('/\bthis\.is_hari_berikutnya\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                            $requestData['where'] = preg_replace('/\bthis\.is_hari_berikutnya\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                        } else {
                            $requestData['where'] .= " and $condition";
                        }
                    } else {
                        $requestData['where'] = $condition;
                    }
                    $request->merge(['where' => $requestData['where']]);
                    if ($request->query) {
                        $request->query->set('where', $requestData['where']);
                        $request->query->remove('search');
                    }
                    $_GET['where'] = $requestData['where'];
                    unset($_GET['search']);
                    unset($requestData['search']);
                }
            } elseif ($searchVal === 'yes' || $searchVal === 'no') {
                if ($modelInstance && in_array('is_superadmin', $modelInstance->columns ?? [])) {
                    $boolVal = ($searchVal === 'yes') ? 'true' : 'false';
                    $condition = "this.is_superadmin=$boolVal";
                    if (!empty($requestData['where'])) {
                        if (preg_match('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $requestData['where'])) {
                            $requestData['where'] = preg_replace('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $condition, $requestData['where']);
                        } else {
                            $requestData['where'] .= " and $condition";
                        }
                    } else {
                        $requestData['where'] = $condition;
                    }
                    $request->merge(['where' => $requestData['where']]);
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
