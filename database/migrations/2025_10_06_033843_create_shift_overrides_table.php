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
        Schema::create('shift_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('override_date');
            $table->enum('type', ['holiday', 'off-day', 'half-day', 'custom-shift']);
            $table->time('custom_start_time')->nullable();
            $table->time('custom_end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['override_date', 'shift_id']);
            $table->index(['override_date', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_overrides');
    }
};
