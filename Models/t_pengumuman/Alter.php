<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class tpengumuman extends Migration
{
    protected $tableName = "t_pengumuman";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (!Schema::hasColumn($this->tableName, 'm_divisi_id')) {
                $table->bigInteger('m_divisi_id')->comment('{"src":"m_divisi.id"}')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (Schema::hasColumn($this->tableName, 'm_divisi_id')) {
                $table->dropColumn('m_divisi_id');
            }
        });
    }
}
