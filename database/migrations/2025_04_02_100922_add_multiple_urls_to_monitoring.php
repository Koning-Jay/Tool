<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magentos', function (Blueprint $table) {
            $table->string('secondary_url')->nullable()->after('url');
            $table->string('tertiary_url')->nullable()->after('secondary_url');
        });
    }

    public function down(): void
    {
        Schema::table('magentos', function (Blueprint $table) {
            $table->dropColumn(['secondary_url', 'tertiary_url']);
        });
    }
};