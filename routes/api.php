<?php

use Illuminate\Http\Request;

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

Route::post('login', 'API\AuthController@login');

Route::group(['middleware' => 'auth:api', 'prefix' => 'v1'], function() {
   Route::post('upload', 'API\AuthController@upload');
   Route::post('proposals', 'API\ProposalsController@store');
   Route::post('tasks', 'API\TasksController@store');
   Route::get('tasks', 'API\TasksController@index');
   Route::get('tasks/lyrics', 'API\TasksController@lyrics');
   Route::get('task/{id}', 'API\TasksController@show');
   Route::post('task/{id}', 'API\TasksController@update');
   Route::post('task/close/{id}', 'API\TasksController@close');
});
