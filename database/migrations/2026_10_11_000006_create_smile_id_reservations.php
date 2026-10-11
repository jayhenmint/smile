<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global identifier namespace, not school-owned learner data.
        // Retain issued identifiers without retaining personal information.
        Schema::create('smile_id_reservations', function (Blueprint $table) {
            $table->string('smile_id', 11)->primary();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE smile_id_reservations ADD CONSTRAINT smile_id_reservations_format CHECK (smile_id ~ '^SM-[A-HJ-NP-Z2-9]{8}$')");
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_smile_id_reservation_change() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Reserved SMILE IDs cannot be changed or removed';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER smile_id_reservations_immutable
                    BEFORE UPDATE OR DELETE ON smile_id_reservations
                    FOR EACH ROW EXECUTE FUNCTION prevent_smile_id_reservation_change();
                CREATE TRIGGER smile_id_reservations_no_truncate
                    BEFORE TRUNCATE ON smile_id_reservations
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_smile_id_reservation_change();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER smile_id_reservations_format BEFORE INSERT ON smile_id_reservations
                WHEN length(NEW.smile_id) <> 11 OR substr(NEW.smile_id, 1, 3) <> 'SM-'
                    OR substr(NEW.smile_id, 4) GLOB '*[^A-HJ-NP-Z2-9]*'
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid SMILE ID format');
                END;
                CREATE TRIGGER smile_id_reservations_no_update BEFORE UPDATE ON smile_id_reservations
                BEGIN
                    SELECT RAISE(ABORT, 'Reserved SMILE IDs cannot be changed or removed');
                END;
                CREATE TRIGGER smile_id_reservations_no_delete BEFORE DELETE ON smile_id_reservations
                BEGIN
                    SELECT RAISE(ABORT, 'Reserved SMILE IDs cannot be changed or removed');
                END;
                SQL);
        } else {
            throw new RuntimeException('SMILE identity storage requires PostgreSQL or SQLite.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smile_id_reservations');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_smile_id_reservation_change()');
        }
    }
};
