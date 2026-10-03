<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Id bagian draf yang ditulis AI dan belum ditinjau pengguna.
        Schema::table('projects', function (Blueprint $table) {
            $table->json('ai_units')->nullable()->after('front_matter');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ai_units');
        });
    }
};
