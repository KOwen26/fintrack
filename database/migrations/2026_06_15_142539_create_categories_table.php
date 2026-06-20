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
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('type')->default('output');
            $table->string('name');
            $table->decimal('order', 3, 3)->default(0.000);
            $table->boolean('is_fixed_cost')->default(false);
            $table->json('decorations')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('parent_id');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
