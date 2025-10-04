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
        Schema::table('devices', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('devices', 'device_type')) {
                $table->string('device_type', 50)->after('location')->default('facial_recognition');
            }
            if (!Schema::hasColumn('devices', 'mac_address')) {
                $table->string('mac_address', 17)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('devices', 'firmware_version')) {
                $table->string('firmware_version', 50)->nullable()->after('mac_address');
            }
            if (!Schema::hasColumn('devices', 'current_count')) {
                $table->unsignedInteger('current_count')->default(0)->after('capacity');
            }
            if (!Schema::hasColumn('devices', 'is_entry_device')) {
                $table->boolean('is_entry_device')->default(true)->after('current_count');
            }
            if (!Schema::hasColumn('devices', 'is_exit_device')) {
                $table->boolean('is_exit_device')->default(true)->after('is_entry_device');
            }
            if (!Schema::hasColumn('devices', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('is_exit_device');
            }
            if (!Schema::hasColumn('devices', 'settings')) {
                $table->json('settings')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('devices', 'last_sync_at')) {
                $table->timestamp('last_sync_at')->nullable()->after('settings');
            }
            if (!Schema::hasColumn('devices', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'mac_address',
                'firmware_version',
                'current_count',
                'is_entry_device',
                'is_exit_device',
                'timezone',
                'settings',
                'last_sync_at',
                'deleted_at',
            ]);
        });
    }
};
