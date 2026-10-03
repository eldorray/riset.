<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('subscription_until')->nullable();
            $table->boolean('unlimited')->default(false);
            $table->timestamp('unlimited_until')->nullable();
        });
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('credits');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach ([['Hemat', 19000, 600], ['Mahasiswa', 39000, 1500], ['Riset', 79000, 3500]] as [$name, $price, $credits]) {
            DB::table('billing_plans')->insert(compact('name', 'price', 'credits') + ['created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('billing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('billing_plans');
            $table->string('kind');
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('credits');
            $table->string('status')->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('credit_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('billing_requests');
            $table->string('kind');
            $table->string('name');
            $table->unsignedInteger('credits');
            $table->unsignedInteger('remaining');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'expires_at']);
        });
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('status');
            $table->integer('credits')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->string('model')->nullable();
            $table->text('description');
            $table->json('allocations')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_grants');
        Schema::dropIfExists('billing_requests');
        Schema::dropIfExists('billing_plans');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['subscription_until', 'unlimited', 'unlimited_until']));
    }
};
