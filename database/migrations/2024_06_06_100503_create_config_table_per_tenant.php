<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Base\Traits\MigrateDataTenant;

class CreateConfigTablePerTenant extends Migration
{
    use MigrateDataTenant;
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->createPerTenant('config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->default(0);
            $table->string('name')->default('')->comment('label / nama config nya');
            $table->string('group')->comment('group config nya');
            $table->string('key')->comment('id / key config nya');
            $table->text('value')->comment('isi / value config nya');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('group');
            $table->index('key');
        });
        $this->statementPerTenant("ALTER TABLE config COMMENT = 'config umum'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropTablePerTenant('anggota_group_items');
    }
}
