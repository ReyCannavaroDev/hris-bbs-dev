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
            if (in_array($val, ['active', 'true', '1'])) {
                $model = $model->where('this.is_active', true);
            } elseif (in_array($val, ['inactive', 'false', '0'])) {
                $model = $model->where('this.is_active', false);
            }
        }

        $hariLain = request('filter_is_hari_berikutnya') ?? request('filter_hari_lain');
        if ($hariLain !== null) {
            $val = strtolower(trim((string)$hariLain));
            if (in_array($val, ['ada', 'yes', 'true', '1'])) {
                $model = $model->where('this.is_hari_berikutnya', true);
            } elseif (in_array($val, ['tidak ada', 'no', 'false', '0'])) {
                $model = $model->where('this.is_hari_berikutnya', false);
            }
        }

        $jamKerja = request('filter_jam_kerja') ?? request('jam_kerja');
        if (!empty($jamKerja)) {
            $val = trim((string)$jamKerja);
            $model = $model->where(function ($q) use ($val) {
                if (str_contains($val, '-')) {
                    $parts = explode('-', $val);
                    $start = trim($parts[0]);
                    $end = trim($parts[1]);
                    if ($start !== '' && $end !== '') {
                        $q->whereRaw("m_jam_kerja.waktu_mulai::text ilike ? and m_jam_kerja.waktu_akhir::text ilike ?", ["%$start%", "%$end%"]);
                    } elseif ($start !== '') {
                        $q->whereRaw("m_jam_kerja.waktu_mulai::text ilike ?", ["%$start%"]);
                    } elseif ($end !== '') {
                        $q->whereRaw("m_jam_kerja.waktu_akhir::text ilike ?", ["%$end%"]);
                    }
                } else {
                    $q->whereRaw("(m_jam_kerja.waktu_mulai::text ilike ? or m_jam_kerja.waktu_akhir::text ilike ?)", ["%$val%", "%$val%"]);
                }
            });
        }

        return $model;
    }

}