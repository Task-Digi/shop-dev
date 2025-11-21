<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSalesListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_lists', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->nullable();
            $table->string('location')->nullable();
            $table->string('type')->nullable();
            $table->string('payment')->nullable();
            $table->string('customerid')->nullable();
            $table->string('orderid')->nullable(); 
            $table->string('productid')->nullable();
            $table->string('count')->nullable();
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
        Schema::dropIfExists('sales_lists');
    }
}
