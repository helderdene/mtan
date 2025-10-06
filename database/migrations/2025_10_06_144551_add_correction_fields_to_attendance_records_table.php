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
            $table->boolean('is_manual_correction')->default(false)->after('direction');
            $table->foreignId('correction_id')->nullable()->after('is_manual_correction')->constrained('attendance_corrections')->onDelete('set null');
            $table->index('is_manual_correction', 'idx_manual_correction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_manual_correction');
            $table->dropForeign(['correction_id']);
            $table->dropColumn(['is_manual_correction', 'correction_id']);
        });
    }
};
