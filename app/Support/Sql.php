<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Sql
{
    /** Ekspresi SQL selisih dua kolom waktu dalam JAM (MariaDB/MySQL dan SQLite untuk tes). */
    public static function hoursBetween(string $from, string $to): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "((julianday($to) - julianday($from)) * 24)"
            : "(TIMESTAMPDIFF(MINUTE, $from, $to) / 60)";
    }
}
