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
            $table->string('device_id', 100)->unique();
            $table->string('name', 100);
            $table->string('location', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('capacity')->default(3000);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('device_id');
            $table->index('is_active');
            $table->index('last_heartbeat_at');
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
