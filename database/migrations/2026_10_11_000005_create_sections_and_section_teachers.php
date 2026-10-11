<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unique(['school_id', 'id']));
        Schema::table('school_years', fn (Blueprint $table) => $table->unique(['school_id', 'id']));
        Schema::create('sections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->unsignedInteger('school_year_id');
            $table->foreign(['school_id', 'school_year_id'])->references(['school_id', 'id'])->on('school_years')->restrictOnDelete();
            $table->text('grade_level')->default('Non-graded');
            $table->text('name');
            $table->enum('placement_type', ['sped_self_contained', 'resource_room', 'regular'])->default('sped_self_contained');
            $table->uuid('adviser_id')->nullable();
            $table->foreign(['school_id', 'adviser_id'])->references(['school_id', 'id'])->on('users')->restrictOnDelete();
            $table->unique(['school_id', 'school_year_id', 'name']);
            $table->unique(['school_id', 'id']);
        });
        Schema::create('section_teachers', function (Blueprint $table) {
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->unsignedInteger('section_id');
            $table->uuid('user_id');
            $table->enum('role', ['adviser', 'co_teacher', 'aide', 'therapist'])->default('co_teacher');
            $table->primary(['section_id', 'user_id']);
            $table->foreign(['school_id', 'section_id'])->references(['school_id', 'id'])->on('sections')->cascadeOnDelete();
            $table->foreign(['school_id', 'user_id'])->references(['school_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_teachers');
        Schema::dropIfExists('sections');
        Schema::table('school_years', fn (Blueprint $table) => $table->dropUnique(['school_id', 'id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['school_id', 'id']));
    }
};
