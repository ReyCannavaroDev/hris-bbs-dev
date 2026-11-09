<?php
namespace App\Cores;

class Respo
{
    private $userCache = [];
    private $hasRespoCache = [];
    private $activeRespoCache = [];
    private $effectiveRolesCache = [];
    private $sidebarIconsCache = null;
    private static $hasCanViewSalaryColCache = null;

    public function resetCache($userId = null)
    {
        if ($userId === null) {
            $this->userCache = [];
            $this->hasRespoCache = [];
            $this->activeRespoCache = [];
            $this->effectiveRolesCache = [];
            $this->sidebarIconsCache = null;
        } else {
            $uid = (int) $userId;
            unset(
                $this->userCache[$uid],
                $this->hasRespoCache[$uid],
                $this->activeRespoCache[$uid],
                $this->effectiveRolesCache[$uid]
            );
        }
    }

    private function hasCanViewSalaryColumn(): bool
    {
        if (self::$hasCanViewSalaryColCache === null) {
            try {
                self::$hasCanViewSalaryColCache = \Schema::hasColumn('m_role', 'can_view_salary');
            } catch (\Throwable $e) {
                self::$hasCanViewSalaryColCache = false;
            }
        }
        return self::$hasCanViewSalaryColCache;
    }

    private function resolveUser($user = null)
    {
        if ($user === null) {
            $authUser = @auth()->user();
            if (!$authUser) {
                return null;
            }
            $user = $authUser;
        }

        if (is_numeric($user)) {
            $uid = (int) $user;
            if (!$uid) return null;
            if (!array_key_exists($uid, $this->userCache)) {
                $this->userCache[$uid] = \DB::table('default_users')->where('id', $uid)->first();
            }
            return $this->userCache[$uid];
        }

        if (is_array($user)) {
            $uid = (int) ($user['id'] ?? 0);
            if ($uid) {
                if (!array_key_exists($uid, $this->userCache)) {
                    $dbUser = \DB::table('default_users')->where('id', $uid)->first();
                    $this->userCache[$uid] = $dbUser ?: (object) $user;
                }
                return $this->userCache[$uid];
            }
            return (object) $user;
        }

        if (is_object($user)) {
            $uid = (int) ($user->id ?? 0);
            if ($uid && !array_key_exists($uid, $this->userCache)) {
                $this->userCache[$uid] = $user;
            }
            return $user;
        }

        return null;
    }

    private function getSidebarIcons(): array
    {
        if ($this->sidebarIconsCache !== null) {
            return $this->sidebarIconsCache;
        }

        $icons = [];
        try {
            $rows = \DB::table('m_general')
                ->where('is_active', true)
                ->whereRaw("lower(key) = 'sidebar-icon'")
                ->get(['code', 'value']);
            foreach ($rows as $r) {
                $code = strtolower(trim((string) ($r->code ?? '')));
                if ($code !== '' && !empty($r->value)) {
                    $icons[$code] = $r->value;
                }
            }
        } catch (\Throwable $e) {
            $icons = [];
        }

        $this->sidebarIconsCache = $icons;
        return $this->sidebarIconsCache;
    }

    /**
     * Memeriksa apakah user sudah memiliki assignment pada default_users_respo (Respo Mode vs Legacy Mode)
     */
    public function userHasAssignedRespo($userId = null): bool
    {
        $uid = (int) ($userId ?? (@auth()->user()->id ?? 0));
        if (!$uid) {
            return false;
        }

        if (!array_key_exists($uid, $this->hasRespoCache)) {
            try {
                $this->hasRespoCache[$uid] = \DB::table('default_users_respo')
                    ->where('default_users_id', $uid)
                    ->exists();
            } catch (\Throwable $e) {
                $this->hasRespoCache[$uid] = false;
            }
        }

        return $this->hasRespoCache[$uid];
    }

