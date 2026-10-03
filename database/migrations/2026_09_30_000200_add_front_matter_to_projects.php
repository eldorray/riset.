<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bagian awal naskah (abstrak, abstract, kata pengantar) per proyek.
        Schema::table('projects', function (Blueprint $table) {
            $table->json('front_matter')->nullable()->after('draft');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('front_matter');
        });
    }
};
