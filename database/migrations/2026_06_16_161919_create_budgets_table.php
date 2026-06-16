<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('limit_amount', 15, 2);
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'category_id', 'year', 'month']);
            $table->index(['account_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
