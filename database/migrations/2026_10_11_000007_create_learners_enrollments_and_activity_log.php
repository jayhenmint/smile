<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', fn (Blueprint $table) => $table->unique(['school_id', 'school_year_id', 'id']));
        Schema::create('learners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->text('lrn')->nullable()->unique();
            $table->string('smile_id', 11)->unique();
            $table->foreign('smile_id')->references('smile_id')->on('smile_id_reservations')->restrictOnDelete();
            $table->enum('lrn_status', ['pending', 'issued', 'needs_correction'])->default('pending');
            $table->text('last_name');
            $table->text('first_name');
            $table->text('middle_name')->nullable();
            $table->date('birthdate');
            $table->enum('sex', ['M', 'F'])->nullable();
            $table->text('address')->nullable();
            $table->text('mother_tongue')->nullable();
            $table->boolean('has_pwd_id')->default(false)->nullable();
            $table->text('pwd_id_number')->nullable();
            $table->text('health_notes')->nullable();
            $table->text('photo_url')->nullable();
            $table->enum('status', ['enrolled', 'transferred_out', 'dropped', 'graduated'])->default('enrolled');
            $table->uuid('consent_confirmed_by');
            $table->foreign(['school_id', 'consent_confirmed_by'])->references(['school_id', 'id'])->on('users')->restrictOnDelete();
            $table->timestampTz('consent_confirmed_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['school_id', 'id']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->uuid('learner_id');
            $table->foreign(['school_id', 'learner_id'])->references(['school_id', 'id'])->on('learners')->restrictOnDelete();
            $table->unsignedInteger('school_year_id');
            $table->foreign(['school_id', 'school_year_id'])->references(['school_id', 'id'])->on('school_years')->restrictOnDelete();
            $table->unsignedInteger('section_id')->nullable();
            $table->foreign(['school_id', 'school_year_id', 'section_id'])->references(['school_id', 'school_year_id', 'id'])->on('sections')->restrictOnDelete();
            $table->enum('level_status', ['non_graded', 'graded'])->default('non_graded');
            $table->text('grade_level')->nullable();
            $table->enum('lis_enrollment_status', ['pending', 'encoded', 'approved'])->default('pending')->nullable();
            $table->date('enrolled_on')->default(DB::raw('CURRENT_DATE'));
            $table->boolean('is_transferee')->default(false)->nullable();
            $table->text('previous_school')->nullable();
            $table->unique(['learner_id', 'school_year_id']);
        });
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE learners ADD CONSTRAINT learners_lrn_format CHECK (lrn IS NULL OR lrn ~ '^[0-9]{12}$'), ADD CONSTRAINT learners_issued_lrn CHECK (lrn_status <> 'issued' OR lrn IS NOT NULL)");
            DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_non_graded_only CHECK (level_status = 'non_graded' AND grade_level IS NULL)");
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_learner_smile_id_change() RETURNS trigger AS $$
                BEGIN
                    IF NEW.smile_id IS DISTINCT FROM OLD.smile_id THEN
                        RAISE EXCEPTION 'A learner SMILE ID cannot be changed';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER learners_smile_id_immutable BEFORE UPDATE ON learners
                    FOR EACH ROW EXECUTE FUNCTION prevent_learner_smile_id_change();
                CREATE INDEX idx_learner_dup_check ON learners(school_id, lower(last_name), lower(first_name), birthdate);
                SQL);
        } else {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER learners_lrn_insert BEFORE INSERT ON learners
                WHEN (NEW.lrn IS NOT NULL AND (length(NEW.lrn) <> 12 OR NEW.lrn GLOB '*[^0-9]*'))
                    OR (NEW.lrn_status = 'issued' AND NEW.lrn IS NULL)
                BEGIN SELECT RAISE(ABORT, 'Invalid LRN format or status'); END;
                CREATE TRIGGER learners_lrn_update BEFORE UPDATE ON learners
                WHEN (NEW.lrn IS NOT NULL AND (length(NEW.lrn) <> 12 OR NEW.lrn GLOB '*[^0-9]*'))
                    OR (NEW.lrn_status = 'issued' AND NEW.lrn IS NULL)
                BEGIN SELECT RAISE(ABORT, 'Invalid LRN format or status'); END;
                CREATE TRIGGER learners_smile_id_immutable BEFORE UPDATE ON learners
                WHEN NEW.smile_id <> OLD.smile_id
                BEGIN SELECT RAISE(ABORT, 'A learner SMILE ID cannot be changed'); END;
                CREATE TRIGGER enrollments_non_graded_insert BEFORE INSERT ON enrollments
                WHEN NEW.level_status <> 'non_graded' OR NEW.grade_level IS NOT NULL
                BEGIN SELECT RAISE(ABORT, 'An approved level-change workflow is required'); END;
                CREATE TRIGGER enrollments_non_graded_update BEFORE UPDATE ON enrollments
                WHEN NEW.level_status <> 'non_graded' OR NEW.grade_level IS NOT NULL
                BEGIN SELECT RAISE(ABORT, 'An approved level-change workflow is required'); END;
                CREATE INDEX idx_learner_dup_check ON learners(school_id, lower(last_name), lower(first_name), birthdate);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('learners');
        Schema::table('sections', fn (Blueprint $table) => $table->dropUnique(['school_id', 'school_year_id', 'id']));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_learner_smile_id_change()');
        }
    }
};
