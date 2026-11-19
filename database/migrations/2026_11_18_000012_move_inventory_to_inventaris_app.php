<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaris pindah ke aplikasi "Inventaris Umum & IT".
 *
 * Laporan sekarang hanya menyimpan ID aset/lokasi di Inventaris (tanpa foreign key)
 * plus salinan nama (snapshot) agar laporan lama tetap terbaca walau Inventaris mati
 * atau barangnya sudah dihapus.
 *
 * Tabel lama (locations, inventory_categories, inventory_items) SENGAJA tidak dihapus:
 * perintah `php artisan inventaris:impor-halodek` di aplikasi Inventaris membacanya
 * untuk memindahkan data (ID barang dan lokasi dipertahankan sehingga cocok dengan asset_id di sini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_id')->nullable()->after('category_id')->index();
            $table->string('asset_code', 60)->nullable()->after('asset_id');
            $table->string('asset_name', 200)->nullable()->after('asset_code');
            $table->unsignedBigInteger('inventory_location_id')->nullable()->after('asset_name')->index();
            $table->string('location_name', 200)->nullable()->after('inventory_location_id');
        });

        if (! Schema::hasTable('inventory_items') || ! Schema::hasTable('locations')) {
            return;
        }

        // Salin data lama ke kolom baru.
        DB::table('reports')->whereNotNull('inventory_item_id')->orWhereNotNull('location_id')
            ->orderBy('id')->select(['id', 'inventory_item_id', 'location_id'])
            ->chunkById(500, function ($rows) {
                $items = DB::table('inventory_items')->whereIn('id', $rows->pluck('inventory_item_id')->filter())->get(['id', 'code', 'name'])->keyBy('id');
                $locs = DB::table('locations')->whereIn('id', $rows->pluck('location_id')->filter())->get(['id', 'name', 'building', 'floor'])->keyBy('id');

                foreach ($rows as $r) {
                    $item = $r->inventory_item_id ? $items->get($r->inventory_item_id) : null;
                    $loc = $r->location_id ? $locs->get($r->location_id) : null;
                    DB::table('reports')->where('id', $r->id)->update([
                        'asset_id' => $item?->id,
                        'asset_code' => $item?->code,
                        'asset_name' => $item?->name,
                        'inventory_location_id' => $loc?->id,
                        'location_name' => $loc ? $loc->name.(($extra = array_filter([$loc->building, $loc->floor ? 'Lt. '.$loc->floor : null])) ? ' ('.implode(', ', $extra).')' : '') : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['asset_id']);
            $table->dropIndex(['inventory_location_id']);
            $table->dropColumn(['asset_id', 'asset_code', 'asset_name', 'inventory_location_id', 'location_name']);
        });
    }
};
