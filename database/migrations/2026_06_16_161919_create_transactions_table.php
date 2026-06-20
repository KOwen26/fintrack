<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('transfer_link_id')->nullable()->constrained('transactions', 'id')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->timestamp('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('category_id');
            $table->index('created_by');
            $table->index(['account_id', 'transaction_date']);
            $table->index('transfer_link_id');
            $table->index(['account_id', 'type', 'transaction_date']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
