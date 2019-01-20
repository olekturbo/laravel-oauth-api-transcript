<?php

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

Route::get('/', 'WelcomeController@index')->name('welcome');

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
    Route::get('/proposals/changestatus/{id}/{status}', 'Voyager\ProposalsController@changeStatus')->name('proposals.changestatus');
    Route::get('/tasks/changestatus/{id}/{status}', 'Voyager\TasksController@changeStatus')->name('tasks.changestatus');
});

Auth::routes();

Route::get('/login', function () {
   return redirect()->route('voyager.dashboard');
});

Route::get('/home', 'HomeController@index')->name('home');
