<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('customer_id', 26);
            $table->string('status');
            $table->unsignedBigInteger('total_amount');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('sale_line_items', function (Blueprint $table) {
            $table->id();
            $table->string('sale_id', 26);
            $table->string('product_id', 26);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->string('currency', 10)->default('IDR');

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_line_items');
        Schema::dropIfExists('sales');
    }
};
