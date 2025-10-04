<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->date('metric_date');
            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedInteger('active_employees')->default(0);
            $table->unsignedInteger('total_devices')->default(0);
            $table->unsignedInteger('active_devices')->default(0);
            $table->unsignedInteger('attendance_records_count')->default(0);
            $table->decimal('storage_used_mb', 12, 2)->default(0);
            $table->unsignedInteger('api_calls_count')->default(0);
            $table->unsignedInteger('webhook_calls_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'metric_date']);
            $table->index(['tenant_id', 'metric_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_metrics');
    }
};
