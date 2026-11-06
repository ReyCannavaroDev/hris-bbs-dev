<?php

namespace App\Models\CustomModels;

class m_role extends \App\Models\BasicModels\m_role
{    
    public function __construct()
    {
        parent::__construct();
        // Override $required agar boolean is_superadmin (0/false) dan is_active (0/false)
        // tidak dianggap 'kosong' oleh generic validator generator Core
        $this->required = ["name"];
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

        $compId = !empty($arrayData['m_comp_id']) ? (int)$arrayData['m_comp_id'] : ((auth()->check() && !empty(auth()->user()->m_comp_id)) ? (int)auth()->user()->m_comp_id : 1);
        $dirId = !empty($arrayData['m_dir_id']) ? (int)$arrayData['m_dir_id'] : ((auth()->check() && !empty(auth()->user()->m_dir_id)) ? (int)auth()->user()->m_dir_id : null);

        $arrayData['m_comp_id'] = $compId;
        $arrayData['m_dir_id'] = $dirId;
        $arrayData['is_superadmin'] = !empty($arrayData['is_superadmin']) ? 1 : 0;
        $arrayData['is_active'] = isset($arrayData['is_active']) ? (!empty($arrayData['is_active']) ? 1 : 0) : 1;

        $req = app()->request;
        $details = $arrayData['m_role_det'] 
            ?? ($req ? $req->m_role_det : null) 
            ?? ($req ? $req->input('m_role_det') : null) 
            ?? ($req && method_exists($req, 'json') ? $req->json('m_role_det') : null)
            ?? null;

        if (empty($details) && $req && method_exists($req, 'getContent')) {
            $rawContent = $req->getContent();
            if (!empty($rawContent)) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && isset($decoded['m_role_det'])) {
                    $details = $decoded['m_role_det'];
                }
            }
        }

        if (is_string($details)) {
            $details = json_decode($details, true) ?? [];
        }

        $details = is_array($details) ? $details : [];

        if (empty($details) || count($details) === 0) {
            return [
                "status" => "error",
                "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
            ];
        }

        // Filter hanya menu yang berstatus aktif (is_active = true)
        $activeMenuIds = [];
        try {
            $activeMenuIds = \DB::table('m_menu')->where('is_active', true)->pluck('id')->toArray();
        } catch (\Throwable $th) {
            $activeMenuIds = [];
        }

        // Sanitize detail rows: pastikan hanya kolom yang valid pada tabel m_role_det dan hanya menu yang aktif
        $cleanedDetails = [];
        foreach ($details as $d) {
            $row = is_array($d) ? $d : (array)$d;
            $menuId = !empty($row['m_menu_id']) ? (int)$row['m_menu_id'] : (!empty($row['id']) ? (int)$row['id'] : null);
            if (!$menuId || (!empty($activeMenuIds) && !in_array($menuId, $activeMenuIds))) {
                continue;
            }
            $cleanedDetails[] = [
                'm_comp_id'   => $compId,
                'm_dir_id'    => $dirId,
                'm_menu_id'   => $menuId,
                'can_create'  => !empty($row['can_create']) ? 1 : 0,
                'can_read'    => isset($row['can_read']) ? (!empty($row['can_read']) ? 1 : 0) : 1,
                'can_update'  => !empty($row['can_update']) ? 1 : 0,
                'can_delete'  => !empty($row['can_delete']) ? 1 : 0,
                'can_verify'  => !empty($row['can_verify']) ? 1 : 0,
            ];
        }

        if (empty($cleanedDetails)) {
            return [
                "status" => "error",
                "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
            ];
        }

        if ($req && method_exists($req, 'merge')) {
            $req->merge(['m_role_det' => $cleanedDetails]);
            if (isset($req->request) && method_exists($req->request, 'set')) {
                $req->request->set('m_role_det', $cleanedDetails);
            }
        }

        $arrayData['m_role_det'] = $cleanedDetails;

        // Bersihkan atribut non-kolom database dari payload utama
        unset(
            $arrayData['id'],
            $arrayData['direktorat'],
            $arrayData['created_at'],
            $arrayData['updated_at'],
            $arrayData['creator_id'],
            $arrayData['last_editor_id'],
            $arrayData['m_comp'],
            $arrayData['m_dir'],
            $arrayData['creator'],
            $arrayData['last_editor']
        );

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

        $compId = !empty($arrayData['m_comp_id']) ? (int)$arrayData['m_comp_id'] : ((auth()->check() && !empty(auth()->user()->m_comp_id)) ? (int)auth()->user()->m_comp_id : 1);
        $dirId = !empty($arrayData['m_dir_id']) ? (int)$arrayData['m_dir_id'] : ((auth()->check() && !empty(auth()->user()->m_dir_id)) ? (int)auth()->user()->m_dir_id : null);

        if (isset($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = $compId;
        }
        if (isset($arrayData['m_dir_id'])) {
            $arrayData['m_dir_id'] = $dirId;
        }
        if (isset($arrayData['is_superadmin'])) {
            $arrayData['is_superadmin'] = !empty($arrayData['is_superadmin']) ? 1 : 0;
        }
        if (isset($arrayData['is_active'])) {
            $arrayData['is_active'] = !empty($arrayData['is_active']) ? 1 : 0;
        }

        $req = app()->request;
        $details = $arrayData['m_role_det'] 
            ?? ($req ? $req->m_role_det : null) 
            ?? ($req ? $req->input('m_role_det') : null) 
            ?? ($req && method_exists($req, 'json') ? $req->json('m_role_det') : null)
            ?? null;

        if (empty($details) && $req && method_exists($req, 'getContent')) {
            $rawContent = $req->getContent();
            if (!empty($rawContent)) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && isset($decoded['m_role_det'])) {
                    $details = $decoded['m_role_det'];
                }
            }
        }

        if (is_string($details)) {
            $details = json_decode($details, true) ?? [];
        }

        if ($details !== null) {
            $details = is_array($details) ? $details : [];
            if (empty($details) || count($details) === 0) {
                return [
                    "status" => "error",
                    "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                    "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
                ];
            }

            $existingDetIds = [];
            if ($id) {
                try {
                    $existingDetIds = \DB::table('m_role_det')->where('m_role_id', $id)->pluck('id')->toArray();
                } catch (\Throwable $th) {
                    $existingDetIds = [];
                }
            }

            // Filter hanya menu yang berstatus aktif (is_active = true)
            $activeMenuIds = [];
            try {
                $activeMenuIds = \DB::table('m_menu')->where('is_active', true)->pluck('id')->toArray();
            } catch (\Throwable $th) {
                $activeMenuIds = [];
            }

            $cleanedDetails = [];
            foreach ($details as $d) {
                $row = is_array($d) ? $d : (array)$d;
                $menuId = !empty($row['m_menu_id']) ? (int)$row['m_menu_id'] : null;
                if (!$menuId && !empty($row['id']) && !in_array($row['id'], $existingDetIds)) {
                    $menuId = (int)$row['id'];
                }
                if (!$menuId || (!empty($activeMenuIds) && !in_array($menuId, $activeMenuIds))) {
                    continue;
                }

                $cleanRow = [
                    'm_comp_id'   => $compId,
                    'm_dir_id'    => $dirId,
                    'm_menu_id'   => $menuId,
                    'can_create'  => !empty($row['can_create']) ? 1 : 0,
                    'can_read'    => isset($row['can_read']) ? (!empty($row['can_read']) ? 1 : 0) : 1,
                    'can_update'  => !empty($row['can_update']) ? 1 : 0,
                    'can_delete'  => !empty($row['can_delete']) ? 1 : 0,
                    'can_verify'  => !empty($row['can_verify']) ? 1 : 0,
                ];

                if (!empty($row['id']) && in_array($row['id'], $existingDetIds)) {
                    $cleanRow['id'] = (int)$row['id'];
                    $cleanRow['m_role_id'] = (int)$id;
                }

                $cleanedDetails[] = $cleanRow;
            }

            if (empty($cleanedDetails)) {
                return [
                    "status" => "error",
                    "message" => "Detail hak akses menu wajib diisi minimal 1 menu",
                    "errors" => ["m_role_det" => ["Detail hak akses menu wajib diisi minimal 1 menu"]]
                ];
            }

            // Hapus data m_role_det lama di database jika menu tersebut telah dihapus oleh pengguna dari tabel detail atau jika menu sudah inactive
            $submittedMenuIds = array_filter(array_column($cleanedDetails, 'm_menu_id'));
            if ($id) {
                try {
                    $delQuery = \DB::table('m_role_det')->where('m_role_id', $id);
                    if (!empty($submittedMenuIds)) {
                        $delQuery->where(function($q) use ($submittedMenuIds) {
                            $q->whereNotIn('m_menu_id', $submittedMenuIds)
                              ->orWhereIn('m_menu_id', function($sub) {
                                  $sub->select('id')->from('m_menu')->where('is_active', false);
                              });
                        });
                    } else {
                        $delQuery->whereIn('m_menu_id', function($sub) {
                            $sub->select('id')->from('m_menu')->where('is_active', false);
                        });
                    }
                    $delQuery->delete();
                } catch (\Throwable $th) {}
            }

            if ($req && method_exists($req, 'merge')) {
                $req->merge(['m_role_det' => $cleanedDetails]);
                if (isset($req->request) && method_exists($req->request, 'set')) {
                    $req->request->set('m_role_det', $cleanedDetails);
                }
            }

            $arrayData['m_role_det'] = $cleanedDetails;
        }

        unset(
            $arrayData['direktorat'],
            $arrayData['created_at'],
            $arrayData['updated_at'],
            $arrayData['creator_id'],
            $arrayData['last_editor_id'],
            $arrayData['m_comp'],
            $arrayData['m_dir'],
            $arrayData['creator'],
            $arrayData['last_editor']
        );

        return [
            "model" => $model,
            "data"  => $arrayData,
        ];
    }

    public function updateAfter($model, $arrayData, $metaData, $id = null)
    {
        $id = $id ?? ($model->id ?? null);
        if (!$id) return;

        $req = app()->request;
        $details = $arrayData['m_role_det'] 
            ?? ($req ? $req->m_role_det : null) 
            ?? ($req ? $req->input('m_role_det') : null) 
            ?? null;

        if (is_array($details)) {
            $submittedMenuIds = [];
            foreach ($details as $d) {
                $row = is_array($d) ? $d : (array)$d;
                $mId = !empty($row['m_menu_id']) ? (int)$row['m_menu_id'] : null;
                if ($mId) {
                    $submittedMenuIds[] = $mId;
                }
            }
            try {
                $delQuery = \DB::table('m_role_det')->where('m_role_id', $id);
                if (!empty($submittedMenuIds)) {
                    $delQuery->where(function($q) use ($submittedMenuIds) {
                        $q->whereNotIn('m_menu_id', $submittedMenuIds)
                          ->orWhereIn('m_menu_id', function($sub) {
                              $sub->select('id')->from('m_menu')->where('is_active', false);
                          });
                    });
                } else {
                    $delQuery->whereIn('m_menu_id', function($sub) {
                        $sub->select('id')->from('m_menu')->where('is_active', false);
                    });
                }
                $delQuery->delete();
            } catch (\Throwable $th) {}
        }
    }
}
