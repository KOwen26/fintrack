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
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->string('flow');
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('category_id');
            $table->index('created_by');
            $table->index('transfer_id');
            $table->index(['account_id', 'transaction_date']);
            $table->index(['account_id', 'flow']);
            $table->index(['transfer_id', 'flow']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
