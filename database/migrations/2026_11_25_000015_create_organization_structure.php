<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur organisasi yang diatur Superadmin: Divisi -> Bidang -> Regu, plus daftar Jabatan dan
 * nama/penjelasan Peran. Isian pengguna menjadi pilihan (dropdown), bukan ketik bebas.
 *
 * Kolom teks users.department / users.position tetap ada sebagai salinan nama (dipakai tampilan,
 * ekspor, dan API karyawan untuk Inventaris), diisi otomatis dari pilihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) { // "Bidang"
            $table->id();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) { // "Jabatan"
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Nama & penjelasan peran yang bisa diubah (hak aksesnya tetap ditentukan aplikasi).
        Schema::create('role_settings', function (Blueprint $table) {
            $table->string('role', 30)->primary();
            $table->string('label', 50);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        // Data lama (teks bebas) -> daftar pilihan, agar tidak ada yang hilang.
        $now = now();
        foreach (DB::table('users')->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department') as $name) {
            $name = mb_substr(trim($name), 0, 100);
            $id = DB::table('departments')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
                ?? DB::table('departments')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('users')->where('department', $name)->update(['department_id' => $id]);
        }
        foreach (DB::table('users')->whereNotNull('position')->where('position', '!=', '')->distinct()->orderBy('position')->pluck('position') as $name) {
            $name = mb_substr(trim($name), 0, 100);
            $id = DB::table('positions')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
                ?? DB::table('positions')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('users')->where('position', $name)->update(['position_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', fn (Blueprint $t) => $t->dropConstrainedForeignId('department_id'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('position_id');
            $t->dropConstrainedForeignId('department_id');
            $t->dropConstrainedForeignId('division_id');
        });
        Schema::dropIfExists('role_settings');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('divisions');
    }
};
