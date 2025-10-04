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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name');
            $table->string('domain')->nullable()->unique();
            $table->string('subdomain', 100)->unique();
            $table->string('database_name', 100);
            $table->string('database_host');
            $table->enum('subscription_plan', ['trial', 'basic', 'professional', 'enterprise'])
                  ->default('trial');
            $table->unsignedInteger('max_employees')->default(50);
            $table->unsignedInteger('max_devices')->default(5);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();

            $table->index('domain');
            $table->index('is_active');
            $table->index(['subscription_plan', 'subscription_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
