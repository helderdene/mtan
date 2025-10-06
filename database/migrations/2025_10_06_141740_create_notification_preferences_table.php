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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type')->nullable(); // 'violation_immediate', 'violation_digest' (for violation notifications)
            $table->enum('report_type', ['attendance', 'violation'])->nullable(); // For scheduled reports
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->nullable(); // For scheduled reports
            $table->json('filters')->nullable(); // Report filters or violation filters
            $table->json('settings')->nullable(); // Additional settings (severity filter, timing, etc.)
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // Indexes for both violation notifications and scheduled reports
            $table->index(['user_id', 'notification_type']);
            $table->index(['user_id', 'enabled']);
            $table->index(['frequency', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
