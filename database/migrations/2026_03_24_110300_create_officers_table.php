<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('officers', function (Blueprint $table) {
			$table->integer("gaijin_id")->primary();
			$table->string("rank")->nullable();

			$table->foreign("gaijin_id")->references("gaijin_id")->on("peck_users")->cascadeOnDelete();
		});
	}	
	public function down(): void
	{
		Schema::dropIfExists("officers");
	}
}
?>