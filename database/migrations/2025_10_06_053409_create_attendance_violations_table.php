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
        Schema::create('attendance_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->onDelete('set null');
            $table->foreignId('daily_summary_id')->nullable()->constrained('daily_attendance_summaries')->onDelete('set null');
            $table->date('violation_date');
            $table->enum('type', ['late_arrival', 'early_departure', 'extended_break', 'missing_checkout']);
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->integer('minutes_deviation')->unsigned();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'acknowledged', 'disputed', 'resolved'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['employee_id', 'violation_date'], 'idx_employee_date');
            $table->index(['violation_date', 'type'], 'idx_date_type');
            $table->index(['status', 'severity'], 'idx_status_severity');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_violations');
    }
};
