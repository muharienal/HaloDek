<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regu / bidang penerima pengaduan. Empat regu bawaan: IT, Sarana dan Prasarana,
        // Kesekretariatan, Keamanan. Regu lain bisa ditambah Superadmin kapan saja.
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('code', 8)->unique();             // prefix nomor tiket, mis. IT, SP, KS, KM
            $table->string('description', 255)->nullable();
            $table->string('icon', 30)->default('users');
            $table->string('color', 20)->default('emerald');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Setiap Kepala Regu & Staf terikat ke tepat satu regu (karyawan & superadmin: kosong).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('allow_custom')->default(false);       // true = pelapor wajib menyebut kategori sendiri ("Lainnya")
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        if (Schema::hasColumn('users', 'team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }
        Schema::dropIfExists('teams');
    }
};
