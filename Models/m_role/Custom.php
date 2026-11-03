<?php

namespace App\Models\CustomModels;

class m_role extends \App\Models\BasicModels\m_role
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function listRoleCheck()
    {
        $request = app()->request;
        if (!$request) {
            return true;
        }

        if ($request->has('filter_is_superadmin') || $request->has('filter_superadmin') || isset($_GET['filter_is_superadmin']) || isset($_GET['filter_superadmin'])) {
            $raw = $request->filter_is_superadmin ?? $request->filter_superadmin ?? $_GET['filter_is_superadmin'] ?? $_GET['filter_superadmin'] ?? null;
            $val = strtolower(trim((string)$raw));
            $boolVal = in_array($val, ['yes', 'true', '1'], true) ? 'true' : (in_array($val, ['no', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_superadmin=$boolVal";
                $where = $request->where ?? $_GET['where'] ?? '';
                if (!empty($where)) {
                    if (preg_match('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $where)) {
                        $where = preg_replace('/\bthis\.is_superadmin\s*=\s*(?:true|false|1|0)\b/i', $condition, $where);
                    } else {
                        $where .= " and $condition";
                    }
                } else {
                    $where = $condition;
                }
                $request->merge(['where' => $where]);
                if ($request->query) {
                    $request->query->set('where', $where);
                    $request->query->remove('filter_is_superadmin');
                    $request->query->remove('filter_superadmin');
                }
                if ($request->request) {
                    $request->request->remove('filter_is_superadmin');
                    $request->request->remove('filter_superadmin');
                }
                $request->offsetUnset('filter_is_superadmin');
                $request->offsetUnset('filter_superadmin');
                $_GET['where'] = $where;
                $_REQUEST['where'] = $where;
                unset($_GET['filter_is_superadmin'], $_GET['filter_superadmin']);
                unset($_REQUEST['filter_is_superadmin'], $_REQUEST['filter_superadmin']);
            }
        }

        if ($request->has('filter_is_active') || $request->has('filter_status') || isset($_GET['filter_is_active']) || isset($_GET['filter_status'])) {
            $raw = $request->filter_is_active ?? $request->filter_status ?? $_GET['filter_is_active'] ?? $_GET['filter_status'] ?? null;
            $val = strtolower(trim((string)$raw));
            $boolVal = in_array($val, ['active', 'true', '1'], true) ? 'true' : (in_array($val, ['inactive', 'false', '0'], true) ? 'false' : null);
            if ($boolVal !== null) {
                $condition = "this.is_active=$boolVal";
                $where = $request->where ?? $_GET['where'] ?? '';
                if (!empty($where)) {
                    if (preg_match('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $where)) {
                        $where = preg_replace('/\bthis\.is_active\s*=\s*(?:true|false|1|0)\b/i', $condition, $where);
                    } else {
                        $where .= " and $condition";
                    }
                } else {
                    $where = $condition;
                }
                $request->merge(['where' => $where]);
                if ($request->query) {
                    $request->query->set('where', $where);
                    $request->query->remove('filter_is_active');
                    $request->query->remove('filter_status');
                }
                if ($request->request) {
                    $request->request->remove('filter_is_active');
                    $request->request->remove('filter_status');
                }
                $request->offsetUnset('filter_is_active');
                $request->offsetUnset('filter_status');
                $_GET['where'] = $where;
                $_REQUEST['where'] = $where;
                unset($_GET['filter_is_active'], $_GET['filter_status']);
                unset($_REQUEST['filter_is_active'], $_REQUEST['filter_status']);
            }
        }

        if ($request->has('where') && !empty($request->where)) {
            $where = preg_replace_callback(
                '/(?:this\.)?(?:superadmin|is_superadmin)\s*(?:=|ilike|like)\s*[\'"]?%?(yes|no|true|false|1|0)%?[\'"]?/i',
                function ($m) {
                    $val = strtolower($m[1]);
                    $bool = in_array($val, ['yes', 'true', '1'], true) ? 'true' : 'false';
                    return "this.is_superadmin=$bool";
                },
                $request->where
            );
            $where = preg_replace_callback(
                '/(?:this\.)?(?:status|is_active)\s*(?:=|ilike|like)\s*[\'"]?%?(active|inactive|true|false|1|0)%?[\'"]?/i',
                function ($m) {
                    $val = strtolower($m[1]);
                    $bool = in_array($val, ['active', 'true', '1'], true) ? 'true' : 'false';
                    return "this.is_active=$bool";
                },
                $where
            );
            $request->merge(['where' => $where]);
            if ($request->query) {
                $request->query->set('where', $where);
            }
            $_GET['where'] = $where;
            $_REQUEST['where'] = $where;
        }

        if ($request->has('search')) {
            $searchVal = strtolower(trim((string)$request->search));
            if ($searchVal === 'active' || $searchVal === 'inactive') {
                $boolVal = ($searchVal === 'active') ? 'true' : 'false';
                $condition = "this.is_active=$boolVal";
                $where = $request->where ?? $_GET['where'] ?? '';
                $where = !empty($where) ? "$where and $condition" : $condition;
                $request->merge(['where' => $where]);
                if ($request->query) {
                    $request->query->set('where', $where);
                    $request->query->remove('search');
                }
                if ($request->request) {
                    $request->request->remove('search');
                }
                $request->offsetUnset('search');
                $_GET['where'] = $where;
                $_REQUEST['where'] = $where;
                unset($_GET['search'], $_REQUEST['search']);
            } elseif ($searchVal === 'yes' || $searchVal === 'no') {
                $boolVal = ($searchVal === 'yes') ? 'true' : 'false';
                $condition = "this.is_superadmin=$boolVal";
                $where = $request->where ?? $_GET['where'] ?? '';
                $where = !empty($where) ? "$where and $condition" : $condition;
                $request->merge(['where' => $where]);
                if ($request->query) {
                    $request->query->set('where', $where);
                    $request->query->remove('search');
                }
                if ($request->request) {
                    $request->request->remove('search');
                }
                $request->offsetUnset('search');
                $_GET['where'] = $where;
                $_REQUEST['where'] = $where;
                unset($_GET['search'], $_REQUEST['search']);
            }
        }

        return true;
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        if (empty($arrayData['name']) || trim((string)$arrayData['name']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama Role wajib diisi",
                "errors" => ["name" => ["Kolom Nama Role wajib diisi"]]
            ];
        }

        $details = $arrayData['m_role_det'] ?? [];
        if (empty($details) || count($details) === 0) {
            return [
                "status" => "error",
                "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
            ];
        }

        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }

    public function updateBefore($model, $arrayData, $metaData, $id = null)
    {
        if (isset($arrayData['name']) && trim((string)$arrayData['name']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama Role wajib diisi",
                "errors" => ["name" => ["Kolom Nama Role wajib diisi"]]
            ];
        }

        if (isset($arrayData['m_role_det'])) {
            $details = $arrayData['m_role_det'];
            if (empty($details) || count($details) === 0) {
                return [
                    "status" => "error",
                    "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                    "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
                ];
            }
        }

        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }
}
