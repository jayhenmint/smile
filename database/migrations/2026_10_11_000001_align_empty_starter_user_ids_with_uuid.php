<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $type = Schema::getColumnType('users', 'id');
        if ($type === 'uuid' || (DB::getDriverName() === 'sqlite' && $type === 'varchar')) {
            return;
        }

        // Only the original, empty PostgreSQL starter database needs conversion.
        // Fresh installations already create UUID columns in the base migration.
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Existing user IDs require a separately reviewed migration.');
        }

        DB::statement('LOCK TABLE users, sessions IN ACCESS EXCLUSIVE MODE');

        if (DB::table('users')->exists() || DB::table('sessions')->whereNotNull('user_id')->exists()) {
            throw new RuntimeException('User ID alignment requires an empty account database. No data was changed.');
        }

        DB::statement('ALTER TABLE users ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE uuid USING NULL::uuid');
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE uuid USING NULL::uuid');
    }

    public function down(): void
    {
        // The base migration now uses UUIDs too, so UUIDs remain on rollback.
    }
};
