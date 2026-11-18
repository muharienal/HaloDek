<?php

use App\Support\WorkHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - respond_work_minutes / resolve_work_minutes : durasi dalam MENIT JAM KERJA, dihitung sekali
 *   saat laporan direspons/diselesaikan sehingga rata-rata tetap dihitung cepat di database.
 * - reminded_at : kapan Kepala Regu terakhir diingatkan untuk laporan yang lewat batas respons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedInteger('respond_work_minutes')->nullable()->after('first_response_at');
            $table->unsignedInteger('resolve_work_minutes')->nullable()->after('resolved_at');
            $table->timestamp('reminded_at')->nullable()->after('reopened_count');
        });

        // Isi durasi untuk laporan lama.
        DB::table('reports')->where(fn ($q) => $q->whereNotNull('first_response_at')->orWhereNotNull('resolved_at'))
            ->orderBy('id')->select(['id', 'created_at', 'first_response_at', 'resolved_at'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $created = Carbon::parse($r->created_at);
                    DB::table('reports')->where('id', $r->id)->update([
                        'respond_work_minutes' => $r->first_response_at ? WorkHours::minutesBetween($created, Carbon::parse($r->first_response_at)) : null,
                        'resolve_work_minutes' => $r->resolved_at ? WorkHours::minutesBetween($created, Carbon::parse($r->resolved_at)) : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $t) => $t->dropColumn(['respond_work_minutes', 'resolve_work_minutes', 'reminded_at']));
    }
};
