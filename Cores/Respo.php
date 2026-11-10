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
     * Mengambil daftar ID role efektif user (Respo Mode & Legacy Mode)
     */
    public function getEffectiveRoleIds($user = null): array
    {
        $ids = [];
        foreach ($this->getEffectiveRoles($user) as $role) {
            $rid = (int) ($role->id ?? 0);
            if ($rid > 0 && !in_array($rid, $ids, true)) {
                $ids[] = $rid;
            }
        }
        return $ids;
    }

    /**
     * Mengambil daftar nama role efektif user (Respo Mode & Legacy Mode)
     */
    public function getEffectiveRoleNames($user = null, bool $lowercase = false): array
    {
        $names = [];
        foreach ($this->getEffectiveRoles($user) as $role) {
            $name = trim((string) ($role->name ?? ''));
            if ($name === '') {
                continue;
            }
            if ($lowercase) {
                $name = strtolower($name);
            }
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Memeriksa kata kunci peran/jabatan HR (dengan boundary agar tidak false-positive pada nama orang seperti 'Fahrizal')
     */
    public function isHrdKeyword(?string $text): bool
    {
        $str = strtolower(trim((string) $text));
        if ($str === '') {
            return false;
        }
        return (bool) preg_match('/(?:^|[^a-z])(hr[a-z]*|personalia|sdm|human[\s_]*capital|human[\s_]*resources?)(?:[^a-z]|$)/i', $str);
    }

    /**
     * Memeriksa kata kunci role Admin operasional (Admin, Admin Master, Administrator, dll)
     */
    public function isAdminKeyword(?string $text): bool
    {
        $str = strtolower(trim((string) $text));
        if ($str === '') {
            return false;
        }
        return (bool) preg_match('/(?:^|[^a-z])(admin[a-z]*)(?:[^a-z]|$)/i', $str);
    }

    /**
     * Memeriksa apakah user berperan sebagai HRD melalui active Respo role / legacy role,
     * dengan fallback ke jabatan/divisi m_kary pada Legacy Mode atau respo tanpa role.
     */
    public function isUserHrd($user = null, bool $allowKaryFallback = true): bool
    {
        $userObj = $this->resolveUser($user);
        $uid = (int) ($userObj->id ?? 0);
        if (!$uid) {
            return false;
        }

        $hasRespo = $this->userHasAssignedRespo($uid);
        $activeRespo = $hasRespo ? $this->checkRespoActive($uid) : null;
        if ($hasRespo && !$activeRespo) {
            return false;
        }

        $roles = $this->getEffectiveRoles($userObj);
        foreach ($roles as $role) {
            if ($this->isHrdKeyword($role->name ?? '')) {
                return true;
            }
        }

        // Pada Respo Mode yang sudah memiliki role spesifik, percayai daftar role active Respo
        // agar pergantian Respo (mis. HRD <-> Finance/User) konsisten. Fallback m_kary tetap aktif
        // pada Legacy Mode atau bila active Respo belum memiliki detail role.
        if ($allowKaryFallback && (!$hasRespo || empty($roles)) && !empty($userObj->m_kary_id)) {
            $userKary = \DB::table('m_kary')
                ->leftJoin('m_posisi', 'm_posisi.id', '=', 'm_kary.m_posisi_id')
                ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                ->where('m_kary.id', $userObj->m_kary_id)
                ->select('m_posisi.desc_kerja as posisi_nama', 'm_divisi.nama as divisi_nama')
                ->first();

            if ($userKary) {
                if ($this->isHrdKeyword($userKary->posisi_nama ?? '') || $this->isHrdKeyword($userKary->divisi_nama ?? '')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah user berwenang melihat data seluruh karyawan di dalam scope org aktifnya
     * (Superadmin, HRD/Personalia, atau Admin Operasional via active Respo / legacy role).
     */
    public function isUserHrdOrAdmin($user = null, bool $allowKaryFallback = true): bool
    {
        $userObj = $this->resolveUser($user);
        if (!$userObj) {
            return false;
        }

        if ($this->isUserSuperadmin($userObj)) {
            return true;
        }

        $uid = (int) ($userObj->id ?? 0);
        $hasRespo = $uid ? $this->userHasAssignedRespo($uid) : false;
        $activeRespo = $hasRespo ? $this->checkRespoActive($uid) : null;
        if ($hasRespo && !$activeRespo) {
            return false;
        }

        foreach ($this->getEffectiveRoles($userObj) as $role) {
            $roleName = $role->name ?? '';
            if ($this->isHrdKeyword($roleName) || $this->isAdminKeyword($roleName)) {
                return true;
            }
        }

        return $this->isUserHrd($userObj, $allowKaryFallback);
    }

    /**
     * Memeriksa apakah target company / divisi / departemen sesuai active company scope user.
     * Catatan: Direct supervisor (atasan langsung) dan explicit default_user_id perlu dibebaskan
     * sebelum memanggil helper ini.
     */
    public function matchesActiveCompanyScope($targetDirId = null, $targetDivisiId = null, $targetDeptId = null, $user = null): bool
    {
        $scope = $this->getActiveCompanyScope($user);

        if (!empty($scope['is_unrestricted'])) {
            return true;
        }

        if (($scope['m_dir_id'] ?? null) === -1) {
            return false;
        }

        if (!empty($scope['m_dir_id'])) {
            if (empty($targetDirId) || (int) $targetDirId !== (int) $scope['m_dir_id']) {
                return false;
            }
        }

        if (!empty($scope['m_divisi_id'])) {
            if (empty($targetDivisiId) || (int) $targetDivisiId !== (int) $scope['m_divisi_id']) {
                return false;
            }
        }

        if (!empty($scope['m_dept_id'])) {
            if (empty($targetDeptId) || (int) $targetDeptId !== (int) $scope['m_dept_id']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mengambil metadata company/divisi/dept/karyawan/atasan untuk tiket approval
     * secara konsisten across t_lembur, t_cuti, t_spd, t_rpd, maupun tabel transaksi lain.
     */
    public function getApprovalTicketScopeMetadata($trxTable, $trxId, $genApp = null): array
    {
        $meta = [
            'm_comp_id'                 => null,
            'm_dir_id'                  => null,
            'm_divisi_id'               => null,
            'm_dept_id'                 => null,
            'm_kary_id'                 => null,
            'atasan_kary_id'            => null,
            'pic_id'                    => null,
            'creator_id'                => null,
            'has_mapped_supervisor'     => false,
        ];

        $genObj = is_object($genApp) ? $genApp : (is_array($genApp) ? (object) $genApp : null);
        $table = $trxTable ?: ($genObj->trx_table ?? null);
        $id = $trxId ?: ($genObj->trx_id ?? null);

        $trx = null;
        $spd = null;
        if ($table && $id) {
            try {
                if (\Schema::hasTable($table)) {
                    $trx = \DB::table($table)->where('id', $id)->first();
                }
            } catch (\Throwable $e) {
                $trx = null;
            }
        }

        if ($table === 't_rpd' && $trx && !empty($trx->t_spd_id)) {
            $spd = \DB::table('t_spd')->where('id', $trx->t_spd_id)->first();
        }

        $sourceForOrg = $spd ?: $trx;
        $picId = $trx->pic_id ?? ($spd->pic_id ?? null);
        $creatorId = $trx->creator_id ?? ($genObj->trx_creator_id ?? ($genObj->creator_id ?? null));

        $karyId = null;
        if (!empty($sourceForOrg->m_kary_id)) {
            $karyId = (int) $sourceForOrg->m_kary_id;
        } elseif (!empty($picId)) {
            $karyId = (int) (\DB::table('default_users')->where('id', $picId)->value('m_kary_id') ?? 0) ?: null;
        } elseif (!empty($creatorId)) {
            $karyId = (int) (\DB::table('default_users')->where('id', $creatorId)->value('m_kary_id') ?? 0) ?: null;
        }

        $kary = $karyId ? \DB::table('m_kary')->where('id', $karyId)->first() : null;
        $creatorUser = $creatorId ? \DB::table('default_users')->where('id', $creatorId)->first() : null;
        $picUser = $picId ? \DB::table('default_users')->where('id', $picId)->first() : null;

        $meta['m_comp_id'] = !empty($trx->m_comp_id)
            ? (int) $trx->m_comp_id
            : (!empty($spd->m_comp_id)
                ? (int) $spd->m_comp_id
                : (!empty($kary->m_comp_id)
                    ? (int) $kary->m_comp_id
                    : (!empty($genObj->m_comp_id)
                        ? (int) $genObj->m_comp_id
                        : (!empty($creatorUser->m_comp_id) ? (int) $creatorUser->m_comp_id : null))));

        $meta['m_dir_id'] = !empty($sourceForOrg->m_dir_id)
            ? (int) $sourceForOrg->m_dir_id
            : (!empty($kary->m_dir_id)
                ? (int) $kary->m_dir_id
                : (!empty($genObj->m_dir_id)
                    ? (int) $genObj->m_dir_id
                    : (!empty($picUser->m_dir_id)
                        ? (int) $picUser->m_dir_id
                        : (!empty($creatorUser->m_dir_id) ? (int) $creatorUser->m_dir_id : null))));

        $meta['m_divisi_id'] = !empty($sourceForOrg->m_divisi_id)
            ? (int) $sourceForOrg->m_divisi_id
            : (!empty($kary->m_divisi_id) ? (int) $kary->m_divisi_id : null);

        $meta['m_dept_id'] = !empty($sourceForOrg->m_dept_id)
            ? (int) $sourceForOrg->m_dept_id
            : (!empty($kary->m_dept_id) ? (int) $kary->m_dept_id : null);

        $meta['m_kary_id'] = $karyId;
        $meta['atasan_kary_id'] = !empty($kary->atasan_id) ? (int) $kary->atasan_id : null;
        $meta['pic_id'] = $picId ? (int) $picId : null;
        $meta['creator_id'] = $creatorId ? (int) $creatorId : null;

        $hasSupervisorUser = false;
        if (!empty($genObj->last_approve_id)) {
            $hasSupervisorUser = true;
        } elseif (!empty($meta['atasan_kary_id'])) {
            $hasSupervisorUser = \DB::table('default_users')
                ->where('m_kary_id', $meta['atasan_kary_id'])
                ->exists();
        }
        $meta['has_mapped_supervisor'] = $hasSupervisorUser;

        return $meta;
    }

    /**
     * Menyusun klausa SQL PostgreSQL untuk memfilter baris generate_approval berdasarkan
     * active company/divisi/dept scope user (hanya untuk approver berbasis Role / HRD).
     */
    public function buildGenerateApprovalScopeSql(string $genAlias = 'generate_approval', ?array $scope = null, $user = null): string
    {
        $activeScope = $scope ?? $this->getActiveCompanyScope($user);

        if (!empty($activeScope['is_unrestricted'])) {
            return '1 = 1';
        }

        if (($activeScope['m_dir_id'] ?? null) === -1) {
            return '1 = 0';
        }

        $conditions = [];

        if (!empty($activeScope['m_dir_id'])) {
            $dirId = (int) $activeScope['m_dir_id'];
            $effDirSql = "coalesce(
                case
                    when {$genAlias}.trx_table = 't_lembur' then (
                        select coalesce(tl.m_dir_id, mk_l.m_dir_id)
                        from t_lembur tl
                        left join m_kary mk_l on mk_l.id = tl.m_kary_id
                        where tl.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_cuti' then (
                        select coalesce(tc.m_dir_id, mk_c.m_dir_id)
                        from t_cuti tc
                        left join m_kary mk_c on mk_c.id = tc.m_kary_id
                        where tc.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_spd' then (
                        select coalesce(ts.m_dir_id, mk_s.m_dir_id, mk_sp.m_dir_id, du_sp.m_dir_id)
                        from t_spd ts
                        left join m_kary mk_s on mk_s.id = ts.m_kary_id
                        left join default_users du_sp on du_sp.id = ts.pic_id
                        left join m_kary mk_sp on mk_sp.id = du_sp.m_kary_id
                        where ts.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_rpd' then (
                        select coalesce(ts2.m_dir_id, mk_r.m_dir_id, mk_rp.m_dir_id, du_rp.m_dir_id)
                        from t_rpd tr
                        left join t_spd ts2 on ts2.id = tr.t_spd_id
                        left join m_kary mk_r on mk_r.id = ts2.m_kary_id
                        left join default_users du_rp on du_rp.id = ts2.pic_id
                        left join m_kary mk_rp on mk_rp.id = du_rp.m_kary_id
                        where tr.id = {$genAlias}.trx_id
                    )
                    else null
                end,
                (select mk_u.m_dir_id from default_users du join m_kary mk_u on mk_u.id = du.m_kary_id where du.id = coalesce({$genAlias}.trx_creator_id, {$genAlias}.creator_id)),
                {$genAlias}.m_dir_id,
                (select du2.m_dir_id from default_users du2 where du2.id = coalesce({$genAlias}.trx_creator_id, {$genAlias}.creator_id))
            )";
            $conditions[] = "({$effDirSql}) = {$dirId}";
        }

        if (!empty($activeScope['m_divisi_id'])) {
            $divId = (int) $activeScope['m_divisi_id'];
            $effDivSql = "coalesce(
                case
                    when {$genAlias}.trx_table = 't_lembur' then (
                        select mk_l.m_divisi_id
                        from t_lembur tl
                        join m_kary mk_l on mk_l.id = tl.m_kary_id
                        where tl.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_cuti' then (
                        select mk_c.m_divisi_id
                        from t_cuti tc
                        join m_kary mk_c on mk_c.id = tc.m_kary_id
                        where tc.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_spd' then (
                        select coalesce(ts.m_divisi_id, mk_s.m_divisi_id, mk_sp.m_divisi_id)
                        from t_spd ts
                        left join m_kary mk_s on mk_s.id = ts.m_kary_id
                        left join default_users du_sp on du_sp.id = ts.pic_id
                        left join m_kary mk_sp on mk_sp.id = du_sp.m_kary_id
                        where ts.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_rpd' then (
                        select coalesce(ts2.m_divisi_id, mk_r.m_divisi_id, mk_rp.m_divisi_id)
                        from t_rpd tr
                        left join t_spd ts2 on ts2.id = tr.t_spd_id
                        left join m_kary mk_r on mk_r.id = ts2.m_kary_id
                        left join default_users du_rp on du_rp.id = ts2.pic_id
                        left join m_kary mk_rp on mk_rp.id = du_rp.m_kary_id
                        where tr.id = {$genAlias}.trx_id
                    )
                    else null
                end,
                (select mk_u.m_divisi_id from default_users du join m_kary mk_u on mk_u.id = du.m_kary_id where du.id = coalesce({$genAlias}.trx_creator_id, {$genAlias}.creator_id))
            )";
            $conditions[] = "({$effDivSql}) = {$divId}";
        }

        if (!empty($activeScope['m_dept_id'])) {
            $deptId = (int) $activeScope['m_dept_id'];
            $effDeptSql = "coalesce(
                case
                    when {$genAlias}.trx_table = 't_lembur' then (
                        select mk_l.m_dept_id
                        from t_lembur tl
                        join m_kary mk_l on mk_l.id = tl.m_kary_id
                        where tl.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_cuti' then (
                        select mk_c.m_dept_id
                        from t_cuti tc
                        join m_kary mk_c on mk_c.id = tc.m_kary_id
                        where tc.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_spd' then (
                        select coalesce(ts.m_dept_id, mk_s.m_dept_id, mk_sp.m_dept_id)
                        from t_spd ts
                        left join m_kary mk_s on mk_s.id = ts.m_kary_id
                        left join default_users du_sp on du_sp.id = ts.pic_id
                        left join m_kary mk_sp on mk_sp.id = du_sp.m_kary_id
                        where ts.id = {$genAlias}.trx_id
                    )
                    when {$genAlias}.trx_table = 't_rpd' then (
                        select coalesce(ts2.m_dept_id, mk_r.m_dept_id, mk_rp.m_dept_id)
                        from t_rpd tr
                        left join t_spd ts2 on ts2.id = tr.t_spd_id
                        left join m_kary mk_r on mk_r.id = ts2.m_kary_id
                        left join default_users du_rp on du_rp.id = ts2.pic_id
                        left join m_kary mk_rp on mk_rp.id = du_rp.m_kary_id
                        where tr.id = {$genAlias}.trx_id
                    )
                    else null
                end,
                (select mk_u.m_dept_id from default_users du join m_kary mk_u on mk_u.id = du.m_kary_id where du.id = coalesce({$genAlias}.trx_creator_id, {$genAlias}.creator_id))
            )";
            $conditions[] = "({$effDeptSql}) = {$deptId}";
        }

        if (empty($conditions)) {
            return '1 = 1';
        }

        return '(' . implode(' and ', $conditions) . ')';
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
     * Memeriksa apakah HTTP request saat ini merupakan endpoint CRUD standar (/operation/{model} atau /operation/{model}/{id}).
     * Berguna agar GlobalScope pada model tidak memblokir query internal lintas-modul (approval, payroll, custom endpoint).
     */
    public function isOperationCrudRequest(string $modelName, array $methods = ['GET', 'PUT', 'PATCH', 'DELETE']): bool
    {
        if (!app()->bound('request') || !app()->request) {
            return false;
        }

        $req = app()->request;
        $method = strtoupper((string) $req->getMethod());
        $allowedMethods = array_map('strtoupper', $methods);
        if (!in_array($method, $allowedMethods, true)) {
            return false;
        }

        $rawPath = (string) ($req->getPathInfo() ?: parse_url((string) $req->getRequestUri(), PHP_URL_PATH) ?: '');
        $path = '/' . ltrim($rawPath, '/');
        return (bool) preg_match('#(?:^|/)operation/' . preg_quote($modelName, '#') . '(?:/\d+)?/?$#i', $path);
    }

    /**
     * Mengambil ID target numerik dari URL CRUD /operation/{model}/{id} (null bila request collection / endpoint lain).
     */
    public function getOperationCrudTargetId(string $modelName): ?int
    {
        if (!app()->bound('request') || !app()->request) {
            return null;
        }

        $req = app()->request;
        $rawPath = (string) ($req->getPathInfo() ?: parse_url((string) $req->getRequestUri(), PHP_URL_PATH) ?: '');
        $path = '/' . ltrim($rawPath, '/');
        if (preg_match('#(?:^|/)operation/' . preg_quote($modelName, '#') . '/(\d+)/?$#i', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Helper standar Master / Referensi: menerapkan isolasi scope Company (m_dir_id), Divisi (m_divisi_id),
     * dan Departemen (m_dept_id) sesuai active Respo user ke query builder Eloquent.
     *
     * Opsi $options:
     * - dir_col     : kolom m_dir_id di tabel target (default "{tableAlias}.m_dir_id", set null jika tidak ada)
     * - divisi_col  : kolom m_divisi_id di tabel target (default "{tableAlias}.m_divisi_id", set null jika via m_kary)
     * - dept_col    : kolom m_dept_id di tabel target (default "{tableAlias}.m_dept_id", set null jika via m_kary)
     * - kary_fk_col : FK menuju m_kary.id (opsional, untuk fallback org dari m_kary bila kolom org tidak ada di tabel)
     */
    public function applyActiveOrgScopeToQuery($builder, string $tableAlias, array $options = [], $user = null)
    {
        $scope = $this->getActiveCompanyScope($user);

        if (!empty($scope['is_unrestricted'])) {
            return $builder;
        }

        if (($scope['m_dir_id'] ?? null) === -1) {
            return $builder->whereRaw('1 = 0');
        }

        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) ?: $tableAlias;
        $dirCol = array_key_exists('dir_col', $options) ? $options['dir_col'] : "{$safeAlias}.m_dir_id";
        $divCol = array_key_exists('divisi_col', $options) ? $options['divisi_col'] : "{$safeAlias}.m_divisi_id";
        $deptCol = array_key_exists('dept_col', $options) ? $options['dept_col'] : "{$safeAlias}.m_dept_id";
        $karyFkCol = $options['kary_fk_col'] ?? null;

        $sanitizeCol = function ($col) {
            return $col ? preg_replace('/[^a-zA-Z0-9_.]/', '', (string) $col) : null;
        };

        $dirCol = $sanitizeCol($dirCol);
        $divCol = $sanitizeCol($divCol);
        $deptCol = $sanitizeCol($deptCol);
        $karyFkCol = $sanitizeCol($karyFkCol);

        $builder->where(function ($scopeQ) use ($scope, $dirCol, $divCol, $deptCol, $karyFkCol) {
            if (!empty($scope['m_dir_id'])) {
                $dirId = (int) $scope['m_dir_id'];
                if ($dirCol && $karyFkCol) {
                    $scopeQ->whereRaw("coalesce({$dirCol}, (select mk_scope.m_dir_id from m_kary mk_scope where mk_scope.id = {$karyFkCol})) = {$dirId}");
                } elseif ($dirCol) {
                    $scopeQ->where($dirCol, $dirId);
                } elseif ($karyFkCol) {
                    $scopeQ->whereRaw("exists (select 1 from m_kary mk_scope where mk_scope.id = {$karyFkCol} and mk_scope.m_dir_id = {$dirId})");
                }
            }

            if (!empty($scope['m_divisi_id'])) {
                $divId = (int) $scope['m_divisi_id'];
                if ($divCol && $karyFkCol) {
                    $scopeQ->whereRaw("coalesce({$divCol}, (select mk_scope.m_divisi_id from m_kary mk_scope where mk_scope.id = {$karyFkCol})) = {$divId}");
                } elseif ($divCol) {
                    $scopeQ->where($divCol, $divId);
                } elseif ($karyFkCol) {
                    $scopeQ->whereRaw("exists (select 1 from m_kary mk_scope where mk_scope.id = {$karyFkCol} and mk_scope.m_divisi_id = {$divId})");
                }
            }

            if (!empty($scope['m_dept_id'])) {
                $deptId = (int) $scope['m_dept_id'];
                if ($deptCol && $karyFkCol) {
                    $scopeQ->whereRaw("coalesce({$deptCol}, (select mk_scope.m_dept_id from m_kary mk_scope where mk_scope.id = {$karyFkCol})) = {$deptId}");
                } elseif ($deptCol) {
                    $scopeQ->where($deptCol, $deptId);
                } elseif ($karyFkCol) {
                    $scopeQ->whereRaw("exists (select 1 from m_kary mk_scope where mk_scope.id = {$karyFkCol} and mk_scope.m_dept_id = {$deptId})");
                }
            }
        });

        return $builder;
    }

    /**
     * Helper standar Transaksi Karyawan (t_cuti, t_lembur, t_potongan, dll):
     * - Superadmin: unrestricted.
     * - HRD / Admin: terisolasi ke Company/Divisi/Dept pada active Respo (Holding HRD/Admin = semua PT).
     * - User Non-HRD / Karyawan Biasa: pada list/write hanya melihat data miliknya (m_kary_id),
     *   sedangkan pada single-record GET detail tetap bisa membuka transaksi yang perlu/pernah di-approve
     *   (atasan langsung atau approver terdaftar pada generate_approval).
     */
    public function applyActiveEmployeeTransactionScopeToQuery($builder, string $tableAlias, array $options = [], $user = null)
    {
        $userObj = $this->resolveUser($user);
        if (!$userObj) {
            return $builder->whereRaw('1 = 0');
        }

        if ($this->isUserSuperadmin($userObj)) {
            return $builder;
        }

        $scope = $this->getActiveCompanyScope($userObj);
        if (($scope['m_dir_id'] ?? null) === -1) {
            return $builder->whereRaw('1 = 0');
        }

        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) ?: $tableAlias;
        $trxTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($options['trx_table'] ?? $safeAlias)) ?: $safeAlias;
        $karyFkCol = preg_replace('/[^a-zA-Z0-9_.]/', '', (string) ($options['kary_fk_col'] ?? "{$safeAlias}.m_kary_id"));
        $orgOptions = [
            'dir_col'     => array_key_exists('dir_col', $options) ? $options['dir_col'] : "{$safeAlias}.m_dir_id",
            'divisi_col'  => array_key_exists('divisi_col', $options) ? $options['divisi_col'] : null,
            'dept_col'    => array_key_exists('dept_col', $options) ? $options['dept_col'] : null,
            'kary_fk_col' => $karyFkCol,
        ];

        $isHrdOrAdmin = $this->isUserHrdOrAdmin($userObj);
        $allowApproverOnSingle = array_key_exists('allow_approver_on_single', $options) ? (bool) $options['allow_approver_on_single'] : true;
        $targetId = $options['target_id'] ?? $this->getOperationCrudTargetId($safeAlias);
        $isGetMethod = app()->bound('request') && app()->request ? app()->request->isMethod('GET') : false;
        $canUseSingleApprover = ($allowApproverOnSingle && $targetId !== null && $isGetMethod);

        $uid = (int) ($userObj->id ?? 0);
        $userKaryId = (int) ($userObj->m_kary_id ?? 0);
        $roleIds = array_values(array_filter(array_map('intval', $this->getEffectiveRoleIds($userObj))));
        $roleIdsIn = !empty($roleIds) ? implode(',', $roleIds) : null;

        $applyApproverOrWhere = function ($q) use ($safeAlias, $trxTable, $karyFkCol, $uid, $userKaryId, $roleIdsIn, $scope, $userObj) {
            if ($userKaryId > 0) {
                $q->orWhere($karyFkCol, $userKaryId);
                $q->orWhereRaw("exists (select 1 from m_kary mk_at_scope where mk_at_scope.id = {$karyFkCol} and mk_at_scope.atasan_id = {$userKaryId})");
            }
            if ($uid > 0) {
                $companyScopeSql = $this->buildGenerateApprovalScopeSql('ga_scope', $scope, $userObj);
                $roleClauseSql = $roleIdsIn
                    ? "or ({$companyScopeSql} and exists (select 1 from generate_approval_det gad_r where gad_r.generate_approval_id = ga_scope.id and gad_r.default_user_id is null and gad_r.m_role_id in ({$roleIdsIn})))"
                    : "";

                $q->orWhereRaw("exists (
                    select 1 from generate_approval ga_scope
                    where ga_scope.trx_table = '{$trxTable}'
                      and ga_scope.trx_id = {$safeAlias}.id
                      and (
                          ga_scope.last_approve_id = {$uid}
                          or exists (
                              select 1 from generate_approval_det gad_scope
                              where gad_scope.generate_approval_id = ga_scope.id
                                and (gad_scope.default_user_id = {$uid} or gad_scope.action_user_id = {$uid})
                          )
                          {$roleClauseSql}
                      )
                )");
            }
        };

        if ($isHrdOrAdmin) {
            if (!empty($scope['is_unrestricted'])) {
                return $builder;
            }

            if ($canUseSingleApprover) {
                $builder->where(function ($outerQ) use ($safeAlias, $orgOptions, $userObj, $applyApproverOrWhere) {
                    $outerQ->where(function ($orgQ) use ($safeAlias, $orgOptions, $userObj) {
                        $this->applyActiveOrgScopeToQuery($orgQ, $safeAlias, $orgOptions, $userObj);
                    });
                    $applyApproverOrWhere($outerQ);
                });
                return $builder;
            }

            return $this->applyActiveOrgScopeToQuery($builder, $safeAlias, $orgOptions, $userObj);
        }

        $builder->where(function ($outerQ) use ($karyFkCol, $userKaryId, $canUseSingleApprover, $applyApproverOrWhere) {
            if ($userKaryId > 0) {
                $outerQ->where($karyFkCol, $userKaryId);
            } else {
                $outerQ->whereRaw('1 = 0');
            }
            if ($canUseSingleApprover) {
                $applyApproverOrWhere($outerQ);
            }
        });

        return $builder;
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

