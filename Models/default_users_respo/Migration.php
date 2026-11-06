<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class defaultusersrespo extends Migration
{
    protected $tableName = "default_users_respo";

    public function up()
    {
        Schema::create($this->tableName, function (Blueprint $table) {
            $table->id()->from(1);
            $table->bigInteger('default_users_id')->comment('{"fk":"default_users.id"}')->nullable();
            $table->bigInteger('m_respo_id')->comment('{"src":"m_respo.id"}')->nullable();
            $table->integer('seq')->nullable();
            $table->boolean('is_primary')->default(0)->nullable();
            $table->boolean('is_active')->default(1)->nullable();
            $table->bigInteger('creator_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->bigInteger('last_editor_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->timestamps();
            $table->bigInteger('deletor_id')->nullable();
            $table->datetime('deleted_at')->nullable();
        });

        table_config($this->tableName, [
            "guarded"       => ["id"],
            "required"      => ["default_users_id", "m_respo_id"],
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

