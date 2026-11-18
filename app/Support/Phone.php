<?php

namespace App\Support;

class Phone
{
    /**
     * Ubah nomor ponsel Indonesia dalam format apa pun (0812-3456-7890, +62 812 3456 7890, 812...)
     * menjadi 62812xxxxxxx. Mengembalikan null bila bukan nomor seluler yang masuk akal.
     */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        $d = preg_replace('/^620/', '62', $d);   // "+62 0812..." salah ketik umum
        if (str_starts_with($d, '0')) {
            $d = '62'.substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62'.$d;
        }

        return preg_match('/^628\d{8,11}$/', $d) ? $d : null;
    }

    /** 6281234567890 -> 0812-3456-7890 (tampilan). */
    public static function pretty(?string $n): string
    {
        if (! $n) {
            return '';
        }
        $local = '0'.substr($n, 2);

        return trim(preg_replace('/(\d{4})(?=\d)/', '$1-', $local));
    }
}
