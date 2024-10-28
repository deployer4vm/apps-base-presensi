<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Base\Traits\MigrateDataTenant;

class CreatePostReferenecesTable extends Migration
{
    use MigrateDataTenant;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (config('AppConfig.system.multitenant.active', false) && $this->tenantMigrateMode() == false) {
            if (!Schema::hasTable('post_references')) {
                Schema::create('post_references', function (Blueprint $table) {
                    $table->string('ref_id');
                    $table->unsignedBigInteger('tenant_id')->default(0);
                    $table->unsignedBigInteger('user_id')->default(0);
                    $table->string('form_id');
                    $table->unsignedTinyInteger('status')->default(0);
                    $table->timestamps();

                    $table->unique('ref_id');

                    $table->index('tenant_id');
                    $table->index('user_id');
                    $table->index('form_id');
                    $table->index('status');
                });
            }
        }

        $this->createPerTenant('post_references', function (Blueprint $table) {
            $table->string('ref_id');
            $table->unsignedBigInteger('tenant_id')->default(0);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('form_id');
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();

            $table->unique('ref_id');

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('form_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_references');
        $this->dropTablePerTenant('post_references');
    }
}
