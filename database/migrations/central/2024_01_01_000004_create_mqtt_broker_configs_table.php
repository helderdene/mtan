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
        Schema::create('mqtt_broker_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host');
            $table->unsignedInteger('port')->default(1883);
            $table->enum('protocol', ['tcp', 'tls', 'ws', 'wss'])->default('tcp');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('client_id')->nullable();
            $table->boolean('clean_session')->default(true);
            $table->unsignedInteger('keep_alive')->default(60);
            $table->unsignedTinyInteger('qos')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('max_connections')->default(1000);
            $table->string('certificate_path', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mqtt_broker_configs');
    }
};
