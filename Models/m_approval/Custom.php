<?php

namespace App\Models\CustomModels;

class m_approval extends \App\Models\BasicModels\m_approval
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

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

        // Sanitize detail rows: ensure only valid columns for m_approval_det
        $cleanedDetails = [];
        foreach ($details as $idx => $d) {
            $row = is_array($d) ? $d : (array)$d;
            $cleanedDetails[] = [
                'level' => (int)($row['level'] ?? ($idx + 1)),
                'tipe' => $idx === 0 ? 'MENGAJUKAN' : ($row['tipe'] ?? 'MENGETAHUI'),
                'm_role_id' => !empty($row['m_role_id']) ? (int)$row['m_role_id'] : null,
                'default_user_id' => !empty($row['default_user_id']) ? (int)$row['default_user_id'] : null,
                'is_full_approve' => !empty($row['is_full_approve']) ? 1 : 0,
                'is_skippable' => !empty($row['is_skippable']) ? 1 : 0,
            ];
        }
        $arrayData['m_approval_det'] = $cleanedDetails;

        // Strip joined relation objects and auto-created keys
        unset(
            $arrayData['id'],
            $arrayData['created_at'],
            $arrayData['updated_at'],
            $arrayData['creator_id'],
            $arrayData['last_editor_id'],
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

            $cleanedDetails = [];
            foreach ($details as $idx => $d) {
                $row = is_array($d) ? $d : (array)$d;
                $item = [
                    'level' => (int)($row['level'] ?? ($idx + 1)),
                    'tipe' => $idx === 0 ? 'MENGAJUKAN' : ($row['tipe'] ?? 'MENGETAHUI'),
                    'm_role_id' => !empty($row['m_role_id']) ? (int)$row['m_role_id'] : null,
                    'default_user_id' => !empty($row['default_user_id']) ? (int)$row['default_user_id'] : null,
                    'is_full_approve' => !empty($row['is_full_approve']) ? 1 : 0,
                    'is_skippable' => !empty($row['is_skippable']) ? 1 : 0,
                ];
                if (!empty($row['id'])) {
                    $item['id'] = (int)$row['id'];
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
}
