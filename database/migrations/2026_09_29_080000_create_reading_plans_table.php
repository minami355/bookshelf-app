<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            $table->enum('status', ['in_progress', 'completed', 'expired'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'book_id', 'status']);
            $table->index(['status', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};
