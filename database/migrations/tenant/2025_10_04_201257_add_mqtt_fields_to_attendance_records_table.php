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
            // Add MQTT fields for complete event tracking
            $table->string('record_id', 100)->nullable()->after('id'); // Device-generated unique record ID
            $table->string('person_name', 255)->nullable()->after('employee_id'); // Employee name from device
            $table->string('device_name', 255)->nullable()->after('device_id'); // Device location name
            $table->string('verify_status', 50)->nullable()->after('recognition_score'); // Device verification result
            $table->decimal('temperature', 4, 1)->nullable()->after('verify_status'); // Body temperature (30.0-45.0°C)
            $table->boolean('mask_status')->nullable()->after('temperature'); // Mask detection (0=no mask, 1=has mask)
            $table->string('photo_path', 500)->nullable()->after('mask_status'); // Stored photo reference

            // Add unique index on record_id for deduplication
            $table->unique('record_id');

            // Add indexes for filtering and reporting
            $table->index('verify_status');
            $table->index('temperature');
            $table->index('mask_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['verify_status']);
            $table->dropIndex(['temperature']);
            $table->dropIndex(['mask_status']);
            $table->dropUnique(['record_id']);

            // Drop columns
            $table->dropColumn([
                'record_id',
                'person_name',
                'device_name',
                'verify_status',
                'temperature',
                'mask_status',
                'photo_path',
            ]);
        });
    }
};
