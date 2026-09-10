<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'invoice_number')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->string('invoice_number', 32)->nullable()->after('id');
                $table->unique('invoice_number', 'sales_invoice_number_unique');
            });
        }

        if (Schema::hasTable('sale_list_items') && ! Schema::hasColumn('sale_list_items', 'invoice_number')) {
            Schema::table('sale_list_items', function (Blueprint $table): void {
                $table->string('invoice_number', 32)->nullable()->after('id');
                $table->index('invoice_number', 'sale_list_items_invoice_number_idx');
            });
        } elseif (! Schema::hasTable('sale_list_items')) {
            // sale_list_items table will be created by earlier migration; ensure the
            // column exists there when both migrations are re-run on a fresh DB.
        }

        if (! Schema::hasTable('sale_invoice_sequences')) {
            Schema::create('sale_invoice_sequences', function (Blueprint $table): void {
                $table->string('sequence_date', 10)->primary();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_list_items') && Schema::hasColumn('sale_list_items', 'invoice_number')) {
            Schema::table('sale_list_items', function (Blueprint $table): void {
                $table->dropIndex('sale_list_items_invoice_number_idx');
                $table->dropColumn('invoice_number');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'invoice_number')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropUnique('sales_invoice_number_unique');
                $table->dropColumn('invoice_number');
            });
        }

        Schema::dropIfExists('sale_invoice_sequences');
    }
};
