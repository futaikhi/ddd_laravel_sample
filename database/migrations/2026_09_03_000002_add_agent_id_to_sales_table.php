<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('agent_id', 26)->nullable()->after('customer_id');
            $table->index('agent_id', 'sales_agent_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_agent_id_idx');
            $table->dropColumn('agent_id');
        });
    }
};
