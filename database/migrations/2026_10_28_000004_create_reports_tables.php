<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 40)->unique();        // mis. IT-20261028-0001
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name');                         // snapshot nama pelapor
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('custom_category', 150)->nullable();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('priority', 20)->default('sedang');
            $table->string('status', 20)->default('menunggu');
            $table->text('follow_up_note')->nullable();             // catatan/hasil tindak lanjut terakhir
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('assigned_to_id');
            $table->index('created_at');
        });

        // Linimasa + percakapan tiket (status, penugasan, komentar, catatan internal, dst.)
        Schema::create('report_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);                 // created|status|assigned|claimed|released|priority|transferred|comment|cancelled
            $table->text('message')->nullable();
            $table->boolean('is_internal')->default(false); // true = hanya terlihat oleh admin
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['report_id', 'created_at']);
        });

        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20)->default('bukti'); // bukti|penyelesaian|komentar
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
        Schema::dropIfExists('report_activities');
        Schema::dropIfExists('reports');
    }
};
