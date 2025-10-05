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
        Schema::create('daily_attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete()
                ->comment('Employee this summary belongs to');
            $table->date('date')
                ->comment('Date of attendance (single day)');
            $table->time('first_check_in')->nullable()
                ->comment('First check-in time of the day');
            $table->time('last_check_out')->nullable()
                ->comment('Last check-out time of the day');
            $table->integer('total_work_minutes')->default(0)
                ->comment('Total work time in minutes (excluding breaks)');
            $table->integer('total_break_minutes')->default(0)
                ->comment('Total break time in minutes');
            $table->integer('overtime_minutes')->default(0)
                ->comment('Overtime work in minutes');
            $table->enum('status', ['present', 'absent', 'half-day', 'on-leave', 'holiday'])
                ->comment('Daily attendance status');
            $table->boolean('is_complete')->default(false)
                ->comment('True when employee has checked out (day is finalized)');
            $table->timestamps();

            // Indexes for efficient querying
            $table->unique(['employee_id', 'date'], 'uq_employee_date');
            $table->index('date', 'idx_date');
            $table->index(['date', 'status'], 'idx_date_status');
            $table->index(['employee_id', 'status'], 'idx_employee_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_summaries');
    }
};
