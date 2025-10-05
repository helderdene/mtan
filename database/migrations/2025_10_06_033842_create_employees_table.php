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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50)->unique();
            $table->string('custom_id', 50)->unique()->comment('System-generated ID synced to devices');
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('designation', 100)->nullable();
            $table->enum('employee_type', ['full-time', 'part-time', 'contractor', 'intern'])
                  ->default('full-time');
            $table->string('card_number', 50)->unique()->nullable();
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->unsignedBigInteger('reporting_manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reporting_manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->index('employee_code');
            $table->index('custom_id');
            $table->index('is_active');
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
