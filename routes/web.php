<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Services\Utilities;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
if (app()->environment('local') && config('app.debug')) {
Route::group(['prefix'=>'system-queue', 'middleware' => 'auth'],function(){
    // listing and manage export
    Route::group(['prefix'=>'export'],function(){
        Route::get('list', 'queue\ExportController@listQueue')->name('system.queue.export.list');
        Route::get('list/history', 'queue\ExportController@historyQueue')->name('system.queue.export.history');
        Route::get('detail/{cacheKey}', 'queue\ExportController@detailQueue')->name('system.queue.export.detail');
        Route::post('detail/{cacheKey}/cancel', 'queue\ExportController@cancelQueue')->name('system.queue.export.cancel');
        Route::delete('detail/{cacheKey}', 'queue\ExportController@deleteQueue')->name('system.queue.export.delete');
    });
    // listing and manage import
    Route::group(['prefix'=>'import'],function(){
        Route::get('list', 'queue\ImportController@listQueue')->name('system.queue.import.list');
        Route::get('list/history', 'queue\ImportController@historyQueue')->name('system.queue.import.history');
        Route::get('detail/{cacheKey}', 'queue\ImportController@detailQueue')->name('system.queue.import.detail');
        Route::post('detail/{cacheKey}/cancel', 'queue\ImportController@cancelQueue')->name('system.queue.import.cancel');
        Route::delete('detail/{cacheKey}', 'queue\ImportController@deleteQueue')->name('system.queue.import.delete');
    });
    // restart scheduller
    Route::post('restart', function(Request $request){
        $return = Utilities::resetSchedulerWorker();
        return $return;
    });
});
}

Route::middleware('auth:api')->group(function () {
    Route::get('/storage/private{any}', 'StorageController@index')->where('any', '.*');
    Route::get('/file/private{any}', 'StorageController@index')->where('any', '.*');
});
Route::get('/storage{any}', 'StorageController@index')->where('any', '.*');
Route::get('/file{any}', 'StorageController@index')->where('any', '.*');

//jika artisan web access aktif, maka buka
if(app()->environment('local') && config('app.debug') && config('AppConfig.system.has_artisan_web_access',false)){
    $artisanEndpoind = config('AppConfig.system.has_artisan_web_access','/update/run-artisan/').'{action}';
    Route::get($artisanEndpoind, function(Request $request){
        $command = $request->route('action');
        $return = Utilities::artisan($command);
        return $return;
    });
}
