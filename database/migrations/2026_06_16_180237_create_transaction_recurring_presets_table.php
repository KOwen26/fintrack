<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_recurring_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('type');
            $table->string('frequency');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->date('next_run_date');
            $table->date('recurrence_end_date')->nullable();
            $table->date('last_run_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('account_id');
            $table->index('created_by');
            $table->index('deleted_at');
            $table->index(['next_run_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_recurring_presets');
    }
};
