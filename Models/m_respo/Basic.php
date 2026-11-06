<?php

namespace App\Models\BasicModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\ModelTrait;

class m_respo extends Model
{   
    use ModelTrait;

    protected $table    = 'm_respo';
    protected $guarded  = ["id"];
    protected $casts    = [
    "created_at"=> "datetime:d\/m\/Y H:i",
    "updated_at"=> "datetime:d\/m\/Y H:i"
	];
    protected $fillable = ["kode","nama","m_comp_id","m_dir_id","m_divisi_id","m_dept_id","desc","is_active","creator_id","last_editor_id"];

    public $columns     = ["id","kode","nama","m_comp_id","m_dir_id","m_divisi_id","m_dept_id","desc","is_active","creator_id","last_editor_id","created_at","updated_at"];
    public $columnsFull = ["id:bigint","kode:string:50","nama:string:150","m_comp_id:bigint","m_dir_id:bigint","m_divisi_id:bigint","m_dept_id:bigint","desc:text","is_active:boolean","creator_id:bigint","last_editor_id:bigint","created_at:datetime","updated_at:datetime"];
    public $rules       = [];
    public $joins       = ["m_comp.id=m_respo.m_comp_id","m_dir.id=m_respo.m_dir_id","m_divisi.id=m_respo.m_divisi_id","m_dept.id=m_respo.m_dept_id","default_users.id=m_respo.creator_id","default_users.id=m_respo.last_editor_id"];
    public $details     = [];
    public $heirs       = ["default_users_respo"];
    public $detailsChild= [];
    public $detailsHeirs= [];
    public $unique      = [];
    public $required    = ["nama","is_active"];
    public $createable  = ["kode","nama","m_comp_id","m_dir_id","m_divisi_id","m_dept_id","desc","is_active","creator_id","last_editor_id"];
    public $updateable  = ["kode","nama","m_comp_id","m_dir_id","m_divisi_id","m_dept_id","desc","is_active","creator_id","last_editor_id"];
    public $searchable  = ["id","kode","nama","m_comp_id","m_dir_id","m_divisi_id","m_dept_id","desc","is_active","creator_id","last_editor_id","created_at","updated_at"];
    public $deleteable  = true;
    public $cascade     = true;
    public $deleteOnUse = false;

    
    
    
    public function m_comp() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_comp', 'm_comp_id', 'id');
    }
    public function m_dir() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dir', 'm_dir_id', 'id');
    }
    public function m_divisi() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_divisi', 'm_divisi_id', 'id');
    }
    public function m_dept() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\m_dept', 'm_dept_id', 'id');
    }
    public function creator() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\default_users', 'creator_id', 'id');
    }
    public function last_editor() :\BelongsTo
    {
        return $this->belongsTo('App\Models\BasicModels\default_users', 'last_editor_id', 'id');
    }
}
