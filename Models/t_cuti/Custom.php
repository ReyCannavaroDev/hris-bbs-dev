<?php

namespace App\Models\CustomModels;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class t_cuti extends \App\Models\BasicModels\t_cuti
{
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore("Helper");
    }

    protected static function boot()
    {
        parent::boot();

        // Isolasi Transaksi Cuti sesuai active Respo:
        // Superadmin = semua PT, HRD/Admin = PT/Divisi/Dept active Respo, User biasa = milik sendiri (+ atasan/approver saat GET single detail)
        static::addGlobalScope('respo_scope', function (Builder $builder) {
            $respoCore = getCore('Respo');
            if (!$respoCore || !$respoCore->isOperationCrudRequest('t_cuti', ['GET', 'PUT', 'PATCH', 'DELETE'])) {
                return;
            }

            $respoCore->applyActiveEmployeeTransactionScopeToQuery($builder, 't_cuti', [
                'trx_table'                => 't_cuti',
                'dir_col'                  => 't_cuti.m_dir_id',
                'divisi_col'               => null,
                'dept_col'                 => null,
                'kary_fk_col'              => 't_cuti.m_kary_id',
                'allow_approver_on_single' => true,
            ]);
        });
    }

    public $fileColumns = [
        'attachment'
    ];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    private function parseCarbonDate($val)
    {
        if (!$val) return null;
        if ($val instanceof Carbon) return $val;
        $val = trim((string) $val);
        if (str_contains($val, '/')) {
            try {
                return Carbon::createFromFormat('d/m/Y', $val);
            } catch (\Exception $e) {}
        }
        try {
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function formatDisplayDateSafe($val, $otherVal = null, $interval = null, $nomor = null)
    {
        if (empty($val)) return '';
        $raw = trim(explode(' ', explode('T', (string) $val)[0])[0]);
        $parts = preg_split('/[-\/.]/', $raw);
        if (count($parts) !== 3) return (string) $val;

        if (strlen($parts[0]) === 4) {
            $y = (int) $parts[0];
            $m = (int) $parts[1];
            $d = (int) $parts[2];
        } else if (strlen($parts[2]) === 4) {
            $d = (int) $parts[0];
            $m = (int) $parts[1];
            $y = (int) $parts[2];
        } else {
            return (string) $val;
        }

        $shouldSwap = false;
        $trxMonth = null;
        if (!empty($nomor) && preg_match('/CT-\d{2}(\d{2})\d{2}/', (string) $nomor, $mNomor)) {
            $trxMonth = (int) $mNomor[1];
        }

        $intv = !empty($interval) ? (int) $interval : 0;
        if ($intv > 0 && $intv <= 5 && $trxMonth && $d === $trxMonth && $m <= 12 && $m !== $trxMonth) {
            $shouldSwap = true;
        }

        if (!$shouldSwap && !empty($otherVal) && !empty($interval)) {
            $intv = (int) $interval;
            $otherRaw = trim(explode(' ', explode('T', (string) $otherVal)[0])[0]);
            $oParts = preg_split('/[-\/.]/', $otherRaw);
            if (count($oParts) === 3) {
                $oD = (int) (strlen($oParts[0]) === 4 ? $oParts[2] : $oParts[0]);
                $oM = (int) $oParts[1];
                if ($d === $oD && (abs($oM - $m) + 1) === $intv && $intv <= 5 && $m <= 12 && $d <= 12) {
                    $shouldSwap = true;
                }
            }
        }

        if (!$shouldSwap && !empty($interval)) {
            $intv = (int) $interval;
            if ($intv <= 5 && $d === 10 && ($m === 6 || $m === 7)) {
                $shouldSwap = true;
            }
        }

        if ($shouldSwap && $d <= 12 && $m <= 12) {
            $tmp = $d;
            $d = $m;
            $m = $tmp;
        }

        return sprintf('%02d/%02d/%04d', $d, $m, $y);
    }

    public function transformRowData( array $row )
    {
        $approval = generate_approval_log::where('trx_table', 't_cuti')->where('trx_id', $row['id']) ->orderBy('created_at', 'desc')->value('action_note');
        $row['approval_note'] = $approval ?? '-';
        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')->where('k.id', $row['m_kary_id'])->value('d.nama');
        }
        if (!empty($compNama)) {
            $row['m_dir.nama'] = $compNama;
        }

        foreach (['date_from', 'date_to'] as $col) {
            if (!empty($row[$col])) {
                $other = ($col === 'date_from') ? ($row['date_to'] ?? null) : ($row['date_from'] ?? null);
                $row[$col] = $this->formatDisplayDateSafe($row[$col], $other, $row['interval'] ?? null, $row['nomor'] ?? null);
            }
        }

        return $row;
    }

    

    private function hitungHari($from , $to) {
        $dateFrom = $this->parseCarbonDate($from);
        $dateTo = $this->parseCarbonDate($to);
        if (!$dateFrom || !$dateTo) return 1;

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        // Libur nasional murni (is_cuti_bersama = 0 atau false/null) TIDAK memotong cuti tahunan (di-skip)
        // Sedangkan Cuti Bersama (is_cuti_bersama = 1) MEMOTONG cuti tahunan (tetap dihitung sebagai hari cuti)
        $liburNasionalMurni = \DB::table('m_libur_nasional')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('is_cuti_bersama')
                  ->orWhere('is_cuti_bersama', false)
                  ->orWhere('is_cuti_bersama', 0);
            })
            ->pluck('tanggal')
            ->map(function($t) { return date('Y-m-d', strtotime($t)); })
            ->toArray();

        $businessDays = 0;

        foreach ($period as $date) {
            $tglStr = $date->format('Y-m-d');
            if ($date->dayOfWeek != Carbon::SUNDAY && !in_array($tglStr, $liburNasionalMurni)) {
                $businessDays++;
            }
        }
        return $businessDays;
    }

    public function custom_hitungHari($req) {
        $from = $req->date_from;
        $to = $req->date_to;
        if(app()->request->header("Source") == "mobile"){
            $dateFrom = Carbon::parse($from);
            $dateTo = Carbon::parse($to);
        }else{
            $dateFrom = Carbon::createFromFormat('d/m/Y', $from);
            $dateTo = Carbon::createFromFormat('d/m/Y', $to);
        }

        // Membuat rentang tanggal antara date_from dan date_to
        $period = CarbonPeriod::create($dateFrom, $dateTo);

        // Menghitung jumlah hari kerja: Hari Minggu & Libur Nasional murni di-skip, Cuti Bersama tetap dihitung
        $liburNasionalMurni = \DB::table('m_libur_nasional')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('is_cuti_bersama')
                  ->orWhere('is_cuti_bersama', false)
                  ->orWhere('is_cuti_bersama', 0);
            })
            ->pluck('tanggal')
            ->map(function($t) { return date('Y-m-d', strtotime($t)); })
            ->toArray();

        $businessDays = 0;

        foreach ($period as $date) {
            $tglStr = $date->format('Y-m-d');
            if ($date->dayOfWeek != Carbon::SUNDAY && !in_array($tglStr, $liburNasionalMurni)) {
                $businessDays++;
            }
        }
        
        return $businessDays;
    }

    private function hitungMenit($from, $to) {
        $dateFrom = Carbon::parse($from);
        $dateTo = Carbon::parse($to);

        // Calculate the difference in minutes for datetime range
        $minutesDifference = $dateFrom->diffInMinutes($dateTo);

        return $minutesDifference;
    }

    private function validateCutiBusinessRules($arrayData, $interval, $interval_min, $id = null)
    {
        $karyId = $arrayData['m_kary_id'] ?? null;
        if (!$karyId) {
            return response([
                "status"  => "error",
                "message" => "Akun ini tidak tersambung dengan data karyawan manapun!",
                "errors"  => ["m_kary_id" => ["Akun ini tidak tersambung dengan data karyawan manapun!"]]
            ], 422);
        }

        // Ambil data m_general untuk alasan & tipe cuti
        $alasanObj = !empty($arrayData['alasan_id']) ? \DB::table('m_general')->where('id', $arrayData['alasan_id'])->first() : null;
        $tipeObj   = !empty($arrayData['tipe_cuti_id']) ? \DB::table('m_general')->where('id', $arrayData['tipe_cuti_id'])->first() : null;

        $alasanStr = strtolower(trim(($alasanObj->value ?? '') . ' ' . ($alasanObj->code ?? '')));
        $tipeStr   = strtolower(trim(($tipeObj->value ?? '') . ' ' . ($tipeObj->code ?? '')));
        $ketStr    = strtolower(trim((string)($arrayData['keterangan'] ?? '')));
        $checkStr  = "{$alasanStr} {$tipeStr} {$ketStr}";

        $isP24 = (!empty($arrayData['time_from']) && !empty($arrayData['time_to'])) || str_contains($checkStr, 'p24');
        $isSakit = str_contains($checkStr, 'sakit');
        $isMenikah = str_contains($checkStr, 'nikah') || str_contains($checkStr, 'menikah');
        $isMeninggal = str_contains($checkStr, 'meninggal') || str_contains($checkStr, 'kematian') || str_contains($checkStr, 'duka');
        $isMelahirkan = str_contains($alasanStr, 'lahir') || str_contains($alasanStr, 'melahirkan') || str_contains($alasanStr, 'bersalin') || str_contains($checkStr, 'melahirkan');
        $isKeagamaan = str_contains($checkStr, 'agama') || str_contains($checkStr, 'keagamaan') || str_contains($checkStr, 'ibadah') || str_contains($checkStr, 'haji') || str_contains($checkStr, 'umroh');

        if ($isP24) {
            // Izin parsial jam-jaman (P24) tidak dibatasi hari
            return null;
        }

        $durasiHari = (int) ($interval ?? 1);

        // Ambil batas maksimal hari berturut-turut secara dinamis dari m_general kolom value_2
        $maxHariBerturut = null;
        if (!empty($alasanObj->value_2) && is_numeric(trim($alasanObj->value_2))) {
            $maxHariBerturut = (int) trim($alasanObj->value_2);
        } elseif (!empty($tipeObj->value_2) && is_numeric(trim($tipeObj->value_2))) {
            $maxHariBerturut = (int) trim($tipeObj->value_2);
        }

        // Fallback default cerdas jika value_2 belum diatur di database m_general
        if (!$maxHariBerturut || $maxHariBerturut <= 0) {
            if ($isMelahirkan) {
                $maxHariBerturut = 90; // Default 3 bulan (90 hari) untuk cuti melahirkan
            } elseif ($isKeagamaan) {
                $maxHariBerturut = 40; // Default 40 hari untuk ibadah haji/keagamaan
            } else {
                $maxHariBerturut = 3;  // Default 3 hari berturut-turut untuk cuti reguler/izin lainnya
            }
        }

        $namaCuti = $alasanObj->value ?? ($tipeObj->value ?? 'cuti');

        // Validasi batas maksimal hari berturut-turut dinamis
        if ($durasiHari > $maxHariBerturut) {
            return response([
                "status"  => "error",
                "message" => "Pengajuan {$namaCuti} maksimal {$maxHariBerturut} hari berturut-turut per pengajuan.",
                "errors"  => ["interval" => ["Pengajuan {$namaCuti} maksimal {$maxHariBerturut} hari berturut-turut per pengajuan."]]
            ], 422);
        }

        // Cek syarat khusus dokumen lampiran surat dokter untuk izin sakit
        if ($isSakit && empty($arrayData['attachment'])) {
            return response([
                "status"  => "error",
                "message" => "Pengajuan izin sakit wajib melampirkan Surat Keterangan Dokter (SKD).",
                "errors"  => ["attachment" => ["Pengajuan izin sakit wajib melampirkan Surat Keterangan Dokter (SKD)."]]
            ], 422);
        }

        // Izin Khusus (Melahirkan, Keagamaan, Sakit SKD, Menikah, Duka Cita, P24): TIDAK memotong kuota 12 hari cuti reguler tahunan
        $isKhusus = $isMelahirkan || $isKeagamaan || $isSakit || $isMenikah || $isMeninggal || $isP24;
        if ($isKhusus) {
            return null;
        }

        // Cuti Reguler / Tahunan: Memotong kuota cuti reguler tahunan karyawan
        $kary = \DB::table('m_kary')->where('id', $karyId)->first();
        if ($kary) {
            $jatahProrata = \App\Models\CustomModels\m_kary::hitungJatahCutiReguler($kary->tgl_masuk ?? null);
            $sisaCuti = isset($kary->cuti_sisa_reguler) ? (int) $kary->cuti_sisa_reguler : $jatahProrata;
            if ($id) {
                $oldCuti = \DB::table('t_cuti')->where('id', $id)->first();
                if ($oldCuti && in_array($oldCuti->status, ['APPROVED', 'POSTED'])) {
                    $sisaCuti += (int) ($oldCuti->interval ?? 0);
                }
            }
            if ($durasiHari > $sisaCuti) {
                return response([
                    "status"  => "error",
                    "message" => "Sisa kuota cuti tahunan tidak mencukupi (sisa kuota: {$sisaCuti} hari, pengajuan: {$durasiHari} hari).",
                    "errors"  => ["interval" => ["Sisa kuota cuti tahunan tidak mencukupi (sisa kuota: {$sisaCuti} hari, pengajuan: {$durasiHari} hari)."]]
                ], 422);
            }
        }

        return null;
    }

    private function canAccessCutiApprovalOrTrx($trx, $genApp = null, $authUser = null, $respoCore = null): bool
    {
        if (!$trx) {
            return false;
        }
        $respo = $respoCore ?: getCore('Respo');
        $user = $authUser ?: (auth()->check() ? auth()->user() : null);
        if (!$user || !$respo) {
            return true;
        }
        if ($respo->isUserSuperadmin($user)) {
            return true;
        }

        $scope = $respo->getActiveCompanyScope($user);
        if (($scope['m_dir_id'] ?? null) === -1) {
            return false;
        }

        $uid = (int) ($user->id ?? 0);
        $userKaryId = (int) ($user->m_kary_id ?? 0);

        if (($userKaryId > 0 && (int) ($trx->m_kary_id ?? 0) === $userKaryId)
            || ($uid > 0 && (int) ($trx->creator_id ?? 0) === $uid)) {
            return true;
        }

        $kary = !empty($trx->m_kary_id) ? \DB::table('m_kary')->where('id', $trx->m_kary_id)->first() : null;
        if ($kary && $userKaryId > 0 && !empty($kary->atasan_id) && (int) $kary->atasan_id === $userKaryId) {
            return true;
        }

        $effDirId = !empty($trx->m_dir_id) ? (int) $trx->m_dir_id : (!empty($kary->m_dir_id) ? (int) $kary->m_dir_id : null);
        $inCompanyScope = $respo->matchesActiveCompanyScope($effDirId, $kary->m_divisi_id ?? null, $kary->m_dept_id ?? null, $user);

        if ($inCompanyScope && $respo->isUserHrdOrAdmin($user)) {
            return true;
        }

        if ($genApp) {
            if ($uid > 0 && !empty($genApp->last_approve_id) && (int) $genApp->last_approve_id === $uid) {
                return true;
            }
            if ($uid > 0) {
                $isDirectDet = \DB::table('generate_approval_det')
                    ->where('generate_approval_id', $genApp->id)
                    ->where(function ($q) use ($uid) {
                        $q->where('default_user_id', $uid)->orWhere('action_user_id', $uid);
                    })
                    ->exists();
                if ($isDirectDet) {
                    return true;
                }
            }
            if ($inCompanyScope) {
                $roleIds = $respo->getEffectiveRoleIds($user);
                if (!empty($roleIds)) {
                    $hasRoleDet = \DB::table('generate_approval_det')
                        ->where('generate_approval_id', $genApp->id)
                        ->whereNull('default_user_id')
                        ->whereIn('m_role_id', $roleIds)
                        ->exists();
                    if ($hasRoleDet) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        $respoCore = getCore('Respo');
        $authUser = auth()->check() ? auth()->user() : null;
        $scope = ($authUser && $respoCore) ? $respoCore->getActiveCompanyScope($authUser) : null;
        if ($scope && ($scope['m_dir_id'] ?? null) === -1) {
            return response([
                "status"  => "error",
                "message" => "Responsibility aktif tidak ditemukan atau sedang nonaktif.",
                "errors"  => ["m_dir_id" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."]],
            ], 422);
        }

        if (empty($arrayData['m_kary_id'])) {
            return response([
                "status"  => "error",
                "message" => "Akun ini tidak Tersambung dengan data karyawan manapun !",
                "errors"  => ["m_kary_id" => ["Akun ini tidak Tersambung dengan data karyawan manapun !"]],
            ], 422);
        }

        $targetKary = \DB::table('m_kary')->where('id', $arrayData['m_kary_id'])->first();
        if (!$targetKary) {
            return response([
                "status"  => "error",
                "message" => "Data karyawan tidak ditemukan.",
                "errors"  => ["m_kary_id" => ["Data karyawan tidak ditemukan."]],
            ], 422);
        }

        if ($authUser && $respoCore) {
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $authKaryId = !empty($authUser->m_kary_id) ? (int) $authUser->m_kary_id : null;

            if (!$isHrdOrAdmin && (!$authKaryId || (int) $arrayData['m_kary_id'] !== $authKaryId)) {
                return response([
                    "status"  => "error",
                    "message" => $authKaryId
                        ? "Tidak diizinkan mengajukan cuti untuk karyawan lain."
                        : "Akun ini tidak Tersambung dengan data karyawan manapun !",
                    "errors"  => ["m_kary_id" => [$authKaryId ? "Tidak diizinkan mengajukan cuti untuk karyawan lain." : "Akun ini tidak Tersambung dengan data karyawan manapun !"]],
                ], 422);
            }

            if (!$isHrdOrAdmin && !empty($targetKary->m_dir_id)) {
                $arrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
            } elseif (!empty($arrayData['m_dir_id']) && !empty($targetKary->m_dir_id) && (int) $arrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
                return response([
                    "status"  => "error",
                    "message" => "Company pengajuan cuti harus sesuai dengan Company karyawan terpilih.",
                    "errors"  => ["m_dir_id" => ["Company pengajuan cuti harus sesuai dengan Company karyawan terpilih."]],
                ], 422);
            }

            if ($scope && empty($scope['is_unrestricted'])) {
                $effDirId = !empty($arrayData['m_dir_id']) ? (int) $arrayData['m_dir_id'] : (!empty($targetKary->m_dir_id) ? (int) $targetKary->m_dir_id : null);
                if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetKary->m_divisi_id ?? null, $targetKary->m_dept_id ?? null, $authUser)) {
                    return response([
                        "status"  => "error",
                        "message" => "Tidak diizinkan membuat pengajuan cuti di luar scope Company/Divisi/Departemen aktif.",
                        "errors"  => ["m_kary_id" => ["Karyawan atau Company berada di luar scope responsibility aktif."]],
                    ], 422);
                }
            }
        }

        $interval = null;
        $interval_min = null;

        if (isset($arrayData['date_from']) && isset($arrayData['date_to']) && (!isset($arrayData['time_from']) || $arrayData['time_from'] === null || $arrayData['time_from'] === '') && (!isset($arrayData['time_to']) || $arrayData['time_to'] === null || $arrayData['time_to'] === '')) {
            $interval = @$this->hitungHari($arrayData['date_from'], $arrayData['date_to']) ?? 1;
        }

        if (isset($arrayData['time_from']) && isset($arrayData['time_to'])) {
            $interval_min = @$this->hitungMenit($arrayData['time_from'], $arrayData['time_to']) ?? 1;
        }

        // Validasi Aturan Bisnis Cuti & Dispensasi MoM Poin 5
        $validationErr = $this->validateCutiBusinessRules($arrayData, $interval, $interval_min, $id);
        if ($validationErr) {
            return $validationErr;
        }

        $newArrayData = array_merge($arrayData, [
            "nomor" => $this->helper->generateNomor("KODE CUTI"),
            "interval" => $interval,
            "interval_min" => $interval_min
        ]);
        if (app()->request->header("Source") == "mobile") {
            $newArrayData = array_merge($newArrayData, [
                "status" => "IN APPROVAL",
                "interval" => @$interval,
                "interval_min" => @$interval_min
            ]);
        }
        if (empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id)) {
            $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
        }
        if (empty($newArrayData['m_comp_id']) && !empty($targetKary->m_comp_id)) {
            $newArrayData['m_comp_id'] = (int) $targetKary->m_comp_id;
        }
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = ($authUser && !empty($authUser->m_comp_id)) ? (int) $authUser->m_comp_id : 1;
        }

        foreach (['date_from', 'date_to'] as $dCol) {
            if (!empty($newArrayData[$dCol])) {
                $parsed = $this->parseCarbonDate($newArrayData[$dCol]);
                if ($parsed) {
                    $newArrayData[$dCol] = $parsed->format('Y-m-d');
                }
            }
        }

        return [
            "model" => $model,
            "data" => $newArrayData,
            // "errors" => ['error1']
        ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $respoCore = getCore('Respo');
        $authUser = auth()->check() ? auth()->user() : null;
        $existing = $id ? \DB::table('t_cuti')->where('id', $id)->first() : null;
        $scope = ($authUser && $respoCore) ? $respoCore->getActiveCompanyScope($authUser) : null;
        if ($scope && ($scope['m_dir_id'] ?? null) === -1) {
            return response([
                "status"  => "error",
                "message" => "Responsibility aktif tidak ditemukan atau sedang nonaktif.",
                "errors"  => ["m_dir_id" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."]],
            ], 422);
        }

        $effKaryId = $arrayData['m_kary_id'] ?? ($existing->m_kary_id ?? null);
        $arrayData['m_kary_id'] = $effKaryId;
        $existingKary = ($existing && !empty($existing->m_kary_id)) ? \DB::table('m_kary')->where('id', $existing->m_kary_id)->first() : null;
        $targetKary = $effKaryId ? \DB::table('m_kary')->where('id', $effKaryId)->first() : null;
        if ($effKaryId && !$targetKary) {
            return response([
                "status"  => "error",
                "message" => "Data karyawan tidak ditemukan.",
                "errors"  => ["m_kary_id" => ["Data karyawan tidak ditemukan."]],
            ], 422);
        }

        if ($authUser && $respoCore) {
            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $authKaryId = !empty($authUser->m_kary_id) ? (int) $authUser->m_kary_id : null;

            if (!$isHrdOrAdmin) {
                if (!$authKaryId || ($existing && (int) $existing->m_kary_id !== $authKaryId)) {
                    return response([
                        "status"  => "error",
                        "message" => "Tidak diizinkan mengubah pengajuan cuti karyawan lain.",
                        "errors"  => ["m_kary_id" => ["Tidak diizinkan mengubah pengajuan cuti karyawan lain."]],
                    ], 422);
                }
                if (!empty($arrayData['m_kary_id']) && (int) $arrayData['m_kary_id'] !== $authKaryId) {
                    return response([
                        "status"  => "error",
                        "message" => "Tidak diizinkan memindahkan pengajuan cuti ke karyawan lain.",
                        "errors"  => ["m_kary_id" => ["Tidak diizinkan memindahkan pengajuan cuti ke karyawan lain."]],
                    ], 422);
                }
                if ($targetKary && !empty($targetKary->m_dir_id)) {
                    $arrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
                }
            } elseif ($targetKary && !empty($targetKary->m_dir_id) && !empty($arrayData['m_dir_id']) && (int) $arrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
                return response([
                    "status"  => "error",
                    "message" => "Company pengajuan cuti harus sesuai dengan Company karyawan terpilih.",
                    "errors"  => ["m_dir_id" => ["Company pengajuan cuti harus sesuai dengan Company karyawan terpilih."]],
                ], 422);
            }

            if ($scope && empty($scope['is_unrestricted'])) {
                $existingDirId = $existing ? (!empty($existing->m_dir_id) ? (int) $existing->m_dir_id : (!empty($existingKary->m_dir_id) ? (int) $existingKary->m_dir_id : null)) : null;
                $existingDivId = $existingKary ? ($existingKary->m_divisi_id ?? null) : null;
                $existingDeptId = $existingKary ? ($existingKary->m_dept_id ?? null) : null;
                if ($existing && !$respoCore->matchesActiveCompanyScope($existingDirId, $existingDivId, $existingDeptId, $authUser)) {
                    return response([
                        "status"  => "error",
                        "message" => "Tidak diizinkan mengubah pengajuan cuti di luar scope responsibility aktif.",
                        "errors"  => ["m_dir_id" => ["Data cuti berada di luar scope responsibility aktif."]],
                    ], 422);
                }

                $effDirId = !empty($arrayData['m_dir_id'])
                    ? (int) $arrayData['m_dir_id']
                    : (($targetKary && !empty($targetKary->m_dir_id)) ? (int) $targetKary->m_dir_id : $existingDirId);
                $targetDivId = $targetKary ? ($targetKary->m_divisi_id ?? null) : null;
                $targetDeptId = $targetKary ? ($targetKary->m_dept_id ?? null) : null;
                if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetDivId, $targetDeptId, $authUser)) {
                    return response([
                        "status"  => "error",
                        "message" => "Tidak diizinkan memindahkan pengajuan cuti ke luar scope Company/Divisi/Departemen aktif.",
                        "errors"  => ["m_kary_id" => ["Target karyawan atau Company berada di luar scope responsibility aktif."]],
                    ], 422);
                }
            }
        }

        $interval = null;
        $interval_min = null;

        if (isset($arrayData['date_from']) && isset($arrayData['date_to']) && (!isset($arrayData['time_from']) || $arrayData['time_from'] === null || $arrayData['time_from'] === '') && (!isset($arrayData['time_to']) || $arrayData['time_to'] === null || $arrayData['time_to'] === '')) {
            $interval = @$this->hitungHari($arrayData['date_from'], $arrayData['date_to']) ?? 1;
        }

        if (isset($arrayData['time_from']) && isset($arrayData['time_to'])) {
            $interval_min = @$this->hitungMenit($arrayData['time_from'], $arrayData['time_to']) ?? 1;
        }

        // Validasi Aturan Bisnis Cuti & Dispensasi MoM Poin 5
        $validationErr = $this->validateCutiBusinessRules($arrayData, $interval, $interval_min, $id);
        if ($validationErr) {
            return $validationErr;
        }

        if (app()->request->header("Source") == "mobile") {
            $data = t_cuti::where('id', $id)->first();
            if($data && $data["status"] === 'REVISED'){
                $status = 'IN APPROVAL';
            }
        }
        $newArrayData  = array_merge( $arrayData,[
            'status' => @$status ?? @$arrayData['status'],
            "interval" =>@ $interval ?? @$arrayData['interval'],
            "interval_min" => @$interval_min ?? @$arrayData['interval_min'] 
        ]);
        if (empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id)) {
            $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
        }
        if (empty($newArrayData['m_comp_id']) && !empty($targetKary->m_comp_id)) {
            $newArrayData['m_comp_id'] = (int) $targetKary->m_comp_id;
        }

        foreach (['date_from', 'date_to'] as $dCol) {
            if (!empty($newArrayData[$dCol])) {
                $parsed = $this->parseCarbonDate($newArrayData[$dCol]);
                if ($parsed) {
                    $newArrayData[$dCol] = $parsed->format('Y-m-d');
                }
            }
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData,
            // "errors" => ['error1']
        ];
    }

 
    

    public function createAfter($model, $arrayData, $metaData, $id = null)
    {
        if (app()->request->header("Source") == "mobile") {
            $app = $this->createAppTicket($model->id);
        }
    }

    public function updateAfterTransaction( $newdata, $olddata, $data, $meta )
    {
        if (app()->request->header("Source") == "mobile") {
            $app = $this->createAppTicket($newdata['id']);  
        }
    }

    public function custom_send_approval()
    {
        $trxId = req("id");
        $trx = $trxId ? \DB::table('t_cuti')->where('id', $trxId)->first() : null;
        if (!$trx) {
            return $this->helper->customResponse(
                "Data cuti tidak ditemukan",
                404
            );
        }

        $respoCore = getCore('Respo');
        $authUser = auth()->check() ? auth()->user() : null;
        if ($authUser && $respoCore && !$respoCore->isUserSuperadmin($authUser)) {
            $scope = $respoCore->getActiveCompanyScope($authUser);
            if (($scope['m_dir_id'] ?? null) === -1) {
                return $this->helper->customResponse(
                    "Responsibility aktif tidak ditemukan atau sedang nonaktif.",
                    403
                );
            }

            $isHrdOrAdmin = $respoCore->isUserHrdOrAdmin($authUser);
            $authKaryId = (int) ($authUser->m_kary_id ?? 0);
            $authUserId = (int) ($authUser->id ?? 0);
            $isOwner = ($authKaryId > 0 && (int) $trx->m_kary_id === $authKaryId)
                || ($authUserId > 0 && (int) $trx->creator_id === $authUserId);

            if (!$isHrdOrAdmin && !$isOwner) {
                return $this->helper->customResponse(
                    "Tidak diizinkan mengirim approval pengajuan cuti karyawan lain.",
                    403
                );
            }

            if (empty($scope['is_unrestricted'])) {
                $kary = !empty($trx->m_kary_id) ? \DB::table('m_kary')->where('id', $trx->m_kary_id)->first() : null;
                $effDir = !empty($trx->m_dir_id) ? (int) $trx->m_dir_id : (!empty($kary->m_dir_id) ? (int) $kary->m_dir_id : null);
                if (!$respoCore->matchesActiveCompanyScope($effDir, $kary->m_divisi_id ?? null, $kary->m_dept_id ?? null, $authUser)) {
                    return $this->helper->customResponse(
                        "Tidak diizinkan mengirim approval data di luar scope responsibility aktif.",
                        403
                    );
                }
            }
        }

        $app = $this->createAppTicket($trxId);
        if (!$app) {
            return $this->helper->customResponse(
                "Terjadi kesalahan, coba kembali nanti",
                400
            );
        }

        if (app()->request->header("Source") != "mobile") {
            $spd = t_cuti::find($trxId);
            if ($spd) {
                $spd->update([
                    "status" => "IN APPROVAL",
                ]);
            }
        }

        return $this->helper->customResponse(
            "Permintaan approval berhasil dibuat"
        );
    }

    public function public_tes($req)
    {
        $id = "4";
        $data = t_cuti::where('id', (int)$id)->first();
        return $data;
    }

    // public function updateAfter( $model, $arrayData, $metaData, $id=null )
    // {
    //     if (app()->request->header("Source") == "mobile") {
    //         $data = t_cuti::where('id', 4)->first();
    //         if(@$data) { 
    //             dd($data);
    //             }
    //         if(@$data->status === 'REVISED'){
    //             $status = 'IN APPROVAL';
    //             $app = $this->createAppTicket($id);
    //              if (!$app) {
    //                 return $this->helper->customResponse(
    //                     "Approval tidak tersedia untuk atribut user anda",
    //                     400
    //                 );
    //             }
    //         }
    //     }
    //     $newArrayData = $arrayData;
    //     if(@$status){
    //         $newArrayData  = array_merge( $newArrayData,[
    //             'status' => $status 
    //         ]);
    //     }

    //     return [
    //         "model"  => $model,
    //         "data"   => $newArrayData,
    //         // "errors" => ['error1']
    //     ];
    // }
    

    private function createAppTicket($id)
    {
        $trx = $this->find($id);

        $trxDate = !empty($trx->date_from) ? date('Y-m-d', strtotime($trx->date_from)) : Date("Y-m-d");
        $conf = [
            "app_name" => "APPROVAL CUTI",
            "trx_id" => $trx->id,
            "trx_table" => $this->getTable(),
            "trx_name" => "Pengajuan Cuti",
            "form_name" => "t_cuti",
            "trx_nomor" => $trx->nomor,
            "trx_date" => $trxDate,
            "trx_creator_id" => $trx->creator_id,
            "m_kary_id" => $trx->m_kary_id,
        ];

        $app = $this->helper->approvalCreateTicket($conf);
        if ($app) {
            return true;
        } else {
            return false;
        }
    }

    // public function custom_post ($request) {
    //     $data = t_cuti::find($request->$id);
    //     if (!$data) {
    //         return response()->json(['message' => 'Data not found'], 404);
    //     }
    //     if ($data->status === 'DRAFT') {
    //         // Change the status to post
    //         $data->update([
    //             "status" => "POSTED"
    //             ]);
    //         // $data->status = 'POSTED';
    //         // $data->save();
    //         return response()->json(['message' => 'DRAFT status changed to "POSTED"']);
    //     } else {
    //         // If the status is not draft, return a message
    //         return response()->json(['message' => 'POSTED status is not "DRAFT"'], 400);
    //     }
    // }

    

    public function custom_progress($req)
    {
        // Start a database transaction
        \DB::beginTransaction();

        try {
            $conf = [
                "app_id" => $req->id,
                "app_type" => $req->type, // APPROVED, REVISED, REJECTED,
                "app_note" => $req->note, // alasan approve
            ];

            $app = $this->helper->approvalProgress($conf, true);
            if ($app->status) {
                $data = $this->find($app->trx_id);
                if ($app->finish) {
                    $data->update([
                        "status" => $req->type
                    ]);

                    // Jika approval APPROVED, periksa apakah cuti reguler untuk potong kuota di m_kary
                    if ($req->type === 'APPROVED' && !empty($data->m_kary_id) && $data->interval > 0) {
                        $alasanObj = !empty($data->alasan_id) ? \DB::table('m_general')->where('id', $data->alasan_id)->first() : null;
                        $tipeObj   = !empty($data->tipe_cuti_id) ? \DB::table('m_general')->where('id', $data->tipe_cuti_id)->first() : null;
                        $checkStr  = strtolower(trim(($alasanObj->value ?? '') . ' ' . ($tipeObj->value ?? '') . ' ' . ($data->keterangan ?? '')));

                        $isKhusus = str_contains($checkStr, 'sakit') ||
                                    str_contains($checkStr, 'nikah') ||
                                    str_contains($checkStr, 'menikah') ||
                                    str_contains($checkStr, 'meninggal') ||
                                    str_contains($checkStr, 'kematian') ||
                                    str_contains($checkStr, 'duka') ||
                                    str_contains($checkStr, 'lahir') ||
                                    str_contains($checkStr, 'melahirkan') ||
                                    str_contains($checkStr, 'bersalin') ||
                                    str_contains($checkStr, 'persalinan') ||
                                    str_contains($checkStr, 'agama') ||
                                    str_contains($checkStr, 'keagamaan') ||
                                    str_contains($checkStr, 'ibadah') ||
                                    str_contains($checkStr, 'haji') ||
                                    str_contains($checkStr, 'umroh') ||
                                    str_contains($checkStr, 'p24') ||
                                    (!empty($data->time_from) && !empty($data->time_to));

                        if (!$isKhusus) {
                            // Cuti Reguler: potong kuota sisa cuti reguler karyawan
                            \DB::table('m_kary')
                                ->where('id', $data->m_kary_id)
                                ->update([
                                    'cuti_sisa_reguler' => \DB::raw("GREATEST(0, COALESCE(cuti_sisa_reguler, 12) - {$data->interval})")
                                ]);
                        }
                    }
                } else {
                    $data->update([
                        "status" => "IN APPROVAL",
                    ]);
                }
            }

            \DB::commit();

            return $this->helper->customResponse("Proses approval berhasil");
        } catch (\Exception $e) {
            \DB::rollback();
            return $this->helper->responseCatch($e);
        }
    }


    public function custom_detail($req)
    {
        $id = $req->id ?? 66;
        $data = $this->helper->approvalDetail($id);
        if ($data && isset($data->trx)) {
            $trx = $data->trx;
            $app = $data->approval ?? null;
            if ($app && ($app->trx_table ?? 't_cuti') === 't_cuti') {
                if (!$this->canAccessCutiApprovalOrTrx($trx, $app)) {
                    return $this->helper->customResponse("Anda tidak memiliki akses ke detail approval cuti ini.", 403);
                }
            }
            $nomor = $trx->nomor ?? null;
            $intv = $trx->interval ?? ($data->approval->interval ?? null);
            if (!empty($trx->date_from)) {
                $trx->date_from = $this->formatDisplayDateSafe($trx->date_from, $trx->date_to ?? null, $intv, $nomor);
            }
            if (!empty($trx->date_to)) {
                $trx->date_to = $this->formatDisplayDateSafe($trx->date_to, $trx->date_from ?? null, $intv, $nomor);
            }
        }
        return $this->helper->customResponse("OK", 200, $data);
    }
    public function custom_log($req)
    {
        $trxId = (int) ($req->id ?? 0);
        if ($trxId > 0) {
            $trx = \DB::table('t_cuti')->where('id', $trxId)->first();
            if ($trx) {
                $genApp = \DB::table('generate_approval')
                    ->where('trx_table', 't_cuti')
                    ->where('trx_id', $trxId)
                    ->orderBy('id', 'desc')
                    ->first();
                if (!$this->canAccessCutiApprovalOrTrx($trx, $genApp)) {
                    return $this->helper->customResponse("Anda tidak memiliki akses ke log approval cuti ini.", 403);
                }
            }
        }

        $conf = [
            "trx_id" => $trxId,
            "trx_table" => $this->getTable(),
        ];
        $data = $this->helper->approvalLog($conf);
        return response($data);
    }
}
