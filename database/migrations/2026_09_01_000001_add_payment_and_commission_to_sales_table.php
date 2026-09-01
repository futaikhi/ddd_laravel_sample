<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            // Populated by ConfirmSaleHandler after PaymentGatewayInterface::process()
            $table->string('payment_method', 50)->nullable()->after('completed_at');
            $table->string('transaction_id', 100)->nullable()->after('payment_method');

            // Populated by CompleteSaleHandler after CommissionCalculatorInterface::calculate()
            $table->unsignedBigInteger('commission_amount')->nullable()->after('transaction_id');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount');
            $table->string('commission_currency', 10)->nullable()->after('commission_rate');

        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_method',
                'transaction_id',
                'commission_amount',
                'commission_rate',
                'commission_currency',
            ]);
        });
    }
};
