<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Template catatan cepat per regu untuk popup Selesaikan / Tolak. Kosong = pakai bawaan dari config. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('note_templates')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('teams', fn (Blueprint $t) => $t->dropColumn('note_templates'));
    }
};
