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
        Schema::create('shift_rotation_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('cycle_type', ['weekly', 'bi_weekly', 'monthly']);
            $table->json('rotation_sequence')->comment('Array of shift_ids in rotation order');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_rotation_patterns');
    }
};
