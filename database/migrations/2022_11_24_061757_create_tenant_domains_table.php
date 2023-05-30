<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTenantDomainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);

            $table->string('domain',255);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('redirect')->nullable();

            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('domain');
            $table->index('status');
        });
        
        // migrate existing DB Domain
        $tenantList = DB::table('tenants')->get();
        foreach ($tenantList as $value) {
            DB::table('tenant_domains')->insert([
                'created_at'=>now(),
                'tenant_id'=>$value->id,
                'domain'=>$value->domain,
                'status'=>1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_domains');
    }
}
