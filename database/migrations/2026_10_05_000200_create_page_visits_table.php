<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Analitik internal: jumlah kunjungan halaman per hari, perangkat, dan pengguna (0 = tamu).
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('device', 10);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->boolean('pwa')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->unique(['date', 'device', 'user_id', 'pwa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
