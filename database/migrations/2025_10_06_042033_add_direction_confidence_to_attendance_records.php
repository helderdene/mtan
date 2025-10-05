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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->integer('confidence_score')->nullable()->after('direction')
                ->comment('Direction detection confidence score (0-100)');
            $table->text('detection_reason')->nullable()->after('confidence_score')
                ->comment('Human-readable explanation of direction detection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['confidence_score', 'detection_reason']);
        });
    }
};
