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
        Schema::table('magentos', function (Blueprint $table) {
            $table->string('health_check_file')->default('healthcheck.php')->after('tertiary_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magentos', function (Blueprint $table) {
            $table->dropColumn('health_check_file');
        });
    }
};