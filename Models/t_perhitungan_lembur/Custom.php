<?php

namespace App\Models\CustomModels;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class t_perhitungan_lembur extends \App\Models\BasicModels\t_perhitungan_lembur
{
    private $helper;

    public $fileColumns = [ /*file_column*/ ];

    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
        $this->casts['detail_lembur'] = 'array';

        $this->columns = [
            "id", "nomor", "m_comp_id", "m_dir_id", "m_kary_id", "m_kary_dir_id", "m_kary_divisi_id",
            "m_kary_dept_id", "periode", "periode_awal", "periode_akhir", "total_spk_min",
            "total_realisasi_min", "total_diakui_min", "total_jam_diakui", "tarif_lembur",
            "total_lembur", "detail_lembur", "deskripsi", "status", "creator_id", "last_editor_id",
            "created_at", "updated_at"
        ];
        $this->columnsFull = [
            "id:bigint", "nomor:string:50", "m_comp_id:bigint", "m_dir_id:bigint", "m_kary_id:bigint",
            "m_kary_dir_id:bigint", "m_kary_divisi_id:bigint", "m_kary_dept_id:bigint",
            "periode:string:100", "periode_awal:date", "periode_akhir:date", "total_spk_min:integer",
            "total_realisasi_min:integer", "total_diakui_min:integer", "total_jam_diakui:decimal",
            "tarif_lembur:decimal", "total_lembur:decimal", "detail_lembur:json", "deskripsi:text",
            "status:string:50", "creator_id:bigint", "last_editor_id:bigint", "created_at:datetime", "updated_at:datetime"
        ];
        $this->fillable = [
            "nomor", "m_comp_id", "m_dir_id", "m_kary_id", "m_kary_dir_id", "m_kary_divisi_id",
            "m_kary_dept_id", "periode", "periode_awal", "periode_akhir", "total_spk_min",
            "total_realisasi_min", "total_diakui_min", "total_jam_diakui", "tarif_lembur",
            "total_lembur", "detail_lembur", "deskripsi", "status", "creator_id", "last_editor_id"
        ];
        $this->createable = $this->fillable;
        $this->updateable = $this->fillable;
        $this->searchable = [
            "id", "nomor", "m_kary.nik", "m_kary.nama_lengkap", "m_kary_dir.nama", "m_kary_divisi.nama", "m_kary_dept.nama", "periode", "total_lembur", "status"
        ];
        $this->joins = [
            "m_kary.id=t_perhitungan_lembur.m_kary_id",
            "m_dir.id=t_perhitungan_lembur.m_kary_dir_id",
            "m_divisi.id=t_perhitungan_lembur.m_kary_divisi_id",
            "m_dept.id=t_perhitungan_lembur.m_kary_dept_id",
            "default_users.id=t_perhitungan_lembur.creator_id",
            "default_users.id=t_perhitungan_lembur.last_editor_id"
        ];
    }

    public function m_kary() : BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_kary', 'm_kary_id', 'id');
    }

    public function m_dir() : BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dir', 'm_dir_id', 'id');
    }

    public function m_kary_dir() : BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dir', 'm_kary_dir_id', 'id');
    }

    public function m_kary_divisi() : BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_divisi', 'm_kary_divisi_id', 'id');
    }

    public function m_kary_dept() : BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dept', 'm_kary_dept_id', 'id');
    }

    public $createAdditionalData = [
        "creator_id" => "auth:id",
        "last_editor_id" => "auth:id"
    ];
    public $updateAdditionalData = [
        "last_editor_id" => "auth:id"
    ];

    /**
     * Hitung durasi dalam menit
     */
    private function hitungMenit($from, $to)
    {
        if (!$from || !$to) return 0;
        $dateFrom = Carbon::parse($from);
        $dateTo = Carbon::parse($to);

        if ($dateTo->lt($dateFrom)) {
            $dateTo->addDay();
        }

        return $dateFrom->diffInMinutes($dateTo);
    }

    /**
     * Cari tarif lembur dari m_lembur dengan multi-layer fallback
     * (Grading ID -> Grading Nama -> Divisi/Dept -> Default Rate)
     */
    private function getTarifLembur($gradingId = null, $karyId = null, $deptId = null, $divisiId = null, $gradingNama = null)
    {
        // 1. Coba cari dengan kecocokan spesifik: grading_id + dept_id / divisi_id
        if ($gradingId) {
            $rate = \DB::table('m_lembur')
                ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))")
                ->where('grading_id', $gradingId)
                ->where(function ($q) use ($deptId, $divisiId) {
                    if ($deptId) $q->where('m_dept_id', $deptId)->orWhereNull('m_dept_id');
                    if ($divisiId) $q->where('m_divisi_id', $divisiId)->orWhereNull('m_divisi_id');
                })
                ->orderByRaw("case when m_dept_id = " . intval($deptId ?? 0) . " then 0 else 1 end")
                ->orderBy('id', 'desc')
                ->first();

            if ($rate && floatval($rate->besaran) > 0) {
                return floatval($rate->besaran);
            }

            // Cari grading_id saja
            $rate = \DB::table('m_lembur')
                ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))")
                ->where('grading_id', $gradingId)
                ->orderBy('id', 'desc')
                ->first();

            if ($rate && floatval($rate->besaran) > 0) {
                return floatval($rate->besaran);
            }
        }

        // 2. Coba cari berdasarkan nama grading string
        if ($gradingNama) {
            $rate = \DB::table('m_lembur')
                ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))")
                ->where('grading', $gradingNama)
                ->orderBy('id', 'desc')
                ->first();

            if ($rate && floatval($rate->besaran) > 0) {
                return floatval($rate->besaran);
            }
        }

        // 3. Coba cari berdasarkan departemen / divisi
        if ($deptId || $divisiId) {
            $rate = \DB::table('m_lembur')
                ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))")
                ->where(function ($q) use ($deptId, $divisiId) {
                    if ($deptId) $q->where('m_dept_id', $deptId);
                    if ($divisiId) $q->orWhere('m_divisi_id', $divisiId);
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($rate && floatval($rate->besaran) > 0) {
                return floatval($rate->besaran);
            }
        }

        // 4. Fallback ke tarif aktif general manapun di m_lembur (misal record grading_id null)
        $rate = \DB::table('m_lembur')
            ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))")
            ->orderByRaw("case when grading_id is null then 0 else 1 end")
            ->orderBy('id', 'desc')
            ->first();

        if ($rate && floatval($rate->besaran) > 0) {
            return floatval($rate->besaran);
        }

        // 5. Coba cari tanpa filter is_active (jika status is_active belum di-set)
        $rate = \DB::table('m_lembur')
            ->orderBy('id', 'desc')
            ->first();

        return $rate ? floatval($rate->besaran) : 0;
    }

    /**
     * Endpoint API: /operation/t_perhitungan_lembur/generate
     * Melakukan kalkulasi lembur dari transaksi t_lembur yang berstatus APPROVED
     */
    public function custom_generate()
    {
        try {
            $req = app()->request;
            $rawAwal = $req->periode_awal;
            $rawAkhir = $req->periode_akhir;

            if (!$rawAwal || !$rawAkhir) {
                return $this->helper->customResponse('Periode awal dan periode akhir wajib diisi', 422);
            }

            // Normalisasi format tanggal Y-m-d
            $strDateFrom = (strlen($rawAwal) === 7) ? $rawAwal . '-01' : date('Y-m-d', strtotime(str_replace('/', '-', $rawAwal)));
            $strDateTo = (strlen($rawAkhir) === 7) ? date('Y-m-t', strtotime(str_replace('/', '-', $rawAkhir) . '-01')) : date('Y-m-d', strtotime(str_replace('/', '-', $rawAkhir)));

            $labelAwal = date('d/m/Y', strtotime($strDateFrom));
            $labelAkhir = date('d/m/Y', strtotime($strDateTo));
            $periodeLabel = ($labelAwal === $labelAkhir) ? $labelAwal : ($labelAwal . ' - ' . $labelAkhir);

            // Query karyawan aktif
            $karyQuery = \DB::table('m_kary')
                ->leftJoin('m_dir', 'm_dir.id', '=', 'm_kary.m_dir_id')
                ->leftJoin('m_dept', 'm_dept.id', '=', 'm_kary.m_dept_id')
                ->leftJoin('m_divisi', 'm_divisi.id', '=', 'm_kary.m_divisi_id')
                ->leftJoin('m_divisi as m_div_dept', 'm_div_dept.id', '=', 'm_dept.m_divisi_id')
                ->leftJoin('m_general as mg_grade', 'mg_grade.id', '=', 'm_kary.grading_id')
                ->select(
                    'm_kary.id',
                    'm_kary.kode',
                    'm_kary.nik',
                    'm_kary.nama_lengkap',
                    'm_kary.grading_id',
                    'm_kary.m_dir_id',
                    'm_kary.m_divisi_id',
                    'm_kary.m_dept_id',
                    'm_dept.m_divisi_id as m_dept_divisi_id',
                    'm_dir.nama as dir_nama',
                    \DB::raw('COALESCE(m_divisi.nama, m_div_dept.nama) as divisi_nama'),
                    'm_dept.nama as dept_nama',
                    'mg_grade.value as grading_nama'
                )
                ->where('m_kary.is_active', true);

            if ($req->m_dir_id) $karyQuery->where('m_kary.m_dir_id', $req->m_dir_id);
            if ($req->m_divisi_id) $karyQuery->where('m_kary.m_divisi_id', $req->m_divisi_id);
            if ($req->m_dept_id) $karyQuery->where('m_kary.m_dept_id', $req->m_dept_id);
            if ($req->m_kary_id) $karyQuery->where('m_kary.id', $req->m_kary_id);

            $karyList = $karyQuery->get();

            $data = [];
            foreach ($karyList as $emp) {
                // Ambil seluruh data lembur APPROVED pada rentang tanggal
                $lemburRows = \DB::table('t_lembur')
                    ->leftJoin('m_general as mg_tipe', 'mg_tipe.id', '=', 't_lembur.tipe_lembur_id')
                    ->leftJoin('m_general as mg_alasan', 'mg_alasan.id', '=', 't_lembur.alasan_id')
                    ->select(
                        't_lembur.*',
                        'mg_tipe.value as tipe_lembur',
                        'mg_alasan.value as alasan'
                    )
                    ->where('t_lembur.m_kary_id', $emp->id)
                    ->whereBetween('t_lembur.tanggal', [$strDateFrom, $strDateTo])
                    ->where('t_lembur.status', 'APPROVED')
                    ->orderBy('t_lembur.tanggal', 'asc')
                    ->get();

                // Jika difilter per karyawan spesifik, tetap tampilkan meski 0 lembur; jika batch, hanya tampilkan yang memiliki lembur
                if ($lemburRows->count() === 0 && !$req->m_kary_id && !req('show_all')) {
                    continue;
                }

                $totalSpkMin = 0;
                $totalRealMin = 0;
                $totalDiakuiMin = 0;
                $totalNominal = 0;
                $detailArr = [];
                $empTarif = $this->getTarifLembur($emp->grading_id, $emp->id, $emp->m_dept_id, $emp->m_divisi_id, $emp->grading_nama);

                foreach ($lemburRows as $l) {
                    $spkMin = $l->interval_min ?? $this->hitungMenit($l->jam_mulai, $l->jam_selesai);
                    $realMin = $l->realisasi_min ?? ($l->jam_mulai_realisasi && $l->jam_selesai_realisasi ? $this->hitungMenit($l->jam_mulai_realisasi, $l->jam_selesai_realisasi) : null);
                    
                    // Sesuai aturan MoM: diakui = min(spk, real) bila real ada, jika tidak default = spk
                    $diakuiMin = $l->diakui_min ?? (($realMin !== null && $realMin > 0) ? min($spkMin, $realMin) : $spkMin);

                    $tarif = floatval($l->tarif_lembur ?? 0);
                    if ($tarif <= 0) {
                        $tarif = $empTarif;
                    }

                    $subtotal = floatval($l->total_lembur ?? 0);
                    if ($subtotal <= 0) {
                        $subtotal = round(($diakuiMin / 60) * $tarif, 2);
                    }

                    $totalSpkMin += $spkMin;
                    $totalRealMin += ($realMin ?? 0);
                    $totalDiakuiMin += $diakuiMin;
                    $totalNominal += $subtotal;

                    // Perbarui tarif default karyawan jika ada salah satu SPK yang memiliki tarif valid
                    if ($empTarif <= 0 && $tarif > 0) {
                        $empTarif = $tarif;
                    }

                    $detailArr[] = [
                        'id'                    => $l->id,
                        'nomor'                 => $l->nomor,
                        'tanggal'               => $l->tanggal,
                        'tipe_lembur'           => $l->tipe_lembur ?? '-',
                        'alasan'                => $l->alasan ?? '-',
                        'jam_mulai'             => substr($l->jam_mulai, 0, 5),
                        'jam_selesai'           => substr($l->jam_selesai, 0, 5),
                        'jam_mulai_realisasi'   => $l->jam_mulai_realisasi ? substr($l->jam_mulai_realisasi, 0, 5) : '-',
                        'jam_selesai_realisasi' => $l->jam_selesai_realisasi ? substr($l->jam_selesai_realisasi, 0, 5) : '-',
                        'spk_jam'               => round($spkMin / 60, 2),
                        'realisasi_jam'         => $realMin !== null ? round($realMin / 60, 2) : 0,
                        'diakui_jam'            => round($diakuiMin / 60, 2),
                        'tarif_lembur'          => $tarif,
                        'total_lembur'          => $subtotal,
                        'keterangan'            => $l->keterangan ?? ''
                    ];
                }

                $nikVal = $emp->nik ?: $emp->kode;
                $divisiVal = $emp->divisi_nama ?? null;
                $deptVal   = $emp->dept_nama ?? null;
                $dirVal    = $emp->dir_nama ?? null;
                $divisiId  = $emp->m_divisi_id ?: ($emp->m_dept_divisi_id ?? null);

                $data[] = [
                    'm_kary_id'           => $emp->id,
                    'm_kary.nik'          => $nikVal,
                    'm_kary.nama_lengkap' => $emp->nama_lengkap,
                    'nik'                 => $nikVal,
                    'kode'                => $emp->kode,
                    'nama_lengkap'        => $emp->nama_lengkap,
                    'karyawan'            => $emp->nama_lengkap,
                    'grading'             => $emp->grading_nama ?? '-',
                    'm_kary_dir_id'       => $emp->m_dir_id,
                    'm_dir_nama'          => $dirVal,
                    'dir_nama'            => $dirVal,
                    'm_kary_dir.nama'     => $dirVal,
                    'm_kary_divisi_id'    => $divisiId,
                    'm_divisi_nama'       => $divisiVal,
                    'divisi_nama'         => $divisiVal,
                    'm_kary_divisi.nama'  => $divisiVal,
                    'm_kary_dept_id'      => $emp->m_dept_id,
                    'm_dept_nama'         => $deptVal,
                    'dept_nama'           => $deptVal,
                    'm_kary_dept.nama'    => $deptVal,
                    'periode'             => $periodeLabel,
                    'periode_awal'        => $strDateFrom,
                    'periode_akhir'       => $strDateTo,
                    'total_spk_min'       => $totalSpkMin,
                    'total_realisasi_min' => $totalRealMin,
                    'total_diakui_min'    => $totalDiakuiMin,
                    'total_jam_diakui'    => round($totalDiakuiMin / 60, 2),
                    'tarif_lembur'        => $empTarif,
                    'total_lembur'        => $totalNominal,
                    'detail_lembur'       => $detailArr,
                ];
            }

            return $this->helper->customResponse('OK', 200, $data);
        } catch (\Exception $e) {
            return $this->helper->responseCatch($e);
        }
    }

    /**
     * Endpoint API: /operation/t_perhitungan_lembur/save
     * Menyimpan hasil perhitungan lembur
     */
    public function custom_save($req)
    {
        $counter = count($req->detail ?? []);
        if ($counter) {
            try {
                $nomor = $this->helper->generateNomor('KODE PERHITUNGAN LEMBUR');
            } catch (\Throwable $e) {
                $nomor = 'PRH-LMB/' . date('Ymd') . '/' . str_pad(($this->max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT);
            }

            foreach ($req->detail as $item) {
                // Hapus data lama dengan karyawan & periode yang sama jika sudah ada
                $this->where('m_kary_id', @$item['m_kary_id'])
                    ->where('periode', @$item['periode'])
                    ->delete();

                $saveData = [
                    'nomor'               => $nomor,
                    'm_comp_id'           => auth()->user()->m_comp_id ?? 1,
                    'm_dir_id'            => $item['m_kary_dir_id'] ?? null,
                    'm_kary_id'           => $item['m_kary_id'],
                    'm_kary_dir_id'       => $item['m_kary_dir_id'] ?? null,
                    'm_kary_divisi_id'    => $item['m_kary_divisi_id'] ?? null,
                    'm_kary_dept_id'      => $item['m_kary_dept_id'] ?? null,
                    'periode'             => $item['periode'],
                    'periode_awal'        => $item['periode_awal'],
                    'periode_akhir'       => $item['periode_akhir'],
                    'total_spk_min'       => $item['total_spk_min'] ?? 0,
                    'total_realisasi_min' => $item['total_realisasi_min'] ?? 0,
                    'total_diakui_min'    => $item['total_diakui_min'] ?? 0,
                    'total_jam_diakui'    => $item['total_jam_diakui'] ?? 0,
                    'tarif_lembur'        => $item['tarif_lembur'] ?? 0,
                    'total_lembur'        => $item['total_lembur'] ?? 0,
                    'detail_lembur'       => is_string($item['detail_lembur'] ?? null) ? $item['detail_lembur'] : json_encode($item['detail_lembur'] ?? []),
                    'deskripsi'           => $item['deskripsi'] ?? null,
                    'status'              => 'POSTED'
                ];

                $this->create($saveData);
            }
        }

        return $this->helper->customResponse("$counter Data perhitungan lembur berhasil disimpan");
    }

    /**
     * Scope untuk penarikan data ke Finalisasi Gaji Mingguan (t_final_gaji)
     */
    public function scopeGenerateForFinal($model)
    {
        $req = app()->request;
        $rawAwal = $req->periode_awal;
        $rawAkhir = $req->periode_akhir;

        if ($rawAwal && $rawAkhir) {
            $strDateFrom = (strlen($rawAwal) === 7) ? $rawAwal . '-01' : date('Y-m-d', strtotime(str_replace('/', '-', $rawAwal)));
            $strDateTo = (strlen($rawAkhir) === 7) ? date('Y-m-t', strtotime(str_replace('/', '-', $rawAkhir) . '-01')) : date('Y-m-d', strtotime(str_replace('/', '-', $rawAkhir)));

            $labelAwal = date('d/m/Y', strtotime($strDateFrom));
            $labelAkhir = date('d/m/Y', strtotime($strDateTo));
            $expectedPeriode = ($labelAwal === $labelAkhir) ? $labelAwal : ($labelAwal . ' - ' . $labelAkhir);

            $model = $model->where(function ($q) use ($expectedPeriode, $strDateFrom, $strDateTo) {
                $q->where('t_perhitungan_lembur.periode', $expectedPeriode)
                  ->orWhere(function ($sq) use ($strDateFrom, $strDateTo) {
                      $sq->where('t_perhitungan_lembur.periode_awal', '<=', $strDateTo)
                         ->where('t_perhitungan_lembur.periode_akhir', '>=', $strDateFrom);
                  });
            });
        }

        if ($req->m_dir_id) $model = $model->where('t_perhitungan_lembur.m_kary_dir_id', $req->m_dir_id);
        if ($req->m_divisi_id) $model = $model->where('t_perhitungan_lembur.m_kary_divisi_id', $req->m_divisi_id);
        if ($req->m_dept_id) $model = $model->where('t_perhitungan_lembur.m_kary_dept_id', $req->m_dept_id);
        if ($req->m_kary_id) $model = $model->where('t_perhitungan_lembur.m_kary_id', $req->m_kary_id);

        return $model;
    }
}
