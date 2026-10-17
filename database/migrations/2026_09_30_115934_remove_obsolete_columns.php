<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite cannot drop foreign-keyed or unique columns in place, so the table
     * is rebuilt there; other drivers drop the columns directly.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable();

            return;
        }

        Schema::table('peck_users', function (Blueprint $table): void {
            $table->dropForeign(['initiator']);
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'joindate', 'initiator']);
        });
    }

    public function down(): void
    {
        // This data is sourced from ThunderAPI and cannot be restored locally.
    }

    private function rebuildSqliteTable(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement('DROP TABLE IF EXISTS peck_users_tmp');

        DB::statement('CREATE TABLE peck_users_tmp (
            gaijin_id integer not null primary key,
            discord_id integer null,
            status varchar not null
        )');

        DB::statement('INSERT INTO peck_users_tmp (gaijin_id, discord_id, status)
            SELECT gaijin_id, discord_id, status FROM peck_users');

        DB::statement('DROP TABLE peck_users');

        DB::statement('ALTER TABLE peck_users_tmp RENAME TO peck_users');

        Schema::enableForeignKeyConstraints();
    }
};
