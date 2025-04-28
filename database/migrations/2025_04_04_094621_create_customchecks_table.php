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
        Schema::dropIfExists('customchecks');
        
        Schema::create('customchecks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('check_type');
            $table->float('threshold_value');
            $table->string('comparison_operator');
            $table->string('api_key')->nullable(); 
            $table->boolean('is_active')->default(true);
            $table->string('alert_severity')->default('medium');
            $table->json('notification_emails')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customchecks');
    }
};