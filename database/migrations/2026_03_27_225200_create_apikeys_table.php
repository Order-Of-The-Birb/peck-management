<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->foreignId('owner')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('key')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
