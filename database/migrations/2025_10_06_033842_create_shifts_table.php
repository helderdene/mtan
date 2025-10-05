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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->unsignedInteger('grace_period_minutes')->default(15);
            $table->unsignedInteger('early_departure_threshold_minutes')->default(15);
            $table->unsignedInteger('overtime_threshold_minutes')->default(30);
            $table->unsignedInteger('half_day_threshold_minutes')->default(240);
            $table->json('working_days')->comment('[1,2,3,4,5] for Mon-Fri');
            $table->enum('shift_type', ['fixed', 'flexible', 'rotating'])->default('fixed');
            $table->boolean('is_overnight')->default(false)->comment('Shift crosses midnight');
            $table->string('color_code', 7)->default('#3498db');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('shift_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
