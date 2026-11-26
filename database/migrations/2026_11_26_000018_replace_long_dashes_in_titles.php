<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Judul otomatis lama memakai dash panjang (em dash); samakan dengan dash biasa. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasColumn('reports', 'title')) {
            return;
        }
        foreach (["\u{2014}", "\u{2013}"] as $dash) {
            DB::table('reports')->where('title', 'like', '%'.$dash.'%')
                ->update(['title' => DB::raw('REPLACE(title, '.DB::getPdo()->quote($dash).", '-')")]);
        }
    }

    public function down(): void
    {
    }
};
