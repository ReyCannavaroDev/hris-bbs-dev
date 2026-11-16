<?php

namespace App\Models\CustomModels;

class m_jam_kerja extends \App\Models\BasicModels\m_jam_kerja
{    
    private $helper;
    public function __construct()
    {
        parent::__construct();
        $this->helper = getCore('Helper');
        $this->fillable = array_merge($this->fillable, ['m_comp_id', 'm_dir_id']);
        $this->createable = array_merge($this->createable, ['m_comp_id', 'm_dir_id']);
        $this->updateable = array_merge($this->updateable, ['m_comp_id', 'm_dir_id']);
        $this->searchable = array_merge($this->searchable, ['m_comp_id', 'm_dir_id']);
        // Batasi joins hanya ke tabel yang pasti ada agar data dengan m_dir_id/last_editor_id NULL tidak tereliminasi oleh INNER JOIN otomatis generator
        $this->joins = [
            "m_comp.id=m_jam_kerja.m_comp_id",
            "m_dir.id=m_jam_kerja.m_dir_id",
            "m_general.id=m_jam_kerja.tipe_jam_kerja_id"
        ];
    }
    
    public function comp() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_comp', 'm_comp_id', 'id');
    }
    
    public $fileColumns    = [ /*file_column*/ ];

    public $createAdditionalData = ["creator_id"=>"auth:id"];
    public $updateAdditionalData = ["last_editor_id"=>"auth:id"];

    public function createBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData = array_merge($arrayData, [
            'kode' => $this->helper->generateNomor('KODE JAM KERJA'),
        ]);

        if (!empty($newArrayData['m_dir_id'])) {
            $dir = \DB::table('m_dir')->where('id', $newArrayData['m_dir_id'])->first();
            if ($dir && !empty($dir->m_comp_id)) {
                $newArrayData['m_comp_id'] = $dir->m_comp_id;
            }
        }

        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }

        return [
            "model"  => $model,
            "data"   => $newArrayData
        ];
    }
    public function transformRowData( array $row )
    {
        if (empty($row['m_dir.nama']) && !empty($row['m_dir_id'])) {
            $row['m_dir.nama'] = \DB::table('m_dir')->where('id', $row['m_dir_id'])->value('nama') ?? '-';
        }
        return $row;
    }

    public function updateBefore( $model, $arrayData, $metaData, $id=null )
    {
        $newArrayData = array_merge($arrayData, []);
        if (!empty($newArrayData['m_dir_id'])) {
            $dir = \DB::table('m_dir')->where('id', $newArrayData['m_dir_id'])->first();
            if ($dir && !empty($dir->m_comp_id)) {
                $newArrayData['m_comp_id'] = $dir->m_comp_id;
            }
        }
        if (empty($newArrayData['m_comp_id'])) {
            $newArrayData['m_comp_id'] = (auth()->check() && !empty(auth()->user()->m_comp_id)) ? auth()->user()->m_comp_id : 1;
        }
        return [
            "model"  => $model,
            "data"   => $newArrayData
        ];
    }

    public function scopeFilter($model)
    {
        $status = request('filter_is_active') ?? request('filter_status');
        if ($status !== null) {
            $val = strtolower(trim((string)$status));
            if (in_array($val, ['inactive', 'inact', 'inac', 'ina', 'in', 'i', 'nonaktif', 'non', 'false', '0'])) {
                $model = $model->where('this.is_active', false);
            } elseif (in_array($val, ['active', 'activ', 'acti', 'act', 'ac', 'a', 'aktif', 'true', '1'])) {
                $model = $model->where('this.is_active', true);
            }
        }

        $hariLain = request('filter_is_hari_berikutnya') ?? request('filter_hari_lain');
        if ($hariLain !== null) {
            $val = strtolower(trim((string)$hariLain));
            if (in_array($val, ['tidak ada', 'tidak', 'tid', 'tdk', 'ti', 'no', 'false', '0'])) {
                $model = $model->where('this.is_hari_berikutnya', false);
            } elseif (in_array($val, ['ada', 'ad', 'a', 'yes', 'true', '1'])) {
                $model = $model->where('this.is_hari_berikutnya', true);
            }
        }

        $jamKerja = request('filter_jam_kerja') ?? request('jam_kerja');
        if (!empty($jamKerja)) {
            $raw = trim((string)$jamKerja);
            $raw = str_replace('.', ':', $raw);
            $raw = preg_replace('/\s*-\s*/', ' - ', $raw);
            if (strpos($raw, '-') !== false) {
                $parts = explode('-', $raw);
                $startRaw = trim($parts[0]);
                $endRaw = trim($parts[1]);
                if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $startRaw, $sm)) {
                    $h = str_pad($sm[1], 2, '0', STR_PAD_LEFT);
                    $i = str_pad($sm[2], 2, '0', STR_PAD_LEFT);
                    $model = $model->where('this.waktu_mulai', "{$h}:{$i}:00");
                } elseif (preg_match('/^(\d{1,2})$/', $startRaw, $sm)) {
                    $h = str_pad($sm[1], 2, '0', STR_PAD_LEFT);
                    $model = $model->where('this.waktu_mulai', '>=', "{$h}:00:00")->where('this.waktu_mulai', '<=', "{$h}:59:59");
                }
                if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $endRaw, $em)) {
                    $h = str_pad($em[1], 2, '0', STR_PAD_LEFT);
                    $i = str_pad($em[2], 2, '0', STR_PAD_LEFT);
                    $model = $model->where('this.waktu_akhir', "{$h}:{$i}:00");
                } elseif (preg_match('/^(\d{1,2})$/', $endRaw, $em)) {
                    $h = str_pad($em[1], 2, '0', STR_PAD_LEFT);
                    $model = $model->where('this.waktu_akhir', '>=', "{$h}:00:00")->where('this.waktu_akhir', '<=', "{$h}:59:59");
                }
            } elseif (preg_match('/^(\d{1,2}):(\d{1,2})$/', $raw, $m)) {
                $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $i = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                if ((int)$m[1] < 12) {
                    $model = $model->where('this.waktu_mulai', "{$h}:{$i}:00");
                } else {
                    $model = $model->where('this.waktu_akhir', "{$h}:{$i}:00");
                }
            } elseif (preg_match('/^(\d{1,2})$/', $raw, $m)) {
                $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                if ((int)$m[1] < 12) {
                    $model = $model->where('this.waktu_mulai', '>=', "{$h}:00:00")->where('this.waktu_mulai', '<=', "{$h}:59:59");
                } else {
                    $model = $model->where('this.waktu_akhir', '>=', "{$h}:00:00")->where('this.waktu_akhir', '<=', "{$h}:59:59");
                }
            }
        }

        return $model;
    }

}