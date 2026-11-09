<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class presensiabsensi extends Migration
{
    protected $tableName = "presensi_absensi";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            //$table->string('_existColumnName_')->change();
            //$table->string('_columnName_');
            if (Schema::hasColumn($this->tableName, 'catatan')) {
                $table->dropColumn(['catatan']);
            }
            if (Schema::hasColumn($this->tableName, 'checkin_foto')) {
                $table->string('checkin_foto')->nullable()->change();
            }
            if (Schema::hasColumn($this->tableName, 'checkout_foto')) {
                $table->string('checkout_foto')->nullable()->change();
            }
            // $table->text('catatan')->nullable();
            // $table->text('catatan_out')->nullable();
        });
    }
}
