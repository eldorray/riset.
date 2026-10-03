<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ponytail: kerangka & draf disimpan sebagai kolom JSON di proyek (satu aktif per proyek, PRD).
        // Pecah ke tabel sendiri bila perlu riwayat versi.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type', 32);
            $table->string('citation_style', 32)->nullable();
            $table->json('outline')->nullable();
            $table->json('draft')->nullable();
            $table->timestamps();
        });

        Schema::create('project_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('source_url', 2048);
            $table->string('source_name')->nullable();
            $table->string('input_method', 16)->default('manual');
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_references');
        Schema::dropIfExists('projects');
    }
};
