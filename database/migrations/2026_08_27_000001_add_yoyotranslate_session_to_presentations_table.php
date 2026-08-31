<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('yoyotranslate_session_id')->nullable()->after('embed_token');
            $table->timestamp('yoyotranslate_session_started_at')->nullable()->after('yoyotranslate_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['yoyotranslate_session_id', 'yoyotranslate_session_started_at']);
        });
    }
};
