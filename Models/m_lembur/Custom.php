<?php

namespace App\Models\CustomModels;

class m_lembur extends \App\Models\BasicModels\m_lembur
{    
    public function __construct()
    {
        parent::__construct();
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
      // Validasi Tarif / Jam (Besaran)
      if (!isset($arrayData['besaran']) || floatval($arrayData['besaran']) <= 0) {
          throw new \Exception('Tarif / Jam (Besaran) wajib diisi dengan nominal lebih dari 0.');
      }

      $dirId = $arrayData['m_dir_id'] ?? (auth()->user()->m_dir_id ?? null);
      $divisiId = !empty($arrayData['m_divisi_id']) ? $arrayData['m_divisi_id'] : null;
      $deptId = !empty($arrayData['m_dept_id']) ? $arrayData['m_dept_id'] : null;
      $gradingId = !empty($arrayData['grading_id']) ? $arrayData['grading_id'] : null;
      $isActive = isset($arrayData['is_active'])
          ? (filter_var($arrayData['is_active'], FILTER_VALIDATE_BOOLEAN) || in_array($arrayData['is_active'], [1, '1', 'true', 't'], true))
          : true;

      // Validasi pencegahan duplikasi data aktif (Company + Divisi + Departemen + Grading)
      if ($isActive) {
          $checkQuery = \DB::table('m_lembur')
              ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))");

          if (!empty($dirId)) {
              $checkQuery->where('m_dir_id', $dirId);
          } else {
              $checkQuery->whereNull('m_dir_id');
          }

          if (!empty($divisiId)) {
              $checkQuery->where('m_divisi_id', $divisiId);
          } else {
              $checkQuery->whereNull('m_divisi_id');
          }

          if (!empty($deptId)) {
              $checkQuery->where('m_dept_id', $deptId);
          } else {
              $checkQuery->whereNull('m_dept_id');
          }

          if (!empty($gradingId)) {
              $checkQuery->where('grading_id', $gradingId);
          } else {
              $checkQuery->whereNull('grading_id');
          }

