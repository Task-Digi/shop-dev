<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRESPONSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('respons', function (Blueprint $table) {
            $table->increments('Respons_ID');
            $table->integer('Respons_Respond_ID');
            $table->integer('Respons_Access_ID')->nullable();
            $table->integer('Respons_Customer_ID')->nullable();
            $table->integer('Respons_Source_ID')->nullable();
            $table->integer('Questions_Standard_ID')->nullable();
            $table->integer('Respons_Question_ID');
            $table->string('Respons_Question_Answer',32);
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
        Schema::dropIfExists('respons');
    }
}
