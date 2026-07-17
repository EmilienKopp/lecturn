<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Timestamped before create_presentations_view so the column exists when
 * the view SQL (which selects it) replays on a fresh database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('embed_token', 40)->nullable()->unique()->after('content');
        });

        DB::table('presentations')->whereNull('embed_token')->pluck('id')->each(function (int $id) {
            DB::table('presentations')->where('id', $id)->update(['embed_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('embed_token');
        });
    }
};
