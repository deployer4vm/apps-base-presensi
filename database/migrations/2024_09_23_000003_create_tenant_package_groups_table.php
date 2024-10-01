<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantPackageGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenant_package_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);

            $table->string('code')->default('');
            $table->text('name')->nullable();
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('single_package')->default(0);
            $table->unsignedTinyInteger('primary_group')->default(0);
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
        Schema::dropIfExists('tenant_package_groups');
    }
}
