<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lokasi/ruangan umum, dipakai pelapor untuk menandai tempat kejadian.
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('building', 100)->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('pic_name', 150)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Inventaris dikelola per regu: tiap regu punya kategori sendiri
        // (mis. IT: Laptop, Printer, Software; Sarpras: AC, Kendaraan, Ruangan).
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['team_id', 'name']);
        });

        // Item inventaris: barang, ruangan, lisensi, atau apa pun yang dicatat sebuah regu.
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('status', 20)->default('tersedia')->index();
            $table->foreignId('holder_id')->nullable()->constrained('users')->nullOnDelete();      // pemegang / penanggung jawab
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('acquired_at')->nullable();   // tanggal perolehan
            $table->date('due_date')->nullable();      // jatuh tempo: garansi / servis berikutnya / masa berlaku lisensi
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'code']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('locations');
    }
};
