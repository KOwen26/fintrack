<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('access_type');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('credit_card_limit', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->json('cosmetics')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('household_id');
            $table->index('owner_id');
            $table->index('archived_at');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
