<?php

use App\Support\CategoryDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - reports.satisfied      : jawaban pelapor "sudah beres?" (true = beres, false = dibuka kembali, null = belum menjawab)
 * - reports.reopened_count : berapa kali pelapor membuka kembali laporan
 * - categories.keywords    : kata kunci untuk kolom "cari jenis masalah" di form lapor
 * - categories.hint        : petunjuk isi deskripsi (placeholder) per kategori
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('satisfied')->nullable()->after('resolved_at');
            $table->unsignedTinyInteger('reopened_count')->default(0)->after('satisfied');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('keywords', 255)->nullable()->after('name');
            $table->string('hint', 255)->nullable()->after('keywords');
        });

        foreach (CategoryDefaults::MAP as $name => [$keywords, $hint]) {
            DB::table('categories')->where('name', $name)->whereNull('keywords')->update(['keywords' => $keywords, 'hint' => $hint]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['keywords', 'hint']));
        Schema::table('reports', fn (Blueprint $t) => $t->dropColumn(['satisfied', 'reopened_count']));
    }
};
