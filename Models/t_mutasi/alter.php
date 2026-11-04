<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class tmutasi extends Migration
{
    protected $tableName = "t_mutasi";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (!Schema::hasColumn($this->tableName, 'gaji_pokok_lama')) {
                $table->decimal('gaji_pokok_lama', 12, 2)->nullable();
            }
            if (!Schema::hasColumn($this->tableName, 'gaji_pokok_baru')) {
                $table->decimal('gaji_pokok_baru', 12, 2)->nullable();
            }
            if (!Schema::hasColumn($this->tableName, 'gaji_acuan_lama')) {
                $table->decimal('gaji_acuan_lama', 12, 2)->nullable();
            }
            if (!Schema::hasColumn($this->tableName, 'gaji_acuan_baru')) {
                $table->decimal('gaji_acuan_baru', 12, 2)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (Schema::hasColumn($this->tableName, 'gaji_acuan_baru')) {
                $table->dropColumn('gaji_acuan_baru');
            }
            if (Schema::hasColumn($this->tableName, 'gaji_acuan_lama')) {
                $table->dropColumn('gaji_acuan_lama');
            }
            if (Schema::hasColumn($this->tableName, 'gaji_pokok_baru')) {
                $table->dropColumn('gaji_pokok_baru');
            }
            if (Schema::hasColumn($this->tableName, 'gaji_pokok_lama')) {
                $table->dropColumn('gaji_pokok_lama');
            }
        });
    }
}
