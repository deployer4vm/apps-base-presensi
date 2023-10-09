<?php

use Illuminate\Support\Facades\Route;
// use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

/**
 * Config
 * -------------------------------------------------
 */

$group = [
    'prefix' => config('AppConfig.system.config_endpoint','/sys/config'),// default : /sys/config
];

Route::group($group,function(){

    //access config
    Route::group(['prefix' => 'access'],function(){
        Route::get('/', 'ConfigController@accessConfig');
        Route::get('/unlock', 'ConfigController@unlockAccess');
        Route::middleware('auth:api')->put('/', 'ConfigController@unlockAccess');
    });
    
    //db config
    Route::get('/', 'ConfigController@readList');
    //create atau update config
    Route::middleware(['auth:api'])->post('/', 'ConfigController@createUpdate');

});

/**
 * Tenant
 * -------------------------------------------------
 */

$group = [
    'prefix' => config('AppConfig.system.multitenant.api_endpoint.tenant','/sys/tenant'),
    // 'middleware' => 'auth:api'
];
Route::group($group,function(){  
    // /api/sys/tenant/active
    Route::get(config('AppConfig.system.multitenant.api_endpoint.tenant_active','/active'),'TenantController@activeTenant');    
    // /api/sys/tenant/group
    Route::get(config('AppConfig.system.multitenant.api_endpoint.tenant_group','/group'),'TenantController@tenantGroupList');
    //----read tenant resource
    //list tenant - /api/sys/tenant
    Route::get('/','TenantController@listTenant');
});

/**
 * languange
 * -------------------------------------------------
 */
// /api/sys/lang
Route::get(config('AppConfig.system.lang_endpoint','/sys/lang'),'LangController@readList');

/**
 * kindeditor
 * -------------------------------------------------
 */
Route::match(['post','get'],config('AppConfig.system.editor_endpoint.upload','/sys/editor/upload'),'KindeditorController@upload')->name('sys.editor.upload');
Route::match(['post','get'],config('AppConfig.system.editor_endpoint.filemanager','/sys/editor/filemanager'),'KindeditorController@filemanager')->name('sys.editor.filemanager');

/**
 * Post reference
 * -------------------------------------------------
 */

$group = [
    'prefix' => config('AppConfig.system.post_ref_endpoint','/sys/postref'),
    'middleware' => 'auth:api'
];
Route::group($group,function(){  
    // register 1 post ref id
    Route::get('/','PostReferenceController@getRef');

});