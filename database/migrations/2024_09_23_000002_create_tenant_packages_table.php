<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);

            $table->unsignedBigInteger('package_group_id');
            $table->string('package_group_code',255);

            $table->unsignedBigInteger('tenant_group_id')->default(0);

            $table->text('name')->nullable();
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('type')->default(1);

            $table->longText('feature')->nullable();
            $table->longText('config')->nullable();

            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('locked_data_mode')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_packages');
    }
}
