<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the new table to hold supplementary user data
        Schema::create('peck_user_data', function (Blueprint $table) {
            $table->unsignedBigInteger('discord_id')->primary();
            $table->boolean('sqb_part')->nullable()->default(null);
            $table->integer('timezone')->nullable()->default(null);
        });

        // 2. Copy existing discord_id and tz values into peck_user_data (de-duplicate on discord_id)
        DB::statement('INSERT INTO peck_user_data (discord_id, timezone) SELECT discord_id, MAX(tz) FROM peck_users WHERE discord_id IS NOT NULL GROUP BY discord_id');

        // 3. Add foreign key on peck_users referencing peck_user_data
        Schema::table('peck_users', function (Blueprint $table) {
            $table->foreign('discord_id')->references('discord_id')->on('peck_user_data')->cascadeOnDelete();
        });

        // 4. Drop the now-redundant tz column from peck_users
        Schema::table('peck_users', function (Blueprint $table) {
            $table->dropColumn('tz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add the tz column to peck_users
        Schema::table('peck_users', function (Blueprint $table) {
            $table->integer('tz')->nullable();
        });

        // 2. Restore tz values from peck_user_data
        DB::statement('UPDATE peck_users SET tz = (SELECT timezone FROM peck_user_data WHERE peck_user_data.discord_id = peck_users.discord_id)');

        // 3. Drop the foreign key before dropping the referenced table
        Schema::table('peck_users', function (Blueprint $table) {
            $table->dropForeign(['discord_id']);
        });

        // 4. Drop the peck_user_data table
        Schema::dropIfExists('peck_user_data');
    }
};
