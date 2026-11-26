<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seragamkan tanda baca: em dash dan en dash di data yang tampil ke pengguna diganti dash biasa "-".
 * Hanya karakter itu yang diganti; isi lain tidak berubah. Kolom yang tidak ada dilewati.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'announcements' => ['title', 'body'],
        'categories' => ['name', 'hint', 'keywords'],
        'departments' => ['name'],
        'divisions' => ['name'],
        'positions' => ['name'],
        'reports' => ['title', 'description', 'follow_up_note', 'custom_category', 'location_name', 'asset_name', 'reporter_name'],
        'report_activities' => ['message'],
        'role_settings' => ['label', 'description'],
        'teams' => ['name', 'description', 'note_templates'],
        'users' => ['name', 'department', 'position'],
        'user_notifications' => ['title', 'message'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                foreach (["\u{2014}", "\u{2013}"] as $dash) {
                    DB::table($table)->where($col, 'like', '%'.$dash.'%')
                        ->update([$col => DB::raw('REPLACE('.DB::getQueryGrammar()->wrap($col).', '.DB::getPdo()->quote($dash).", '-')")]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
