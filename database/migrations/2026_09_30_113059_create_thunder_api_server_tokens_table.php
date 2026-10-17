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
        Schema::create('thunderapi_server_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->unsignedBigInteger('gaijin_id')->nullable();
            $table->unsignedBigInteger('expires_at');
            $table->timestamp('refreshed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thunderapi_server_tokens');
    }
};
