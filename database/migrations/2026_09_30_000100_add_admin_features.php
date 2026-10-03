<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Login manual: akun dibuat admin. Akun Google tetap tanpa password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });

        // Pengaturan yang diubah admin (gaya sitasi aktif, struktur jenis tulisan).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        // Template format Word per institusi, diatur admin.
        Schema::create('docx_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('font_family', 64)->default('Times New Roman');
            $table->unsignedTinyInteger('font_size')->default(12);
            $table->decimal('line_spacing', 3, 2)->default(1.5);
            $table->decimal('margin_top', 4, 2)->default(3);
            $table->decimal('margin_bottom', 4, 2)->default(3);
            $table->decimal('margin_left', 4, 2)->default(4);
            $table->decimal('margin_right', 4, 2)->default(3);
            $table->decimal('first_line_indent', 4, 2)->default(1.25);
            $table->boolean('page_numbers')->default(true);
            $table->boolean('title_page')->default(true);
            $table->text('title_page_text')->nullable();
            $table->boolean('table_of_contents')->default(false);
            $table->boolean('chapter_uppercase')->default(true);
            $table->boolean('chapter_page_break')->default(true);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('docx_template_id')->nullable()->after('citation_style')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docx_template_id');
        });
        Schema::dropIfExists('docx_templates');
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
