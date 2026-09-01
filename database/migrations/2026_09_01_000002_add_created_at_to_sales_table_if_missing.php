<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('sales', 'created_at')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'created_at')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
