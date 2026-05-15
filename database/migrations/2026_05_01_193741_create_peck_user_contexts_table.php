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
        Schema::create('peck_user_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('context_id');
            $table->string('type');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->text('comment')->nullable();

            $table->unique(['user_id', 'context_id']);
            $table->index(['user_id', 'type']);
            $table->foreign('user_id')->references('gaijin_id')->on('peck_users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peck_user_contexts');
    }
};
