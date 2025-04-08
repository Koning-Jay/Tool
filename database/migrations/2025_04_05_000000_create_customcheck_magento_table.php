<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customcheck_magento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customchecks_id')->constrained('customchecks')->cascadeOnDelete();
            $table->foreignId('magento_id')->constrained('magentos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customcheck_magento');
    }
};
