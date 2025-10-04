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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100)->unique()->comment('Unique device identifier from hardware');
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('device_type', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('firmware_version', 50)->nullable();
            $table->unsignedInteger('capacity')->nullable()->comment('Max face templates');
            $table->unsignedInteger('current_count')->default(0);
            $table->boolean('is_entry_device')->default(true);
            $table->boolean('is_exit_device')->default(true);
            $table->string('timezone', 50)->default('UTC');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('device_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
