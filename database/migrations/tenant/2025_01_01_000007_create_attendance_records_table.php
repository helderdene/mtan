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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->timestamp('recorded_at');
            $table->enum('direction', ['check-in', 'check-out', 'break-start', 'break-end'])->default('check-in');
            $table->decimal('recognition_score', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->timestamps();

            $table->index(['employee_id', 'recorded_at']);
            $table->index(['device_id', 'recorded_at']);
            $table->index('recorded_at');
            $table->index('direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
