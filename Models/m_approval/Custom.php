<?php

namespace App\Models\CustomModels;

class m_approval extends \App\Models\BasicModels\m_approval
{    
    public function __construct()
    {
        parent::__construct();
        $this->casts = array_merge($this->casts, [
            'is_active' => 'boolean',
        ]);
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id" => "auth:id"];
    public $updateAdditionalData = ["last_editor_id" => "auth:id"];

    public function createBefore($model, $arrayData, $metaData, $id = null)
    {
        if (empty($arrayData['nama']) || trim((string)$arrayData['nama']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama wajib diisi",
                "errors" => ["nama" => ["Kolom Nama wajib diisi"]]
            ];
        }

        if (empty($arrayData['m_menu_id'])) {
            return [
                "status" => "error",
                "message" => "Kolom Menu wajib dipilih",
                "errors" => ["m_menu_id" => ["Kolom Menu wajib dipilih"]]
            ];
        }

        $details = $arrayData['m_approval_det'] ?? [];
        $approvers = array_filter($details, function($d, $k) {
            $tipe = is_array($d) ? ($d['tipe'] ?? '') : ($d->tipe ?? '');
            $level = is_array($d) ? ($d['level'] ?? ($k + 1)) : ($d->level ?? ($k + 1));
            return (int)$level > 1 || in_array($tipe, ['MENGETAHUI', 'MENYETUJUI']);
        }, ARRAY_FILTER_USE_BOTH);

        if (count($details) <= 1 || empty($approvers)) {
            return [
                "status" => "error",
                "message" => "Detail Approval wajib diisi minimal 1 level persetujuan (Mengetahui / Menyetujui)",
                "errors" => ["m_approval_det" => ["Detail Approval wajib diisi minimal 1 level persetujuan (Mengetahui / Menyetujui)"]]
            ];
        }

        if (empty($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }

        if (empty($arrayData['m_dir_id']) && auth()->check() && !empty(auth()->user()->m_dir_id)) {
            $arrayData['m_dir_id'] = auth()->user()->m_dir_id;
        }

        if (empty($arrayData['creator_id']) && auth()->check()) {
            $arrayData['creator_id'] = auth()->id();
        }

        $arrayData['is_active'] = !empty($arrayData['is_active']) ? true : false;

        // Sanitize detail rows: ensure only valid columns for m_approval_det
        $currentUserId = auth()->check() ? auth()->id() : 1;
        $cleanedDetails = [];
        foreach ($details as $idx => $d) {
            $row = is_array($d) ? $d : (array)$d;
            $cleanedDetails[] = [
                'level' => (int)($row['level'] ?? ($idx + 1)),
                'tipe' => substr(trim((string)($idx === 0 ? 'MENGAJUKAN' : ($row['tipe'] ?? 'MENGETAHUI'))), 0, 10),
                'm_role_id' => !empty($row['m_role_id']) ? (int)$row['m_role_id'] : null,
                'default_user_id' => !empty($row['default_user_id']) ? (int)$row['default_user_id'] : null,
                'is_full_approve' => !empty($row['is_full_approve']) ? true : false,
                'is_skippable' => !empty($row['is_skippable']) ? true : false,
                'creator_id' => $currentUserId,
                'last_editor_id' => $currentUserId,
            ];
        }
        $arrayData['m_approval_det'] = $cleanedDetails;

        // Strip joined relation objects and auto-created keys
        unset(
            $arrayData['id'],
            $arrayData['created_at'],
            $arrayData['updated_at'],
            $arrayData['m_menu'],
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
        if (isset($arrayData['nama']) && trim((string)$arrayData['nama']) === '') {
            return [
                "status" => "error",
                "message" => "Kolom Nama wajib diisi",
                "errors" => ["nama" => ["Kolom Nama wajib diisi"]]
            ];
        }

        if (isset($arrayData['m_menu_id']) && empty($arrayData['m_menu_id'])) {
            return [
                "status" => "error",
                "message" => "Kolom Menu wajib dipilih",
                "errors" => ["m_menu_id" => ["Kolom Menu wajib dipilih"]]
            ];
        }

        if (empty($arrayData['m_comp_id'])) {
            $arrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }

        if (empty($arrayData['last_editor_id']) && auth()->check()) {
            $arrayData['last_editor_id'] = auth()->id();
        }

        if (isset($arrayData['is_active'])) {
            $arrayData['is_active'] = !empty($arrayData['is_active']) ? true : false;
        }

        if (isset($arrayData['m_approval_det'])) {
            $details = $arrayData['m_approval_det'];
            $approvers = array_filter($details, function($d, $k) {
                $tipe = is_array($d) ? ($d['tipe'] ?? '') : ($d->tipe ?? '');
                $level = is_array($d) ? ($d['level'] ?? ($k + 1)) : ($d->level ?? ($k + 1));
                return (int)$level > 1 || in_array($tipe, ['MENGETAHUI', 'MENYETUJUI']);
            }, ARRAY_FILTER_USE_BOTH);

            if (count($details) <= 1 || empty($approvers)) {
                return [
                    "status" => "error",
                    "message" => "Detail Approval wajib diisi minimal 1 level persetujuan (Mengetahui / Menyetujui)",
                    "errors" => ["m_approval_det" => ["Detail Approval wajib diisi minimal 1 level persetujuan (Mengetahui / Menyetujui)"]]
                ];
            }

            $currentUserId = auth()->check() ? auth()->id() : 1;
            $cleanedDetails = [];
            foreach ($details as $idx => $d) {
                $row = is_array($d) ? $d : (array)$d;
                $item = [
                    'level' => (int)($row['level'] ?? ($idx + 1)),
                    'tipe' => substr(trim((string)($idx === 0 ? 'MENGAJUKAN' : ($row['tipe'] ?? 'MENGETAHUI'))), 0, 10),
                    'm_role_id' => !empty($row['m_role_id']) ? (int)$row['m_role_id'] : null,
                    'default_user_id' => !empty($row['default_user_id']) ? (int)$row['default_user_id'] : null,
                    'is_full_approve' => !empty($row['is_full_approve']) ? true : false,
                    'is_skippable' => !empty($row['is_skippable']) ? true : false,
                    'last_editor_id' => $currentUserId,
                ];
                if (!empty($row['id'])) {
                    $item['id'] = (int)$row['id'];
                } else {
                    $item['creator_id'] = $currentUserId;
                }
                $cleanedDetails[] = $item;
            }
            $arrayData['m_approval_det'] = $cleanedDetails;
        }

        unset(
            $arrayData['m_menu'],
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

    public function scopeFilter($model)
    {
        $status = request('filter_is_active') ?? request('filter_status');
        if ($status !== null) {
            $val = strtolower(trim((string)$status));
            if (in_array($val, ['active', 'true', '1'])) {
                return $model->where('this.is_active', true);
            } elseif (in_array($val, ['inactive', 'false', '0'])) {
                return $model->where('this.is_active', false);
            }
        }
    }
}
