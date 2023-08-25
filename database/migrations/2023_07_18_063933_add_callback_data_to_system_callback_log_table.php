<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCallbackDataToSystemCallbackLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_callback_log', function (Blueprint $table) {
            $table->text('callback_url')->after('callback_id');
            $table->mediumText('callback_data')->nullable()->after('callback_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_callback_log', function (Blueprint $table) {
            $table->dropColumn(['callback_url','callback_data']);
        });
    }
}
