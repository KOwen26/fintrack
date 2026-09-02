<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('access_type');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->json('decorations')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('owner_id');
            $table->index('archived_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
