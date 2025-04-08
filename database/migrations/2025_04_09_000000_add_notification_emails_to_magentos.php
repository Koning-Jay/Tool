<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magentos', function (Blueprint $table) {
            $table->json('notification_emails')->default(json_encode(['jay@wedigify.nl']))->after('api_key');
        });

        DB::table('magentos')->whereNull('notification_emails')->update([
            'notification_emails' => json_encode(['jay@wedigify.nl'])
        ]);
    }

    public function down(): void
    {
        Schema::table('magentos', function (Blueprint $table) {
            $table->dropColumn('notification_emails');
        });
    }
};
