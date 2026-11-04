<?php

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers');
            $table->string('source_filename');
            $table->string('status')->default(ImportBatchStatus::Staging->value);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('created_by');
            $table->index('status');
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
