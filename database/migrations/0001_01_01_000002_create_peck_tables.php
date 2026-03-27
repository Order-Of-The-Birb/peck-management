<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("peck_users", function (Blueprint $table) {
            $table->unsignedBigInteger("gaijin_id")->primary();
            $table->string("username")->unique();
            $table->unsignedBigInteger("discord_id")->nullable();
            $table->integer("tz")->nullable();
            $table->enum("status", ["applicant","unverified","member","ex_member","alt"]);
            $table->dateTime("joindate", precision: 0)->nullable();
            $table->unsignedBigInteger("initiator")->nullable();

            $table->foreign("initiator")->references("gaijin_id")->on("officers")->nullOnDelete();
        });
        Schema::create("peck_alts", function (Blueprint $table) {
            $table->unsignedBigInteger("alt_id")->primary();
            $table->unsignedBigInteger("owner_id");

            $table->foreign("alt_id")->references("gaijin_id")->on("peck_users")->cascadeOnDelete();
            $table->foreign("owner_id")->references("gaijin_id")->on("peck_users")->cascadeOnDelete();
        });
        Schema::create("peck_leave_info", function (Blueprint $table) {
            $table->unsignedBigInteger("user_id")->primary();
            $table->string("type")->notnullable();

            $table->foreign("user_id")->references("gaijin_id")->on("peck_users")->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists("peck_users");
        Schema::dropIfExists("peck_alts");
        Schema::dropIfExists("peck_leave_info");
    }
};
