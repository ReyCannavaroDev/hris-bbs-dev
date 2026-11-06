<?php
namespace App\Cores;

class Respo
{
    /**
     * Menyusun format menu dinamis yang siap dikonsumsi oleh frontend
     */
    public function formatMenu()
    {
        $req = app()->request;
        $fixedMenu = [];

        $user_id = @auth()->user()->id ?? 0;
        $user = \DB::table("default_users")->where("id", $user_id)->first();
        $isSuperadmin = $this->isUserSuperadmin($user);

        // Jika bukan superadmin dan tidak memiliki respo aktif, hanya tampilkan fixedMenu
        $respo_active = $this->checkRespoActive();
        if (!$isSuperadmin && empty($respo_active)) {
            return $fixedMenu;
        }

        $level1 = $this->qMenu('modul');

        foreach ($level1 as $dt) {
            if ($dt["type"] === "multi") {
                $dt["children"] = $this->getMenuItemsByModul($dt["modul"]);
            }
            $fixedMenu[] = $dt;
            $fixedMenu[] = ["separator" => true];
        }

        // Fitur pencarian menu di sidebar
        if ($req->has("search") && $req->search !== "" && $req->search !== "null") {
            $searchText = strtolower($req->search);

            $filterMenu = function ($menu, $searchText) use (&$filterMenu) {
                if (isset($menu["children"])) {
                    $menu["children"] = array_values(
                        array_filter($menu["children"], function ($child) use ($searchText, &$filterMenu) {
                            return \Str::contains(strtolower($child["modul"] ?? ''), $searchText) ||
                                \Str::contains(strtolower($child["text"] ?? ''), $searchText) ||
                                \Str::contains(strtolower($child["path"] ?? ''), $searchText) ||
                                $filterMenu($child, $searchText);
                        })
                    );
                    return !empty($menu["children"]) ||
                        \Str::contains(strtolower($menu["modul"] ?? ''), $searchText) ||
                        \Str::contains(strtolower($menu["text"] ?? ''), $searchText) ||
                        \Str::contains(strtolower($menu["path"] ?? ''), $searchText);
                } else {
                    return \Str::contains(strtolower($menu["modul"] ?? ''), $searchText) ||
                        \Str::contains(strtolower($menu["text"] ?? ''), $searchText) ||
                        \Str::contains(strtolower($menu["path"] ?? ''), $searchText);
                }
            };

            $fixedMenu = array_values(
                array_filter($fixedMenu, function ($menu) use ($searchText, $filterMenu) {
                    return $filterMenu($menu, $searchText);
                })
            );
        }

        return $fixedMenu;
    }

