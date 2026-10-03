<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp', 20);
            $table->string('bank', 100);
            $table->string('account_number', 50);
            $table->string('account_holder', 150);
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_settings');
    }
};
