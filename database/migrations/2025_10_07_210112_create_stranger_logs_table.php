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
        Schema::create('stranger_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->foreignId('matched_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('detected_at');
            $table->string('photo_path');
            $table->enum('match_status', ['unreviewed', 'matched', 'security_issue'])->default('unreviewed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'detected_at']);
            $table->index('match_status');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stranger_logs');
    }
};
