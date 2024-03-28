<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSaleItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date');
            $table->string('location');
            $table->string('type');
            $table->string('payment');
            $table->string('customerid');
            $table->string('productid');
            $table->string('productid2')->nullable();
            $table->string('productid3')->nullable();
            $table->string('productid4')->nullable();
            $table->string('productid5')->nullable();
            $table->string('productid6')->nullable();
            $table->string('productid7')->nullable();
            $table->string('productid8')->nullable();
            $table->string('productid9')->nullable();
            $table->string('productid10')->nullable();
            $table->string('orderid'); 
            $table->string('count');
            $table->string('count2')->nullable();
            $table->string('count3')->nullable();
            $table->string('count4')->nullable();
            $table->string('count5')->nullable();
            $table->string('count6')->nullable();
            $table->string('count7')->nullable();
            $table->string('count8')->nullable();
            $table->string('count9')->nullable();
            $table->string('count10')->nullable();
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
        Schema::dropIfExists('sale_items');
    }
}
