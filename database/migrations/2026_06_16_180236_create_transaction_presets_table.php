<?php

use App\Enums\TransactionPresetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('default_source_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default(TransactionPresetType::Expense->value);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->string('default_description')->nullable();
            $table->decimal('default_transfer_fee', 15, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_presets');
    }
};
