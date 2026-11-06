<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->dateTime('transaction_date')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('flow')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('category_id')->nullable();
            $table->boolean('is_included')->default(true);
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('transfer_destination_account_id')->nullable()->constrained('accounts');
            $table->string('fingerprint');
            $table->json('parse_errors')->nullable();
            $table->json('raw');
            $table->timestamps();

            $table->index('batch_id');
            $table->index('fingerprint');
            $table->index(['batch_id', 'is_included']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
