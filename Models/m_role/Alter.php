<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class mrole extends Migration
{
    protected $tableName = "m_role";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            //$table->string('_existColumnName_')->change();
            //$table->string('_columnName_');
            //$table->dropColumn([ ]);
            //$table->bigInteger('m_dir_id')->default(1)->comment('{"src":"m_dir.id"}')->change();
            $table->bigInteger('m_comp_id')->comment('{"src":"m_comp.id"}')->nullable()->change();
            if (!Schema::hasColumn($this->tableName, 'can_view_salary')) {
                $table->boolean('can_view_salary')->default(false);
            }
        });
    }

    public function down()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            if (Schema::hasColumn($this->tableName, 'can_view_salary')) {
                $table->dropColumn('can_view_salary');
            }
        });
    }
}
