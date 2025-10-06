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
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('flexible_checkin_start')->nullable()->after('is_overnight');
            $table->time('flexible_checkin_end')->nullable()->after('flexible_checkin_start');
            $table->decimal('core_hours_required', 4, 2)->nullable()->after('flexible_checkin_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['flexible_checkin_start', 'flexible_checkin_end', 'core_hours_required']);
        });
    }
};
