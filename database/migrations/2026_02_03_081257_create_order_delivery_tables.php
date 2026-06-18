<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderDeliveryTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add ean_code to products table
        if (!Schema::hasColumn('products', 'ean_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('ean_code')->nullable()->after('product_id');
            });
        }

        // Create ORDERItem table
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id');
            $table->date('order_date')->nullable();
            $table->string('ordered_by')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->string('status')->nullable();
            $table->string('your_reference')->nullable();
            $table->string('sku')->nullable(); // Product ID - Keep valid string length, default 255 is safe
            $table->string('item_name')->nullable();
            $table->integer('packaging_quantity')->default(1);
            $table->string('packaging_unit')->nullable(); // STK, SPA, BOX
            $table->integer('ordered_quantity')->default(0);
            $table->integer('delivered')->default(0); // Supplier's count
            $table->integer('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // Create ORDERRecord table
        Schema::create('order_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id')->unique();
            $table->date('order_date')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->dateTime('delivery_handling_date')->nullable();
            $table->string('status')->default('Started'); // Started, Completed, Done with ERR
            $table->text('note')->nullable();
            $table->string('staff')->nullable();
            $table->timestamps();
        });

        // Create ORDERScan table
        Schema::create('order_scans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id');
            $table->dateTime('scan_date_time');
            $table->string('ean_code');
            $table->integer('units')->default(1);
            $table->boolean('deactivated')->default(false);
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
        if (Schema::hasColumn('products', 'ean_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('ean_code');
            });
        }
        Schema::dropIfExists('order_scans');
        Schema::dropIfExists('order_records');
        Schema::dropIfExists('order_items');
    }
}
