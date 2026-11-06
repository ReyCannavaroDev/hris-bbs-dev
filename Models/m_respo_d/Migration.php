<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class mrespod extends Migration
{
    protected $tableName = "m_respo_d";

    public function up()
    {
        Schema::create($this->tableName, function (Blueprint $table) {
            $table->id()->from(1);
            $table->bigInteger('m_respo_id')->comment('{"fk":"m_respo.id"}')->nullable();
            $table->bigInteger('m_role_id')->comment('{"src":"m_role.id"}')->nullable();
            $table->text('desc')->nullable();
            $table->bigInteger('creator_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->bigInteger('last_editor_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->timestamps();
        });

        table_config($this->tableName, [
            "guarded"       => ["id"],
            "required"      => ["m_respo_id", "m_role_id"],
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

