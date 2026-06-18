<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRESPONDSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('respond', function (Blueprint $table) {
            $table->increments('Respond_ID');
            $table->string('Respond_IP');
            $table->string('Respond_Email')->nullable();
            $table->string('Respond_OtherInfo')->nullable();
            $table->string('Respond_Contact')->nullable();
            $table->string('Respond_Name')->nullable();
            $table->string('Respond_Phone')->nullable();
            $table->string('Respond_Newsletter')->nullable();
            $table->timestamps();
        });
        DB::update("ALTER TABLE respond AUTO_INCREMENT = 225;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('respond');
    }
}
