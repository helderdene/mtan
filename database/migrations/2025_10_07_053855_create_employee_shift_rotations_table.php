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
        Schema::create('employee_shift_rotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('rotation_pattern_id')->constrained('shift_rotation_patterns')->onDelete('restrict');
            $table->date('start_date');
            $table->integer('current_position')->default(0)->comment('Current index in rotation sequence');
            $table->date('last_rotated_at')->nullable()->comment('Last rotation date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_shift_rotations');
    }
};
