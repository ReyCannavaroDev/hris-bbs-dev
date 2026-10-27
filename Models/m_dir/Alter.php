<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class mdir extends Migration
{
    protected $tableName = "m_dir";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (!Schema::hasColumn($this->tableName, 'kode')) {
                $table->string('kode', 50)->nullable()->after('id');
            }
        });
    }
}
