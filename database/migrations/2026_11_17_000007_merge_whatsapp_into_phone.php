<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu nomor saja: kolom `phone` jadi nomor WhatsApp (format 62xxxxxxxxxx).
 * Isi kolom `whatsapp` (bila ada) dipindah ke `phone`, nomor lama dirapikan, lalu kolom `whatsapp` dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasWa = Schema::hasColumn('users', 'whatsapp');

        DB::table('users')->orderBy('id')->each(function ($u) use ($hasWa) {
            $raw = ($hasWa && $u->whatsapp) ? $u->whatsapp : $u->phone;
            if ($raw === null || trim((string) $raw) === '') {
                return;
            }
            $new = Phone::normalize($raw) ?? $u->phone;   // tidak valid -> biarkan apa adanya
            if ($new !== $u->phone) {
                DB::table('users')->where('id', $u->id)->update(['phone' => $new]);
            }
        });

        if ($hasWa) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('whatsapp'));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('whatsapp', 20)->nullable()->after('phone');
        });
    }
};
