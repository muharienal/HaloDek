<?php

namespace App\Support;

/** Format angka gaya Indonesia (koma desimal, titik ribuan). */
class Num
{
    /** 51.7 -> "51,7"; 12.0 -> "12"; null -> "-". */
    public static function dec(?float $v, int $decimals = 1): string
    {
        if ($v === null) {
            return '-';
        }

        return $v == (int) $v ? number_format($v, 0, ',', '.') : number_format($v, $decimals, ',', '.');
    }

    /** Jam kerja: "51,7 jam kerja" atau "-". */
    public static function workHours(?float $v): string
    {
        return $v === null ? '-' : self::dec($v).' jam kerja';
    }
}
