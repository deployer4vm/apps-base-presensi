<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTenantIdColumnToConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('config', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(0)->comment('tenant id dan workshop id sama jadi bisa merelasi ke keduanya, 0 : berarti termasuk disemua tenant atau bukan multitenant')->after('id');
            
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('config', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
}
