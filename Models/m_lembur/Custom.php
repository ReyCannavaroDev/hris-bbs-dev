<?php

namespace App\Models\CustomModels;

class m_lembur extends \App\Models\BasicModels\m_lembur
{    
    public function __construct()
    {
        parent::__construct();
        // Kosongkan $joins agar generator tidak melakukan JOIN otomatis
        // yang dapat menyebabkan ambiguitas kolom (seperti 'kode' atau 'is_active') di PostgreSQL
        // atau mengeliminasi data dengan nilai NULL
        $this->joins = [];
        // Pastikan is_active tidak berada dalam required agar status inactive (0/false)
        // tidak dianggap 'kosong' oleh validator generator
        $this->required = ["besaran"];
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    //public $createAdditionalData = ["creator_id"=>"auth:id"];
    //public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
      $dirId = $arrayData['m_dir_id'] ?? (auth()->user()->m_dir_id ?? null);
      $divisiId = !empty($arrayData['m_divisi_id']) ? $arrayData['m_divisi_id'] : null;
      $deptId = !empty($arrayData['m_dept_id']) ? $arrayData['m_dept_id'] : null;
      $gradingId = !empty($arrayData['grading_id']) ? $arrayData['grading_id'] : null;

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

      if (isset($newArrayData['besaran'])) {
          $newArrayData['besaran'] = is_numeric($newArrayData['besaran'])
              ? (float)$newArrayData['besaran']
              : (float)str_replace(['.', ','], ['', '.'], $newArrayData['besaran']);
      }

      if (array_key_exists('is_active', $newArrayData)) {
          $isActive = filter_var($newArrayData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
          $boolVal = ($isActive === true || in_array($newArrayData['is_active'], [1, '1', 'true', 't'], true));
          $newArrayData['is_active'] = $boolVal ? 1 : 0;
      }

      // Bersihkan kolom relasi atau kolom tampilan yang tidak ada di tabel database
      unset(
        $newArrayData['id'],
        $newArrayData['direktorat'],
        $newArrayData['company'],
        $newArrayData['m_dir'],
        $newArrayData['m_divisi'],
        $newArrayData['m_dept'],
        $newArrayData['creator'],
        $newArrayData['last_editor'],
        $newArrayData['created_at'],
        $newArrayData['updated_at']
      );

      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }

    public function transformRowData( array $row )
    {
        if (empty($row['m_dir.nama']) && !empty($row['m_dir_id'])) {
            $row['m_dir.nama'] = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama') ?? '-';
        }
        if (empty($row['m_divisi.nama']) && !empty($row['m_divisi_id'])) {
            $row['m_divisi.nama'] = \DB::table('m_divisi')->where('id', $row['m_divisi_id'])->value('nama') ?? 'Semua Divisi';
        }
        if (empty($row['m_dept.nama']) && !empty($row['m_dept_id'])) {
            $row['m_dept.nama'] = \DB::table('m_dept')->where('id', $row['m_dept_id'])->value('nama') ?? 'Semua Dept';
        }
        return $row;
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
      $newArrayData = $arrayData;

      if (array_key_exists('grading_id', $arrayData)) {
        $newArrayData['grading'] = !empty($arrayData['grading_id'])
            ? (\DB::table('m_general')->where('id', $arrayData['grading_id'])->value('value') ?? '')
            : 'Semua Grading';
      }

      if (isset($newArrayData['besaran'])) {
          $newArrayData['besaran'] = is_numeric($newArrayData['besaran'])
              ? (float)$newArrayData['besaran']
              : (float)str_replace(['.', ','], ['', '.'], $newArrayData['besaran']);
      }

      if (array_key_exists('is_active', $newArrayData)) {
          $isActive = filter_var($newArrayData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
          $boolVal = ($isActive === true || in_array($newArrayData['is_active'], [1, '1', 'true', 't'], true));
          $newArrayData['is_active'] = $boolVal ? 1 : 0;
      }

      // Bersihkan kolom relasi atau kolom tampilan yang tidak ada di tabel database
      unset(
        $newArrayData['id'],
        $newArrayData['direktorat'],
        $newArrayData['company'],
        $newArrayData['m_dir'],
        $newArrayData['m_divisi'],
        $newArrayData['m_dept'],
        $newArrayData['creator'],
        $newArrayData['last_editor'],
        $newArrayData['created_at'],
        $newArrayData['updated_at']
      );

      return [
          "model"  => $model,
          "data"   => $newArrayData,
      ];
    }
}
