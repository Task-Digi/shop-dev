<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('access', function (Blueprint $table) {
            $table->increments('Access_ID');
            $table->string('Access_Code',32);
            $table->integer('Access_Active');
            $table->integer('Access_Customer_ID');
            $table->integer('Access_Standard_ID');
            $table->integer('Access_Source_ID');
        });
        DB::update("ALTER TABLE access AUTO_INCREMENT = 951;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access');
    }
}
