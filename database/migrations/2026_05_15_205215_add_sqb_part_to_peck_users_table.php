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
        Schema::table('peck_users', function (Blueprint $table) {
            $table->boolean('sqb_part')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peck_users', function (Blueprint $table) {
            $table->dropColumn('sqb_part');
        });
    }
};
