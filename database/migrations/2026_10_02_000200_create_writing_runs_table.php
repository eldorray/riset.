<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('status')->default('queued');
            $table->json('payload');
            $table->json('results');
            $table->unsignedInteger('cursor')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->boolean('stop_requested')->default(false);
            $table->boolean('has_suggestions')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreignId('writing_run_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('writing_run_id'));
        Schema::dropIfExists('writing_runs');
    }
};
