<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Banding laporan yang ditolak: sekali per laporan, dalam batas hari setelah ditolak. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('resolved_at');
            $table->timestamp('appealed_at')->nullable()->after('rejected_at');
        });

        // Laporan yang sudah ditolak sebelum fitur ini: anggap ditolak pada perubahan terakhirnya.
        DB::table('reports')->where('status', 'ditolak')->update(['rejected_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'appealed_at']);
        });
    }
};
