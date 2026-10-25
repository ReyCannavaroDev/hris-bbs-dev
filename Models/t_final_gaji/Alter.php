<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class tfinalgaji extends Migration
{
    protected $tableName = "t_final_gaji";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (!Schema::hasColumn($this->tableName, 'm_dir_id')) {
                $table->bigInteger('m_dir_id')->comment('{"src":"m_dir.id"}')->nullable();
            }
        });
    }
}