    /**
     * Query modul tingkat atas
     */
    private function qMenu($type = "modul", $ctx = null)
    {
        $where = "";
        $parent_condition = "";
        $parent_condition_c = "";
        if ($type === "submodul" || $type === "menu") {
            $where = " and b.modul = " . \DB::getPdo()->quote($ctx);
            $parent_condition = " and a.modul = " . \DB::getPdo()->quote($ctx);
            $parent_condition_c = " and c.modul = " . \DB::getPdo()->quote($ctx);
        }

        $menu_respo = $this->getMenuRespo();
        if (empty($menu_respo)) {
            return [];
        }

        $menu_ids = implode(",", array_map('intval', $menu_respo));
        $respo_condition = " and b.id in($menu_ids)";

        $data = \DB::select("
            select 
                (select case 
                    when count(1) < 2 then 'single'
                    else 'multi'
                end
                from m_menu a where a.$type = b.$type $parent_condition limit 3) as type,
                b.$type as modul,
                (select 
                    case
                        when count(1) < 2 then (select c.sequence from m_menu c where c.$type = b.$type $parent_condition_c)
                        else (select c.sequence from m_menu c where c.$type = b.$type $parent_condition_c order by c.sequence asc limit 1)
                    end
                    from m_menu a where a.$type = b.$type $parent_condition group by a.$type limit 2) as sequence,
                (select 
                    case
                        when count(1) < 2 then (select c.path from m_menu c where c.$type = b.$type $parent_condition_c)
                        else '#'
                    end
                    from m_menu a where a.$type = b.$type $parent_condition group by a.$type limit 2) as path,
                (select 
                    case
                        when count(1) < 2 then (select c.endpoint from m_menu c where c.$type = b.$type $parent_condition_c)
                        else ''
                    end
                    from m_menu a where a.$type = b.$type $parent_condition group by a.$type limit 2) as endpoint,
                (select 
                    case
                        when count(1) < 2 then (select c.description from m_menu c where c.$type = b.$type $parent_condition_c)
                        else '#'
                    end
                    from m_menu a where a.$type = b.$type $parent_condition group by a.$type limit 2) as description,
                (select 
                    case
                        when count(1) < 2 then (select c.icon from m_menu c where c.$type = b.$type $parent_condition_c)
                        else null
                    end
                    from m_menu a where a.$type = b.$type $parent_condition group by a.$type limit 2) as icon
            from m_menu b 
            where b.is_active = true $where $respo_condition
            group by b.$type 
            order by 3 asc
        ");

        return $this->transformFormatSupportFronted($data);
    }

    private function transformFormatSupportFronted($arr)
    {
        $tamp = [];
        foreach ($arr as $dt) {
            $access = $this->checkAccessForm($dt->path);
            $tamp[] = array_merge([
                'modul'        => $dt->modul,
                'text'         => $dt->modul,
                'path'         => $dt->path,
                'truncatable'  => true,
                'icon'         => $dt->icon ?? 'arrow-right',
                'description'  => $dt->description,
                'endpoint'     => $dt->endpoint ?? $dt->path,
                'type'         => $dt->type,
            ], $access);
        }
        return $tamp;
    }

    /**
     * Mengambil item menu anak di bawah sebuah modul
     */
    private function getMenuItemsByModul($modulName)
    {
        $menu_respo = $this->getMenuRespo();
        if (empty($menu_respo)) {
            return [];
        }

        $menu_ids = implode(",", array_map('intval', $menu_respo));
        $respo_condition = " and b.id in($menu_ids)";

        $rows = \DB::select("
            select 
                b.id,
                b.modul,
                b.submodul,
                b.menu,
                b.path,
                b.endpoint,
                b.icon,
                b.sequence,
                b.description,
                b.truncatable
            from m_menu b
            where b.is_active = true 
              and b.modul = ? 
              $respo_condition
            order by b.sequence asc, b.id asc
        ", [$modulName]);

        if (empty($rows)) {
            return [];
        }

        // Cek apakah modul ini memiliki submodul
        $hasSubmodul = false;
        foreach ($rows as $r) {
            $sub = trim($r->submodul ?? '');
            if (!empty($sub) && $sub !== '-' && strtolower($sub) !== strtolower($modulName)) {
                $hasSubmodul = true;
                break;
            }
        }

        if (!$hasSubmodul) {
            $children = [];
            foreach ($rows as $r) {
                $access = $this->checkAccessForm($r->path);
                $children[] = array_merge([
                    'modul'       => $r->modul,
                    'text'        => $r->menu,
                    'path'        => $r->path,
                    'truncatable' => @$r->truncatable ? true : false,
                    'icon'        => $r->icon ?? 'arrow-right',
                    'description' => $r->description,
                    'endpoint'    => $r->endpoint ?? $r->path,
                    'type'        => 'single',
                ], $access);
            }
            return $children;
        }

        // Pengelompokan berhirarki submodul
        $grouped = [];
        $standalone = [];

        foreach ($rows as $r) {
            $sub = trim($r->submodul ?? '');
            $access = $this->checkAccessForm($r->path);
            $itemFormatted = array_merge([
                'modul'       => $r->modul,
                'submodul'    => $r->submodul,
                'text'        => $r->menu,
                'path'        => $r->path,
                'truncatable' => @$r->truncatable ? true : false,
                'icon'        => $r->icon ?? 'arrow-right',
                'description' => $r->description,
                'endpoint'    => $r->endpoint ?? $r->path,
                'type'        => 'single',
            ], $access);

            if (!empty($sub) && $sub !== '-' && strtolower($sub) !== strtolower($modulName)) {
                if (!isset($grouped[$sub])) {
                    $grouped[$sub] = [
                        'sequence' => $r->sequence ?? 999,
                        'items'    => []
                    ];
                }
                $grouped[$sub]['items'][] = $itemFormatted;
            } else {
                $standalone[] = $itemFormatted;
            }
        }

        $resultChildren = [];
        foreach ($grouped as $subName => $groupData) {
            $resultChildren[] = [
                'modul'       => $modulName,
                'text'        => $subName,
                'path'        => '#',
                'truncatable' => true,
                'icon'        => 'arrow-right',
                'description' => null,
                'endpoint'    => '#',
                'type'        => 'multi',
                'can_read'    => true,
                'can_create'  => true,
                'can_update'  => true,
                'can_delete'  => true,
                'own_data'    => false,
                'children'    => $groupData['items'],
            ];
        }

        foreach ($standalone as $st) {
            $resultChildren[] = $st;
        }

        return $resultChildren;
    }

    /**
     * Mendapatkan daftar ID menu yang diizinkan untuk responsibility aktif
     */
    public function getMenuRespo()
    {
        $user_id = @auth()->user()->id ?? 0;
        $user = \DB::table("default_users")->where("id", $user_id)->first();

        // Bypass untuk Superadmin atau Developer
        if ($this->isUserSuperadmin($user)) {
            $allMenu = \DB::table("m_menu")
                ->where("is_active", true)
                ->pluck("id")
                ->toArray();
            return $allMenu;
        }

        $respo_active = $this->checkRespoActive();
        if (!$respo_active) {
            return [];
        }

        // Ambil m_menu_id dari relasi: m_respo_d -> m_role -> m_role_det
        $menuIds = \DB::table("m_respo_d as rd")
            ->join("m_role as r", "r.id", "=", "rd.m_role_id")
            ->join("m_role_det as rdet", "rdet.m_role_id", "=", "r.id")
            ->where("rd.m_respo_id", $respo_active->id)
            ->where("r.is_active", true)
            ->pluck("rdet.m_menu_id")
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        return $menuIds;
    }

    /**
     * Mengecek hak akses CRUD form/modul untuk responsibility aktif
     */
    public function checkAccessForm($modul)
    {
        $user_id = @auth()->user()->id ?? 0;
        $user = \DB::table('default_users')->where('id', $user_id)->first();

        if ($this->isUserSuperadmin($user) || in_array($modul, ['#', '/dashboard', 'dashboard', '/notifikasi', 'notifikasi', '/presensi_absen_online', 'presensi_absen_online'])) {
            return [
                'can_read'   => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
                'own_data'   => false,
            ];
        }

        $respo_active = $this->checkRespoActive();
        if (!$respo_active) {
            return [
                'can_read'   => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'own_data'   => true,
            ];
        }

        $cleanPath = '/' . ltrim($modul, '/');

        $access = \DB::table('m_respo_d as rd')
            ->join('m_role as r', 'r.id', '=', 'rd.m_role_id')
            ->join('m_role_det as rdet', 'rdet.m_role_id', '=', 'r.id')
            ->join('m_menu as m', 'm.id', '=', 'rdet.m_menu_id')
            ->where('rd.m_respo_id', $respo_active->id)
            ->where('r.is_active', true)
            ->where('m.path', $cleanPath)
            ->select([
                'rdet.can_read',
                'rdet.can_create',
                'rdet.can_update',
                'rdet.can_delete',
            ])
            ->first();

        if (!$access) {
            return [
                'can_read'   => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'own_data'   => true,
            ];
        }

        return [
            'can_read'   => (bool) $access->can_read,
            'can_create' => (bool) $access->can_create,
            'can_update' => (bool) $access->can_update,
            'can_delete' => (bool) $access->can_delete,
            'own_data'   => false,
        ];
    }

    /**
     * Mengambil responsibility yang sedang aktif (is_primary = true)
     */
    public function checkRespoActive()
    {
        $user_id = @auth()->user()->id ?? 0;
        if (!$user_id) {
            return null;
        }

        $active = \DB::table("default_users_respo as dur")
            ->join("m_respo as r", "r.id", "=", "dur.m_respo_id")
            ->leftJoin("m_dir as d", "d.id", "=", "r.m_dir_id")
            ->leftJoin("m_divisi as div", "div.id", "=", "r.m_divisi_id")
            ->leftJoin("m_dept as dept", "dept.id", "=", "r.m_dept_id")
            ->where("dur.default_users_id", $user_id)
            ->where("dur.is_active", true)
            ->where("r.is_active", true)
            ->where("dur.is_primary", true)
            ->select([
                "r.*",
                "d.nama as dir_nama",
                "div.nama as divisi_nama",
                "dept.nama as dept_nama",
                "dur.id as user_respo_id",
                "dur.seq"
            ])
            ->first();

        // Fallback: Jika belum ada yang ditandai primary, ambil respo pertama yang aktif
        if (!$active) {
            $active = \DB::table("default_users_respo as dur")
                ->join("m_respo as r", "r.id", "=", "dur.m_respo_id")
                ->leftJoin("m_dir as d", "d.id", "=", "r.m_dir_id")
                ->leftJoin("m_divisi as div", "div.id", "=", "r.m_divisi_id")
                ->leftJoin("m_dept as dept", "dept.id", "=", "r.m_dept_id")
                ->where("dur.default_users_id", $user_id)
                ->where("dur.is_active", true)
                ->where("r.is_active", true)
                ->select([
                    "r.*",
                    "d.nama as dir_nama",
                    "div.nama as divisi_nama",
                    "dept.nama as dept_nama",
                    "dur.id as user_respo_id",
                    "dur.seq"
                ])
                ->orderBy("dur.seq", "asc")
                ->first();
        }

        return $active;
    }

    /**
     * Memeriksa apakah user adalah Superadmin atau Developer
     */
    private function isUserSuperadmin($user)
    {
        if (!$user) return false;
        if (strtolower($user->username ?? '') === 'developer') return true;
        if (strtolower($user->user_type ?? '') === 'admin') return true;

        // Cek m_role_access is_superadmin
        $hasSuperadminRole = \DB::table('m_role as r')
            ->join('m_role_access as ra', 'ra.m_role_id', '=', 'r.id')
            ->where('ra.user_id', $user->id)
            ->where('r.is_superadmin', true)
            ->exists();

        return (bool) $hasSuperadminRole;
    }
}

