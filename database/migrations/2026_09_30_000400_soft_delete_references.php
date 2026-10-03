<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus langsung tanpa konfirmasi, dengan "Batalkan": referensi dihapus lunak dan teks
        // bagian draf sebelum sitasinya dibuang disimpan agar bisa dikembalikan.
        Schema::table('project_references', function (Blueprint $table) {
            $table->json('removed_citations')->nullable()->after('notes');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('project_references', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('removed_citations');
        });
    }
};
