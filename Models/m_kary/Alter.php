<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class mkary extends Migration
{
    protected $tableName = "m_kary";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (!Schema::hasColumn($this->tableName, 'can_outscope')) {
                $table->boolean('can_outscope')->default(false);
            }
            if (!Schema::hasColumn($this->tableName, 'gaji_pokok')) {
                $table->decimal('gaji_pokok', 12, 2)->nullable();
            }
            if (!Schema::hasColumn($this->tableName, 'gaji_acuan')) {
                $table->decimal('gaji_acuan', 12, 2)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (Schema::hasColumn($this->tableName, 'gaji_acuan')) {
                $table->dropColumn('gaji_acuan');
            }
            if (Schema::hasColumn($this->tableName, 'gaji_pokok')) {
                $table->dropColumn('gaji_pokok');
            }
        });
    }
}
