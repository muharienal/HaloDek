<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lanjutan pembersihan dash panjang: notifikasi dan catatan riwayat buatan sistem masih memuat
 * judul lama. Komentar yang diketik pengguna tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            ['user_notifications', ['title', 'message'], null],
            ['report_activities', ['message'], fn ($q) => $q->where('type', '!=', 'comment')],
        ];
        foreach ($targets as [$table, $columns, $scope]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                foreach (["\u{2014}", "\u{2013}"] as $dash) {
                    $q = DB::table($table)->where($col, 'like', '%'.$dash.'%');
                    if ($scope) {
                        $scope($q);
                    }
                    $q->update([$col => DB::raw('REPLACE('.$col.', '.DB::getPdo()->quote($dash).", '-')")]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
