<?php

namespace App\Models\CustomModels;

use Illuminate\Database\Eloquent\Builder;

class t_cuti_adjustment extends \App\Models\BasicModels\t_cuti_adjustment
{    
    private $helper;

    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
    }

    protected static function boot()
    {
        parent::boot();

        // Isolasi Transaksi Adjustment Cuti sesuai active Respo:
        // Superadmin = semua PT, HRD/Admin = PT/Divisi/Dept active Respo (kary_fk_col menangani data lawas yang m_dir_id-nya null), User biasa = milik sendiri
        static::addGlobalScope('respo_scope', function (Builder $builder) {
            $respoCore = getCore('Respo');
            if (!$respoCore || !$respoCore->isOperationCrudRequest('t_cuti_adjustment', ['GET', 'PUT', 'PATCH', 'DELETE'])) {
                return;
            }

            $respoCore->applyActiveEmployeeTransactionScopeToQuery($builder, 't_cuti_adjustment', [
                'trx_table'                => 't_cuti_adjustment',
                'dir_col'                  => 't_cuti_adjustment.m_dir_id',
                'divisi_col'               => null,
                'dept_col'                 => null,
                'kary_fk_col'              => 't_cuti_adjustment.m_kary_id',
                'allow_approver_on_single' => false,
            ]);
        });
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function transformRowData( array $row )
    {
        $compNama = $row['m_dir.nama'] ?? null;
        if (empty($compNama) && !empty($row['m_dir_id'])) {
            $compNama = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama');
        }
        if (empty($compNama) && !empty($row['m_kary_id'])) {
            $compNama = \DB::table('m_kary as k')
                ->join('m_dir as d', 'd.id', '=', 'k.m_dir_id')
                ->where('k.id', $row['m_kary_id'])
                ->value('d.nama');
        }
        if (!empty($compNama)) {
            $row['m_dir.nama'] = $compNama;
        }
        return $row;
    }

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;
      $targetKary = !empty($newArrayData['m_kary_id'])
          ? \DB::table('m_kary')->where('id', $newArrayData['m_kary_id'])->first()
          : null;

      if (!$targetKary) {
          return [
              "model"  => $model,
              "data"   => $arrayData,
              "errors" => ["Karyawan wajib dipilih dan harus valid."],
          ];
      }

      if (!empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
          return [
              "model"  => $model,
              "data"   => $arrayData,
              "errors" => ["Company adjustment cuti harus sesuai dengan Company karyawan terpilih."],
          ];
      }

      $respoCore = getCore('Respo');
      $authUser = app()->bound('auth') ? app('auth')->user() : (auth()->check() ? auth()->user() : null);
      if ($authUser && $respoCore) {
          if (!$respoCore->isUserHrdOrAdmin($authUser)) {
              return [
                  "model"  => $model,
                  "data"   => $arrayData,
                  "errors" => ["Hanya HRD/Admin atau Superadmin yang berhak melakukan adjustment cuti."],
              ];
          }

          if (!$respoCore->isUserSuperadmin($authUser)) {
              $scope = $respoCore->getActiveCompanyScope($authUser);
              if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                  return [
                      "model"  => $model,
                      "data"   => $arrayData,
                      "errors" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."],
                  ];
              }

              $effDirId = !empty($targetKary->m_dir_id) ? (int) $targetKary->m_dir_id : (!empty($newArrayData['m_dir_id']) ? (int) $newArrayData['m_dir_id'] : null);
              if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetKary->m_divisi_id ?? null, $targetKary->m_dept_id ?? null, $authUser)) {
                  return [
                      "model"  => $model,
                      "data"   => $arrayData,
                      "errors" => ["Karyawan atau Perusahaan/Cabang berada di luar scope responsibility aktif."],
                  ];
              }
          }
      }

      if (!empty($targetKary->m_dir_id)) {
          $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
      }
      if (empty($newArrayData['m_comp_id'])) {
          $newArrayData['m_comp_id'] = !empty($targetKary->m_comp_id) ? (int) $targetKary->m_comp_id : 1;
      }

      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
      $existing = \DB::table('t_cuti_adjustment')->where('id', $id)->first();
      if (!$existing) {
          return [
              "model"  => $model,
              "data"   => $arrayData,
              "errors" => ["Data adjustment cuti tidak ditemukan."],
          ];
      }

      $newArrayData = $arrayData;
      $targetKaryId = array_key_exists('m_kary_id', $newArrayData) ? $newArrayData['m_kary_id'] : $existing->m_kary_id;
      $targetKary = !empty($targetKaryId)
          ? \DB::table('m_kary')->where('id', $targetKaryId)->first()
          : null;

      if (!$targetKary) {
          return [
              "model"  => $model,
              "data"   => $arrayData,
              "errors" => ["Data karyawan tidak ditemukan."],
          ];
      }

      if (!empty($newArrayData['m_dir_id']) && !empty($targetKary->m_dir_id) && (int) $newArrayData['m_dir_id'] !== (int) $targetKary->m_dir_id) {
          return [
              "model"  => $model,
              "data"   => $arrayData,
              "errors" => ["Company adjustment cuti harus sesuai dengan Company karyawan terpilih."],
          ];
      }

      $respoCore = getCore('Respo');
      $authUser = app()->bound('auth') ? app('auth')->user() : (auth()->check() ? auth()->user() : null);
      if ($authUser && $respoCore) {
          if (!$respoCore->isUserHrdOrAdmin($authUser)) {
              return [
                  "model"  => $model,
                  "data"   => $arrayData,
                  "errors" => ["Hanya HRD/Admin atau Superadmin yang berhak mengubah adjustment cuti."],
              ];
          }

          if (!$respoCore->isUserSuperadmin($authUser)) {
              $scope = $respoCore->getActiveCompanyScope($authUser);
              if (!$scope || ($scope['m_dir_id'] ?? null) === -1) {
                  return [
                      "model"  => $model,
                      "data"   => $arrayData,
                      "errors" => ["Responsibility aktif tidak ditemukan atau sedang nonaktif."],
                  ];
              }

              $existKary = !empty($existing->m_kary_id) ? \DB::table('m_kary')->where('id', $existing->m_kary_id)->first() : null;
              $existDirId = !empty($existing->m_dir_id) ? (int) $existing->m_dir_id : (!empty($existKary->m_dir_id) ? (int) $existKary->m_dir_id : null);
              if (!$respoCore->matchesActiveCompanyScope($existDirId, $existKary->m_divisi_id ?? null, $existKary->m_dept_id ?? null, $authUser)) {
                  return [
                      "model"  => $model,
                      "data"   => $arrayData,
                      "errors" => ["Tidak diizinkan mengubah adjustment cuti di luar scope responsibility aktif."],
                  ];
              }

              $effDirId = !empty($targetKary->m_dir_id) ? (int) $targetKary->m_dir_id : (!empty($newArrayData['m_dir_id']) ? (int) $newArrayData['m_dir_id'] : null);
              if (!$respoCore->matchesActiveCompanyScope($effDirId, $targetKary->m_divisi_id ?? null, $targetKary->m_dept_id ?? null, $authUser)) {
                  return [
                      "model"  => $model,
                      "data"   => $arrayData,
                      "errors" => ["Target karyawan atau Perusahaan/Cabang berada di luar scope responsibility aktif."],
                  ];
              }
          }
      }

      if (!empty($targetKary->m_dir_id)) {
          $newArrayData['m_dir_id'] = (int) $targetKary->m_dir_id;
      }
      if (empty($newArrayData['m_comp_id'])) {
          $newArrayData['m_comp_id'] = !empty($targetKary->m_comp_id) ? (int) $targetKary->m_comp_id : ($existing->m_comp_id ?? 1);
      }

      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }
}