          $existing = $checkQuery->first();
          if ($existing) {
              $divName = $divisiId ? (\DB::table('m_divisi')->where('id', $divisiId)->value('nama') ?? $divisiId) : 'Umum (Semua Divisi)';
              $deptName = $deptId ? (\DB::table('m_dept')->where('id', $deptId)->value('nama') ?? $deptId) : 'Umum (Semua Departemen)';
              $gradName = $gradingId ? (\DB::table('m_general')->where('id', $gradingId)->value('value') ?? $gradingId) : 'Semua Grading';
              $tarifFmt = number_format((float)$existing->besaran, 0, ',', '.');
              throw new \Exception("Tarif lembur aktif untuk Divisi: {$divName}, Departemen: {$deptName}, dan Grading: {$gradName} sudah terdaftar dengan Kode {$existing->kode} (Rp {$tarifFmt}/jam). Silakan ubah data yang sudah ada atau nonaktifkan terlebih dahulu.");
          }
      }

      // Generate auto sequential kode LMB-xxxx
      $maxId = (int) ($model->max('id') ?? 0);
      $nextSeq = $maxId + 1;
      do {
          $candidate = 'LMB-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
          $exists = \DB::table('m_lembur')->where('kode', $candidate)->exists();
          if (!$exists) {
              $autoKode = $candidate;
              break;
          }
          $nextSeq++;
      } while (true);

      // Jika kode kosong atau kode yang dikirim sudah pernah ada di database (misal akibat copy data), pakai auto-generate baru
      $finalKode = (!empty($arrayData['kode']) && !\DB::table('m_lembur')->where('kode', $arrayData['kode'])->exists())
          ? $arrayData['kode']
          : $autoKode;

      $newArrayData  = array_merge( $arrayData,[
        'm_dir_id'    => $dirId,
        'm_divisi_id' => $divisiId,
        'm_dept_id'   => $deptId,
        'grading_id'  => $gradingId,
        'kode'        => $finalKode,
        'grading'     => !empty($gradingId) ? (\DB::table('m_general')->where('id', $gradingId)->value('value') ?? '') : (!empty($arrayData['grading']) ? $arrayData['grading'] : 'Semua Grading')
      ] );
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;
      if (array_key_exists('besaran', $arrayData)) {
          if (empty($arrayData['besaran']) || floatval($arrayData['besaran']) <= 0) {
              throw new \Exception('Tarif / Jam (Besaran) wajib diisi dengan nominal lebih dari 0.');
          }
      }

      $current = $id ? \DB::table('m_lembur')->where('id', $id)->first() : null;

      $dirId = array_key_exists('m_dir_id', $arrayData) ? $arrayData['m_dir_id'] : ($current->m_dir_id ?? null);
      $divisiId = array_key_exists('m_divisi_id', $arrayData) ? (!empty($arrayData['m_divisi_id']) ? $arrayData['m_divisi_id'] : null) : ($current->m_divisi_id ?? null);
      $deptId = array_key_exists('m_dept_id', $arrayData) ? (!empty($arrayData['m_dept_id']) ? $arrayData['m_dept_id'] : null) : ($current->m_dept_id ?? null);
      $gradingId = array_key_exists('grading_id', $arrayData) ? (!empty($arrayData['grading_id']) ? $arrayData['grading_id'] : null) : ($current->grading_id ?? null);

      $isActive = array_key_exists('is_active', $arrayData)
          ? (filter_var($arrayData['is_active'], FILTER_VALIDATE_BOOLEAN) || in_array($arrayData['is_active'], [1, '1', 'true', 't'], true))
          : (isset($current->is_active) ? (filter_var($current->is_active, FILTER_VALIDATE_BOOLEAN) || in_array($current->is_active, [1, '1', 'true', 't'], true)) : true);

      // Validasi pencegahan duplikasi data aktif jika record ini aktif
      if ($isActive && $id) {
          $checkQuery = \DB::table('m_lembur')
              ->where('id', '!=', $id)
              ->whereRaw("(is_active is true or is_active = true or is_active::text in ('1', 'true', 't'))");

          if (!empty($dirId)) {
              $checkQuery->where('m_dir_id', $dirId);
          } else {
              $checkQuery->whereNull('m_dir_id');
          }

          if (!empty($divisiId)) {
              $checkQuery->where('m_divisi_id', $divisiId);
          } else {
              $checkQuery->whereNull('m_divisi_id');
          }

          if (!empty($deptId)) {
              $checkQuery->where('m_dept_id', $deptId);
          } else {
              $checkQuery->whereNull('m_dept_id');
          }

          if (!empty($gradingId)) {
              $checkQuery->where('grading_id', $gradingId);
          } else {
              $checkQuery->whereNull('grading_id');
          }

          $existing = $checkQuery->first();
          if ($existing) {
              $divName = $divisiId ? (\DB::table('m_divisi')->where('id', $divisiId)->value('nama') ?? $divisiId) : 'Umum (Semua Divisi)';
              $deptName = $deptId ? (\DB::table('m_dept')->where('id', $deptId)->value('nama') ?? $deptId) : 'Umum (Semua Departemen)';
              $gradName = $gradingId ? (\DB::table('m_general')->where('id', $gradingId)->value('value') ?? $gradingId) : 'Semua Grading';
              $tarifFmt = number_format((float)$existing->besaran, 0, ',', '.');
              throw new \Exception("Tarif lembur aktif untuk Divisi: {$divName}, Departemen: {$deptName}, dan Grading: {$gradName} sudah terdaftar dengan Kode {$existing->kode} (Rp {$tarifFmt}/jam). Silakan ubah data yang sudah ada atau nonaktifkan terlebih dahulu.");
          }
      }

      if (array_key_exists('grading_id', $arrayData)) {
        $newArrayData['grading'] = !empty($arrayData['grading_id']) ? (\DB::table('m_general')->where('id', $arrayData['grading_id'])->value('value') ?? '') : 'Semua Grading';
      }
      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }
}
