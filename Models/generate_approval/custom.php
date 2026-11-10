<?php

namespace App\Models\CustomModels;

class generate_approval extends \App\Models\BasicModels\generate_approval
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function generate_approval_det(): HasMany
    {
        return $this->hasMany(generate_approval_det::class);
    }

    public function custom_send_approval()
    {
        $conf = [
            'app_name'          => 'Contoh Approval',
            'trx_id'            => 1,
            'trx_table'         => $this->getTable(),
            'trx_name'          => 'Pengajuan Lembur', 
            'form_name'         => 't_lembur', 
            'trx_nomor'         => '123',           // isi dengan nomor transaksi, ini wajib!
            'trx_date'          => Date('Y-m-d'),   // isi dengan tanggal transaksi, ini wajib!
            'trx_creator_id'    => 1                // isi dengan user creator transaksi, ini wajib!
        ];

        $app = $this->helper->approvalCreateTicket($conf);
        if($app) {
            // disini update status transaksi
        }
        return $this->helper->customResponse('Permintaan approval berhasil dibuat');
    }   

    public function custom_progress()
    {
        $conf = [
            'app_id' => 1,
            'app_type' => 'APPROVED', // APPROVED, REVISED, REJECTED,
            'app_note' => 'Contoh Approve by sistem' // alasan approve
        ];

        $app = $this->helper->approvalProgress($conf);
        if($app->status) {
            if(!$app->finish){
                // approval berhasil -> namun masih ada approval lainnya

            }else{
                // approval berkahir -> waktunya update status header transaksi
            
            }
        }
        return $this->helper->customResponse('Proses approval berhasil');
    }   

    public function custom_detail()
    {
        $id = 1;
        $data = $this->helper->approvalDetail($id);
        return $this->helper->customResponse('OK', 200, $data);
    }

    public function custom_outstanding()
    {
        $userAuth = auth()->user();
        $respoCore = getCore('Respo');
        $isSuperadmin = $userAuth ? $respoCore->isUserSuperadmin($userAuth) : false;

        $query = generate_approval::selectRaw("
            generate_approval.id, generate_approval.m_comp_id, generate_approval.m_dir_id, generate_approval.nomor, generate_approval.m_approval_id, generate_approval.trx_id, generate_approval.trx_name, generate_approval.form_name, generate_approval.trx_table, generate_approval.trx_nomor,
            (case 
                when generate_approval.trx_table = 't_lembur' then coalesce((select tl.tanggal from t_lembur tl where tl.id = generate_approval.trx_id), generate_approval.trx_date)
                when generate_approval.trx_table = 't_cuti' then coalesce((select tc.date_from from t_cuti tc where tc.id = generate_approval.trx_id), generate_approval.trx_date)
                when generate_approval.trx_table = 't_spd' then coalesce((select ts.tanggal from t_spd ts where ts.id = generate_approval.trx_id), generate_approval.trx_date)
                when generate_approval.trx_table = 't_rpd' then coalesce((select ts.tanggal from t_rpd tr join t_spd ts on ts.id = tr.t_spd_id where tr.id = generate_approval.trx_id), generate_approval.trx_date)
                else generate_approval.trx_date 
            end) as trx_date,
            generate_approval.trx_creator_id, generate_approval.creator_id, generate_approval.created_at, generate_approval.updated_at, generate_approval.last_approve_id, generate_approval.last_approve_det_id,
            (case 
                when generate_approval.trx_table = 't_lembur' then coalesce((select tl.status from t_lembur tl where tl.id = generate_approval.trx_id), generate_approval.status)
                else generate_approval.status 
            end) as status,
            (select u.name from default_users u where u.id = generate_approval.creator_id) creator")
            ->leftJoin('default_users', 'default_users.id', 'generate_approval.creator_id')
            ->whereIn('generate_approval.status', ['PROGRESS', 'HALF APPROVED'])
            ->whereRaw("case when generate_approval.trx_table = 't_lembur' then coalesce((select tl.status from t_lembur tl where tl.id = generate_approval.trx_id), generate_approval.status) not in ('APPROVED', 'REJECTED', 'REVISED') else true end");

        // Tiket yang sudah pernah disetujui oleh user bersangkutan tidak boleh muncul lagi di inbox outstanding miliknya
        $query->whereRaw("generate_approval.id not in (
            select d_done.generate_approval_id from generate_approval_det d_done 
            where d_done.action_user_id = " . intval($userAuth->id ?? 0) . " and d_done.action_type = 'APPROVED' and d_done.is_done = true
        )");

        // Filter agar hanya menampilkan notifikasi approval dari menu yang aktif (is_active = true)
        $query->whereRaw("
            coalesce((select app.is_active from m_approval app where app.id = generate_approval.m_approval_id), true) = true
            and coalesce((select m.is_active from m_approval app join m_menu m on m.id = app.m_menu_id where app.id = generate_approval.m_approval_id), true) = true
            and not exists (
                select 1 from m_menu m_chk
                where (
                    replace(coalesce(m_chk.path, ''), '/', '') in (generate_approval.form_name, generate_approval.trx_table)
                    or replace(coalesce(m_chk.endpoint, ''), '/', '') in (generate_approval.form_name, generate_approval.trx_table)
                    or m_chk.path in (generate_approval.form_name, concat('/', generate_approval.form_name), generate_approval.trx_table, concat('/', generate_approval.trx_table))
                    or m_chk.endpoint in (generate_approval.form_name, concat('/', generate_approval.form_name), generate_approval.trx_table, concat('/', generate_approval.trx_table))
                )
                and coalesce(m_chk.is_active, true) = false
            )
            and (
                (
                    generate_approval.m_approval_id is not null
                    and exists (
                        select 1 from m_approval app 
                        join m_menu m on m.id = app.m_menu_id 
                        where app.id = generate_approval.m_approval_id 
                          and coalesce(app.is_active, true) = true 
                          and coalesce(m.is_active, true) = true
                    )
                )
                or exists (
                    select 1 from m_menu m_act
                    where (
                        replace(coalesce(m_act.path, ''), '/', '') in (generate_approval.form_name, generate_approval.trx_table)
                        or replace(coalesce(m_act.endpoint, ''), '/', '') in (generate_approval.form_name, generate_approval.trx_table)
                        or m_act.path in (generate_approval.form_name, concat('/', generate_approval.form_name), generate_approval.trx_table, concat('/', generate_approval.trx_table))
                        or m_act.endpoint in (generate_approval.form_name, concat('/', generate_approval.form_name), generate_approval.trx_table, concat('/', generate_approval.trx_table))
                    )
                    and coalesce(m_act.is_active, true) = true
                )
            )
        ");

        if ($isSuperadmin) {
            $data = $query->orderBy('generate_approval.id', 'desc')
                ->search(['trx_name', 'nomor', 'trx_date', 'trx_nomor', 'default_users.name'])
                ->paginate(app()->request->paginate ?? 50);
            return response($data);
        }

        $uid = intval($userAuth->id ?? 0);
        $userKaryId = intval($userAuth->m_kary_id ?? 0);

        // 1. Identifikasi Role aktif & status HRD user melalui Core Respo (mendukung Respo Mode & Legacy Mode)
        $userRoleIds = $userAuth ? $respoCore->getEffectiveRoleIds($userAuth) : [];
        $isHrd = $userAuth ? $respoCore->isUserHrd($userAuth) : false;

        $allActiveRoles = \DB::table('m_role')->where('is_active', true)->get(['id', 'name']);
        $hrdRoleIds = [];
        $atasanRoleIds = [];
        foreach ($allActiveRoles as $rObj) {
            $rid = (int) $rObj->id;
            if (stripos((string) $rObj->name, 'atasan') !== false) {
                $atasanRoleIds[] = $rid;
            }
            if ($respoCore->isHrdKeyword($rObj->name)) {
                $hrdRoleIds[] = $rid;
            }
        }

        $userAtasanRoleIds = array_values(array_intersect($userRoleIds, $atasanRoleIds));
        $combinedNonAtasanRoles = array_values(array_diff(
            array_unique(array_merge($userRoleIds, $isHrd ? $hrdRoleIds : [])),
            $atasanRoleIds
        ));

        $atasanRoleIdsIn = !empty($atasanRoleIds) ? implode(',', array_map('intval', $atasanRoleIds)) : '0';
        $userAtasanRoleIdsIn = !empty($userAtasanRoleIds) ? implode(',', array_map('intval', $userAtasanRoleIds)) : '0';
        $nonAtasanRoleIdsIn = !empty($combinedNonAtasanRoles) ? implode(',', array_map('intval', $combinedNonAtasanRoles)) : '0';

        $companyScopeSql = $respoCore->buildGenerateApprovalScopeSql('generate_approval', null, $userAuth);

        $mappedSupervisorExistsSql = "(
            generate_approval.last_approve_id is not null
            or (
                generate_approval.trx_table = 't_lembur' and exists (
                    select 1 from t_lembur tl join m_kary mk on mk.id = tl.m_kary_id join default_users du_at on du_at.m_kary_id = mk.atasan_id
                    where tl.id = generate_approval.trx_id
                )
            )
            or (
                generate_approval.trx_table = 't_cuti' and exists (
                    select 1 from t_cuti tc join m_kary mk on mk.id = tc.m_kary_id join default_users du_at on du_at.m_kary_id = mk.atasan_id
                    where tc.id = generate_approval.trx_id
                )
            )
            or (
                generate_approval.trx_table = 't_spd' and exists (
                    select 1 from t_spd ts left join default_users du_sp on du_sp.id = ts.pic_id join m_kary mk on mk.id = coalesce(ts.m_kary_id, du_sp.m_kary_id) join default_users du_at on du_at.m_kary_id = mk.atasan_id
                    where ts.id = generate_approval.trx_id
                )
            )
            or (
                generate_approval.trx_table = 't_rpd' and exists (
                    select 1 from t_rpd tr join t_spd ts on ts.id = tr.t_spd_id left join default_users du_rp on du_rp.id = coalesce(ts.pic_id, tr.creator_id) join m_kary mk on mk.id = coalesce(ts.m_kary_id, du_rp.m_kary_id) join default_users du_at on du_at.m_kary_id = mk.atasan_id
                    where tr.id = generate_approval.trx_id
                )
            )
        )";

        // Alur fleksibel + Respo-aware:
        // - Skenario 1 & 3 (Atasan langsung / explicit default_user_id): BEBAS company scope (mendukung atasan lintas PT/Holding)
        // - Skenario 2 (HRD / Role dalam active Respo): TERIKAT active company scope
        $data = $query->where(function($q) use ($uid, $userKaryId, $atasanRoleIdsIn, $userAtasanRoleIdsIn, $nonAtasanRoleIdsIn, $isHrd, $companyScopeSql, $mappedSupervisorExistsSql) {
                // Skenario 1a: User adalah last_approve_id langsung yang aktif dan slot atasan/level-1 belum selesai
                $q->where(function($sub) use ($uid, $atasanRoleIdsIn) {
                    $sub->where('generate_approval.last_approve_id', $uid)
                        ->whereRaw("exists (
                            select 1 from generate_approval_det d 
                            where d.generate_approval_id = generate_approval.id
                              and d.is_done = false
                              and (d.urutan_level = 1 or d.m_role_id in ({$atasanRoleIdsIn}))
                        )");
                })
                // Skenario 1b: User ditunjuk spesifik lewat default_user_id pada slot aktif terdepan yang belum selesai
                ->orWhereRaw("exists (
                    select 1 from generate_approval_det d
                    where d.generate_approval_id = generate_approval.id
                      and d.is_done = false
                      and d.default_user_id = {$uid}
                      and not exists (
                          select 1 from generate_approval_det d_prev
                          where d_prev.generate_approval_id = generate_approval.id
                            and d_prev.is_done = false
                            and d_prev.urutan_level < d.urutan_level
                      )
                )");

                // Skenario 2: Slot approval aktif terdepan HRD / Role aktif sesuai active Respo company scope
                $hrFallbackClause = $isHrd
                    ? "or ((d.m_role_id is null and d.urutan_level >= 2) or (generate_approval.trx_table = 't_lembur' and d.urutan_level >= 2))"
                    : "";

                $q->orWhereRaw("(
                    {$companyScopeSql}
                    and exists (
                        select 1 from generate_approval_det d
                        where d.generate_approval_id = generate_approval.id
                          and d.is_done = false
                          and d.default_user_id is null
                          and not exists (
                              select 1 from generate_approval_det d_prev
                              where d_prev.generate_approval_id = generate_approval.id
                                and d_prev.is_done = false
                                and d_prev.urutan_level < d.urutan_level
                          )
                          and not (
                              generate_approval.trx_table = 't_lembur'
                              and upper(coalesce((select tl_st.status from t_lembur tl_st where tl_st.id = generate_approval.trx_id), '')) = 'IN APPROVAL'
                              and d.urutan_level >= 2
                          )
                          and (
                              d.m_role_id in ({$nonAtasanRoleIdsIn})
                              {$hrFallbackClause}
                              or (
                                  d.m_role_id in ({$userAtasanRoleIdsIn})
                                  and not {$mappedSupervisorExistsSql}
                              )
                          )
                    )
                )");

                // Skenario 3: Atasan langsung dari karyawan (m_kary.atasan_id) untuk transaksi lembur, cuti, spd, rpd
                if ($userKaryId > 0) {
                    $q->orWhereRaw("(
                        (
                            (generate_approval.trx_table = 't_lembur' and exists (select 1 from t_lembur tl join m_kary mk on mk.id = tl.m_kary_id where tl.id = generate_approval.trx_id and mk.atasan_id = {$userKaryId}))
                            or (generate_approval.trx_table = 't_cuti' and exists (select 1 from t_cuti tc join m_kary mk on mk.id = tc.m_kary_id where tc.id = generate_approval.trx_id and mk.atasan_id = {$userKaryId}))
                            or (generate_approval.trx_table = 't_spd' and exists (select 1 from t_spd ts left join default_users du_sp on du_sp.id = ts.pic_id join m_kary mk on mk.id = coalesce(ts.m_kary_id, du_sp.m_kary_id) where ts.id = generate_approval.trx_id and mk.atasan_id = {$userKaryId}))
                            or (generate_approval.trx_table = 't_rpd' and exists (select 1 from t_rpd tr join t_spd ts on ts.id = tr.t_spd_id left join default_users du_rp on du_rp.id = coalesce(ts.pic_id, tr.creator_id) join m_kary mk on mk.id = coalesce(ts.m_kary_id, du_rp.m_kary_id) where tr.id = generate_approval.trx_id and mk.atasan_id = {$userKaryId}))
                        )
                        and exists (
                            select 1 from generate_approval_det d
                            where d.generate_approval_id = generate_approval.id
                              and d.is_done = false
                              and d.default_user_id is null
                              and not exists (
                                  select 1 from generate_approval_det d_prev
                                  where d_prev.generate_approval_id = generate_approval.id
                                    and d_prev.is_done = false
                                    and d_prev.urutan_level < d.urutan_level
                              )
                              and (d.m_role_id in ({$atasanRoleIdsIn}) or (d.urutan_level = 1 and (d.m_role_id is null or generate_approval.trx_table = 't_lembur')))
                        )
                    )");
                }
            })
            ->orderBy('generate_approval.id', 'desc')
            ->search(['trx_name', 'nomor', 'trx_date', 'trx_nomor', 'default_users.name'])
            ->paginate(app()->request->paginate ?? 50);

        return response($data);
    }

    public function custom_outstandingDetail($request){
        $data = $this->helper->approvalDetail($request->id);
         if (isset($data->trx)) {
            $mappedTrx = new \stdClass();


            if($data->approval->trx_table === 't_spd'){
                $mappedTrx->nomor = $data->trx->nomor;
                $mappedTrx->tanggal = $data->trx->tanggal;
                $mappedTrx->tgl_acara_awal = $data->trx->tgl_acara_awal;
                $mappedTrx->tgl_acara_akhir = $data->trx->tgl_acara_akhir;
                $mappedTrx->jenis_spd_id = $data->trx->jenis_spd_id;
                $mappedTrx->jenis_spd = m_general::where('id', $data->trx->jenis_spd_id)->value('value');
                $mappedTrx->m_zona_asal_id = $data->trx->m_zona_asal_id;
                $mappedTrx->zona_asal = m_zona::where('id', $data->trx->m_zona_asal_id)->value('nama');
                $mappedTrx->m_zona_tujuan_id = $data->trx->m_zona_tujuan_id;
                $mappedTrx->zona_tujuan = m_zona::where('id', $data->trx->m_zona_tujuan_id)->value('nama');
                $mappedTrx->m_lokasi_tujuan_id = $data->trx->m_lokasi_tujuan_id;
                $mappedTrx->lokasi = m_lokasi::where('id', $data->trx->m_lokasi_tujuan_id)->value('nama');
                $mappedTrx->m_kary_id = $data->trx->m_kary_id;
                $mappedTrx->nama_kary = m_kary::where('id', $data->trx->m_kary_id)->value('nama_lengkap');
                $mappedTrx->pic_id = $data->trx->pic_id;
                $mappedTrx->nama_pic = default_users::where('id', $data->trx->pic_id)->value('name');
                $mappedTrx->total_biaya = $data->trx->total_biaya;
                $mappedTrx->kegiatan = $data->trx->kegiatan;
                $mappedTrx->keterangan = $data->trx->keterangan;
                $mappedTrx->status = $data->trx->status;
                $mappedTrx->interval = t_spd::where('id', $data->approval->trx_id)->value('interval');
                $mappedTrx->catatan_kend = $data->trx->catatan_kend;
            }
            elseif($data->approval->trx_table === 't_cuti')
            {
                $datas = \DB::select("select public.employee_attendance(?,?)",[Date('Y-m-d'),$data->trx->m_kary_id ??0]);
                $datas = json_decode($datas[0]->employee_attendance);
                $mappedTrx->nomor = $data->trx->nomor;
                $mappedTrx->alasan_id = $data->trx->alasan_id;
                $mappedTrx->alasan = m_general::where('id', $data->trx->alasan_id)->value('value');
                $mappedTrx->tipe_cuti_id = $data->trx->tipe_cuti_id;
                $mappedTrx->tipe_cuti = m_general::where('id', $data->trx->tipe_cuti_id)->value('value');
                $mappedTrx->date_from = $data->trx->date_from;
                $mappedTrx->date_to = $data->trx->date_to;
                $mappedTrx->time_from = $data->trx->time_from;
                $mappedTrx->time_to = $data->trx->time_to;
                $mappedTrx->keterangan = $data->trx->keterangan;
                $mappedTrx->status = $data->trx->status;
                $mappedTrx->interval = t_cuti::where('id', $data->approval->trx_id)->value('interval');
                $mappedTrx->interval_min = t_cuti::where('id', $data->approval->trx_id)->value('interval_min');
                $mappedTrx->attachment = $data->trx->attachment;
                $mappedTrx->cuti_sisa_panjang = $datas->sisa_cuti_reguler ?? 0;
                $mappedTrx->cuti_sisa_reguler = $datas->sisa_cuti_masa_kerja ?? 0;
                $mappedTrx->cuti_sisa_p24 = $datas->sisa_cuti_p24 ?? 0;
                $mappedTrx->info_cuti = $datas ?? [];

            }
            elseif($data->approval->trx_table === 't_rpd')
            {
                $t_spd = t_spd::leftJoin('m_divisi', 't_spd.m_divisi_id', '=', 'm_divisi.id')
                    ->leftJoin('m_dept', 't_spd.m_dept_id', '=', 'm_dept.id')
                    ->leftJoin('m_lokasi', 't_spd.m_lokasi_tujuan_id', '=', 'm_lokasi.id')
                    ->leftJoin('default_users', 't_spd.m_kary_id', '=', 'default_users.m_kary_id')
                    ->select('t_spd.*', 'm_divisi.nama as nama_divisi', 'm_dept.nama as nama_dept', 'm_lokasi.nama as lokasi_tujuan', 'default_users.name')
                    ->where('t_spd.id', $data->trx->t_spd_id)
                    ->first();
                $pic = m_kary::where('id', $t_spd->m_kary_id)->value('nama_lengkap');
                $mappedTrx->nomor = $data->trx->nomor;
                $mappedTrx->total_biaya_spd = $data->trx->total_biaya_spd;
                $mappedTrx->total_biaya_selisih = $data->trx->total_biaya_selisih;
                $mappedTrx->keterangan = $data->trx->keterangan;
                $mappedTrx->status = $data->trx->status;
                $mappedTrx->tgl_acara_awal = @$t_spd->tgl_acara_awal ?? null;
                $mappedTrx->tgl_acara_akhir = @$t_spd->tgl_acara_akhir ?? null; 
                $mappedTrx->nama_divisi = @$t_spd->nama_divisi ?? null;
                $mappedTrx->nama_dept = @$t_spd->nama_dept ?? null;
                $mappedTrx->lokasi_tujuan = @$t_spd->lokasi_tujuan ?? null;
                $mappedTrx->pic = @$pic ?? @$t_spd->name ?? null;
                $mappedTrx->interval = @$t_spd->interval ?? 0;
                $mappedTrx->kegiatan = @$t_spd->kegiatan ?? null;
                $mappedTrx->catatan_kend = @$t_spd->catatan_kend ?? null;
                $mappedTrx->t_rpd_det = t_rpd_det::leftJoin('m_general', 't_rpd_det.tipe_spd_id', '=', 'm_general.id')
                                        ->select('t_rpd_det.*', 'm_general.value as tipe_spd')
                                        ->where('t_rpd_det.t_rpd_id', $data->approval->trx_id)
                                        ->get() ?? [];
            }
            else{
                $mappedTrx->nomor = $data->trx->nomor;
                $mappedTrx->tanggal = $data->trx->tanggal;
                $mappedTrx->jam_mulai = $data->trx->jam_mulai;
                $mappedTrx->jam_selesai = $data->trx->jam_selesai;
                $mappedTrx->no_doc = $data->trx->no_doc;
                $mappedTrx->doc = $data->trx->doc;
                $mappedTrx->keterangan = $data->trx->keterangan;
                $mappedTrx->status = $data->trx->status;
                $mappedTrx->nama_pic = default_users::where('id', $data->trx->pic_id)->value('name');
                $mappedTrx->interval_min = t_lembur::where('id', $data->approval->trx_id)->value('interval_min');
            }
            $data->trx = $mappedTrx;
        }
        return $this->helper->customResponse("OK", 200, $data);
    }

    public function custom_log()
    {
        $conf = [
            'trx_id' => 1,
            'trx_table' => $this->getTable()
        ];
        $data = $this->helper->approvalLog($conf);
        return response($data);
    }

    public function custom_progressing($req)
    {
        \DB::beginTransaction();

        try {
            $userAuth = auth()->user();
            $datas = generate_approval::where('id', $req->id)->first();

            if (!$datas) {
                return $this->helper->customResponse('errors', 404, "Data not found");
            }

            $cek = \DB::table($datas->trx_table)->where('id', $datas->trx_id)->first();
            if ($cek && ($cek->status === 'REJECTED' || $cek->status === 'REVISED')) {
                return $this->helper->customResponse('Data Sudah Dalam Status Rejected atau Revised , Harap Ulangi atau Perbaiki Pengajuan', 422);
            }

            if ($req->type === 'REVISED') {
                $cleanedNote = trim(strval($req->note ?? ''));
                if (empty($cleanedNote) || $cleanedNote === '-' || strtolower($cleanedNote) === 'revised') {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Catatan approval wajib diisi saat memilih revisi',
                        'errors'  => ['catatan' => ['Catatan approval wajib diisi saat memilih revisi']]
                    ], 422);
                }
            }

            // Identifikasi peran & active Respo scope user
            $respoCore = getCore('Respo');
            $isSuperadmin = $userAuth ? $respoCore->isUserSuperadmin($userAuth) : false;
            $uid = (int) ($userAuth->id ?? 0);
            $userKaryId = (int) ($userAuth->m_kary_id ?? 0);

            $userRoleIds = $userAuth ? $respoCore->getEffectiveRoleIds($userAuth) : [];
            $userRoleNames = $userAuth ? $respoCore->getEffectiveRoleNames($userAuth, true) : [];
            $ticketMeta = $respoCore->getApprovalTicketScopeMetadata($datas->trx_table, $datas->trx_id, $datas);
            $inCompanyScope = $userAuth
                ? $respoCore->matchesActiveCompanyScope($ticketMeta['m_dir_id'], $ticketMeta['m_divisi_id'], $ticketMeta['m_dept_id'], $userAuth)
                : false;

            $isDirectAtasan = (!empty($datas->last_approve_id) && $uid > 0 && (int) $datas->last_approve_id === $uid)
                || ($userKaryId > 0 && !empty($ticketMeta['atasan_kary_id']) && $userKaryId === (int) $ticketMeta['atasan_kary_id']);

            $hasAtasanRole = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $hasAtasanRole = true;
                    break;
                }
            }

            $isAtasan = $isDirectAtasan || ($inCompanyScope && $hasAtasanRole && empty($ticketMeta['has_mapped_supervisor']));
            $isHrdInScope = $inCompanyScope && $userAuth && $respoCore->isUserHrd($userAuth);

            // Cegah double-approve oleh user yang sama (kecuali Superadmin)
            $alreadyApprovedByMe = $uid > 0 && \DB::table('generate_approval_det')
                ->where('generate_approval_id', $datas->id)
                ->where('action_user_id', $uid)
                ->where('action_type', 'APPROVED')
                ->where('is_done', true)
                ->exists();

            if ($alreadyApprovedByMe && !$isSuperadmin) {
                return $this->helper->customResponse('Anda sudah memberikan persetujuan sebelumnya untuk transaksi ini', 403);
            }

            // Ambil slot detail approval yang belum selesai
            $undoneDets = \DB::table('generate_approval_det')
                ->leftJoin('m_role', 'm_role.id', '=', 'generate_approval_det.m_role_id')
                ->where('generate_approval_det.generate_approval_id', $datas->id)
                ->where('generate_approval_det.is_done', false)
                ->select('generate_approval_det.*', 'm_role.name as role_name')
                ->orderBy('generate_approval_det.urutan_level', 'asc')
                ->orderBy('generate_approval_det.id', 'asc')
                ->get();

            if ($undoneDets->isEmpty()) {
                return $this->helper->customResponse('Seluruh persetujuan sudah selesai dilakukan', 422);
            }

            // Tentukan target detail slot secara fleksibel + Respo/Scope aware:
            $targetDet = null;

            // 0. Slot eksplisit default_user_id (bebas company scope)
            if ($uid > 0) {
                $targetDet = $undoneDets->first(function($d) use ($uid) {
                    return !empty($d->default_user_id) && (int) $d->default_user_id === $uid;
                });
            }

            // 1. Direct Supervisor (bebas company scope) / Atasan fallback in-scope tanpa mapped supervisor
            if (!$targetDet && $isAtasan) {
                $targetDet = $undoneDets->first(function($d) use ($datas) {
                    if (!empty($d->default_user_id)) {
                        return false;
                    }
                    $rn = strtolower($d->role_name ?? '');
                    return stripos($rn, 'atasan') !== false
                        || ((int) $d->urutan_level === 1 && (empty($d->m_role_id) || $datas->trx_table === 't_lembur'));
                });
            }

            // 2. HRD in-scope (t_lembur IN APPROVAL tetap menunggu tahap 1 Atasan terlebih dahulu)
            $isLemburWaitingAtasan = ($datas->trx_table === 't_lembur' && $cek && strtoupper(trim((string) ($cek->status ?? ''))) === 'IN APPROVAL');
            if (!$targetDet && $isHrdInScope && !$isLemburWaitingAtasan) {
                $targetDet = $undoneDets->first(function($d) use ($userRoleIds, $respoCore, $datas) {
                    if (!empty($d->default_user_id)) {
                        return false;
                    }
                    $rn = strtolower($d->role_name ?? '');
                    if (stripos($rn, 'atasan') !== false) {
                        return false;
                    }
                    return $respoCore->isHrdKeyword($rn)
                        || (!empty($d->m_role_id) && in_array((int) $d->m_role_id, $userRoleIds, true))
                        || (empty($d->m_role_id) && (int) $d->urutan_level >= 2)
                        || ($datas->trx_table === 't_lembur' && (int) $d->urutan_level >= 2);
                });
            }

            // 3. Role aktif user dalam active company scope (role non-atasan, atau atasan bila tanpa mapped supervisor)
            if (!$targetDet && $inCompanyScope && !empty($userRoleIds)) {
                $targetDet = $undoneDets->first(function($d) use ($userRoleIds, $ticketMeta, $isLemburWaitingAtasan) {
                    if (!empty($d->default_user_id) || empty($d->m_role_id)) {
                        return false;
                    }
                    $rn = strtolower($d->role_name ?? '');
                    if (stripos($rn, 'atasan') !== false && !empty($ticketMeta['has_mapped_supervisor'])) {
                        return false;
                    }
                    if ($isLemburWaitingAtasan && (int) $d->urutan_level >= 2) {
                        return false;
                    }
                    return in_array((int) $d->m_role_id, $userRoleIds, true);
                });
            }

            // 4. Superadmin bypass
            if (!$targetDet && $isSuperadmin) {
                $targetDet = $undoneDets->first();
            }

            if (!$targetDet) {
                return $this->helper->customResponse('Anda tidak memiliki akses untuk menyetujui transaksi ini', 403);
            }

            // Update slot detail terpilih
            $now = date('Y-m-d H:i:s');
            \DB::table('generate_approval_det')->where('id', $targetDet->id)->update([
                'action_at' => $now,
                'action_type' => $req->type,
                'action_user_id' => $userAuth->id,
                'action_note' => $req->note ?? 'Approved',
                'is_done' => true,
            ]);

            // Catat log approval
            \DB::table('generate_approval_log')->insert([
                'nomor' => $datas->nomor,
                'generate_approval_id' => $datas->id,
                'generate_approval_det_id' => $targetDet->id,
                'trx_id' => $datas->trx_id,
                'trx_table' => $datas->trx_table,
                'trx_name' => $datas->trx_name,
                'trx_nomor' => $datas->trx_nomor,
                'trx_date' => $datas->trx_date,
                'trx_creator_id' => $datas->trx_creator_id,
                'action_type' => $req->type,
                'action_user_id' => $userAuth->id,
                'creator_id' => $userAuth->id,
                'action_at' => $now,
                'action_note' => $req->note ?? 'Approved',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Cek slot yang tersisa
            $remainingDets = \DB::table('generate_approval_det')
                ->where('generate_approval_id', $datas->id)
                ->where('is_done', false)
                ->orderBy('urutan_level', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($req->type === 'REJECTED' || $req->type === 'REVISED') {
                \DB::table($datas->trx_table)->where('id', $datas->trx_id)->update(['status' => $req->type]);
                \DB::table('generate_approval')->where('id', $datas->id)->update([
                    'status' => $req->type,
                    'last_approve_id' => null,
                    'last_approve_det_id' => null
                ]);
            } elseif ($remainingDets->isEmpty()) {
                // Semua approver sudah selesai
                \DB::table($datas->trx_table)->where('id', $datas->trx_id)->update(['status' => 'APPROVED']);
                \DB::table('generate_approval')->where('id', $datas->id)->update([
                    'status' => 'APPROVED',
                    'last_approve_id' => null,
                    'last_approve_det_id' => null
                ]);
            } else {
                // Masih ada approval lain -> status HALF APPROVED
                $midStatus = ($datas->trx_table === 't_lembur') ? 'HALF APPROVED' : 'IN APPROVAL';
                \DB::table($datas->trx_table)->where('id', $datas->trx_id)->update(['status' => $midStatus]);
                
                $nextDet = $remainingDets->first();
                \DB::table('generate_approval')->where('id', $datas->id)->update([
                    'status' => 'PROGRESS',
                    'last_approve_det_id' => $nextDet ? $nextDet->id : null,
                    'last_approve_id' => null
                ]);
            }

            \DB::commit();

            return $this->helper->customResponse("Proses approval berhasil");
        } catch (\Exception $e) {
            \DB::rollback();

            return $this->helper->responseCatch($e);
        }
    }

    public function public_tes_object()
    {
        return $this->helper->customResponse('OK',200,[
            'nomor' => 'CT-231213-00000136',
            'tanggal' => '13/12/2023',
            'alasan' => 'Sakit',
            'tipe' => 'Cuti Tahunan',
            'status' => 'IN APPROVAL',
            'detail' => [
                [
                    'no' => 1,
                    'tipe' => 'Makan',
                    'biaya' => 50000,
                    'ket' => 'tes'
                ],
                [
                    'no' => 2,
                    'tipe' => 'Lain-lain',
                    'biaya' => 40000,
                    'ket' => 'aaa'
                ],
                [
                    'no' => 3,
                    'tipe' => 'Makan',
                    'biaya' => 20000,
                    'ket' => 'aaa'
                ]
            ]
        ]);
    }
}