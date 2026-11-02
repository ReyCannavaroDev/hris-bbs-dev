<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class tperhitunganlembur extends Migration
{
    protected $tableName = "t_perhitungan_lembur";
    
    public function up()
    {
        Schema::table($this->tableName, function (Blueprint $table) {
            $table->string('nomor', 50)->nullable();
            $table->bigInteger('m_comp_id')->comment('{"src":"m_comp.id"}')->default(1)->nullable();
            $table->bigInteger('m_dir_id')->comment('{"src":"m_dir.id"}')->nullable();
            $table->bigInteger('m_kary_id')->comment('{"src":"m_kary.id"}')->nullable();
            $table->bigInteger('m_kary_dir_id')->comment('{"src":"m_dir.id"}')->nullable();
            $table->bigInteger('m_kary_divisi_id')->comment('{"src":"m_divisi.id"}')->nullable();
            $table->bigInteger('m_kary_dept_id')->comment('{"src":"m_dept.id"}')->nullable();
            $table->string('periode', 100)->nullable();
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->integer('total_spk_min')->default(0)->nullable();
            $table->integer('total_realisasi_min')->default(0)->nullable();
            $table->integer('total_diakui_min')->default(0)->nullable();
            $table->decimal('total_jam_diakui', 8, 2)->default(0)->nullable();
            $table->decimal('tarif_lembur', 12, 2)->default(0)->nullable();
            $table->decimal('total_lembur', 22, 2)->default(0)->nullable();
            $table->jsonb('detail_lembur')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status', 50)->default('DRAFT')->nullable();
            $table->bigInteger('creator_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->bigInteger('last_editor_id')->comment('{"src":"default_users.id"}')->nullable();
            $table->dropColumn(['samplecolumn']);
        });
    }
}
