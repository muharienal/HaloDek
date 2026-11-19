<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan istilah pada pengumuman contoh bawaan ("pengaduan" -> "laporan").
 * Hanya mengubah pengumuman yang isinya PERSIS teks bawaan lama; pengumuman yang sudah
 * Anda ubah atau buat sendiri tidak disentuh.
 */
return new class extends Migration
{
    private const OLD = "Mulai sekarang seluruh pengaduan IT, sarana prasarana, dan keselamatan kerja disampaikan lewat HaloDek.\nPilih regu yang sesuai, jelaskan masalahnya, dan pantau tindak lanjutnya langsung dari aplikasi.";

    private const NEW = "Mulai sekarang seluruh laporan IT, sarana prasarana, dan keselamatan kerja disampaikan lewat HaloDek.\nKetik masalahnya atau pilih regu, jelaskan singkat, lalu pantau penanganannya langsung dari aplikasi.";

    public function up(): void
    {
        foreach (["\n", "\r\n"] as $eol) {
            DB::table('announcements')->where('body', str_replace("\n", $eol, self::OLD))->update(['body' => str_replace("\n", $eol, self::NEW)]);
        }
    }

    public function down(): void
    {
        DB::table('announcements')->where('body', self::NEW)->update(['body' => self::OLD]);
    }
};