    /**
     * Mengambil role aktif efektif user:
     * - Respo Mode: dari active default_users_respo -> m_respo_d -> m_role
     * - Legacy Mode: dari m_role_access -> m_role
     */
    public function getEffectiveRoles($user = null): array
    {
        $userObj = $this->resolveUser($user);
        $uid = (int) ($userObj->id ?? 0);
        if (!$uid) {
            return [];
        }

        if (array_key_exists($uid, $this->effectiveRolesCache)) {
            return $this->effectiveRolesCache[$uid];
        }

        $selectCols = ['r.id', 'r.name', 'r.is_superadmin', 'r.is_active'];
        if ($this->hasCanViewSalaryColumn()) {
            $selectCols[] = 'r.can_view_salary';
        }

        $rows = [];
        if ($this->userHasAssignedRespo($uid)) {
            $activeRespo = $this->checkRespoActive($uid);
            if ($activeRespo) {
                $rows = \DB::table('m_respo_d as rd')
                    ->join('m_role as r', 'r.id', '=', 'rd.m_role_id')
                    ->where('rd.m_respo_id', $activeRespo->id)
                    ->where('r.is_active', true)
                    ->select($selectCols)
                    ->orderBy('r.id', 'asc')
                    ->get()
                    ->toArray();
            }
        } else {
            $rows = \DB::table('m_role_access as ra')
                ->join('m_role as r', 'r.id', '=', 'ra.m_role_id')
                ->where('ra.user_id', $uid)
                ->where('r.is_active', true)
                ->select($selectCols)
                ->orderBy('r.id', 'asc')
                ->get()
                ->toArray();
        }

        $unique = [];
        foreach ($rows as $row) {
            $rid = (int) ($row->id ?? 0);
            if ($rid && !isset($unique[$rid])) {
                $unique[$rid] = $row;
            }
        }

        $this->effectiveRolesCache[$uid] = array_values($unique);
        return $this->effectiveRolesCache[$uid];
    }

