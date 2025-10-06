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
            $table->enum('report_type', ['attendance', 'violation'])->default('attendance');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->boolean('enabled')->default(true);
            $table->json('filters')->nullable(); // Store report filters as JSON
            $table->timestamps();

            // Indexes
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
