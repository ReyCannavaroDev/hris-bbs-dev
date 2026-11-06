<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class mrespo extends Migration
{
    protected $tableName = "m_respo";

    public function up()
    {
        Schema::create($this->tableName, function (Blueprint $table) {
            $table->id()->from(1);
            $table->string('kode', 50)->nullable();
            $table->string('nama', 150);
            $table->bigInteger('m_comp_id')->comment('{"src":"m_comp.id"}')->default(1)->nullable();
            $table->bigInteger('m_dir_id')->comment('{"src":"m_dir.id"}')->nullable();
            $table->bigInteger('m_divisi_id')->comment('{"src":"m_divisi.id"}')->nullable();
            $table->bigInteger('m_dept_id')->comment('{"src":"m_dept.id"}')->nullable();
            $table->text('desc')->nullable();
            $table->boolean('is_active')->default(1);
            $table->bigInteger('creator_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->bigInteger('last_editor_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->timestamps();
        });

        table_config($this->tableName, [
            "guarded"       => ["id"],
            "required"      => ["nama", "is_active"],
            "!createable"   => ["id", "created_at", "updated_at"],
            "!updateable"   => ["id", "created_at", "updated_at"],
            "searchable"    => "all",
            "deleteable"    => "true",
            "deleteOnUse"   => "false",
            "extendable"    => "false",
            "casts"         => [
                'created_at' => 'datetime:d/m/Y H:i',
                'updated_at' => 'datetime:d/m/Y H:i'
            ]
        ]);
    }

    public function down()
    {
        Schema::dropIfExists($this->tableName);
    }
}