    /**
     * Memeriksa apakah user diizinkan melihat / mengelola nominal gaji (allowlist / fail-closed)
     */
    public function canViewSalary($user = null): bool
    {
        $userObj = $this->resolveUser($user);
        if (!$userObj) {
            return false;
        }

        if ($this->isUserSuperadmin($userObj)) {
            return true;
        }

        if (!$this->hasCanViewSalaryColumn()) {
            return false;
        }

        foreach ($this->getEffectiveRoles($userObj) as $role) {
            if (!empty($role->can_view_salary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mengambil company scope aktif untuk user (siap dipakai modul transaksi/master/laporan)
     */
    public function getActiveCompanyScope($user = null): array
    {
        $userObj = $this->resolveUser($user);
        $uid = (int) ($userObj->id ?? 0);
        $hasRespo = $uid ? $this->userHasAssignedRespo($uid) : false;
        $activeRespo = $hasRespo ? $this->checkRespoActive($uid) : null;

        if ($this->isUserSuperadmin($userObj)) {
            return [
                'has_respo'       => $hasRespo,
                'is_unrestricted' => true,
                'is_holding'      => true,
                'm_dir_id'        => null,
                'm_divisi_id'     => null,
                'm_dept_id'       => null,
                'm_respo_id'      => $activeRespo ? (int) $activeRespo->id : null,
            ];
        }

        if ($hasRespo) {
            if (!$activeRespo) {
                return [
                    'has_respo'       => true,
                    'is_unrestricted' => false,
                    'is_holding'      => false,
                    'm_dir_id'        => -1,
                    'm_divisi_id'     => null,
                    'm_dept_id'       => null,
                    'm_respo_id'      => null,
                ];
            }

            $dirId = ($activeRespo->m_dir_id !== null && $activeRespo->m_dir_id !== '') ? (int) $activeRespo->m_dir_id : null;
            $isHolding = ($dirId === null);

            return [
                'has_respo'       => true,
                'is_unrestricted' => $isHolding,
                'is_holding'      => $isHolding,
                'm_dir_id'        => $dirId,
                'm_divisi_id'     => ($activeRespo->m_divisi_id !== null && $activeRespo->m_divisi_id !== '') ? (int) $activeRespo->m_divisi_id : null,
                'm_dept_id'       => ($activeRespo->m_dept_id !== null && $activeRespo->m_dept_id !== '') ? (int) $activeRespo->m_dept_id : null,
                'm_respo_id'      => (int) $activeRespo->id,
            ];
        }

        $legacyDirId = ($userObj && !empty($userObj->m_dir_id)) ? (int) $userObj->m_dir_id : null;
        $isLegacyHolding = ($legacyDirId === null);

        return [
            'has_respo'       => false,
            'is_unrestricted' => $isLegacyHolding,
            'is_holding'      => $isLegacyHolding,
            'm_dir_id'        => $legacyDirId,
            'm_divisi_id'     => null,
            'm_dept_id'       => null,
            'm_respo_id'      => null,
        ];
    }

    /**
     * Menyusun format menu dinamis yang siap dikonsumsi oleh frontend
     */
    public function formatMenu()
    {
        $req = app()->request;
        $fixedMenu = [];

        $level1 = $this->qMenu('modul');
        if (empty($level1)) {
            return $fixedMenu;
        }

        foreach ($level1 as $dt) {
            if ($dt["type"] === "multi") {
                $dt["children"] = $this->getMenuItemsByModul($dt["modul"]);
            }
            $fixedMenu[] = $dt;
            $fixedMenu[] = ["separator" => true];
        }

        // Fitur pencarian menu di sidebar
        if ($req && $req->has("search") && $req->search !== "" && $req->search !== "null") {
            $searchText = strtolower((string) $req->search);

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

        if ($req && $req->has("notin") && $req->notin !== "" && $req->notin !== "null") {
            $notinArr = array_map('trim', explode(',', strtolower((string) $req->notin)));
            $fixedMenu = array_values(
                array_filter($fixedMenu, function ($menu) use ($notinArr) {
                    if (!empty($menu["separator"])) {
                        return true;
                    }
                    $label = strtolower((string) ($menu["text"] ?? $menu["modul"] ?? ''));
                    return !in_array($label, $notinArr, true);
                })
            );
        }

        return $fixedMenu;
    }

    /**
     * Endpoint kompatibilitas untuk /frontend-menu/{name}
     */
    public function getMenu($filterModul = null)
    {
        if ($filterModul) {
            return $this->getMenuItemsByModul($filterModul);
        }

        return $this->qMenu('modul');
    }

    /**
     * Query modul tingkat atas
     */
    private function qMenu($type = "modul", $ctx = null)
    {
        $menu_respo = $this->getMenuRespo();
        if (empty($menu_respo)) {
            return [];
        }

        $menuIds = array_values(array_unique(array_filter(array_map('intval', $menu_respo))));
        if (empty($menuIds)) {
            return [];
        }

        $query = \DB::table('m_menu as b')
            ->where('b.is_active', true)
            ->whereIn('b.id', $menuIds);

        if (($type === "submodul" || $type === "menu") && $ctx !== null) {
            $query->where('b.modul', $ctx);
        }

        $rows = $query->select([
            'b.id',
            'b.modul',
            'b.submodul',
            'b.menu',
            'b.path',
            'b.endpoint',
            'b.description',
            'b.icon',
            'b.sequence',
            'b.truncatable',
        ])
        ->orderBy('b.sequence', 'asc')
        ->orderBy('b.id', 'asc')
        ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $groupKey = (string) ($row->$type ?? $row->modul ?? '');
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'items'       => [],
                    'minSequence' => $row->sequence ?? 999999,
                ];
            }
            $grouped[$groupKey]['items'][] = $row;
            if ($row->sequence !== null && $row->sequence < $grouped[$groupKey]['minSequence']) {
                $grouped[$groupKey]['minSequence'] = $row->sequence;
            }
        }

        uasort($grouped, function ($a, $b) {
            return ($a['minSequence'] <=> $b['minSequence']);
        });

        $summary = [];
        foreach ($grouped as $groupKey => $bucket) {
            $items = $bucket['items'];
            $isSingle = count($items) < 2;
            $first = $items[0];
            $summary[] = (object) [
                'type'        => $isSingle ? 'single' : 'multi',
                'modul'       => $groupKey,
                'sequence'    => $bucket['minSequence'],
                'path'        => $isSingle ? ($first->path ?? '#') : '#',
                'endpoint'    => $isSingle ? ($first->endpoint ?? '') : '',
                'description' => $isSingle ? ($first->description ?? null) : '#',
                'icon'        => $isSingle ? ($first->icon ?? null) : null,
                'truncatable' => $isSingle ? ((bool) ($first->truncatable ?? false)) : true,
            ];
        }

        return $this->transformFormatSupportFronted($summary);
    }

    private function transformFormatSupportFronted($arr)
    {
        $sidebarIcons = $this->getSidebarIcons();
        $tamp = [];
        foreach ($arr as $dt) {
            $access = $this->checkAccessForm($dt->path);
            $modulLower = strtolower(trim((string) ($dt->modul ?? '')));
            $injIcon = $sidebarIcons[$modulLower] ?? null;
            $tamp[] = array_merge([
                'modul'        => $dt->modul,
                'text'         => $dt->modul,
                'path'         => $dt->path,
                'truncatable'  => isset($dt->truncatable) ? (bool) $dt->truncatable : true,
                'icon'         => $injIcon ?? ($dt->icon ?? 'cog'),
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

        $menuIds = array_values(array_unique(array_filter(array_map('intval', $menu_respo))));
        if (empty($menuIds)) {
            return [];
        }

        $rows = \DB::table('m_menu as b')
            ->where('b.is_active', true)
            ->where('b.modul', $modulName)
            ->whereIn('b.id', $menuIds)
            ->select([
                'b.id',
                'b.modul',
                'b.submodul',
                'b.menu',
                'b.path',
                'b.endpoint',
                'b.icon',
                'b.sequence',
                'b.description',
                'b.truncatable',
            ])
            ->orderBy('b.sequence', 'asc')
            ->orderBy('b.id', 'asc')
            ->get()
            ->toArray();

        if (empty($rows)) {
            return [];
        }

        // Cek apakah modul ini memiliki submodul
        $hasSubmodul = false;
        foreach ($rows as $r) {
            $sub = trim((string) ($r->submodul ?? ''));
            if (!empty($sub) && $sub !== '-' && strtolower($sub) !== strtolower((string) $modulName)) {
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
            $sub = trim((string) ($r->submodul ?? ''));
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

            if (!empty($sub) && $sub !== '-' && strtolower($sub) !== strtolower((string) $modulName)) {
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
                'can_verify'  => true,
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
     * Mendapatkan daftar ID menu yang diizinkan (Respo Mode & Legacy Mode)
     */
    public function getMenuRespo($user = null)
    {
        $userObj = $this->resolveUser($user);
        if (!$userObj) {
            return [];
        }

        // Bypass untuk Superadmin atau Developer
        if ($this->isUserSuperadmin($userObj)) {
            return \DB::table("m_menu")
                ->where("is_active", true)
                ->pluck("id")
                ->toArray();
        }

        $roles = $this->getEffectiveRoles($userObj);
        $roleIds = array_values(array_unique(array_filter(array_map(function ($r) {
            return (int) ($r->id ?? 0);
        }, $roles))));

        if (empty($roleIds)) {
            return [];
        }

        $menuIds = \DB::table("m_role_det as rdet")
            ->join("m_role as r", "r.id", "=", "rdet.m_role_id")
            ->join("m_menu as m", "m.id", "=", "rdet.m_menu_id")
            ->whereIn("r.id", $roleIds)
            ->where("r.is_active", true)
            ->where("m.is_active", true)
            ->pluck("rdet.m_menu_id")
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        return $menuIds;
    }

    /**
     * Mengecek hak akses CRUD form/modul dengan agregasi OR dari seluruh role aktif user
     */
    public function checkAccessForm($modul, $user = null)
    {
        $userObj = $this->resolveUser($user);

        if ($this->isUserSuperadmin($userObj) || in_array($modul, ['#', '/dashboard', 'dashboard', '/notifikasi', 'notifikasi', '/presensi_absen_online', 'presensi_absen_online', '/account', 'account'], true)) {
            return [
                'can_read'   => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
                'can_verify' => true,
                'own_data'   => false,
            ];
        }

        $roles = $this->getEffectiveRoles($userObj);
        $roleIds = array_values(array_unique(array_filter(array_map(function ($r) {
            return (int) ($r->id ?? 0);
        }, $roles))));

        if (empty($roleIds)) {
            return [
                'can_read'   => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'can_verify' => false,
                'own_data'   => true,
            ];
        }

        $trimmedPath = ltrim((string) $modul, '/');
        $cleanPath = '/' . $trimmedPath;

        $rows = \DB::table('m_role_det as rdet')
            ->join('m_role as r', 'r.id', '=', 'rdet.m_role_id')
            ->join('m_menu as m', 'm.id', '=', 'rdet.m_menu_id')
            ->whereIn('r.id', $roleIds)
            ->where('r.is_active', true)
            ->where('m.is_active', true)
            ->where(function ($q) use ($cleanPath, $trimmedPath) {
                $q->where('m.path', $cleanPath)
                  ->orWhere('m.path', $trimmedPath);
            })
            ->select([
                'rdet.can_read',
                'rdet.can_create',
                'rdet.can_update',
                'rdet.can_delete',
                'rdet.can_verify',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [
                'can_read'   => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'can_verify' => false,
                'own_data'   => true,
            ];
        }

        $canRead = false;
        $canCreate = false;
        $canUpdate = false;
        $canDelete = false;
        $canVerify = false;

        foreach ($rows as $row) {
            $canRead = $canRead || (bool) $row->can_read;
            $canCreate = $canCreate || (bool) $row->can_create;
            $canUpdate = $canUpdate || (bool) $row->can_update;
            $canDelete = $canDelete || (bool) $row->can_delete;
            $canVerify = $canVerify || (bool) $row->can_verify;
        }

        return [
            'can_read'   => $canRead,
            'can_create' => $canCreate,
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
            'can_verify' => $canVerify,
            'own_data'   => false,
        ];
    }

    /**
     * Mengambil responsibility yang sedang aktif (prioritas is_primary = true, lalu seq asc)
     */
    public function checkRespoActive($userId = null)
    {
        $uid = (int) ($userId ?? (@auth()->user()->id ?? 0));
        if (!$uid) {
            return null;
        }

        if (array_key_exists($uid, $this->activeRespoCache)) {
            return $this->activeRespoCache[$uid];
        }

        $active = \DB::table("default_users_respo as dur")
            ->join("m_respo as r", "r.id", "=", "dur.m_respo_id")
            ->leftJoin("m_dir as d", "d.id", "=", "r.m_dir_id")
            ->leftJoin("m_divisi as div", "div.id", "=", "r.m_divisi_id")
            ->leftJoin("m_dept as dept", "dept.id", "=", "r.m_dept_id")
            ->where("dur.default_users_id", $uid)
            ->where("dur.is_active", true)
            ->where("r.is_active", true)
            ->select([
                "r.*",
                "d.nama as dir_nama",
                "div.nama as divisi_nama",
                "dept.nama as dept_nama",
                "dur.id as user_respo_id",
                "dur.is_primary",
                "dur.seq"
            ])
            ->orderBy("dur.is_primary", "desc")
            ->orderBy("dur.seq", "asc")
            ->orderBy("dur.id", "asc")
            ->first();

        $this->activeRespoCache[$uid] = $active;
        return $this->activeRespoCache[$uid];
    }

    /**
     * Memeriksa apakah user adalah Superadmin atau Developer (mendukung Respo Mode & Legacy Mode)
     */
    public function isUserSuperadmin($user = null)
    {
        $userObj = $this->resolveUser($user);
        if (!$userObj) return false;
        if (strtolower((string) ($userObj->username ?? '')) === 'developer') return true;
        if (strtolower((string) ($userObj->user_type ?? '')) === 'admin') return true;

        foreach ($this->getEffectiveRoles($userObj) as $role) {
            if (!empty($role->is_superadmin)) {
                return true;
            }
        }

        return false;
    }
}

