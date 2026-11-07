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
        $isSuperadmin = (bool) ($userAuth->is_superadmin ?? false);

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
            ->whereRaw("case when generate_approval.trx_table = 't_lembur' then coalesce((select tl.status from t_lembur tl where tl.id = generate_approval.trx_id), generate_approval.status) not in ('APPROVED', 'REJECTED') else true end");

        // Tiket yang sudah pernah disetujui oleh user bersangkutan tidak boleh muncul lagi di inbox outstanding miliknya
        $query->whereRaw("generate_approval.id not in (
            select d_done.generate_approval_id from generate_approval_det d_done 
            where d_done.action_user_id = " . intval($userAuth->id) . " and d_done.action_type = 'APPROVED' and d_done.is_done = true
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

        // 1. Identifikasi Role user dari m_role_access
        $userRoleIds = \DB::table('m_role_access')->where('user_id', $userAuth->id)->pluck('m_role_id')->toArray();
        $userRoleNames = \DB::table('m_role')->whereIn('id', $userRoleIds)->pluck('name')->map(function($n) {
            return strtolower($n);
        })->toArray();

        $isHrd = false;
        foreach ($userRoleNames as $rn) {
            if (stripos($rn, 'hr') !== false || stripos($rn, 'personalia') !== false) {
                $isHrd = true;
                break;
            }
        }

        // 2. Jika belum terdeteksi dari role, periksa dari Jabatan (m_posisi) atau Divisi (m_divisi) karyawan
        if (!$isHrd && !empty($userAuth->m_kary_id)) {
            $userKary = \DB::table('m_kary')
                ->leftJoin('m_posisi', 'm_posisi.id', '=', 'm_kary.m_posisi_id')
                ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                ->where('m_kary.id', $userAuth->m_kary_id)
                ->select('m_posisi.desc_kerja as posisi_nama', 'm_divisi.nama as divisi_nama')
                ->first();
            if ($userKary) {
                $posNama = strtolower($userKary->posisi_nama ?? '');
                $divNama = strtolower($userKary->divisi_nama ?? '');
                if (stripos($posNama, 'hr') !== false || stripos($posNama, 'personalia') !== false ||
                    stripos($divNama, 'hr') !== false || stripos($divNama, 'personalia') !== false) {
                    $isHrd = true;
                }
            }
        }

        // Dapatkan role-role HRD jika user adalah HRD
        $hrdRoleIds = [];
        if ($isHrd) {
            $hrdRoleIds = \DB::table('m_role')
                ->where(function($q) {
                    $q->where('name', 'like', '%HR%')
                      ->orWhere('name', 'like', '%hr%')
                      ->orWhere('name', 'like', '%Hr%')
                      ->orWhere('name', 'like', '%Personalia%')
                      ->orWhere('name', 'like', '%personalia%');
                })
                ->pluck('id')->toArray();
        }

        $combinedRoleIds = array_unique(array_merge($userRoleIds, $hrdRoleIds));
        $roleIdsIn = !empty($combinedRoleIds) ? implode(',', array_map('intval', $combinedRoleIds)) : '0';

        // Alur fleksibel: baik Atasan maupun HRD dapat melihat tiket outstanding jika slotnya belum selesai
        $data = $query->where(function($q) use ($userAuth, $roleIdsIn, $isHrd) {
                // Skenario 1: User adalah last_approve_id langsung yang aktif dan slot belum selesai
                $q->where(function($sub) use ($userAuth) {
                    $sub->where('generate_approval.last_approve_id', $userAuth->id)
                        ->whereRaw("generate_approval.id in (
                            select d.generate_approval_id from generate_approval_det d 
                            where d.is_done = false
                              and (d.urutan_level = 1 or lower(coalesce((select r.name from m_role r where r.id = d.m_role_id), '')) like '%atasan%')
                        )");
                })
                // Skenario 2: Slot approval HRD / role user yang belum selesai
                ->orWhereRaw("generate_approval.id in (
                    select d.generate_approval_id from generate_approval_det d 
                    where d.is_done = false
                      and (
                          d.m_role_id in ($roleIdsIn)
                          " . ($isHrd ? " or d.urutan_level >= 2 or lower(coalesce((select r.name from m_role r where r.id = d.m_role_id), '')) like '%hr%' or lower(coalesce((select r.name from m_role r where r.id = d.m_role_id), '')) like '%personalia%'" : "") . "
                      )
                )");

                // Skenario 3: Transaksi t_lembur untuk atasan langsung dari karyawan bersangkutan jika slot atasan belum selesai
                if (!empty($userAuth->m_kary_id)) {
                    $q->orWhereRaw("generate_approval.trx_table = 't_lembur' and generate_approval.trx_id in (
                        select tl.id from t_lembur tl 
                        join m_kary mk on mk.id = tl.m_kary_id 
                        where mk.atasan_id = " . intval($userAuth->m_kary_id) . "
                          and generate_approval.id in (
                              select d.generate_approval_id from generate_approval_det d 
                              where d.is_done = false
                                and (d.urutan_level = 1 or lower(coalesce((select r.name from m_role r where r.id = d.m_role_id), '')) like '%atasan%')
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

            // Identifikasi peran user (Atasan / HRD / Superadmin)
            $isSuperadmin = (bool) ($userAuth->is_superadmin ?? false);
            $userRoleIds = \DB::table('m_role_access')->where('user_id', $userAuth->id)->pluck('m_role_id')->toArray();
            $userRoleNames = \DB::table('m_role')->whereIn('id', $userRoleIds)->pluck('name')->map(function($n) {
                return strtolower($n);
            })->toArray();

            $isAtasan = false;
            if ($datas->trx_table === 't_lembur') {
                $trxKary = \DB::table('t_lembur')->where('id', $datas->trx_id)->first();
                if ($trxKary && !empty($userAuth->m_kary_id)) {
                    $atasanId = \DB::table('m_kary')->where('id', $trxKary->m_kary_id)->value('atasan_id');
                    if ($atasanId && $atasanId == $userAuth->m_kary_id) {
                        $isAtasan = true;
                    }
                }
            }
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'atasan') !== false) {
                    $isAtasan = true;
                }
            }

            $isHrd = false;
            foreach ($userRoleNames as $rn) {
                if (stripos($rn, 'hr') !== false || stripos($rn, 'personalia') !== false) {
                    $isHrd = true;
                    break;
                }
            }
            if (!$isHrd && !empty($userAuth->m_kary_id)) {
                $userKary = \DB::table('m_kary')
                    ->leftJoin('m_posisi', 'm_posisi.id', '=', 'm_kary.m_posisi_id')
                    ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                    ->where('m_kary.id', $userAuth->m_kary_id)
                    ->select('m_posisi.desc_kerja as posisi_nama', 'm_divisi.nama as divisi_nama')
                    ->first();
                if ($userKary) {
                    $posNama = strtolower($userKary->posisi_nama ?? '');
                    $divNama = strtolower($userKary->divisi_nama ?? '');
                    if (stripos($posNama, 'hr') !== false || stripos($posNama, 'personalia') !== false ||
                        stripos($divNama, 'hr') !== false || stripos($divNama, 'personalia') !== false) {
                        $isHrd = true;
                    }
                }
            }

            // Ambil slot detail approval yang belum selesai
            $undoneDets = \DB::table('generate_approval_det')
                ->leftJoin('m_role', 'm_role.id', '=', 'generate_approval_det.m_role_id')
                ->where('generate_approval_det.generate_approval_id', $datas->id)
                ->where('generate_approval_det.is_done', false)
                ->select('generate_approval_det.*', 'm_role.name as role_name')
                ->orderBy('generate_approval_det.urutan_level', 'asc')
                ->get();

            if ($undoneDets->isEmpty()) {
                return $this->helper->customResponse('Seluruh persetujuan sudah selesai dilakukan', 422);
            }

            // Tentukan target detail slot secara fleksibel:
            $targetDet = null;
            if ($isAtasan) {
                $targetDet = $undoneDets->first(function($d) {
                    return stripos(strtolower($d->role_name ?? ''), 'atasan') !== false || $d->urutan_level == 1;
                });
            }
            if (!$targetDet && $isHrd) {
                $targetDet = $undoneDets->first(function($d) use ($userRoleIds) {
                    return stripos(strtolower($d->role_name ?? ''), 'hr') !== false 
                        || stripos(strtolower($d->role_name ?? ''), 'personalia') !== false
                        || in_array($d->m_role_id, $userRoleIds)
                        || $d->urutan_level >= 2;
                });
            }
            if (!$targetDet) {
                $targetDet = $undoneDets->first(function($d) use ($userRoleIds) {
                    return in_array($d->m_role_id, $userRoleIds);
                });
            }
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
                ->get();

            if ($req->type === 'REJECTED') {
                \DB::table($datas->trx_table)->where('id', $datas->trx_id)->update(['status' => 'REJECTED']);
                \DB::table('generate_approval')->where('id', $datas->id)->update([
                    'status' => 'REJECTED',
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