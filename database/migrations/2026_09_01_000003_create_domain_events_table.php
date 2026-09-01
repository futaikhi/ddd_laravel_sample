<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('aggregate_id', 26)->index();
            $table->string('aggregate_type', 100)->index();
            $table->string('event_type', 150)->index();
            $table->string('event_name', 100)->index();
            $table->json('event_data');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['event_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
