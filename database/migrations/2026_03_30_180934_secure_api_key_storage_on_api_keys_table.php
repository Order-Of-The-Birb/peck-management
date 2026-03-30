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
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->string('key_prefix', 12)->nullable()->after('key');
        });

        $apiKeys = DB::table('api_keys')
            ->select(['owner', 'key'])
            ->get();

        foreach ($apiKeys as $apiKey) {
            $existingKey = (string) $apiKey->key;
            $alreadyHashed = preg_match('/\A[a-f0-9]{64}\z/', $existingKey) === 1;

            DB::table('api_keys')
                ->where('owner', $apiKey->owner)
                ->update([
                    'key' => $alreadyHashed ? $existingKey : hash('sha256', $existingKey),
                    'key_prefix' => $alreadyHashed ? null : substr($existingKey, 0, 12),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropColumn('key_prefix');
        });
    }
};
