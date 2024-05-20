<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHitCountToSystemCallbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_callback', function (Blueprint $table) {
            $table->unsignedInteger('hit_count')->default(0)->after('data');
            $table->unsignedInteger('max_hit_retry')->default(3)->after('hit_count');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_callback', function (Blueprint $table) {
            $table->dropColumn(['hit_count','max_hit_retry']);
        });
    }
}
