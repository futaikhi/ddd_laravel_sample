<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_list_items', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('customer_id', 26);
            $table->string('customer_name')->nullable();
            $table->string('status', 32);
            $table->unsignedBigInteger('total_amount');
            $table->string('currency', 10)->default('IDR');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('projected_at')->nullable();

            $table->index(['status', 'created_at'], 'sale_list_items_status_created_at_idx');
            $table->index(['customer_id', 'created_at'], 'sale_list_items_customer_created_at_idx');
            $table->index(['created_at', 'id'], 'sale_list_items_created_at_id_idx');
            $table->index('projected_at', 'sale_list_items_projected_at_idx');
        });

        Schema::create('sales_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedBigInteger('revenue_total')->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->timestamp('projected_at')->nullable();

            $table->unique('report_date', 'sales_reports_report_date_unique');
            $table->index(['report_date', 'currency'], 'sales_reports_date_currency_idx');
            $table->index('projected_at', 'sales_reports_projected_at_idx');
        });

        Schema::create('commission_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('agent_id', 26)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('completed_sales_count')->default(0);
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->timestamp('projected_at')->nullable();

            $table->unique(['agent_id', 'period_start', 'period_end'], 'commission_reports_agent_period_unique');
            $table->index(['period_start', 'period_end'], 'commission_reports_period_idx');
            $table->index(['agent_id', 'period_start'], 'commission_reports_agent_period_start_idx');
            $table->index('projected_at', 'commission_reports_projected_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_reports');
        Schema::dropIfExists('sales_reports');
        Schema::dropIfExists('sale_list_items');
    }
};
