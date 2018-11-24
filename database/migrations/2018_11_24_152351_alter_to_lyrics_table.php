<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterToLyricsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lyrics', function (Blueprint $table) {
            $table->string('flacPath')->nullable()->change();
            $table->string('lyricsPath')->nullable()->change();
            $table->enum('language', ['pl_PL', 'en_US']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lyrics', function (Blueprint $table) {
            //
        });
    }
}
