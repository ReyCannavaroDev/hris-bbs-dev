<?php

namespace App\Models\CustomModels;

class t_pengumuman extends \App\Models\BasicModels\t_pengumuman
{    
    public function __construct()
    {
        parent::__construct();
        $this->fillable    = array_merge($this->fillable, ['m_divisi_id']);
        $this->columns     = array_merge($this->columns, ['m_divisi_id']);
        $this->columnsFull = array_merge($this->columnsFull, ['m_divisi_id:bigint']);
        $this->createable  = array_merge($this->createable, ['m_divisi_id']);
        $this->updateable  = array_merge($this->updateable, ['m_divisi_id']);
        $this->searchable  = array_merge($this->searchable, ['m_divisi_id']);
        $this->joins       = array_merge($this->joins, ['m_divisi.id=t_pengumuman.m_divisi_id']);
    }
    
    public $fileColumns = [ 'thumb' ];

    public function m_divisi() :\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_divisi', 'm_divisi_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        // Di mobile API, filter pengumuman agar karyawan hanya melihat
        // pengumuman untuk divisinya ATAU pengumuman untuk seluruh divisi (m_divisi_id IS NULL)
        if (app()->request->header('Source') === 'mobile') {
            static::addGlobalScope('divisi_audience', function ($builder) {
                $user = auth()->user();
                if ($user && !empty($user->m_kary_id)) {
                    $karyDivisiId = \DB::table('m_kary')->where('id', $user->m_kary_id)->value('m_divisi_id');
                    if ($karyDivisiId) {
                        $builder->where(function ($q) use ($karyDivisiId) {
                            $q->whereNull('t_pengumuman.m_divisi_id')
                              ->orWhere('t_pengumuman.m_divisi_id', $karyDivisiId);
                        });
                    }
                }
            });
        }
    }

    /**
     * Scope untuk memfilter pengumuman berdasarkan divisi tertentu.
     * Jika divisi dipilih, tetap menyertakan pengumuman yang ditujukan ke seluruh divisi (m_divisi_id IS NULL).
     */
    public function scopeFilterDivisi($query)
    {
        $divisiId = app()->request->m_divisi_id ?? app()->request->divisi_id;
        if (!empty($divisiId)) {
            return $query->where(function ($q) use ($divisiId) {
                $q->where('t_pengumuman.m_divisi_id', $divisiId)
                  ->orWhereNull('t_pengumuman.m_divisi_id');
            });
        }
        return $query;
    }

    public function transformRowData( array $row )
    {
        $divisiNama = 'Semua Divisi';
        if (!empty($row['m_divisi_id'])) {
            $nama = \DB::table('m_divisi')->where('id', $row['m_divisi_id'])->value('nama');
            if ($nama) {
                $divisiNama = $nama;
            }
        }

        return array_merge($row, [
            'm_divisi.nama' => $divisiNama,
            'target_divisi' => $divisiNama,
        ]);
    }
}
