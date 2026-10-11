<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disability_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->text('name')->unique();
        });
        foreach (['Learning disability', 'Intellectual disability', 'Autism spectrum', 'Hearing impairment', 'Visual impairment', 'Physical/orthopedic', 'Speech/language', 'Emotional-behavioral', 'Multiple disabilities', 'Other'] as $name) {
            DB::table('disability_categories')->insert(['name' => $name]);
        }
        Schema::create('learner_disabilities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->uuid('learner_id');
            $table->foreign(['school_id', 'learner_id'])->references(['school_id', 'id'])->on('learners')->cascadeOnDelete();
            $table->unsignedInteger('category_id');
            $table->foreign('category_id')->references('id')->on('disability_categories')->restrictOnDelete();
            // Text columns hold encrypted clinical data, including the date.
            $table->text('diagnosis_date')->nullable();
            $table->text('assessed_by')->nullable();
            $table->text('notes')->nullable();
        });
        Schema::create('teacher_assessments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->uuid('learner_id');
            $table->foreign(['school_id', 'learner_id'])->references(['school_id', 'id'])->on('learners')->cascadeOnDelete();
            $table->date('assessed_on');
            $table->text('observations');
            $table->uuid('recorded_by');
            $table->foreign(['school_id', 'recorded_by'])->references(['school_id', 'id'])->on('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assessments');
        Schema::dropIfExists('learner_disabilities');
        Schema::dropIfExists('disability_categories');
    }
};
